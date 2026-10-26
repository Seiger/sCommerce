<?php namespace Seiger\sCommerce\Payment;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\sCommerce;
use Throwable;

/**
 * Class PaymentFlow
 *
 * Stable payment ledger API:
 * - If the payments ledger table exists: records payments into the ledger.
 * - If not: falls back to legacy order-level storage (deprecated).
 *
 * The ledger is the source of truth; s_orders.payment_status is a projection of it.
 * Every ledger write of the package goes through this class.
 */
class PaymentFlow
{
    /**
     * Reserved metadata key for ledger context (parent payment, event scope, transitions).
     * Callers cannot write it.
     *
     * @since 1.5.0
     */
    public const CONTEXT_KEY = '_ledger';

    /**
     * Maximum size of caller metadata, in bytes of JSON.
     *
     * @since 1.5.0
     */
    public const MAX_METADATA_BYTES = 65536;

    /**
     * Allowed payment kinds for ledger records.
     *
     * @var array<int,string>
     */
    private const ALLOWED_KINDS = ['deposit', 'final', 'manual', 'refund'];

    /** Kinds that bring money in; `refund` returns it. */
    private const PAYMENT_KINDS = ['deposit', 'final', 'manual'];

    /** Terminal unsuccessful statuses. */
    private const FAILURE_STATUSES = ['failed', 'rejected', 'expired', 'canceled'];

    /** Statuses of returned money. */
    private const REFUND_STATUSES = ['refunded', 'partially_refunded'];

    /** Statuses of payment rows whose money was received. */
    private const RECEIVED_STATUSES = ['captured', 'disputed', 'refunded', 'partially_refunded'];

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

    /** @var array<int, true> Orders whose foreign-currency rows were already reported. */
    private static array $currencyWarnings = [];

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
     * Record a money movement; idempotent by `(provider, providerRef)`.
     *
     * A known reference applies an allowed status transition. A replay with the same status
     * changes nothing; a transition that is not allowed (for example `captured` → `failed`)
     * is ignored and logged. A replay with another order, kind, amount, currency or parent
     * throws. Without `providerRef` every call inserts a new row.
     *
     * @param sOrder $order Persisted order.
     * @param LedgerEntry $entry Money movement.
     * @return LedgerResult
     * @throws \InvalidArgumentException When the entry is invalid or its currency differs from the order currency.
     * @throws LedgerConflictException When the reference belongs to a different payment.
     * @since 1.5.0
     */
    public function record(sOrder $order, LedgerEntry $entry): LedgerResult
    {
        $this->assertLedgerAvailable();
        $entry = $this->normalizeEntry($order, $entry);

        return DB::transaction(function () use ($order, $entry): LedgerResult {
            // The order row lock serializes sequence numbering and projection per order.
            $this->lockOrder($order);

            if ($entry->providerRef !== null && ($existing = $this->findLocked($entry->provider, $entry->providerRef))) {
                return $this->applyToExisting($order, $existing, $entry);
            }

            $this->assertCreatable($entry);
            $this->assertParent($order, $entry);
            try {
                // A savepoint keeps the outer transaction usable after a duplicate-key error.
                $payment = DB::transaction(fn () => $this->insert($order, $entry));
            } catch (QueryException $exception) {
                if ($entry->providerRef === null || !$this->isProviderRefConflict($exception)
                    || !($existing = $this->findLocked($entry->provider, $entry->providerRef))) {
                    throw $exception;
                }
                return $this->applyToExisting($order, $existing, $entry);
            }

            $this->syncOrderPaymentStatusFromLedger($order);
            return new LedgerResult($payment, true, true, null);
        });
    }

    /**
     * Record an authorization that is not captured yet.
     *
     * @since 1.5.0
     */
    public function recordAuthorized(sOrder $o, string $kind, float $amount, string $currency, string $provider, string $providerRef, array $metadata = []): LedgerResult
    {
        return $this->record($o, new LedgerEntry($kind, 'authorized', $amount, $currency, $provider, $this->requireRef($providerRef), null, $metadata));
    }

    /**
     * Record a failed payment attempt.
     *
     * @since 1.5.0
     */
    public function recordFailed(sOrder $o, string $kind, float $amount, string $currency, string $provider, string $providerRef, array $metadata = []): LedgerResult
    {
        return $this->record($o, new LedgerEntry($kind, 'failed', $amount, $currency, $provider, $this->requireRef($providerRef), null, $metadata));
    }

    /**
     * Cancel a recorded payment, for example a voided authorization.
     *
     * @throws \OutOfBoundsException When no entry with this reference exists yet.
     * @since 1.5.0
     */
    public function recordCanceled(sOrder $o, string $provider, string $providerRef, array $metadata = []): LedgerResult
    {
        return $this->transitionExisting($o, $provider, $providerRef, 'canceled', $metadata);
    }

    /**
     * Record returned money as a separate `refund` entry.
     *
     * @param string $refundRef PSP id of the refund, not of the original payment.
     * @param string|null $parentProviderRef `providerRef` of the original payment on the same order.
     * @since 1.5.0
     */
    public function recordRefunded(sOrder $o, float $amount, string $currency, string $provider, string $refundRef, ?string $parentProviderRef = null, array $metadata = []): LedgerResult
    {
        return $this->record($o, new LedgerEntry('refund', 'refunded', $amount, $currency, $provider, $this->requireRef($refundRef), $parentProviderRef, $metadata));
    }

    /**
     * Mark a recorded payment as disputed.
     *
     * @throws \OutOfBoundsException When no entry with this reference exists yet.
     * @since 1.5.0
     */
    public function recordDisputed(sOrder $o, string $provider, string $providerRef, array $metadata = []): LedgerResult
    {
        return $this->transitionExisting($o, $provider, $providerRef, 'disputed', $metadata);
    }

    /**
     * Find a ledger entry by provider namespace and reference.
     *
     * @since 1.5.0
     */
    public function findByProviderRef(string $provider, string $providerRef): ?sOrderPayment
    {
        if (!$this->ledgerAvailable()) {
            return null;
        }

        return sOrderPayment::query()->where('provider', $this->normalize($provider))->where('provider_ref', trim($providerRef))->first();
    }

    /**
     * Ledger totals of an order in the order currency, with the next installment of its payment schedule.
     *
     * @since 1.5.0
     */
    public function summary(sOrder $order): PaymentSummary
    {
        $rows = $this->ledgerAvailable()
            ? sOrderPayment::query()->where('order_id', $order->id)->orderBy('sequence')->orderBy('id')->get()
            : collect();

        return $this->summarize($order, $rows);
    }

    /**
     * Record a captured payment for an order.
     *
     * Kept for BC; prefer {@see self::record()}. A known `providerRef` returns the existing
     * sequence; a replay with a different amount or currency throws.
     *
     * @param sOrder $order
     * @param string $kind deposit|final|manual
     * @param float $amount
     * @param string $currency
     * @param string $provider Provider namespace, e.g. stripe or manual.
     * @param string|null $providerRef PSP id of the money movement (not a webhook event id).
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

        $result = $this->record($order, new LedgerEntry($kind, 'captured', $amount, $currency, $provider, $providerRef, null, $metadata));
        return (int)$result->payment->sequence;
    }

    /**
     * Update last payment status in the ledger and sync order projection.
     *
     * Kept for BC; prefer {@see self::record()}.
     *
     * @deprecated 1.5.0 Changes whichever row is last, which is wrong for orders with several payments.
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
            $this->lockOrder($order);
            /** @var sOrderPayment|null $payment */
            $payment = sOrderPayment::query()->where('order_id', $order->id)->orderByDesc('sequence')->orderByDesc('id')->lockForUpdate()->first();

            if (!$payment) {
                return false;
            }

            $this->changeStatus($payment, $newStatus, [], null, [
                'manager_user_id' => $managerUserId,
                'note' => $note,
                'changed_at' => now()->toDateTimeString(),
                'from' => (string)$payment->status,
                'to' => $newStatus,
            ]);

            $this->syncOrderPaymentStatusFromLedger($order);
            return true;
        });
    }

    /**
     * Apply a payment status chosen in the manager order form to the ledger.
     *
     * Changes the latest row (any status, as the form always allowed) or creates a manual row
     * when the order has none. A partially refunded payment without a recorded returned amount
     * stays unresolved; no amount is inferred. "Partially paid" is refused: it needs an amount,
     * which the manager records in the Payments panel.
     *
     * @param sOrder $order Order being edited.
     * @param int $orderPaymentStatus sOrder::PAYMENT_STATUS_* chosen by the manager.
     * @param int $managerId Manager user id.
     * @return array<string, mixed> Order history entry describing the change.
     * @throws \InvalidArgumentException For "partially paid", with a translated message.
     * @since 1.5.0
     */
    public function applyManagerOverride(sOrder $order, int $orderPaymentStatus, int $managerId): array
    {
        $this->assertLedgerAvailable();
        if ($orderPaymentStatus === sOrder::PAYMENT_STATUS_PARTIALLY_PAID) {
            throw new \InvalidArgumentException(__('sCommerce::global.payment_partial_use_record'));
        }
        $ledgerStatus = $this->mapOrderStatusToLedgerStatus($orderPaymentStatus);

        return DB::transaction(function () use ($order, $orderPaymentStatus, $ledgerStatus, $managerId): array {
            $this->lockOrder($order);
            /** @var sOrderPayment|null $payment */
            $payment = sOrderPayment::query()->where('order_id', $order->id)->orderByDesc('sequence')->orderByDesc('id')->lockForUpdate()->first();
            $override = ['manager_user_id' => $managerId, 'changed_at' => now()->toDateTimeString()];

            if ($payment) {
                $this->changeStatus($payment, $ledgerStatus, [], null, $override + ['from' => (string)$payment->status, 'to' => $ledgerStatus]);
                $entry = ['payment_ledger_updated' => ['payment_id' => (int)$payment->id, 'status' => $ledgerStatus]];
            } else {
                $amount = in_array($orderPaymentStatus, [sOrder::PAYMENT_STATUS_PAID, sOrder::PAYMENT_STATUS_PARTIALLY_PAID], true)
                    ? self::toMinor($order->cost) / 100 : 0.0;
                $payment = $this->insert($order, new LedgerEntry('manual', $ledgerStatus, $amount, $this->orderCurrency($order), 'manual', null, null, [
                    'admin_override' => $override + ['note' => 'Created by manager via orderSave payment_status change'],
                ]));
                $entry = ['payment_ledger_created' => [
                    'payment_id' => (int)$payment->id,
                    'sequence' => (int)$payment->sequence,
                    'kind' => 'manual',
                    'status' => $ledgerStatus,
                    'amount' => $amount,
                ]];
            }

            $this->syncOrderPaymentStatusFromLedger($order);
            return $entry + ['timestamp' => now()->toDateTimeString(), 'user_id' => $managerId];
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
     * Only rows in the order currency count. The order is saved through the model, so
     * `sCommerce.OrderPaymentStatusChanged` fires, and the given instance is refreshed
     * without becoming dirty.
     *
     * @param sOrder $order
     * @return void
     */
    public function syncOrderPaymentStatusFromLedger(sOrder $order): void
    {
        if (!$this->ledgerAvailable()) {
            return;
        }

        DB::transaction(function () use ($order): void {
            /** @var sOrder|null $fresh */
            $fresh = sOrder::withTrashed()->whereKey($order->id)->lockForUpdate()->first();
            if (!$fresh) {
                return;
            }

            $rows = sOrderPayment::query()->where('order_id', $fresh->id)->orderBy('sequence')->orderBy('id')->get();
            $summary = $this->summarize($fresh, $rows);
            if (($summary->ignoredCurrencies || $summary->unresolvedLegacyRefunds) && !isset(self::$currencyWarnings[(int)$fresh->id])) {
                self::$currencyWarnings[(int)$fresh->id] = true;
                $this->warning('sCommerce payment ledger rows excluded from the order projection', [
                    'order_id' => (int)$fresh->id,
                    'order_currency' => $summary->currency,
                    'ignored_currencies' => $summary->ignoredCurrencies,
                    'unresolved_partial_refunds' => $summary->unresolvedLegacyRefunds,
                ]);
            }

            if ((int)$fresh->payment_status !== $summary->paymentStatus) {
                $fresh->payment_status = $summary->paymentStatus;
                if (!$fresh->save()) {
                    throw new \RuntimeException('Order payment status save was canceled');
                }
            }

            $order->setAttribute('payment_status', $summary->paymentStatus);
            $order->syncOriginalAttribute('payment_status');
        });
    }

    /**
     * Re-project the payment status of an order with ledger rows after its cost changed, once
     * the change commits. Orders without rows keep their status.
     *
     * @since 1.5.0
     */
    public static function resyncAfterCostChange(sOrder $order): void
    {
        $orderId = (int)$order->id;
        $task = static fn () => EventScope::defer(static function () use ($orderId): void {
            try {
                $flow = new self();
                $fresh = $flow->ledgerAvailable() ? sOrder::withTrashed()->find($orderId) : null;
                if ($fresh && sOrderPayment::query()->where('order_id', $orderId)->exists()) {
                    $flow->syncOrderPaymentStatusFromLedger($fresh);
                }
            } catch (Throwable $exception) {
                (new self())->warning('sCommerce payment status resync failed', ['order_id' => $orderId, 'error' => $exception->getMessage()]);
            }
        }, 'payment_resync:' . $orderId);
        try {
            $order->getConnection()->afterCommit($task);
        } catch (\RuntimeException) {
            // Minimal bootstraps without a transactions manager.
            $task();
        }
    }

    /**
     * Read metadata stored by any sCommerce version: an array, JSON, or JSON encoded twice.
     *
     * @param mixed $value Raw or cast `metadata` value.
     * @return array<string, mixed>
     * @since 1.5.0
     */
    public static function normalizeMetadata(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Convert a decimal amount to integer minor units (cents).
     *
     * @since 1.5.0
     */
    public static function toMinor(mixed $amount): int
    {
        if (is_string($amount)) {
            $amount = trim($amount);
            if (preg_match('/\A(-?)(\d+)(?:\.(\d*))?\z/', $amount, $match)) {
                $fraction = str_pad(substr(($match[3] ?? '') . '000', 0, 3), 3, '0');
                $minor = (int)$match[2] * 100 + intdiv((int)$fraction + 5, 10);
                return $match[1] === '-' ? -$minor : $minor;
            }
        }

        return is_numeric($amount) ? (int)round((float)$amount * 100) : 0;
    }

    /**
     * Totals and projected status of the given rows.
     *
     * @param iterable<sOrderPayment> $rows Rows ordered by sequence.
     */
    private function summarize(sOrder $order, iterable $rows): PaymentSummary
    {
        $currency = $this->orderCurrency($order, false);
        $captured = $refunded = $authorized = $pending = $unresolved = 0;
        $disputed = false;
        $ignored = [];
        $last = null;

        foreach ($rows as $row) {
            $rowCurrency = strtoupper(trim((string)$row->currency));
            if ($rowCurrency !== $currency) {
                $ignored[$rowCurrency] = $rowCurrency;
                continue;
            }

            $kind = (string)$row->kind;
            $status = (string)$row->status;
            $amount = self::toMinor($row->getAttributes()['amount'] ?? 0);
            if (in_array($kind, self::PAYMENT_KINDS, true)) {
                if (in_array($status, self::RECEIVED_STATUSES, true)) {
                    $captured += $amount;
                }
                if ($status === 'authorized') {
                    $authorized += $amount;
                } elseif ($status === 'pending') {
                    $pending += $amount;
                } elseif ($status === 'refunded') {
                    // Legacy whole-row refund set by a manager.
                    $refunded += $amount;
                } elseif ($status === 'partially_refunded') {
                    $returned = $this->legacyReturnedAmount($row);
                    if ($returned === null) {
                        $unresolved++;
                    } else {
                        $refunded += min($returned, $amount);
                    }
                }
            } elseif ($kind === 'refund' && in_array($status, self::REFUND_STATUSES, true)) {
                $refunded += $amount;
            }
            $disputed = $disputed || $status === 'disputed';
            $last = $status;
        }

        $total = self::toMinor($order->getAttributes()['cost'] ?? 0);
        $outstanding = max(0, $total - max(0, $captured - $refunded));

        $schedule = PaymentSchedule::of($order);

        return new PaymentSummary(
            $currency,
            $total,
            $captured,
            $refunded,
            $authorized,
            $disputed,
            $this->resolveOrderPaymentStatus($total, $captured, $refunded, $disputed, $unresolved, $last),
            array_values($ignored),
            $unresolved,
            // Installments already received or in progress are not requested again.
            $schedule ? PaymentSchedule::next($schedule, $captured + $authorized + $pending, $outstanding, $total) : null,
            $pending
        );
    }

    /**
     * Resolve sOrder::PAYMENT_STATUS_* from ledger aggregates (minor units) and last attempt.
     */
    private function resolveOrderPaymentStatus(int $total, int $captured, int $refunded, bool $disputed, int $unresolved, ?string $lastLedgerStatus): int
    {
        if ($disputed) {
            return sOrder::PAYMENT_STATUS_DISPUTED;
        }

        if ($captured <= 0) {
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

        $net = max(0, $captured - $refunded);
        if ($refunded > 0) {
            if ($net <= 0) {
                return sOrder::PAYMENT_STATUS_REFUNDED;
            }
            if ($net < $total) {
                return sOrder::PAYMENT_STATUS_PARTIALLY_REFUNDED;
            }
        }

        if ($unresolved > 0) {
            // The manager marked a partial refund without an amount: show it, change no totals.
            return sOrder::PAYMENT_STATUS_PARTIALLY_REFUNDED;
        }

        if ($net < $total) {
            return sOrder::PAYMENT_STATUS_PARTIALLY_PAID;
        }

        return sOrder::PAYMENT_STATUS_PAID;
    }

    /**
     * Explicit returned amount of a legacy partially refunded payment row, in minor units.
     */
    private function legacyReturnedAmount(sOrderPayment $row): ?int
    {
        $metadata = self::normalizeMetadata($row->getAttributes()['metadata'] ?? null);
        $value = $metadata[self::CONTEXT_KEY]['refunded_amount'] ?? $metadata['refunded_amount'] ?? null;

        return is_numeric($value) && (float)$value > 0 ? self::toMinor((string)$value) : null;
    }

    /**
     * Apply an entry to the row that already has its provider reference.
     */
    private function applyToExisting(sOrder $order, sOrderPayment $existing, LedgerEntry $entry): LedgerResult
    {
        if ((int)$existing->order_id !== (int)$order->id) {
            throw new LedgerConflictException("Provider reference {$entry->provider}/{$entry->providerRef} belongs to another order.");
        }

        $stored = self::normalizeMetadata($existing->getAttributes()['metadata'] ?? null);
        $storedParent = $stored[self::CONTEXT_KEY]['parent_provider_ref'] ?? null;
        $existingMinor = self::toMinor($existing->getAttributes()['amount'] ?? 0);
        $entryMinor = self::toMinor($entry->amount);
        $authorizedMinor = isset($stored[self::CONTEXT_KEY]['authorized_amount']) ? self::toMinor((string)$stored[self::CONTEXT_KEY]['authorized_amount']) : null;
        if ((string)$existing->kind === $entry->kind && $entry->status === 'captured' && (string)$existing->status === 'authorized'
            && $entryMinor > 0 && $entryMinor < $existingMinor) {
            // Partial capture: the captured amount replaces the authorized one, which is kept for audit.
            $stored[self::CONTEXT_KEY]['authorized_amount'] = PaymentSummary::decimal($existingMinor);
            $existing->amount = PaymentSummary::decimal($entryMinor);
            $existing->metadata = $stored;
            $existingMinor = $entryMinor;
        } elseif ($authorizedMinor === $entryMinor && $entry->status === 'authorized') {
            // A late replay of the authorization of a partially captured payment.
            $entryMinor = $existingMinor;
        }
        if ((string)$existing->kind !== $entry->kind
            || $existingMinor !== $entryMinor
            || strtoupper((string)$existing->currency) !== $entry->currency
            || ($entry->parentProviderRef !== null && $storedParent !== $entry->parentProviderRef)) {
            throw new LedgerConflictException("Provider reference {$entry->provider}/{$entry->providerRef} was recorded with a different kind, amount, currency or parent.");
        }

        return $this->applyStatus($order, $existing, $entry->status, $entry->metadata, $entry->occurredAt);
    }

    /**
     * Status-only update of an existing reference.
     */
    private function transitionExisting(sOrder $order, string $provider, string $providerRef, string $status, array $metadata): LedgerResult
    {
        $this->assertLedgerAvailable();
        $provider = $this->normalizeProvider($provider);
        $providerRef = $this->requireRef($providerRef);
        $this->assertMetadata($metadata);

        return DB::transaction(function () use ($order, $provider, $providerRef, $status, $metadata): LedgerResult {
            $this->lockOrder($order);
            $existing = $this->findLocked($provider, $providerRef)
                ?? throw new \OutOfBoundsException("No ledger entry for provider reference $provider/$providerRef.");
            if ((int)$existing->order_id !== (int)$order->id) {
                throw new LedgerConflictException("Provider reference $provider/$providerRef belongs to another order.");
            }

            return $this->applyStatus($order, $existing, $status, $metadata, null);
        });
    }

    /**
     * Apply a status transition if the transition table allows it.
     */
    private function applyStatus(sOrder $order, sOrderPayment $payment, string $status, array $metadata, ?\DateTimeImmutable $occurredAt): LedgerResult
    {
        $from = (string)$payment->status;
        if ($from === $status) {
            return new LedgerResult($payment, false, false, $from);
        }
        if (!$this->transitionAllowed((string)$payment->kind, $from, $status)) {
            $this->warning('sCommerce payment ledger ignored a status transition', [
                'payment_id' => (int)$payment->id,
                'provider' => (string)$payment->provider,
                'provider_ref' => (string)$payment->provider_ref,
                'from' => $from,
                'to' => $status,
            ]);
            return new LedgerResult($payment, false, false, $from);
        }

        $this->changeStatus($payment, $status, $metadata, $occurredAt);
        $this->syncOrderPaymentStatusFromLedger($order);

        return new LedgerResult($payment, false, true, $from);
    }

    /**
     * Allowed transitions of the API (manager overrides bypass them).
     */
    private function transitionAllowed(string $kind, string $from, string $to): bool
    {
        if ($kind === 'refund') {
            return $from === 'pending' && (in_array($to, self::REFUND_STATUSES, true) || in_array($to, self::FAILURE_STATUSES, true));
        }

        return match (true) {
            $from === 'pending' => in_array($to, ['authorized', 'captured'], true) || in_array($to, self::FAILURE_STATUSES, true),
            $from === 'authorized' => $to === 'captured' || in_array($to, self::FAILURE_STATUSES, true),
            $from === 'captured' => $to === 'disputed',
            $from === 'disputed' => $to === 'captured' || in_array($to, self::REFUND_STATUSES, true),
            in_array($from, self::FAILURE_STATUSES, true) => $to === 'captured',
            default => false,
        };
    }

    /**
     * Persist a status change with its audit trail.
     *
     * @param array<string, mixed> $metadata Caller metadata; existing keys are kept.
     * @param array<string, mixed>|null $override Manager override audit, stored as `admin_override`.
     */
    private function changeStatus(sOrderPayment $payment, string $status, array $metadata, ?\DateTimeImmutable $occurredAt, ?array $override = null): void
    {
        $from = (string)$payment->status;
        unset($metadata[self::CONTEXT_KEY]);
        $stored = self::normalizeMetadata($payment->getAttributes()['metadata'] ?? null);
        $merged = array_replace($metadata, $stored);
        if ($override !== null) {
            $merged['admin_override'] = $override;
        }
        $scope = EventScope::current();
        $merged[self::CONTEXT_KEY]['transitions'][] = array_filter([
            'from' => $from,
            'to' => $status,
            'at' => now()->toDateTimeString(),
            'source' => $scope['source'],
            'actor_id' => $scope['actorId'] ?: null,
            'causation_id' => $scope['causationId'],
        ], static fn ($value) => $value !== null);

        $payment->status = $status;
        if ($status === 'captured' && empty($payment->captured_at)) {
            $payment->captured_at = $occurredAt ?? now();
        }
        $payment->metadata = $merged;
        if (!$payment->save()) {
            throw new \RuntimeException('Ledger entry save was canceled');
        }
    }

    /**
     * Insert a new row; the caller holds the order lock.
     */
    private function insert(sOrder $order, LedgerEntry $entry): sOrderPayment
    {
        $metadata = $entry->metadata;
        unset($metadata[self::CONTEXT_KEY]);
        $scope = EventScope::current();
        $metadata[self::CONTEXT_KEY] = array_filter([
            'parent_provider_ref' => $entry->parentProviderRef,
            'source' => $scope['source'],
            'actor_id' => $scope['actorId'] ?: null,
            'correlation_id' => $scope['correlationId'],
            'causation_id' => $scope['causationId'],
            'integration_actor' => $scope['integrationActor'],
        ], static fn ($value) => $value !== null);

        $payment = new sOrderPayment();
        $payment->forceFill([
            'order_id' => $order->id,
            'sequence' => (int)sOrderPayment::query()->where('order_id', $order->id)->max('sequence') + 1,
            'kind' => $entry->kind,
            'status' => $entry->status,
            'amount' => PaymentSummary::decimal(self::toMinor($entry->amount)),
            'currency' => $entry->currency,
            'provider' => $entry->provider,
            'provider_ref' => $entry->providerRef,
            'metadata' => $metadata,
            'captured_at' => $entry->status === 'captured' ? ($entry->occurredAt ?? now()) : null,
        ]);
        if (!$payment->save()) {
            throw new \RuntimeException('Ledger entry save was canceled');
        }

        return $payment;
    }

    /**
     * Validate and normalize an entry against its order.
     */
    private function normalizeEntry(sOrder $order, LedgerEntry $entry): LedgerEntry
    {
        $kind = $this->normalize($entry->kind);
        $this->assertAllowedKind($kind);
        $status = $this->normalize($entry->status);
        $this->assertAllowedLedgerStatus($status);

        if (!is_finite($entry->amount) || self::toMinor($entry->amount) <= 0 || $entry->amount > 9999999999.99) {
            throw new \InvalidArgumentException('Ledger amount must be a positive amount with at most two decimals.');
        }

        $orderCurrency = $this->orderCurrency($order);
        $currency = strtoupper(trim($entry->currency)) ?: $orderCurrency;
        if ($currency !== $orderCurrency) {
            throw new \InvalidArgumentException("Ledger currency $currency differs from the order currency $orderCurrency.");
        }

        $parent = $this->normalizeRef($entry->parentProviderRef);
        if ($parent !== null && $kind !== 'refund') {
            throw new \InvalidArgumentException('Only refund entries can reference a parent payment.');
        }
        $this->assertMetadata($entry->metadata);

        return new LedgerEntry($kind, $status, $entry->amount, $currency, $this->normalizeProvider($entry->provider),
            $this->normalizeRef($entry->providerRef), $parent, $entry->metadata, $entry->occurredAt);
    }

    /**
     * New rows start in a status that does not assert a missing history.
     */
    private function assertCreatable(LedgerEntry $entry): void
    {
        $allowed = $entry->kind === 'refund'
            ? array_merge(['pending'], self::REFUND_STATUSES, self::FAILURE_STATUSES)
            : array_merge(['pending', 'authorized', 'captured'], self::FAILURE_STATUSES);
        if (!in_array($entry->status, $allowed, true)) {
            throw new \InvalidArgumentException("A new {$entry->kind} entry cannot start as {$entry->status}.");
        }
    }

    /**
     * A refund parent must be a payment of the same order, currency and provider namespace.
     */
    private function assertParent(sOrder $order, LedgerEntry $entry): void
    {
        if ($entry->parentProviderRef === null) {
            return;
        }

        $parent = $this->findLocked($entry->provider, $entry->parentProviderRef)
            ?? throw new \InvalidArgumentException("Unknown parent payment {$entry->provider}/{$entry->parentProviderRef}.");
        if ((int)$parent->order_id !== (int)$order->id || strtoupper((string)$parent->currency) !== $entry->currency
            || !in_array((string)$parent->kind, self::PAYMENT_KINDS, true)) {
            throw new LedgerConflictException("Parent payment {$entry->provider}/{$entry->parentProviderRef} belongs to another order, currency or kind.");
        }
    }

    /**
     * Caller metadata must be JSON-encodable and within the size limit.
     */
    private function assertMetadata(array $metadata): void
    {
        unset($metadata[self::CONTEXT_KEY]);
        try {
            $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new \InvalidArgumentException('Ledger metadata must be JSON-encodable.');
        }
        if (strlen($json) > self::MAX_METADATA_BYTES) {
            throw new \InvalidArgumentException('Ledger metadata exceeds ' . self::MAX_METADATA_BYTES . ' bytes.');
        }
    }

    /**
     * Whether a query error is a duplicate of the `(provider, provider_ref)` unique index.
     */
    private function isProviderRefConflict(QueryException $exception): bool
    {
        if (!in_array((string)$exception->getCode(), ['23000', '23505'], true)) {
            return false;
        }
        $message = $exception->getMessage();

        // MySQL/MariaDB/PostgreSQL name the index; SQLite names its columns.
        return str_contains($message, 'uniq_provider_ref')
            || (str_contains($message, 'UNIQUE constraint failed') && str_contains($message, '.provider_ref'));
    }

    private function lockOrder(sOrder $order): void
    {
        if (!$order->exists || !sOrder::withTrashed()->whereKey($order->id)->lockForUpdate()->exists()) {
            throw new \InvalidArgumentException('The order must be saved before recording payments.');
        }
    }

    private function findLocked(string $provider, string $providerRef): ?sOrderPayment
    {
        return sOrderPayment::query()->where('provider', $provider)->where('provider_ref', $providerRef)->lockForUpdate()->first();
    }

    /**
     * Order currency, or the main shop currency when the order has none.
     *
     * @param bool $strict Throw instead of returning an empty string for an invalid currency.
     */
    private function orderCurrency(sOrder $order, bool $strict = true): string
    {
        $currency = strtoupper(trim((string)$order->currency));
        if ($currency === '' && function_exists('config')) {
            $currency = strtoupper(trim((string)sCommerce::config('basic.main_currency', '')));
        }
        if (!preg_match('/\A[A-Z]{3}\z/', $currency)) {
            if ($strict) {
                throw new \InvalidArgumentException('The order has no valid currency.');
            }
            return '';
        }

        return $currency;
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = $this->normalize($provider);
        if (!preg_match('/\A[a-z0-9][a-z0-9_.:-]{0,31}\z/', $provider)) {
            throw new \InvalidArgumentException('Provider must be a lowercase key of at most 32 characters.');
        }

        return $provider;
    }

    private function normalizeRef(?string $reference): ?string
    {
        $reference = $reference === null ? '' : trim($reference);
        if (strlen($reference) > 255) {
            throw new \InvalidArgumentException('Provider references are limited to 255 characters.');
        }

        return $reference === '' ? null : $reference;
    }

    /**
     * Shortcuts with a reference parameter must stay idempotent.
     */
    private function requireRef(string $reference): string
    {
        return $this->normalizeRef($reference) ?? throw new \InvalidArgumentException('Provider reference is required.');
    }

    private function assertLedgerAvailable(): void
    {
        if (!$this->ledgerAvailable()) {
            throw new \LogicException('The payments ledger table is not available.');
        }
    }

    private function warning(string $message, array $context): void
    {
        try {
            Log::channel('scommerce')->warning($message, $context);
        } catch (Throwable) {
            // Logging must not break a committed ledger write.
        }
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
}
