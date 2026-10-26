<?php namespace Seiger\sCommerce\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Interfaces\OfflineSettlementPaymentMethodInterface;
use Seiger\sCommerce\Interfaces\PaymentMethodInterface;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Models\sPaymentMethod;
use Seiger\sCommerce\Models\sPaymentOperation;
use Seiger\sCommerce\Payment\LedgerEntry;
use Seiger\sCommerce\Payment\LedgerResult;
use Seiger\sCommerce\Payment\PaymentFlow;
use Seiger\sCommerce\Payment\PaymentMethodResolver;
use Throwable;

/**
 * Records the unpaid balance of an offline payment (cash on delivery, bank transfer) as
 * received when the order reaches one of the `settle_on_statuses` of its payment method.
 *
 * Disabled by default. Committed status changes schedule a settlement; the payment
 * reconciliation worker retries missed ones, so correctness does not depend on public
 * event listeners. The ledger reference `settle/{orderId}/{status}` makes it idempotent.
 * Enable it only when no other owner (an ERP receipt import or a financial package) settles
 * the same method.
 *
 * @since 1.5.0
 */
final class OfflineSettlement
{
    /** Event scope source of settlement writes. */
    public const SOURCE = 'offline_settlement';

    private const OPEN_PAYMENT_STATUSES = [
        sOrder::PAYMENT_STATUS_PENDING,
        sOrder::PAYMENT_STATUS_AWAITING_CONFIRMATION,
        sOrder::PAYMENT_STATUS_PARTIALLY_PAID,
    ];

    public function __construct(private readonly PaymentFlow $flow = new PaymentFlow())
    {
    }

    /**
     * Schedule settlement after the status change of `$order` commits and its operation ends.
     *
     * @since 1.5.0
     */
    public static function schedule(sOrder $order): void
    {
        $orderId = (int)$order->id;
        $task = static fn () => EventScope::defer(static fn () => (new self())->settleSafely($orderId), self::SOURCE . ':' . $orderId);
        try {
            $order->getConnection()->afterCommit($task);
        } catch (\RuntimeException) {
            // Minimal bootstraps without a transactions manager.
            $task();
        }
    }

    /**
     * Order statuses that settle a payment method; empty when disabled.
     *
     * @return list<int>
     * @since 1.5.0
     */
    public static function statusesOf(PaymentMethodInterface $method): array
    {
        if (!$method instanceof OfflineSettlementPaymentMethodInterface) {
            return [];
        }
        $statuses = $method->getSettings()['settle_on_statuses'] ?? [];
        $statuses = is_array($statuses) ? $statuses : [$statuses];

        return array_values(array_unique(array_map('intval', array_filter($statuses, static fn ($status) => is_numeric($status) && (int)$status > 0))));
    }

    /**
     * Settle one order if its current status and payment method call for it.
     *
     * @return LedgerResult|null The recorded capture, or null when nothing was settled.
     * @since 1.5.0
     */
    public function settle(int $orderId): ?LedgerResult
    {
        if (!self::tablesAvailable()) {
            return null;
        }
        $order = sOrder::query()->find($orderId);
        $method = $order ? PaymentMethodResolver::forOrder($order) : null;
        if (!$method || !in_array((int)$order->status, self::statusesOf($method), true)) {
            return null;
        }

        return EventScope::run(self::SOURCE, 0, fn () => DB::transaction(function () use ($order, $method): ?LedgerResult {
            /** @var sOrder|null $locked */
            $locked = sOrder::query()->whereKey($order->id)->lockForUpdate()->first();
            if (!$locked || !in_array((int)$locked->status, self::statusesOf($method), true)) {
                return null;
            }
            $active = sPaymentOperation::query()->where('order_id', $locked->id)->whereIn('state', sPaymentOperation::ACTIVE_STATES)->exists();
            $summary = $this->flow->summary($locked);
            // Returned money needs a new explicit collection, never an automatic recollection.
            if ($active || $summary->refundedMinor > 0 || $summary->outstandingMinor() <= 0 || $summary->ignoredCurrencies !== []) {
                return null;
            }
            $provider = $method->getName();
            $reference = "settle/{$locked->id}/{$locked->status}";
            if ($this->flow->findByProviderRef($provider, $reference)) {
                return null;
            }

            return $this->flow->record($locked, new LedgerEntry('final', 'captured', $summary->outstandingMinor() / 100, $summary->currency,
                $provider, $reference, null, ['source' => self::SOURCE, 'trigger_status' => (int)$locked->status]));
        }));
    }

    /**
     * Settle orders whose settlement was missed, e.g. after a failure or a direct status write.
     *
     * @return int Number of settled orders.
     * @since 1.5.0
     */
    public function reconcile(): int
    {
        if (!self::tablesAvailable()) {
            return 0;
        }

        $statuses = [];
        foreach (sPaymentMethod::query()->get() as $row) {
            $method = PaymentMethodResolver::forKey($row->name . $row->identifier);
            $statuses = array_merge($statuses, $method ? self::statusesOf($method) : []);
        }
        if ($statuses === []) {
            return 0;
        }

        $settled = 0;
        sOrder::query()->whereIn('status', array_unique($statuses))->whereIn('payment_status', self::OPEN_PAYMENT_STATUSES)
            ->select('id')->chunkById(200, function ($orders) use (&$settled): void {
                foreach ($orders as $order) {
                    $settled += $this->settleSafely((int)$order->id) ? 1 : 0;
                }
            });

        return $settled;
    }

    /**
     * Whether the ledger and payment method tables exist; installations before the ledger
     * migration have nothing to settle. A positive answer is kept for the process.
     */
    private static function tablesAvailable(): bool
    {
        static $available = false;
        if (!$available) {
            try {
                $schema = (new sPaymentMethod())->getConnection()->getSchemaBuilder();
                $available = $schema->hasTable((new sPaymentMethod())->getTable()) && $schema->hasTable((new sOrderPayment())->getTable());
            } catch (Throwable) {
                return false;
            }
        }

        return $available;
    }

    /**
     * Settle and log failures; the reconciliation worker retries later.
     */
    private function settleSafely(int $orderId): bool
    {
        try {
            return $this->settle($orderId) !== null;
        } catch (Throwable $exception) {
            try {
                Log::channel('scommerce')->error('sCommerce offline settlement failed', ['order_id' => $orderId, 'error' => $exception->getMessage()]);
            } catch (Throwable) {
                // Logging must not break the committed status change.
            }
            return false;
        }
    }
}
