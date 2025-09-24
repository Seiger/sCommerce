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
     * The method handles:
     * - Integration resolution and validation
     * - Task creation with proper status initialization
     * - Asynchronous worker launching (exec/shell_exec or fallback)
     * - Error handling and logging
     *
     * Route: POST /scommerce/integrations/{key}/tasks/{action}
     *
     * @param string $key The integration key (e.g., 'simpexpcsv')
     * @param string $action The action to perform (e.g., 'export', 'import')
     * @return JsonResponse JSON response with task ID and status
     */
    public function start(string $key, string $action): JsonResponse
    {
        try {
            $integration = $this->resolveIntegrationOrFail($key);
            $task = $integration->createTask($action);

            if ($task && Carbon::parse($task->start_at) <= Carbon::now()) {
                // Try to launch detached CLI worker
                $this->launchTaskWorker();
            }
        } catch (\Throwable $e) {
            Log::channel('scommerce')->warning('IntegrationActionController launch failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'task' => (int)$task->id, 'message' => $task->message]);
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
        } catch (\Throwable $e) {
            Log::channel('scommerce')->error('Failed to get task progress', [
                'task_id' => $id,
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
            
            // Generate filename from task info
            $extension = pathinfo($task->result, PATHINFO_EXTENSION);
            $filename = sprintf(
                '%s_%s_%s.%s',
                $task->slug,
                $task->action,
                now()->format('Y-m-d_H-i-s'),
                $extension
            );
            
            // Return file download response
            return response()->download(
                $task->result,
                $filename,
                [
                    'Content-Type' => $this->getMimeType($extension),
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
            
            // Try system execution first (if available)
            if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
                $command = "php \"{$artisanPath}\" scommerce:task.worker > /dev/null 2>&1 &";
                exec($command);
                return;
            }
            
            if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
                $command = "php \"{$artisanPath}\" scommerce:task.worker > /dev/null 2>&1 &";
                shell_exec($command);
                return;
            }
            
        // Production fallback: execute after HTTP response is sent
            register_shutdown_function(function() {
                try {
                    // Use EvolutionCMS Console for better integration
                    $console = app('Console');
                    $console->call('scommerce:task.worker');
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
}
