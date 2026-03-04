<?php namespace Seiger\sCommerce\Payment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Throwable;

/**
 * Class PaymentFlow
 *
 * Transitional payment API:
 * - If the payments ledger table exists: records payments into the ledger (new flow).
 * - If not: falls back to legacy order-level storage (deprecated).
 *
 * The ledger is the source of truth; s_orders.payment_status is treated as a projection.
 */
class PaymentFlow
{
    /**
     * Allowed payment kinds for ledger records.
     *
     * @var array<int,string>
     */
    private const ALLOWED_KINDS = ['deposit', 'final', 'manual', 'refund'];

    /**
     * Allowed ledger statuses for a single payment record.
     *
     * @var array<int,string>
     */
    private const ALLOWED_LEDGER_STATUSES = [
        'pending',
        'authorized',
        'captured',
        'failed',
        'refunded',
        'partially_refunded',
        'canceled',
        'rejected',
        'expired',
        'disputed',
    ];

    /**
     * Determine whether payments ledger table exists.
     *
     * @return bool
     */
    public function ledgerAvailable(): bool
    {
        // Schema::hasTable applies connection prefix internally, so model table name
        // should be passed without manual prefix concatenation.
        $table = (new sOrderPayment())->getTable();
        if (Schema::hasTable($table)) {
            return true;
        }

        // Backward-compat fallback for cases when model table is already prefixed.
        $prefix = DB::getTablePrefix();
        if ($prefix !== '' && !str_starts_with($table, $prefix)) {
            return Schema::hasTable($prefix . $table);
        }

        return false;
    }

    /**
     * Record a captured payment for an order.
     *
     * @param sOrder $order
     * @param string $kind deposit|final|manual
     * @param float $amount
     * @param string $currency
     * @param string $provider stripe|manual|other
     * @param string|null $providerRef Provider reference (e.g., Stripe event id).
     * @param array<string,mixed> $metadata
     * @return int Payment sequence number (1..N). Returns 0 in legacy mode.
     */
    public function recordCaptured(
        sOrder $order,
        string $kind,
        float $amount,
        string $currency,
        string $provider,
        ?string $providerRef = null,
        array $metadata = []
    ): int {
        if (!$this->ledgerAvailable()) {
            // Legacy fallback (deprecated)
            return 0;
        }

        $kind = $this->normalize($kind);
        $this->assertAllowedKind($kind);

        $currency = $this->normalizeCurrency($currency);

        return (int) DB::transaction(function () use ($order, $kind, $amount, $currency, $provider, $providerRef, $metadata) {

            // Anti-dup: if provider_ref exists and already recorded, return existing sequence.
            if (!empty($providerRef)) {
                $existing = sOrderPayment::query()->where('provider', $provider)->where('provider_ref', $providerRef)->first();

                if ($existing) {
                    return (int) $existing->sequence;
                }
            }

            $maxSeq = (int)sOrderPayment::query()->where('order_id', $order->id)->max('sequence');
            $seq = $maxSeq + 1;

            sOrderPayment::query()->create([
                'order_id' => $order->id,
                'sequence' => $seq,
                'kind' => $kind,
                'status' => 'captured',
                'amount' => (float)$amount,
                'currency' => $currency,
                'provider' => $provider,
                'provider_ref' => $providerRef,
                'metadata' => $this->safeJson($metadata),
                'captured_at' => now(),
            ]);

            $this->syncOrderPaymentStatusFromLedger($order);
            return $seq;
        });
    }

    /**
     * Update last payment status in the ledger and sync order projection.
     *
     * @param sOrder $order
     * @param string $newStatus One of allowed ledger statuses.
     * @param int|null $managerUserId
     * @param string|null $note
     * @return bool
     */
    public function updateLastPaymentStatus(
        sOrder $order,
        string $newStatus,
        ?int $managerUserId = null,
        ?string $note = null
    ): bool {
        if (!$this->ledgerAvailable()) {
            return false;
        }

        $newStatus = $this->normalize($newStatus);
        $this->assertAllowedLedgerStatus($newStatus);

        return (bool)DB::transaction(function () use ($order, $newStatus, $managerUserId, $note) {
            /** @var sOrderPayment|null $payment */
            $payment = sOrderPayment::query()->where('order_id', $order->id)->orderByDesc('sequence')->orderByDesc('id')->first();

            if (!$payment) {
                return false;
            }

            $meta = $this->decodeMetadata((string)($payment->metadata ?? ''));

            $meta['admin_override'] = [
                'manager_user_id' => $managerUserId,
                'note' => $note,
                'changed_at' => now()->toDateTimeString(),
                'from' => (string) $payment->status,
                'to' => $newStatus,
            ];

            $payment->status = $newStatus;
            $payment->metadata = $this->safeJson($meta);
            $payment->save();

            $this->syncOrderPaymentStatusFromLedger($order);
            return true;
        });
    }

    /**
     * Convert order-level payment status into a ledger status for admin manual overrides.
     *
     * @param int $orderPaymentStatus sOrder::PAYMENT_STATUS_*
     * @return string
     */
    public function mapOrderStatusToLedgerStatus(int $orderPaymentStatus): string
    {
        return match ($orderPaymentStatus) {
            sOrder::PAYMENT_STATUS_PAID => 'captured',
            sOrder::PAYMENT_STATUS_PARTIALLY_PAID => 'captured',
            sOrder::PAYMENT_STATUS_FAILED => 'failed',
            sOrder::PAYMENT_STATUS_REJECTED => 'rejected',
            sOrder::PAYMENT_STATUS_EXPIRED => 'expired',
            sOrder::PAYMENT_STATUS_CANCELED => 'canceled',
            sOrder::PAYMENT_STATUS_AUTHORIZED => 'authorized',
            sOrder::PAYMENT_STATUS_REFUNDED => 'refunded',
            sOrder::PAYMENT_STATUS_PARTIALLY_REFUNDED => 'partially_refunded',
            sOrder::PAYMENT_STATUS_DISPUTED => 'disputed',
            sOrder::PAYMENT_STATUS_AWAITING_CONFIRMATION => 'pending',
            default => 'pending',
        };
    }

    /**
     * Synchronize order.payment_status based on ledger aggregates and last attempt status.
     *
     * @param sOrder $order
     * @return void
     */
    public function syncOrderPaymentStatusFromLedger(sOrder $order): void
    {
        if (!$this->ledgerAvailable()) {
            return;
        }

        $total = (float)$order->cost;
        $captured = (float)sOrderPayment::query()->where('order_id', $order->id)->where('status', 'captured')->whereIn('kind', ['deposit', 'final', 'manual'])->sum('amount');
        $refunded = (float)sOrderPayment::query()->where('order_id', $order->id)->whereIn('status', ['refunded', 'partially_refunded'])->sum('amount');
        $net = max(0.0, $captured - $refunded);

        /** @var sOrderPayment|null $last */
        $last = sOrderPayment::query()->where('order_id', $order->id)->orderByDesc('sequence')->orderByDesc('id')->first();
        $hasAuthorized = (bool)sOrderPayment::query()->where('order_id', $order->id)->where('status', 'authorized')->exists();

        $paymentStatus = $this->resolveOrderPaymentStatus(
            $total,
            $net,
            $captured,
            $refunded,
            $hasAuthorized,
            $last ? (string) $last->status : null
        );

        // Use Eloquent table resolution (prefix-safe)
        $order->newQuery()->whereKey($order->id)->update([
            'payment_status' => $paymentStatus,
            'updated_at' => now(),
        ]);
    }

    /**
     * Resolve sOrder::PAYMENT_STATUS_* from ledger aggregates and last attempt.
     *
     * @param float $total
     * @param float $net
     * @param float $captured
     * @param float $refunded
     * @param bool $hasAuthorized
     * @param string|null $lastLedgerStatus
     * @return int
     */
    private function resolveOrderPaymentStatus(
        float $total,
        float $net,
        float $captured,
        float $refunded,
        bool $hasAuthorized,
        ?string $lastLedgerStatus
    ): int {
        if ($lastLedgerStatus === 'disputed') {
            return sOrder::PAYMENT_STATUS_DISPUTED;
        }

        if ($captured <= 0.00001) {
            return match ($lastLedgerStatus) {
                'failed' => sOrder::PAYMENT_STATUS_FAILED,
                'expired' => sOrder::PAYMENT_STATUS_EXPIRED,
                'canceled' => sOrder::PAYMENT_STATUS_CANCELED,
                'rejected' => sOrder::PAYMENT_STATUS_REJECTED,
                'authorized' => sOrder::PAYMENT_STATUS_AUTHORIZED,
                'pending' => sOrder::PAYMENT_STATUS_AWAITING_CONFIRMATION,
                default => sOrder::PAYMENT_STATUS_PENDING,
            };
        }

        if ($refunded > 0) {
            if ($net <= 0.00001) {
                return sOrder::PAYMENT_STATUS_REFUNDED;
            }
            if ($net + 0.00001 < $total) {
                return sOrder::PAYMENT_STATUS_PARTIALLY_REFUNDED;
            }
        }

        if ($net + 0.00001 < $total) {
            return sOrder::PAYMENT_STATUS_PARTIALLY_PAID;
        }

        return sOrder::PAYMENT_STATUS_PAID;
    }

    /**
     * Normalize string input.
     *
     * @param string $value
     * @return string
     */
    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * Normalize currency code.
     *
     * @param string $currency
     * @return string
     */
    private function normalizeCurrency(string $currency): string
    {
        $c = strtoupper(trim($currency));
        return $c !== '' ? $c : 'UAH';
    }

    /**
     * Validate kind against allowed list.
     *
     * @param string $kind
     * @return void
     */
    private function assertAllowedKind(string $kind): void
    {
        if (!in_array($kind, self::ALLOWED_KINDS, true)) {
            throw new \InvalidArgumentException('Invalid payment kind: ' . $kind);
        }
    }

    /**
     * Validate ledger status against allowed list.
     *
     * @param string $status
     * @return void
     */
    private function assertAllowedLedgerStatus(string $status): void
    {
        if (!in_array($status, self::ALLOWED_LEDGER_STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid ledger payment status: ' . $status);
        }
    }

    /**
     * Decode metadata JSON safely.
     *
     * @param string $json
     * @return array<string,mixed>
     */
    private function decodeMetadata(string $json): array
    {
        if ($json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Encode metadata as JSON safely.
     *
     * @param array<string,mixed> $data
     * @return string|null
     */
    private function safeJson(array $data): ?string
    {
        if (empty($data)) {
            return null;
        }

        try {
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return json_encode(['_error' => 'metadata_encode_failed'], JSON_UNESCAPED_UNICODE);
        }
    }
}