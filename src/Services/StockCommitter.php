<?php namespace Seiger\sCommerce\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Checkout\CheckoutContext;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\sCommerce;
use Throwable;

/**
 * Opt-in commitment of order stock: decrements inventory once per order and restores it once
 * when a committed order is cancelled or deleted.
 *
 * `product.inventory_commit` selects the trigger: `none` (default, nothing changes), `order_created`
 * or `order_paid` (payment status *Paid*). A bound {@see \Seiger\sCommerce\Interfaces\StockProviderInterface}
 * may handle commit and release itself. `s_orders.inventory_committed_at` marks committed orders;
 * `meta.inventory` keeps the pending trigger and the committed quantities, so release restores
 * exactly what was taken even after the order lines were edited.
 *
 * Triggers are recorded in the same transaction as the order change and executed after commit,
 * independently of public event listeners; the stock reconciliation worker retries failures.
 * Test orders never touch stock. An ERP that imports stock already net of open orders must keep
 * `product.inventory_commit = none`, or open orders are subtracted twice.
 *
 * @since 1.5.0
 */
final class StockCommitter
{
    /** Event scope source of commit and release writes. */
    public const SOURCE = 'inventory';

    /** `stockSource` of the resulting `sCommerce.ProductStockChanged` events. */
    public const STOCK_SOURCE = 'order';

    public const MODE_NONE = 'none';
    public const MODE_ORDER_CREATED = 'order_created';
    public const MODE_ORDER_PAID = 'order_paid';
    public const MODES = [self::MODE_NONE, self::MODE_ORDER_CREATED, self::MODE_ORDER_PAID];

    /** Key of the commitment state in `s_orders.meta`. */
    public const META_KEY = 'inventory';

    public const COLUMN = 'inventory_committed_at';

    /** Set while this class writes its own `meta.inventory`. */
    private static bool $writing = false;

    /** @var array<int, sOrder> Rows of force-deleted orders captured before deletion. */
    private static array $deleting = [];

    /**
     * Configured trigger; unknown values disable commitment.
     *
     * @since 1.5.0
     */
    public static function mode(): string
    {
        $mode = (string)self::setting('product.inventory_commit', self::MODE_NONE);

        return in_array($mode, self::MODES, true) ? $mode : self::MODE_NONE;
    }

    /**
     * Whether cancelled or deleted orders restore their committed stock.
     *
     * @since 1.5.0
     */
    public static function releaseOnCancel(): bool
    {
        return (int)self::setting('product.inventory_release_on_cancel', 1) === 1;
    }

    /**
     * Settings that conflict with each other or with the installation, as translated messages.
     *
     * @return list<string>
     * @since 1.5.0
     */
    public static function configurationWarnings(): array
    {
        $configured = (string)self::setting('product.inventory_commit', self::MODE_NONE);
        $warnings = [];
        if (!in_array($configured, self::MODES, true)) {
            $warnings[] = __('sCommerce::global.inventory_commit_warning_mode', ['mode' => $configured]);
        }
        if (self::mode() !== self::MODE_NONE) {
            if ((int)self::setting('product.inventory_on', 0) === 0) {
                $warnings[] = __('sCommerce::global.inventory_commit_warning_inventory_off');
            }
            $order = new sOrder();
            if (!$order->hasOptionalColumn(self::COLUMN) || !$order->hasOptionalColumn('meta')) {
                $warnings[] = __('sCommerce::global.inventory_commit_warning_migration');
            }
        }

        return $warnings;
    }

    /**
     * Commit the stock of an order now: the bound provider, otherwise a local decrement of every
     * line that tracks inventory. All lines change or none; inventory never becomes negative.
     *
     * The configured trigger is not checked. Test, cancelled, deleted and committed orders are skipped.
     *
     * @param sOrder|int $order Order or its id.
     * @return bool Whether this call committed the order.
     * @throws StockShortageException When a product has less inventory than the order needs.
     * @since 1.5.0
     */
    public function commit(sOrder|int $order): bool
    {
        $orderId = $order instanceof sOrder ? (int)$order->getKey() : $order;
        if (!self::available()) {
            return false;
        }
        $order = sOrder::query()->find($orderId);
        if (!self::canCommit($order)) {
            return false;
        }

        return EventScope::run(self::SOURCE, 0, function () use ($order, $orderId): bool {
            $provider = StockResolver::provider();
            $committedBy = $provider && $provider->commit($order) ? 'provider' : 'local';

            return DB::transaction(function () use ($orderId, $committedBy): bool {
                /** @var sOrder|null $locked */
                $locked = sOrder::query()->whereKey($orderId)->lockForUpdate()->first();
                if (!self::canCommit($locked)) {
                    return false;
                }
                $quantities = [];
                if ($committedBy === 'local') {
                    $quantities = self::quantities($locked);
                    $rows = StockWriter::lock(array_keys($quantities));
                    foreach ($quantities as $productId => $quantity) {
                        $row = $rows[$productId] ?? null;
                        if (!$row) {
                            self::log('warning', 'sCommerce stock commit skipped a missing product', ['order_id' => $orderId, 'product_id' => $productId]);
                            unset($quantities[$productId]);
                        } elseif ((int)$row->inventory < $quantity) {
                            throw new StockShortageException("Order {$orderId} needs {$quantity} of product {$productId}, {$row->inventory} in stock.");
                        }
                    }
                    foreach ($quantities as $productId => $quantity) {
                        StockWriter::write($rows[$productId], (int)$rows[$productId]->inventory - $quantity, null, self::STOCK_SOURCE);
                    }
                }

                $locked->setAttribute(self::COLUMN, $locked->freshTimestamp());
                self::saveState($locked, ['committed_by' => $committedBy, 'lines' => $quantities, 'committed_at' => self::now()]);

                return true;
            });
        });
    }

    /**
     * Restore the stock of a committed order now: the bound provider when it committed the order,
     * otherwise the quantities this class decremented. Clears the commit marker.
     *
     * @param sOrder|int $order Order or its id; a force-deleted order is restored from the given model.
     * @return bool Whether this call released the order.
     * @since 1.5.0
     */
    public function release(sOrder|int $order): bool
    {
        $orderId = $order instanceof sOrder ? (int)$order->getKey() : $order;
        if (!self::available()) {
            return false;
        }
        $stored = sOrder::withTrashed()->find($orderId);
        $current = $stored ?? ($order instanceof sOrder ? $order : null);
        if (!$current || $current->getAttribute(self::COLUMN) === null) {
            return false;
        }

        return EventScope::run(self::SOURCE, 0, function () use ($current, $stored, $orderId): bool {
            // Whoever committed the stock releases it.
            $handled = (self::state($current)['committed_by'] ?? 'local') === 'provider';
            $provider = $handled ? StockResolver::provider() : null;
            if ($handled && (!$provider || !$provider->release($current))) {
                self::log('warning', 'sCommerce stock provider did not release an order it committed', ['order_id' => $orderId]);
                return false;
            }

            return DB::transaction(function () use ($current, $stored, $orderId, $handled): bool {
                $locked = $stored ? sOrder::withTrashed()->whereKey($orderId)->lockForUpdate()->first() : null;
                if ($stored && (!$locked || $locked->getAttribute(self::COLUMN) === null)) {
                    return false;
                }
                $order = $locked ?? $current;
                if (!$handled) {
                    $state = self::state($order);
                    $quantities = is_array($state['lines'] ?? null) ? array_map('intval', $state['lines']) : self::quantities($order);
                    $rows = StockWriter::lock(array_keys($quantities));
                    foreach ($quantities as $productId => $quantity) {
                        if (isset($rows[$productId]) && $quantity > 0) {
                            StockWriter::write($rows[$productId], (int)$rows[$productId]->inventory + $quantity, null, self::STOCK_SOURCE);
                        }
                    }
                }
                if ($locked) {
                    $locked->setAttribute(self::COLUMN, null);
                    self::saveState($locked, ['released_by' => $handled ? 'provider' : 'local', 'released_at' => self::now()]);
                }

                return true;
            });
        });
    }

    /**
     * Apply line changes of a locally committed order to stock: the difference between the
     * current billable quantities and the committed ones, for changed, added and removed lines.
     *
     * Orders that are not committed, are cancelled or deleted, or were committed by the stock
     * provider are left alone; the provider learns of changes from `sCommerce.OrderUpdated`.
     * A later release restores exactly the adjusted quantities.
     *
     * @param sOrder|int $order Order or its id.
     * @return bool Whether stock changed.
     * @throws StockShortageException When a product has less inventory than an increase needs; nothing changes.
     * @since 1.5.0
     */
    public function adjust(sOrder|int $order): bool
    {
        $orderId = $order instanceof sOrder ? (int)$order->getKey() : $order;
        if (!self::available()) {
            return false;
        }

        return EventScope::run(self::SOURCE, 0, static fn (): bool => DB::transaction(static function () use ($orderId): bool {
            /** @var sOrder|null $locked */
            $locked = sOrder::query()->whereKey($orderId)->lockForUpdate()->first();
            if (!$locked || $locked->getAttribute(self::COLUMN) === null || (int)$locked->status === sOrder::ORDER_STATUS_CANCELED) {
                return false;
            }
            $state = self::state($locked);
            if (($state['committed_by'] ?? 'local') !== 'local' || !is_array($state['lines'] ?? null)) {
                return false;
            }
            $committed = array_map('intval', $state['lines']);
            $current = self::quantities($locked);
            $deltas = [];
            foreach (array_unique(array_merge(array_keys($committed), array_keys($current))) as $productId) {
                $delta = ($current[$productId] ?? 0) - ($committed[$productId] ?? 0);
                if ($delta !== 0) {
                    $deltas[(int)$productId] = $delta;
                }
            }
            if ($deltas === []) {
                return false;
            }
            $rows = StockWriter::lock(array_keys($deltas));
            foreach ($deltas as $productId => $delta) {
                $row = $rows[$productId] ?? null;
                if (!$row) {
                    // A missing product cannot be taken or returned; keep its committed quantity.
                    self::log('warning', 'sCommerce stock adjustment skipped a missing product', ['order_id' => $orderId, 'product_id' => $productId]);
                    unset($deltas[$productId], $current[$productId]);
                    if (isset($committed[$productId])) {
                        $current[$productId] = $committed[$productId];
                    }
                } elseif ($delta > 0 && (int)$row->inventory < $delta) {
                    throw new StockShortageException("Order {$orderId} needs {$delta} more of product {$productId}, {$row->inventory} in stock.");
                }
            }
            foreach ($deltas as $productId => $delta) {
                StockWriter::write($rows[$productId], (int)$rows[$productId]->inventory - $delta, null, self::STOCK_SOURCE);
            }
            ksort($current);
            self::saveState($locked, ['lines' => $current, 'adjusted_at' => self::now()] + $state);

            return $deltas !== [];
        }));
    }

    /**
     * Retry missed or failed commits and releases.
     *
     * @return int Number of committed or released orders.
     * @since 1.5.0
     */
    public function reconcile(): int
    {
        $order = new sOrder();
        if (!self::available() || !$order->hasOptionalColumn('meta')) {
            return 0;
        }

        $done = 0;
        sOrder::query()->live()->whereNull(self::COLUMN)->whereNotNull('meta->' . self::META_KEY . '->due')
            ->where('status', '!=', sOrder::ORDER_STATUS_CANCELED)->select('id')
            ->chunkById(200, function ($orders) use (&$done): void {
                foreach ($orders as $order) {
                    $done += $this->runSafely('commit', (int)$order->id) ? 1 : 0;
                }
            });
        if (self::releaseOnCancel()) {
            sOrder::withTrashed()->whereNotNull(self::COLUMN)
                ->where(static fn ($q) => $q->where('status', sOrder::ORDER_STATUS_CANCELED)->orWhereNotNull('deleted_at'))->select('id')
                ->chunkById(200, function ($orders) use (&$done): void {
                    foreach ($orders as $order) {
                        $done += $this->runSafely('release', (int)$order->id) ? 1 : 0;
                    }
                });
        }

        return $done;
    }

    /**
     * `sOrder` creating hook: record the `order_created` trigger with the new order.
     *
     * @internal
     */
    public static function creating(sOrder $order): void
    {
        $meta = is_array($order->meta) ? $order->meta : null;
        if (isset($meta[self::META_KEY])) {
            // The commitment state is owned by sCommerce, never by checkout listeners.
            unset($meta[self::META_KEY]);
            $order->meta = $meta ?: null;
        }
        if (self::mode() === self::MODE_ORDER_CREATED && CheckoutContext::isLive($order) && self::available() && $order->hasOptionalColumn('meta')) {
            self::putState($order, ['due' => self::MODE_ORDER_CREATED]);
        }
    }

    /**
     * `sOrder` updating hook: keep the stored commitment state and record the `order_paid` trigger,
     * or a new trigger when a released order leaves the cancelled status.
     *
     * @internal
     */
    public static function updating(sOrder $order): void
    {
        if (!self::$writing && $order->isDirty('meta') && $order->hasOptionalColumn('meta')) {
            $stored = $order->newQueryWithoutScopes()->whereKey($order->getKey())->first(['meta']);
            self::putState($order, ($stored ? self::state($stored) : []) ?: null);
        }

        $mode = self::mode();
        if ($mode === self::MODE_NONE || (int)$order->status === sOrder::ORDER_STATUS_CANCELED || !CheckoutContext::isLive($order)) {
            return;
        }
        $paid = (int)$order->payment_status === sOrder::PAYMENT_STATUS_PAID;
        $paidNow = $mode === self::MODE_ORDER_PAID && $order->isDirty('payment_status') && $paid;
        $restored = $order->isDirty('status') && (int)$order->getOriginal('status') === sOrder::ORDER_STATUS_CANCELED
            && ($mode === self::MODE_ORDER_CREATED || $paid);
        if ((!$paidNow && !$restored) || !self::available() || !$order->hasOptionalColumn('meta')) {
            return;
        }
        // The instance may predate a commit or release that ran meanwhile.
        $stored = $order->newQueryWithoutScopes()->whereKey($order->getKey())->first([self::COLUMN, 'meta']);
        $state = $stored ? self::state($stored) : [];
        if (!$stored || $stored->getAttribute(self::COLUMN) !== null || isset($state['due']) || (!$paidNow && !isset($state['released_at']))) {
            return;
        }
        self::putState($order, ['due' => $mode]);
    }

    /**
     * `sOrder` created and updated hooks: schedule a due commit, or a release after cancellation.
     *
     * @internal
     */
    public static function saved(sOrder $order): void
    {
        if ($order->wasChanged('status') && (int)$order->status === sOrder::ORDER_STATUS_CANCELED && self::releaseOnCancel()) {
            // The instance may predate a commit that ran meanwhile; release() reads the stored row.
            self::schedule($order, 'release');
        } elseif ($order->getAttribute(self::COLUMN) === null && isset(self::state($order)['due'])) {
            self::schedule($order, 'commit');
        }
    }

    /**
     * `sOrder` deleting hook: keep the row of a force-deleted order for its release.
     *
     * @internal
     */
    public static function deleting(sOrder $order): void
    {
        if ($order->isForceDeleting() && self::releaseOnCancel() && self::available()
            && ($stored = sOrder::withTrashed()->find($order->getKey())) && $stored->getAttribute(self::COLUMN) !== null) {
            self::$deleting[(int)$order->getKey()] = $stored;
        }
    }

    /**
     * `sOrder` deleted hook: schedule the release of a deleted order.
     *
     * @internal
     */
    public static function deleted(sOrder $order): void
    {
        $orderId = (int)$order->getKey();
        $snapshot = self::$deleting[$orderId] ?? null;
        unset(self::$deleting[$orderId]);
        if (self::releaseOnCancel() && ($snapshot || !$order->isForceDeleting())) {
            self::schedule($order, 'release', $snapshot);
        }
    }

    /**
     * Run after the change of `$order` commits and its operation ends; one task per order, the latest wins.
     */
    private static function schedule(sOrder $order, string $action, ?sOrder $snapshot = null): void
    {
        $orderId = (int)$order->getKey();
        $task = static fn () => EventScope::defer(static fn () => (new self())->runSafely($action, $orderId, $snapshot), 'stock:' . $orderId);
        try {
            $order->getConnection()->afterCommit($task);
        } catch (\RuntimeException) {
            // Minimal bootstraps without a transactions manager.
            $task();
        }
    }

    /**
     * Commit or release and log failures; the reconciliation worker retries later.
     */
    private function runSafely(string $action, int $orderId, ?sOrder $snapshot = null): bool
    {
        try {
            if ($action === 'commit') {
                return $this->commit($orderId);
            }
            $order = $snapshot ?? sOrder::withTrashed()->find($orderId);
            if (!$snapshot && (!$order || ((int)$order->status !== sOrder::ORDER_STATUS_CANCELED && !$order->trashed()))) {
                return false;
            }

            return $this->release($order ?? $orderId);
        } catch (Throwable $exception) {
            self::log($exception instanceof StockShortageException ? 'warning' : 'error', "sCommerce stock {$action} failed",
                ['order_id' => $orderId, 'error' => $exception->getMessage()]);
            return false;
        }
    }

    private static function canCommit(?sOrder $order): bool
    {
        return $order && !$order->trashed() && $order->getAttribute(self::COLUMN) === null
            && (int)$order->status !== sOrder::ORDER_STATUS_CANCELED && CheckoutContext::isLive($order);
    }

    /**
     * Billable quantities of lines that track inventory, by product id in id order: the actual
     * quantity of a measured line when set, else the ordered one.
     *
     * @return array<int, int>
     */
    private static function quantities(sOrder $order): array
    {
        $quantities = [];
        foreach (is_array($order->products) ? $order->products : [] as $line) {
            if (!is_array($line) || !OrderLineIdentity::tracksInventory($line)) {
                continue;
            }
            $productId = (int)($line['product_id'] ?? $line['id'] ?? 0);
            $quantity = MeasuredQuantity::effective($line);
            if ($productId > 0 && $quantity > 0) {
                $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
            }
        }
        ksort($quantities);

        return $quantities;
    }

    /**
     * @return array<string, mixed>
     */
    private static function state(sOrder $order): array
    {
        $meta = $order->meta;
        $state = is_array($meta) ? ($meta[self::META_KEY] ?? null) : null;

        return is_array($state) ? $state : [];
    }

    private static function putState(sOrder $order, ?array $state): void
    {
        $meta = is_array($order->meta) ? $order->meta : [];
        if ($state === null) {
            unset($meta[self::META_KEY]);
        } else {
            $meta[self::META_KEY] = $state;
        }
        $order->meta = $meta ?: null;
    }

    /**
     * Save the order with a new commitment state; without the `meta` column only the marker is saved.
     */
    private static function saveState(sOrder $order, array $state): void
    {
        if ($order->hasOptionalColumn('meta')) {
            self::putState($order, $state);
        }
        self::$writing = true;
        try {
            $order->save();
        } finally {
            self::$writing = false;
        }
    }

    /**
     * Whether the commit marker column exists; a positive answer is kept for the process.
     */
    private static function available(): bool
    {
        try {
            return (new sOrder())->hasOptionalColumn(self::COLUMN);
        } catch (Throwable) {
            return false;
        }
    }

    private static function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    private static function setting(string $key, mixed $default): mixed
    {
        try {
            return sCommerce::config($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }

    private static function log(string $level, string $message, array $context): void
    {
        try {
            Log::channel('scommerce')->{$level}($message, $context);
        } catch (Throwable) {
            // Logging must not break the committed order change.
        }
    }
}
