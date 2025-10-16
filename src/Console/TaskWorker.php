<?php namespace Seiger\sCommerce\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Models\sIntegration;
use Seiger\sCommerce\Models\sIntegrationTask;
use Seiger\sCommerce\Integration\TaskProgress;

/**
 * @deprecated
 * TaskWorker - Console command for processing sCommerce background tasks
 *
 * This command processes queued sIntegrationTask jobs from the database queue.
 * It runs continuously, claiming and executing any type of background tasks
 * through their respective integration implementations. Tasks can include
 * import/export operations, data synchronization, cleanup jobs, notifications,
 * and any other background work defined by integration modules.
 *
 * Features:
 * - Processes any type of background tasks from sIntegrationTask queue
 * - Supports multiple integration types via database configuration
 * - Tracks progress through TaskProgress file-based system
 * - Handles task status updates (running, finished, failed)
 * - Provides comprehensive error handling and logging
 * - Scheduled to run every minute via Laravel Scheduler
 * - Extensible architecture for custom task types
 * - Asynchronous execution with proper environment setup
 *
 * Task Processing Flow:
 * 1. Retrieves all tasks ready for execution (startNow scope)
 * 2. For each task, resolves the integration class from database
 * 3. Validates integration implements IntegrationInterface
 * 4. Calls the appropriate action method (e.g., taskExport, taskImport)
 * 5. Updates task status and progress through TaskProgress
 * 6. Handles errors gracefully with proper cleanup
 *
 * Usage:
 * php artisan scommerce:task.worker
 *
 * @package Seiger\sCommerce\Console
 * @author Seiger IT Team
 * @since 1.0.0
 */
class TaskWorker extends Command
{
    /** @var string */
    protected $signature = 'scommerce:task.worker';

    /** @var string */
    protected $description = 'Process all queued sIntegrationTask jobs and exit.';

    /**
     * Execute the console command.
     *
     * Retrieves all tasks ready for execution and processes them sequentially.
     * Each task is executed through its respective integration implementation.
     *
     * @return int Command exit code (0 for success)
     */
    public function handle(): int
    {
        $tasks = sIntegrationTask::startNow()->get();
        $processed = 0;

        foreach ($tasks as $task) {
            $this->runOne($task);
            $processed++;
        }

        $this->info("[scommerce:task.worker] processed {$processed} task(s).");
        return self::SUCCESS;
    }

    /**
     * Execute one claimed task via its integration implementation.
     *
     * This method handles the complete lifecycle of a single task:
     * 1. Resolves the integration class from database configuration
     * 2. Validates the integration implements IntegrationInterface
     * 3. Executes the task through the integration's action method
     * 4. Ensures proper task finalization and status updates
     * 5. Handles any errors with appropriate cleanup
     *
     * @param sIntegrationTask $task The task to execute
     * @return void
     */
    private function runOne(sIntegrationTask $task): void
    {
        try {
            // Resolve integration by slug from database
            $integrationRecord = sIntegration::where('key', $task->slug)->where('active', true)->first();

            if (!$integrationRecord) {
                throw new \RuntimeException("Integration '{$task->slug}' not found or inactive");
            }

            $className = $integrationRecord->class;
            if (!$className || !class_exists($className)) {
                throw new \RuntimeException("Integration class '{$className}' not found");
            }

            $integration = app($className);
            if (!$integration instanceof \Seiger\sCommerce\Interfaces\IntegrationInterface) {
                throw new \RuntimeException("Class '{$className}' must implement IntegrationInterface");
            }

            // Delegate the real work to integration
            // Integration will handle all progress updates and messages
            $this->executeIntegrationTask($integration, $task);

            // Ensure finalization if integration didn't mark finished
            $freshTask = $task->fresh();
            if ($freshTask->status !== sIntegrationTask::TASK_STATUS_FINISHED) {
                $task->update([
                    'status' => sIntegrationTask::TASK_STATUS_FINISHED,
                    'message' => $task->message ?: __('sCommerce::global.done'),
                    'finished_at' => now()
                ]);

                TaskProgress::write([
                    'id'        => (int)$task->id,
                    'slug'      => $task->slug,
                    'action'    => $task->action,
                    'status'    => (string)sIntegrationTask::statuses()[$task->status],
                    'progress'  => 100,
                    'message'   => $task->message ?: __('sCommerce::global.done'),
                ]);
            } else {
                // Task is already finished, no need to add duplicate progress
                // Just ensure finished_at is set if missing
                if (!$freshTask->finished_at) {
                    $freshTask->update(['finished_at' => now()]);
                }
            }
        } catch (\Throwable $e) {
            Log::channel('scommerce')->error('task.worker failed: ' . $e->getMessage(), ['task' => $task->id]);

            $task->update([
                'status' => sIntegrationTask::TASK_STATUS_FAILED,
                'finished_at' => now()
            ]);

            TaskProgress::write([
                'id'      => (int)$task->id,
                'slug'    => $task->slug,
                'action'  => $task->action,
                'status'  => (string)sIntegrationTask::statuses()[$task->status],
                'code'    => 500,
                'message' => '**Failed @ '.basename($e->getFile()).':'.$e->getLine().' — '.$e->getMessage() .'**',
            ]);
        }
    }

    /**
     * Execute integration task by calling the appropriate action method.
     *
     * This method sets up the execution environment for long-running tasks
     * and dynamically calls the integration's action method based on the task action.
     * The action method name is derived by converting the action to StudlyCase
     * and prefixing with 'task' (e.g., 'export' becomes 'taskExport').
     *
     * @param \Seiger\sCommerce\Interfaces\IntegrationInterface $integration The integration instance
     * @param sIntegrationTask $task The task to execute
     * @return void
     * @throws \BadMethodCallException If the action method doesn't exist
     */
    private function executeIntegrationTask($integration, sIntegrationTask $task): void
    {
        // Set execution environment for long-running tasks
        @ignore_user_abort(true);
        @set_time_limit(0);
        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        // Resolve action method name (e.g., "export" -> "taskExport")
        $action = $task->action;
        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($action))));
        $method = 'task' . $studly;

        // Check if method exists
        if (!method_exists($integration, $method)) {
            throw new \BadMethodCallException(
                get_class($integration) . " missing action method {$method}()"
            );
        }

        // Call the action method with task and options
        $options = array_merge((array)$task->options, (array)$task->meta);
        $integration->{$method}($task, $options);
    }

    /**
     * Define the command's schedule.
     *
     * Configures the command to run every minute via Laravel Scheduler.
     * This ensures tasks are processed regularly without manual intervention.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule The schedule instance
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        $schedule->command(static::class)->everyMinute();
    }
}
