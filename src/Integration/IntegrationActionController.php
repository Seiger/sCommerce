<?php namespace Seiger\sCommerce\Integration;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Interfaces\IntegrationInterface;
use Seiger\sCommerce\Integration\TaskProgress;
use Seiger\sCommerce\Models\sIntegration;
use Seiger\sCommerce\Models\sIntegrationTask;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * IntegrationActionController - Controller for integration task management
 *
 * This controller handles the complete lifecycle of integration tasks from creation
 * to completion, providing RESTful endpoints for task management, progress tracking,
 * and file downloads. It serves as the primary interface between the frontend and
 * the background task processing system.
 *
 * Key Features:
 * - Task creation and initialization through integration resolution
 * - Asynchronous task execution with background worker launching
 * - Real-time progress tracking via filesystem-based snapshots
 * - File download handling for completed export tasks
 * - Comprehensive error handling and logging
 * - Support for multiple integration types
 *
 * API Endpoints:
 * - POST /scommerce/integrations/{key}/tasks/{action} - Start a new task
 * - GET /scommerce/integrations/tasks/{id}/progress - Get task progress
 * - GET /scommerce/integrations/tasks/{id}/download - Download task result
 *
 * Task Lifecycle:
 * 1. Task creation via start() method
 * 2. Background worker launching for asynchronous execution
 * 3. Progress tracking through progress() method
 * 4. File download via download() method upon completion
 *
 * @package Seiger\sCommerce\Integration
 * @author Seiger IT Team
 * @since 1.0.0
 */
class IntegrationActionController extends BaseController
{
    /**
     * Start an integration task for a given key and action.
     *
     * This method creates a new integration task and attempts to launch it asynchronously.
     * It resolves the integration from the database, creates the task with proper
     * initialization, and launches the background worker to process the task.
     * 
     * This method accepts ALL request parameters and passes them as options to the
     * integration task, providing maximum flexibility for third-party developers.
     * Security is ensured through admin middleware protection.
     *
     * The method handles:
     * - Integration resolution and validation
     * - Task creation with all request parameters as options
     * - Asynchronous worker launching (exec/shell_exec or fallback)
     * - Error handling and logging
     *
     * Route: POST /scommerce/integrations/{key}/tasks/{action}
     *
     * @param string $key The integration key (e.g., 'simpexpcsv')
     * @param string $action The action to perform (e.g., 'export', 'import')
     * @return JsonResponse JSON response with task ID and status
     * 
     * @example
     * // Import with filename
     * POST /scommerce/integrations/simpexpcsv/tasks/import
     * Body: {"filename": "import_file.csv"}
     * 
     * @example
     * // Export with custom options
     * POST /scommerce/integrations/simpexpcsv/tasks/export
     * Body: {"delimiter": ";", "batch_size": 100, "include_attributes": true}
     * 
     * @example
     * // Custom integration with any parameters
     * POST /scommerce/integrations/custom/tasks/sync
     * Body: {"api_key": "xxx", "endpoint": "https://api.example.com", "settings": {...}}
     */
    public function start(string $key, string $action): JsonResponse
    {
        try {
            $options = request()->all();
            $integration = $this->resolveIntegrationOrFail($key);
            $task = $integration->createTask($action, $options);

            if ($task && Carbon::parse($task->start_at) <= Carbon::now()) {
                // Try to launch detached CLI worker
                $this->launchTaskWorker();
            }
        } catch (\Throwable $e) {
            Log::channel('scommerce')->warning('IntegrationActionController launch failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'id' => (int)$task->id, 'message' => $task->message]);
    }

    /**
     * Get task progress snapshot from filesystem-based tracking.
     *
     * This method retrieves the current progress state for a task from the TaskProgress
     * filesystem-based tracking system. It provides real-time updates on task status,
     * progress percentage, processing statistics, and current messages.
     *
     * The method handles:
     * - Progress file existence validation
     * - JSON data parsing and validation
     * - Error state detection and reporting
     * - Comprehensive error handling and logging
     *
     * Route: GET /scommerce/integrations/tasks/{id}/progress
     *
     * @param int $id The task ID to get progress for
     * @return JsonResponse JSON response with progress data or error information
     */
    public function progress(int $id): JsonResponse
    {
        try {
            // Check if ID is valid
            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'code' => 400,
                    'error' => 'Invalid task ID',
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Task ID must be greater than 0'
                ], 400);
            }

            $task = sIntegrationTask::findOrFail($id);
            $file = TaskProgress::file($id);
            
        if (!is_file($file)) {
                return response()->json([
                    'success' => false,
                    'code' => 404,
                    'error' => 'Progress file not found',
                    'id' => $id,
                    'status' => 'not_found',
                    'message' => 'Progress tracking not available'
                ], 404);
            }

            $json = file_get_contents($file);
            $data = json_decode($json, true);
            
            if (!is_array($data)) {
                return response()->json([
                    'success' => false,
                    'code' => 500,
                    'error' => 'Invalid progress data',
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Invalid progress data'
                ], 500);
            }

            return response()->json(array_merge([
                'success' => (isset($data['code']) && $data['code'] == 500 ? false : true),
                'code' => 200,
            ], $data));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::channel('scommerce')->warning('Task not found', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'code' => 404,
                'error' => 'Task not found',
                'id' => $id,
                'status' => 'not_found',
                'message' => 'Task with ID ' . $id . ' does not exist'
            ], 404);
        } catch (\Throwable $e) {
            Log::channel('scommerce')->error('Failed to get task progress', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'code' => 500,
                'error' => 'Failed to get progress',
                'id' => $id,
                'status' => 'error',
                'progress' => 0,
                'message' => 'Failed to get progress'
            ], 500);
        }
    }

    /**
     * Download exported file for completed task.
     *
     * This method serves exported files as downloadable responses for tasks that have
     * completed successfully. It validates task completion status, checks file existence,
     * generates appropriate filenames, and returns the file with proper MIME types.
     *
     * The method handles:
     * - Task completion status validation
     * - File existence and accessibility checks
     * - Dynamic filename generation with timestamps
     * - MIME type detection and proper headers
     * - Error handling for missing or inaccessible files
     *
     * Route: GET /scommerce/integrations/tasks/{id}/download
     *
     * @param int $id The task ID to download the result file for
     * @return BinaryFileResponse|JsonResponse File download response or error JSON
     */
    public function download(int $id)
    {
        try {
            $task = sIntegrationTask::findOrFail($id);
            
            // Check if task is finished
            if ((int)$task->status !== sIntegrationTask::TASK_STATUS_FINISHED) {
                return response()->json([
                    'success' => false,
                    'code' => 400,
                    'error' => 'Task not completed',
                    'message' => 'Task must be completed before downloading'
                ], 400);
            }
            
            // Check if result file exists
            if (!$task->result || !is_file($task->result)) {
                return response()->json([
                    'success' => false,
                    'code' => 404,
                    'error' => 'Export file not found',
                    'message' => 'Export file is not available'
                ], 404);
            }
            
            // Return file download response
            $filename = basename($task->result);
            return response()->download(
                $task->result,
                $filename,
                [
                    'Content-Type' => $this->getMimeType(pathinfo($filename, PATHINFO_EXTENSION)),
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                ]
            );
        } catch (\Throwable $e) {
            Log::channel('scommerce')->error('Failed to download task file', [
                'task_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'code' => 500,
                'error' => 'Download failed',
                'message' => 'Failed to download file'
            ], 500);
        }
    }

    /**
     * Get MIME type for file extension.
     *
     * This utility method maps file extensions to their corresponding MIME types
     * for proper HTTP response headers. It supports common file types used in
     * integration exports and falls back to 'application/octet-stream' for unknown types.
     *
     * Supported file types:
     * - CSV files (text/csv)
     * - Excel files (xlsx, xls)
     * - JSON files (application/json)
     * - XML files (application/xml)
     * - Text files (text/plain)
     * - ZIP archives (application/zip)
     *
     * @param string $extension The file extension (without leading dot)
     * @return string The corresponding MIME type
     */
    protected function getMimeType(string $extension): string
    {
        $mimeTypes = [
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }

    /**
     * Resolve active integration instance by key using database lookup.
     *
     * This method retrieves an active integration from the database by its key,
     * validates the integration class exists, instantiates it, and ensures it
     * implements the IntegrationInterface. It provides comprehensive error handling
     * for missing, inactive, or invalid integrations.
     *
     * The resolution process:
     * 1. Queries sIntegration table for active integration with matching key
     * 2. Validates the integration class exists and is loadable
     * 3. Instantiates the integration class via service container
     * 4. Verifies the instance implements IntegrationInterface
     * 5. Returns the validated integration instance
     *
     * @param string $key The integration key to resolve
     * @return IntegrationInterface The resolved integration instance
     * @throws \InvalidArgumentException If integration not found or inactive
     * @throws \RuntimeException If integration class not found or invalid
     */
    protected function resolveIntegrationOrFail(string $key): IntegrationInterface
    {
        $rec = sIntegration::query()->active()->where('key', $key)->first();

        if (!$rec) {
            throw new \InvalidArgumentException(__('sCommerce::global.integration_not_found_or_inactive', ['key' => $key]));
        }

        $className = $rec->class ?? null;
        if (!$className || !class_exists($className)) {
            throw new \RuntimeException(__('sCommerce::global.integration_class_not_found', ['className' => $className]));
        }

        $instance = app()->make($className);
        if (!$instance instanceof IntegrationInterface) {
            throw new \RuntimeException(__('sCommerce::global.integration_must_implement_IntegrationInterface', ['className' => $className]));
        }

        return $instance;
    }

    /**
     * Launch TaskWorker command in background for asynchronous task processing.
     *
     * This method attempts to launch the scommerce:task.worker command in the background
     * to process queued tasks asynchronously. It tries multiple approaches in order of
     * preference to ensure compatibility with different server configurations.
     *
     * Execution strategies (in order):
     * 1. Direct exec() call if available and not disabled
     * 2. shell_exec() call if available and not disabled
     * 3. register_shutdown_function() fallback for production servers
     *
     * The fallback approach executes the command after the HTTP response is sent,
     * ensuring the user gets immediate feedback while tasks process in the background.
     *
     * @return void
     */
    protected function launchTaskWorker(): void
    {
        try {
            $artisanPath = EVO_CORE_PATH . 'artisan';
            
            Log::channel('scommerce')->debug('Launching TaskWorker', [
                'artisan_path' => $artisanPath,
                'exec_available' => function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions'))),
                'shell_exec_available' => function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))
            ]);
            
            // Try system execution first (if available)
            if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
                $command = "php \"{$artisanPath}\" scommerce:task.worker > /dev/null 2>&1 &";
                exec($command);
                Log::channel('scommerce')->debug('TaskWorker launched via exec', ['command' => $command]);
                return;
            }
            
            if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
                $command = "php \"{$artisanPath}\" scommerce:task.worker > /dev/null 2>&1 &";
                shell_exec($command);
                Log::channel('scommerce')->debug('TaskWorker launched via shell_exec', ['command' => $command]);
                return;
            }

            register_shutdown_function(function() {
                try {
                    // Use EvolutionCMS Console for better integration
                    $console = app('Console');
                    $console->call('scommerce:task.worker');
                    Log::channel('scommerce')->debug('TaskWorker executed via fallback');
                } catch (\Throwable $e) {
                    Log::channel('scommerce')->error('TaskWorker failed to execute in shutdown function', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::channel('scommerce')->error('Failed to launch TaskWorker', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Upload CSV file for import with chunked upload support.
     *
     * This method handles file uploads for CSV import operations, supporting both
     * single file uploads and chunked uploads for large files. It creates a task
     * to track the upload progress and returns the task information.
     *
     * @param string $key Integration key
     * @param Request $request HTTP request containing file data
     * @return JsonResponse JSON response with task information
     * @throws \RuntimeException If integration not found or upload fails
     */
    public function upload(string $key, Request $request): JsonResponse
    {
        try {
            // Resolve integration
            $integration = $this->resolveIntegrationOrFail($key);

            // Get server limits
            $limitsResponse = $this->serverLimits();
            $limits = $limitsResponse->getData(true);

            // Check if this is a chunked upload
            if ($request->has('chunk_index') && $request->has('total_chunks')) {
                return $this->handleChunkedUpload($key, $request, $limits);
            } else {
                return $this->handleSingleUpload($key, $request, $limits);
            }
        } catch (\Exception $e) {
            Log::channel('scommerce')->error('Upload failed', [
                'key' => $key,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'code' => 500,
                'message' => $e->getMessage(),
                'rev' => time(),
            ], 500);
        }
    }

    /**
     * Handle single file upload (for smaller files).
     *
     * @param string $key Integration key
     * @param Request $request HTTP request
     * @param array $limits Server limits
     * @return JsonResponse
     */
    private function handleSingleUpload(string $key, Request $request, array $limits): JsonResponse
    {
        $uploadedFile = $request->file('file');
        
        if (!$uploadedFile) {
            throw new \RuntimeException('No file uploaded');
        }

        // Validate file
        $this->validateUploadedFile($uploadedFile, $limits);

        // Generate unique filename
        $filename = $this->generateUniqueFilename($uploadedFile);
        $uploadPath = $this->getUploadPath('scommerce/uploads', $filename);

        // Move uploaded file
        $uploadedFile->move(dirname($uploadPath), basename($uploadPath));

        return response()->json([
            'success' => true,
            'code' => 200,
            'slug' => $key,
            'action' => 'upload',
            'status' => 'finished',
            'message' => __('sCommerce::global.upload_completed') . '. ' . __('sCommerce::global.temporary_file') . ': **' . basename($uploadPath) . '**',
            'result' => $uploadPath,
            'rev' => time(),
        ]);
    }

    /**
     * Handle chunked file upload (for large files).
     *
     * @param string $key Integration key
     * @param Request $request HTTP request
     * @param array $limits Server limits
     * @return JsonResponse
     */
    private function handleChunkedUpload(string $key, Request $request, array $limits): JsonResponse
    {
        $chunkIndex = (int)$request->input('chunk_index');
        $totalChunks = (int)$request->input('total_chunks');
        $uploadedFile = $request->file('file');
        
        if (!$uploadedFile) {
            throw new \RuntimeException('No file chunk uploaded');
        }

        // Generate unique filename for this upload session
        $sessionId = $request->input('session_id', uniqid('upload_', true));
        $originalFilename = $request->input('original_filename', 'upload.csv');
        $filename = $sessionId . '_' . $originalFilename;
        $uploadPath = $this->getUploadPath('scommerce/uploads', $filename);
        $chunkPath = $uploadPath . '.chunk.' . $chunkIndex;

        // Save chunk
        $uploadedFile->move(dirname($chunkPath), basename($chunkPath));

        // If this is the last chunk, combine all chunks
        if ($chunkIndex + 1 === $totalChunks) {
            $this->combineChunks($uploadPath, $totalChunks);
            
            return response()->json([
                'success' => true,
                'code' => 200,
                'slug' => $key,
                'action' => 'upload',
                'status' => 'finished',
                'message' => __('sCommerce::global.upload_completed') . '. ' . __('sCommerce::global.temporary_file') . ': **' . basename($uploadPath) . '**',
                'result' => $uploadPath,
                'rev' => time(),
            ]);
        } else {
            return response()->json([
                'success' => true,
                'code' => 200,
                'slug' => $key,
                'action' => 'upload',
                'status' => 'running',
                'message' => __('sCommerce::global.uploading_file') . '... (' . ($chunkIndex + 1) . '/' . $totalChunks . ')',
                'progress' => (int)(($chunkIndex + 1) * 100 / $totalChunks),
                'rev' => time(),
            ]);
        }
    }

    /**
     * Validate uploaded file.
     *
     * @param \Illuminate\Http\UploadedFile $file The uploaded file
     * @param array $limits Server limits
     * @return void
     * @throws \RuntimeException If validation fails
     */
    private function validateUploadedFile($file, array $limits): void
    {
        // Check file size
        if ($file->getSize() > $limits['maxFileSize']) {
            throw new \RuntimeException(__('sCommerce::global.file_too_large') . ' (max: ' . niceSize($limits['maxFileSize']) . ')');
        }

        // Check file extension
        $allowedExtensions = ['csv'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            throw new \RuntimeException(__('sCommerce::global.invalid_file_extension') . ' ' . implode(', ', $allowedExtensions));
        }

        // Check if file is actually uploaded
        if (!$file->isValid()) {
            throw new \RuntimeException('File upload failed: ' . $file->getError());
        }
    }

    /**
     * Generate unique filename for upload.
     *
     * @param \Illuminate\Http\UploadedFile $file The uploaded file
     * @return string Unique filename
     */
    private function generateUniqueFilename($file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = date('Ymd_His');
        $random = substr(md5(uniqid()), 0, 8);
        
        return "import_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Get upload path for file.
     *
     * @param string $uploadDir Upload directory
     * @param string $filename Filename
     * @return string Full path
     */
    private function getUploadPath(string $uploadDir, string $filename): string
    {
        $fullDir = storage_path($uploadDir);
        
        // Create directory if it doesn't exist
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        return $fullDir . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Combine all chunks into final file.
     *
     * @param string $finalPath Final file path
     * @param int $totalChunks Total number of chunks
     * @return void
     * @throws \RuntimeException If combination fails
     */
    private function combineChunks(string $finalPath, int $totalChunks): void
    {
        $finalFile = fopen($finalPath, 'wb');
        if (!$finalFile) {
            throw new \RuntimeException('Cannot create final file');
        }

        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = $finalPath . '.chunk.' . $i;
                
                if (!file_exists($chunkPath)) {
                    throw new \RuntimeException("Chunk {$i} not found");
                }

                $chunkFile = fopen($chunkPath, 'rb');
                if (!$chunkFile) {
                    throw new \RuntimeException("Cannot read chunk {$i}");
                }

                stream_copy_to_stream($chunkFile, $finalFile);
                fclose($chunkFile);
                
                // Remove chunk file
                unlink($chunkPath);
            }
        } finally {
            fclose($finalFile);
        }
    }

    /**
     * Get server upload limits for file operations.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function serverLimits()
    {
        try {
            // Get PHP configuration
            $uploadMaxFilesize = $this->parseSize(ini_get('upload_max_filesize'));
            $postMaxSize = $this->parseSize(ini_get('post_max_size'));
            $memoryLimit = $this->parseSize(ini_get('memory_limit'));
            $maxExecutionTime = (int)ini_get('max_execution_time');
            
            // Calculate safe limits
            $maxFileSize = min($uploadMaxFilesize, $postMaxSize);
            
            // For chunked uploads, we can handle larger files
            // Use 80% of available memory or 100MB, whichever is smaller
            $chunkedMaxSize = min($memoryLimit * 0.8, 100 * 1024 * 1024);
            $maxFileSize = max($maxFileSize, $chunkedMaxSize);
            
            // Chunk size: 1/4 of memory limit or 1MB, whichever is smaller
            $chunkSize = min($memoryLimit / 4, 1024 * 1024);
            
            // Single upload limit: 80% of upload_max_filesize
            $singleUploadLimit = $uploadMaxFilesize * 0.8;
            
            // Ensure minimum values
            $maxFileSize = max($maxFileSize, 5 * 1024 * 1024); // At least 5MB
            $chunkSize = max($chunkSize, 256 * 1024); // At least 256KB
            $singleUploadLimit = max($singleUploadLimit, 1 * 1024 * 1024); // At least 1MB

            $limits = [
                'success' => true,
                'code' => 200,
                'maxFileSize' => (int)$maxFileSize,
                'chunkSize' => (int)$chunkSize,
                'singleUploadLimit' => (int)$singleUploadLimit,
                'maxExecutionTime' => $maxExecutionTime,
                'memoryLimit' => $memoryLimit,
                'uploadMaxFilesize' => $uploadMaxFilesize,
                'postMaxSize' => $postMaxSize,
            ];
            
            return response()->json($limits);
        } catch (\Exception $e) {
            Log::channel('scommerce')->error('Failed to get server limits', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'code' => 500,
                'message' => $e->getMessage(),
                'rev' => time(),
            ], 500);
        }
    }

    /**
     * Parse size string (e.g., "128M", "1G") to bytes.
     *
     * @param string $size Size string
     * @return int Size in bytes
     */
    private function parseSize(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int)$size;

        switch ($last) {
            case 'g':
                $size *= 1024;
                // Fall through
            case 'm':
                $size *= 1024;
                // Fall through
            case 'k':
                $size *= 1024;
        }

        return $size;
    }
}
