<?php namespace Seiger\sCommerce\Integration;

use Seiger\sCommerce\Services\OfflineSettlement;
use Seiger\sCommerce\Services\OrderPaymentAction;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Workers\BaseWorker;

/**
 * Retries offline settlements and resolves unfinished manager payment operations.
 *
 * The migration registers this class as a hidden sTask worker that runs every 15 minutes.
 * Both services are idempotent, so overlapping or repeated runs change nothing twice.
 *
 * @since 1.5.0
 */
class PaymentReconciliation extends BaseWorker
{
    public function identifier(): string
    {
        return 'sCommercePaymentReconciliation';
    }

    public function scope(): string
    {
        return 'sCommerce';
    }

    public function icon(): string
    {
        return '<i class="fas fa-money-check-alt"></i>';
    }

    public function title(): string
    {
        return __('sCommerce::global.payment_reconciliation_worker');
    }

    public function description(): string
    {
        return __('sCommerce::global.payment_reconciliation_worker_desc');
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
            'message' => __('sCommerce::global.payment_reconciliation_worker') . '...',
        ]);

        $settled = (new OfflineSettlement())->reconcile();
        $task->update(['progress' => 60]);
        $resolved = (new OrderPaymentAction())->recover();

        $task->update(['progress' => 100]);
        $this->markFinished($task, null, __('sCommerce::global.payment_reconciliation_worker_done', [
            'settled' => $settled,
            'resolved' => $resolved,
        ]));
    }
}
