<?php namespace Seiger\sCommerce\Integration;

use Seiger\sCommerce\Services\StockCommitter;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Workers\BaseWorker;

/**
 * Retries order stock commits and releases that failed or were missed.
 *
 * The migration registers this class as a hidden sTask worker that runs every 15 minutes.
 * {@see StockCommitter} is idempotent, so overlapping or repeated runs change nothing twice.
 *
 * @since 1.5.0
 */
class StockReconciliation extends BaseWorker
{
    public function identifier(): string
    {
        return 'sCommerceStockReconciliation';
    }

    public function scope(): string
    {
        return 'sCommerce';
    }

    public function icon(): string
    {
        return '<i class="fas fa-boxes"></i>';
    }

    public function title(): string
    {
        return __('sCommerce::global.stock_reconciliation_worker');
    }

    public function description(): string
    {
        return __('sCommerce::global.stock_reconciliation_worker_desc');
    }

    /**
     * Hidden worker without manager controls.
     */
    public function renderWidget(): string
    {
        return '';
    }

    public function getSchedule(): array
    {
        return $this->getConfig('schedule', [
            'type' => 'periodic',
            'enabled' => true,
            'frequency' => 'every_15min',
            'time' => '',
            'days' => [],
        ]);
    }

    /**
     * @param sTaskModel $task Task used for status reporting.
     * @param array<string, mixed> $opt Unused.
     */
    public function taskMake(sTaskModel $task, array $opt = []): void
    {
        $task->update([
            'status' => sTaskModel::TASK_STATUS_RUNNING,
            'progress' => 10,
            'message' => __('sCommerce::global.stock_reconciliation_worker') . '...',
        ]);

        $done = (new StockCommitter())->reconcile();

        $task->update(['progress' => 100]);
        $this->markFinished($task, null, __('sCommerce::global.stock_reconciliation_worker_done', ['orders' => $done]));
    }
}
