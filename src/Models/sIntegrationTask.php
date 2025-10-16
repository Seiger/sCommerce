<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated
 * sIntegrationTask - Model for integration task management
 *
 * This model represents individual background tasks in the sCommerce integration system.
 * Each task represents a single long-running operation (export, import, sync, cleanup)
 * that can be executed asynchronously by the TaskWorker system.
 *
 * Key Features:
 * - Asynchronous task execution with status tracking
 * - Flexible scheduling (immediate or delayed execution)
 * - Progress tracking and status updates
 * - Result file management for completed tasks
 * - User attribution and metadata storage
 * - Comprehensive query scopes for task filtering
 *
 * Database Table: s_integration_tasks
 * Primary Key: id (auto-increment)
 *
 * Task Lifecycle:
 * 1. QUEUED - Task created and waiting for execution
 * 2. PREPARING - Task is being prepared for execution
 * 3. RUNNING - Task is currently executing
 * 4. SAVING - Task is saving results
 * 5. FINISHED - Task completed successfully
 * 6. FAILED - Task failed with error
 *
 * @package Seiger\sCommerce\Models
 * @author Seiger IT Team
 * @since 1.0.0
 */
class sIntegrationTask extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int,string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'meta'        => 'array',
        'start_at'    => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Task status constants defining the execution lifecycle.
     *
     * These constants represent the various states a task can be in during
     * its execution lifecycle. The numeric values allow for easy comparison
     * and ordering of task states.
     */
    public const TASK_STATUS_QUEUED    = 10;  // Task is queued for execution
    public const TASK_STATUS_PREPARING = 20;  // Task is being prepared
    public const TASK_STATUS_RUNNING   = 30;  // Task is currently running
    public const TASK_STATUS_SAVING    = 40;  // Task is saving results
    public const TASK_STATUS_FINISHED  = 50;  // Task completed successfully
    public const TASK_STATUS_FAILED    = 90;  // Task failed with error

    /**
     * Get all task status constants as an associative array.
     *
     * This method dynamically retrieves all TASK_STATUS_* constants from the class
     * and returns them as an associative array where keys are the numeric values
     * and values are the human-readable status names.
     *
     * The returned array format:
     * - Key: numeric status value (e.g., 10, 20, 30)
     * - Value: lowercase status name (e.g., 'queued', 'preparing', 'running')
     *
     * @return array Associative array of status values and names
     */
    public static function statuses(): array
    {
        $list = [];
        $class = new \ReflectionClass(__CLASS__);
        foreach ($class->getConstants() as $constant => $value) {
            if (str_starts_with($constant, 'TASK_STATUS_')) {
                $const = strtolower(str_replace('TASK_STATUS_', '' ,$constant));
                $list[$value] = $const;
            }
        }
        return $list;
    }

    /**
     * Scope: tasks that are not yet finished or failed.
     *
     * Filters tasks that are still in progress (not in FINISHED or FAILED status).
     * Useful for finding active tasks that need monitoring.
     *
     * @param Builder $q
     * @return Builder
     */
    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNotIn('status', [self::TASK_STATUS_FINISHED, self::TASK_STATUS_FAILED]);
    }

    /**
     * Scope: tasks that must be started now.
     * 
     * Filters tasks that are in QUEUED status and either:
     * - have start_at time less than or equal to current time (scheduled tasks)
     * - have start_at set to NULL (immediate execution tasks)
     * 
     * This scope is used by TaskWorker to identify tasks that are ready
     * for execution, supporting both scheduled and immediate task execution.
     * 
     * @param Builder $q
     * @return Builder
     */
    public function scopeStartNow(Builder $q): Builder
    {
        return $q->where('status', self::TASK_STATUS_QUEUED)->where(function($query) {
            $query->where('start_at', '<=', now())->orWhereNull('start_at');
        });
    }

    /**
     * Scope: filter tasks by integration key (slug).
     *
     * Filters tasks that belong to a specific integration type.
     * Useful for finding all tasks for a particular integration.
     *
     * @param Builder $q
     * @param string  $slug The integration slug to filter by
     * @return Builder
     */
    public function scopeForSlug(Builder $q, string $slug): Builder
    {
        return $q->where('slug', $slug);
    }

    /**
     * Mark task as failed and persist error message.
     *
     * Updates task status to FAILED, sets the error message,
     * records the finished_at timestamp, and saves to database.
     *
     * @param string $message Error message describing the failure
     * @return void
     */
    public function markFailed(string $message): void
    {
        $this->status  = self::TASK_STATUS_FAILED;
        $this->message = $message;
        $this->finished_at = now();
        $this->save();
    }

    /**
     * Check if task is finished successfully.
     *
     * Convenience method to check if task status is FINISHED.
     * This is an accessor that can be used as a property: $task->is_finished
     *
     * @return bool True if task status is FINISHED, false otherwise
     */
    public function getIsFinishedAttribute(): bool
    {
        return (int)$this->status === self::TASK_STATUS_FINISHED;
    }
}
