<?php namespace Seiger\sCommerce\Integration;

use Seiger\sCommerce\Interfaces\IntegrationInterface;
use Seiger\sCommerce\Models\sIntegration;
use Seiger\sCommerce\Models\sIntegrationTask;

/**
 * BaseIntegration - Abstract base class for sCommerce integrations
 *
 * This abstract class provides a comprehensive foundation for all sCommerce integrations.
 * It implements common functionality shared across all integrations, including task management,
 * progress tracking, settings handling, and action dispatching.
 *
 * Key Features:
 * - Task creation and management through sIntegrationTask
 * - Progress tracking via TaskProgress file-based system
 * - Settings retrieval and management from database
 * - Action method resolution and invocation
 * - Error handling and task status management
 * - Field extraction utilities for configuration
 *
 * Integration Lifecycle:
 * 1. Constructor loads integration configuration from database
 * 2. createTask() creates new tasks with proper initialization
 * 3. invokeAction() dispatches actions to concrete implementation
 * 4. pushProgress() updates task progress in real-time
 * 5. markFinished()/markFailed() finalizes task execution
 *
 * Concrete implementations must:
 * - Implement IntegrationInterface methods (getKey, getIcon, getTitle, etc.)
 * - Define action methods following naming convention (task{Action})
 * - Handle specific business logic for their integration type
 *
 * @package Seiger\sCommerce\Integration
 * @author Seiger IT Team
 * @since 1.0.0
 */
abstract class BaseIntegration implements IntegrationInterface
{
    /**
     * The integration instance loaded from the database or initialized with default values.
     *
     * @var sIntegration
     */
    protected sIntegration $integration;

    /**
     * BaseIntegration constructor.
     *
     * This constructor initializes the integration by loading its configuration from the database
     * using the integration key. If no configuration exists, it creates a new instance with
     * default values including the integration key and class name.
     *
     * The integration configuration includes settings, active status, and other metadata
     * that controls the behavior of the integration.
     */
    public function __construct()
    {
        $this->integration = sIntegration::where('key', $this->getKey())->first() ?? new sIntegration([
            'key' => $this->getKey(),
            'class' => static::class,
        ]);
    }

    /**
     * Retrieve the settings of the integration.
     *
     * The settings are stored in the database as a JSON string and represent configurable options.
     *
     * @return array An associative array of settings for the integration.
     */
    public function getSettings(): array
    {
        $settings = json_decode($this->integration->settings ?? '', true);
        return is_array($settings) ? $settings : [];
    }

    /**
     * Create a new integration task and initialize progress tracking.
     *
     * This method creates a new sIntegrationTask record with the specified action and options,
     * initializes the TaskProgress system, and returns the created task instance.
     *
     * The method automatically:
     * - Retrieves options from the current request if not provided
     * - Determines the user who started the task
     * - Sets initial task status to QUEUED
     * - Initializes progress tracking with TaskProgress::init()
     *
     * @param string $action The action to perform (e.g., 'export', 'import', 'sync_stock')
     * @param array|null $options Optional explicit options (overrides request input)
     * @return sIntegrationTask The created task instance
     */
    public function createTask(string $action, ?array $options = null): sIntegrationTask
    {
        $options ??= (array)request()->input('options', []);

        $startedBy = 0;
        try {
            if (evo()->getLoginUserID()) {
                $startedBy = (int)evo()->getLoginUserID();
            }
        } catch (\Throwable) {}

        $task = sIntegrationTask::create([
            'slug'       => $this->getKey(),
            'action'     => $action,
            'status'     => sIntegrationTask::TASK_STATUS_QUEUED,
            'message'    => __('sCommerce::global.task_queued') . '...',
            'started_by' => $startedBy,
            'meta'       => $options,
        ]);

        TaskProgress::init([
            'id'       => (int)$task->id,
            'slug'     => $task->slug,
            'action'   => $task->action,
            'status'   => (string)sIntegrationTask::statuses()[$task->status],
            'message'  => $task->message,
        ]);

        return $task;
    }

    /**
     * Invoke a concrete action method by naming convention.
     *
     * This method dynamically calls the appropriate action method on the concrete integration
     * class based on the action name. The method name is derived by converting the action
     * to StudlyCase and prefixing with 'task' (e.g., 'export' becomes 'taskExport').
     *
     * @param string $action The action to invoke (e.g., 'export', 'import', 'sync_stock')
     * @param sIntegrationTask $task The task instance containing execution context
     * @param array $options Additional options to pass to the action method
     * @return void
     * @throws \BadMethodCallException If the action method doesn't exist
     */
    public function invokeAction(string $action, sIntegrationTask $task, array $options = []): void
    {
        $method = $this->resolveActionMethod($action);
        if (!method_exists($this, $method)) {
            throw new \BadMethodCallException(static::class." missing action method {$method}()");
        }
        $this->{$method}($task, $options);
    }

    /**
     * Resolve a method name from action using StudlyCase conversion.
     *
     * This method converts action names to method names by:
     * 1. Converting to lowercase
     * 2. Replacing hyphens and underscores with spaces
     * 3. Converting to StudlyCase (first letter of each word uppercase)
     * 4. Prefixing with 'task'
     *
     * Examples:
     * - "export" -> "taskExport"
     * - "sync_stock" -> "taskSyncStock"
     * - "import-csv" -> "taskImportCsv"
     *
     * @param string $action The action name to convert
     * @return string The resolved method name
     */
    protected function resolveActionMethod(string $action): string
    {
        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($action))));
        return 'task' . $studly;
    }

    /**
     * Push a volatile progress snapshot to the filesystem-based progress system.
     *
     * This method updates the task progress by writing a snapshot to the TaskProgress
     * system. It merges the provided delta with default values to ensure all required
     * fields are present. This approach avoids database churn during long-running tasks.
     *
     * The progress snapshot includes:
     * - Task identification (id, slug, action)
     * - Current status and progress percentage
     * - Processing statistics (processed, total, eta)
     * - Current message and result information
     *
     * @param sIntegrationTask $task The task to update progress for
     * @param array $delta Progress delta to merge with defaults (e.g., ['status'=>'running','progress'=>55,'message'=>'...'])
     * @return void
     */
    protected function pushProgress(sIntegrationTask $task, array $delta = []): void
    {
        $payload = array_merge([
            'id'        => (int)$task->id,
            'slug'      => $task->slug,
            'action'    => $task->action,
            'status'    => (string)sIntegrationTask::statuses()[$task->status],
            'progress'  => 0,
            'processed' => 0,
            'total'     => 0,
            'eta'       => '—',
            'message'   => $task->message,
            'result'    => $task->result,
        ], $delta);

        TaskProgress::write($payload);
    }

    /**
     * Mark task as finished with optional result file path and custom message.
     *
     * This method finalizes a task by updating its status to FINISHED, setting the
     * finished timestamp, and optionally providing a result file path and custom message.
     * It also pushes a final progress update to the TaskProgress system.
     *
     * @param sIntegrationTask $task The task to mark as finished
     * @param string|null $result Path to the result file (for exports, downloads, etc.)
     * @param string|null $message Custom completion message (defaults to 'Done')
     * @return void
     */
    protected function markFinished(sIntegrationTask $task, ?string $result = null, ?string $message = null): void
    {
        $task->update([
            'status' => sIntegrationTask::TASK_STATUS_FINISHED,
            'message' => $message ?? __('sCommerce::global.done'),
            'result' => $result,
            'finished_at' => now(),
        ]);

        $this->pushProgress($task, [
            'status' => (string)sIntegrationTask::statuses()[$task->status],
            'progress' => 100,
            'message' => $message ?? __('sCommerce::global.done'),
            'result' => $result,
        ]);
    }

    /**
     * Mark task as failed with error message.
     *
     * This method finalizes a task by updating its status to FAILED, setting the
     * finished timestamp, and providing an error message. It also pushes a final
     * progress update to the TaskProgress system with the error information.
     *
     * @param sIntegrationTask $task The task to mark as failed
     * @param string $message Error message describing the failure
     * @return void
     */
    protected function markFailed(sIntegrationTask $task, string $message): void
    {
        $task->update([
            'status' => sIntegrationTask::TASK_STATUS_FAILED,
            'message' => $message,
            'finished_at' => now(),
        ]);

        $this->pushProgress($task, [
            'status' => (string)sIntegrationTask::statuses()[$task->status],
            'message' => $message,
        ]);
    }

    /**
     * Recursively extract field names from a nested fields configuration.
     *
     * This utility method processes a fields configuration array to extract all defined
     * field names, including nested fields. It's commonly used for form generation,
     * validation, and settings management in integrations.
     *
     * The method traverses the fields array recursively, looking for 'name' properties
     * in each field definition and collecting them into a flat array.
     *
     * @param array $fields The fields configuration array with nested structure
     * @return array A flat array of field names found in the configuration
     */
    protected function extractFieldNames(array $fields): array
    {
        $names = [];

        foreach ($fields as $key => $field) {
            if (isset($field['name'])) {
                $names[] = $field['name'];
            }

            if (isset($field['fields']) && is_array($field['fields'])) {
                $names = array_merge($names, $this->extractFieldNames($field['fields']));
            }
        }

        return $names;
    }
}