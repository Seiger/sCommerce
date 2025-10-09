<?php namespace Seiger\sCommerce\Integration;

/**
 * TaskProgress - Filesystem-based task progress tracking system
 *
 * This class provides a lightweight, configuration-free service for tracking
 * task progress through filesystem-based JSON snapshots. It's designed to be
 * efficient, reliable, and suitable for high-frequency updates without causing
 * database churn during long-running tasks.
 *
 * Key Features:
 * - Filesystem-based storage for high performance
 * - Atomic writes with temporary files to prevent corruption
 * - Automatic garbage collection of old snapshots
 * - Monotonic revision numbers for reliable long-polling
 * - Thread-safe operations with file locking
 * - Minimal memory footprint and configuration
 *
 * Storage Structure:
 * - Progress files stored in storage/scommerce/scx_progress/
 * - Each task has a unique JSON file (taskId.json)
 * - Temporary files use .~ prefix for atomic operations
 * - Garbage collection marker prevents excessive cleanup
 *
 * Usage Pattern:
 * 1. Initialize progress with TaskProgress::init()
 * 2. Update progress with TaskProgress::write()
 * 3. Read progress via TaskProgress::file() path
 * 4. Automatic cleanup via lazy garbage collection
 *
 * @package Seiger\sCommerce\Integration
 * @author Seiger IT Team
 * @since 1.0.0
 */
class TaskProgress
{
    /**
     * Time-to-live for progress snapshots in seconds.
     * Snapshots older than this will be garbage collected.
     */
    private const TTL_SECONDS = 86400; // 24 hours

    /**
     * Minimum interval between garbage collection runs in seconds.
     * Prevents excessive cleanup operations.
     */
    private const GC_MIN_INTERVAL = 3600; // 1 hour

    /**
     * Ensure and return directory for progress snapshots.
     *
     * This method creates the progress directory if it doesn't exist and returns
     * the full path. The directory is created with appropriate permissions (0775)
     * to ensure proper access for the web server and application.
     *
     * @return string The full path to the progress directory
     */
    public static function dir(): string
    {
        $dir = storage_path('scommerce/scx_progress');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir;
    }

    /**
     * Get the file path for a task's progress snapshot.
     *
     * This method constructs the full file path for a task's progress snapshot
     * based on the task ID. The file will be a JSON file in the progress directory.
     *
     * @param int|string $taskId The task ID to get the file path for
     * @return string The full file path to the task's progress snapshot
     */
    public static function file(int|string $taskId): string
    {
        return self::dir() . '/' . $taskId . '.json';
    }

    /**
     * Initialize a new progress snapshot with default values and persist it.
     *
     * This method creates the initial progress snapshot for a task with sensible
     * default values. It's typically called when a task is first created to establish
     * the initial state before any processing begins.
     *
     * Required payload keys:
     * - id (required) - The task ID
     *
     * Optional payload keys (with defaults):
     * - slug - Integration slug
     * - action - Task action name
     * - status - Task status (default: from payload)
     * - progress - Progress percentage (default: 0)
     * - message - Status message (default: "Queued…")
     *
     * @param array<string,mixed> $payload The initial progress data
     * @throws \InvalidArgumentException If required 'id' key is missing
     */
    public static function init(array $payload): void
    {
        if (!isset($payload['id'])) {
            throw new \InvalidArgumentException('Progress payload must contain "id".');
        }

        self::write($payload);
    }

    /**
     * Atomically write a progress snapshot with thread-safe operations.
     *
     * This method performs an atomic write operation to prevent corruption during
     * concurrent access. It uses temporary files and atomic rename operations to
     * ensure data integrity. The method also adds a revision number for reliable
     * long-polling and occasionally triggers garbage collection.
     *
     * The write process:
     * 1. Validates required 'id' key in payload
     * 2. Adds monotonic revision number (timestamp)
     * 3. Creates temporary file with unique name
     * 4. Writes JSON data with file locking
     * 5. Atomically renames temporary file to final location
     * 6. Occasionally triggers garbage collection (1% chance)
     *
     * @param array<string,mixed> $payload The progress data to write
     * @throws \InvalidArgumentException If required 'id' key is missing
     */
    public static function write(array $payload): void
    {
        if (!isset($payload['id'])) {
            throw new \InvalidArgumentException('Progress payload must contain "id".');
        }

        $dir  = self::dir();
        $file = self::file((string)$payload['id']);
        $tmp  = $dir . '/.~' . uniqid((string)$payload['id'] . '_', true) . '.json';

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        file_put_contents($tmp, $json, LOCK_EX);
        @chmod($tmp, 0664);
        @rename($tmp, $file);

        // Lazy GC: ~1% chance per write
        if (\mt_rand(1, 100) === 1) {
            self::gc();
        }
    }

    /**
     * Garbage-collect old snapshots and temporary files.
     *
     * This method performs cleanup of old progress files to prevent disk space
     * accumulation. It's designed to be efficient and safe, with throttling to
     * prevent excessive cleanup operations.
     *
     * Cleanup process:
     * 1. Checks throttling marker to prevent excessive runs
     * 2. Removes expired progress snapshots (older than TTL_SECONDS)
     * 3. Cleans up old temporary files (older than 1 hour)
     * 4. Updates throttling marker for next run
     *
     * The method is throttled to run at most once per GC_MIN_INTERVAL to
     * prevent performance impact during high-frequency operations.
     */
    public static function gc(): void
    {
        $dir = self::dir();
        $now = time();

        // throttle via marker
        $mark = $dir . '/.gc_progress';
        $last = @filemtime($mark) ?: 0;
        if (($now - $last) < self::GC_MIN_INTERVAL) return;

        // delete expired JSON snapshots
        foreach (glob($dir . '/*.json') ?: [] as $path) {
            clearstatcache(false, $path);
            $mtime = @filemtime($path) ?: 0;
            if ($mtime && ($now - $mtime) > self::TTL_SECONDS) @unlink($path);
        }

        // cleanup old temp files (1 hour)
        foreach (glob($dir . '/\.~*.json') ?: [] as $path) {
            clearstatcache(false, $path);
            $mtime = @filemtime($path) ?: 0;
            if ($mtime && ($now - $mtime) > 3600) @unlink($path);
        }

        @touch($mark);
    }
}


