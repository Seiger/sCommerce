<?php namespace Seiger\sCommerce\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderShipment;
use Seiger\sCommerce\sCommerce;
use Seiger\sCommerce\Shipping\ShipmentConflictException;
use Seiger\sCommerce\Shipping\ShipmentData;
use Throwable;

/**
 * Public API for shipments and tracking: managers, ERPs and carrier integrations create and
 * advance shipments here instead of writing `s_orders` directly.
 *
 * Every change records `sCommerce.ShipmentUpdated` after commit and rewrites the template
 * projection `delivery_info.tracking`. With `shipping.sync_order_status` on, fully shipped or
 * delivered orders also move to SHIPPED or DELIVERED (never backwards, never from COMPLETED or later).
 *
 * @since 1.5.0
 */
final class OrderShipments
{
    /** Event scope source of the manager tracking form. */
    public const SOURCE_MANAGER = 'manager_shipment';

    /** Partial shipment policies of `shipping.partial_policy`. */
    public const PARTIAL_ON_COMPLETE = 'on_complete';
    public const PARTIAL_ON_FIRST = 'on_first';

    public const MAX_METADATA_BYTES = 65536;

    /**
     * Create a shipment or update the one with the same `(source, externalRef)`.
     *
     * Repeating the same data changes nothing. Status moves only forward along
     * `pending → ready → shipped → in_transit → delivered`; `failed`, `returned` and
     * `canceled` end any unfinished shipment. Other moves are ignored and logged.
     *
     * @param sOrder $order Persisted order.
     * @param ShipmentData $data Shipment state.
     * @return sOrderShipment The stored shipment; `wasRecentlyCreated` tells whether it is new.
     * @throws \InvalidArgumentException When the data or its order lines are invalid.
     * @throws ShipmentConflictException When the reference belongs to another order or quantities exceed the order.
     * @since 1.5.0
     */
    public function upsert(sOrder $order, ShipmentData $data): sOrderShipment
    {
        $fields = $this->normalize($data);
        $source = $fields['source'];
        $reference = $fields['external_ref'];

        return DB::transaction(function () use ($order, $data, $fields, $source, $reference): sOrderShipment {
            // The order row lock serializes sequence numbering, allocation checks and projection per order.
            $order = $this->lockOrder($order);
            if ($reference !== null && ($existing = $this->findLocked($source, $reference))) {
                return $this->applyExisting($order, $existing, $data, $fields);
            }

            try {
                // A savepoint keeps the outer transaction usable after a duplicate-key error.
                return DB::transaction(fn () => $this->create($order, $data, $fields));
            } catch (QueryException $exception) {
                if ($reference === null || !$this->isReferenceConflict($exception) || !($existing = $this->findLocked($source, $reference))) {
                    throw $exception;
                }
                return $this->applyExisting($order, $existing, $data, $fields);
            }
        });
    }

    /**
     * Apply data to a known shipment with the rules of {@see self::upsert()}. The `source`
     * and `externalRef` of the data are ignored.
     *
     * @since 1.5.0
     */
    public function update(sOrderShipment $shipment, ShipmentData $data): sOrderShipment
    {
        $fields = $this->normalize($data);

        return DB::transaction(function () use ($shipment, $data, $fields): sOrderShipment {
            $order = $this->lockOrder(sOrder::withTrashed()->findOrFail($shipment->order_id));
            $locked = sOrderShipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            return $this->applyExisting($order, $locked, $data, $fields);
        });
    }

    /**
     * Move a shipment to another status.
     *
     * @param sOrderShipment $shipment Stored shipment.
     * @param string $status Target status.
     * @param \DateTimeImmutable|null $at When it happened; used for `shipped_at`/`delivered_at`, now by default.
     * @param array<string, mixed> $metadata Merged into the stored metadata.
     * @since 1.5.0
     */
    public function setStatus(sOrderShipment $shipment, string $status, ?\DateTimeImmutable $at = null, array $metadata = []): sOrderShipment
    {
        $data = new ShipmentData(status: $status, metadata: $metadata,
            shippedAt: $status === sOrderShipment::STATUS_SHIPPED ? $at : null,
            deliveredAt: $status === sOrderShipment::STATUS_DELIVERED ? $at : null);

        return $this->update($shipment, $data);
    }

    /**
     * Shipment written by a source system under its own reference.
     *
     * @since 1.5.0
     */
    public function findByExternalRef(string $source, string $externalRef): ?sOrderShipment
    {
        return sOrderShipment::query()->where('source', strtolower(trim($source)))->where('external_ref', trim($externalRef))->first();
    }

    /**
     * Whether the shipments table exists; a positive answer is kept for the process.
     *
     * @since 1.5.0
     */
    public static function available(): bool
    {
        static $available = false;
        if (!$available) {
            try {
                $model = new sOrderShipment();
                $available = $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable());
            } catch (Throwable) {
                return false;
            }
        }

        return $available;
    }

    /**
     * Whether shipments move the order status (`shipping.sync_order_status`).
     *
     * @since 1.5.0
     */
    public static function syncesOrderStatus(): bool
    {
        return (int)self::setting('shipping.sync_order_status', 0) === 1;
    }

    /**
     * Quantity coverage of an order by its shipments.
     *
     * Only lines that require shipping count. A shipment with `items = null` covers the whole
     * order. Canceled, failed and returned shipments count for nothing; items whose line no
     * longer matches the order snapshot are skipped. An order without shipped lines is never
     * fully shipped or delivered.
     *
     * @param sOrder $order Order with its `products` snapshot.
     * @param iterable<sOrderShipment>|null $shipments Shipments of the order; loaded when null.
     * @return array{fullyShipped: bool, fullyDelivered: bool, anySent: bool}
     * @since 1.5.0
     */
    public static function coverage(sOrder $order, ?iterable $shipments = null): array
    {
        $lines = is_array($order->products) ? $order->products : [];
        $ordered = self::shippableQuantities($lines);
        $shipments ??= self::available() ? sOrderShipment::query()->where('order_id', $order->id)->get() : [];

        $sent = $delivered = array_fill_keys(array_keys($ordered), 0);
        $wholeSent = $wholeDelivered = $anySent = false;
        foreach ($shipments as $shipment) {
            $status = (string)$shipment->status;
            if (!in_array($status, sOrderShipment::SENT_STATUSES, true)) {
                continue;
            }
            $anySent = true;
            $isDelivered = $status === sOrderShipment::STATUS_DELIVERED;
            if ($shipment->items === null) {
                $wholeSent = true;
                $wholeDelivered = $wholeDelivered || $isDelivered;
                continue;
            }
            foreach (self::matchingItems($lines, $shipment->items) as $index => $quantity) {
                if (isset($ordered[$index])) {
                    $sent[$index] += $quantity;
                    $delivered[$index] += $isDelivered ? $quantity : 0;
                }
            }
        }

        $covers = static function (array $shipped) use ($ordered): bool {
            foreach ($ordered as $index => $quantity) {
                if ($shipped[$index] < $quantity) {
                    return false;
                }
            }
            return true;
        };
        $hasLines = $ordered !== [];

        return [
            'fullyShipped' => $hasLines && ($wholeSent || $covers($sent)),
            'fullyDelivered' => $hasLines && ($wholeDelivered || $covers($delivered)),
            'anySent' => $hasLines && $anySent,
        ];
    }

    /**
     * Rewrite `delivery_info.tracking` and, when enabled, move the order status from its shipments.
     *
     * Called after every shipment change; safe to call again, e.g. from a reconciliation job.
     *
     * @since 1.5.0
     */
    public function project(int $orderId): void
    {
        if (!self::available()) {
            return;
        }

        $project = function () use ($orderId): void {
            /** @var sOrder|null $order */
            $order = sOrder::withTrashed()->whereKey($orderId)->lockForUpdate()->first();
            if (!$order) {
                return;
            }
            $shipments = sOrderShipment::query()->where('order_id', $order->id)->orderBy('sequence')->orderBy('id')->get();

            $delivery = is_array($order->delivery_info) ? $order->delivery_info : [];
            if ($shipments->isNotEmpty() || array_key_exists('tracking', $delivery)) {
                $delivery['tracking'] = self::tracking($shipments);
                $order->delivery_info = $delivery;
            }

            $target = !$order->trashed() && self::syncesOrderStatus() ? self::targetStatus((int)$order->status, self::coverage($order, $shipments)) : null;
            if ($target !== null) {
                (new OrderStatusUpdater())->apply($order, $target, 0);
            }
            if ($order->isDirty()) {
                $order->save();
            }
        };
        // Joining the open transaction keeps order events after the shipment event that caused them.
        DB::transactionLevel() > 0 ? $project() : DB::transaction($project);
    }

    /**
     * Template projection of shipments: `[{carrier, number, url, status, updated_at}]`.
     *
     * Canceled shipments and shipments without a tracking number or URL are left out.
     *
     * @param iterable<sOrderShipment> $shipments
     * @return list<array{carrier: ?string, number: ?string, url: ?string, status: string, updated_at: ?string}>
     * @since 1.5.0
     */
    public static function tracking(iterable $shipments): array
    {
        $tracking = [];
        foreach ($shipments as $shipment) {
            if ($shipment->status === sOrderShipment::STATUS_CANCELED || ((string)$shipment->tracking_number === '' && (string)$shipment->tracking_url === '')) {
                continue;
            }
            $tracking[] = [
                'carrier' => $shipment->carrier,
                'number' => $shipment->tracking_number,
                'url' => $shipment->tracking_url,
                'status' => (string)$shipment->status,
                'updated_at' => $shipment->updated_at?->format('Y-m-d H:i:s'),
            ];
        }

        return $tracking;
    }

    /**
     * Order status the coverage calls for, or null to keep the current one.
     */
    private static function targetStatus(int $current, array $coverage): ?int
    {
        if ($current <= sOrder::ORDER_STATUS_DELETED || $current >= sOrder::ORDER_STATUS_COMPLETED) {
            return null;
        }
        if ($coverage['fullyDelivered'] && $current < sOrder::ORDER_STATUS_DELIVERED) {
            return sOrder::ORDER_STATUS_DELIVERED;
        }
        $onFirst = self::setting('shipping.partial_policy', self::PARTIAL_ON_COMPLETE) === self::PARTIAL_ON_FIRST;
        if (($coverage['fullyShipped'] || ($onFirst && $coverage['anySent'])) && $current < sOrder::ORDER_STATUS_SHIPPED) {
            return sOrder::ORDER_STATUS_SHIPPED;
        }

        return null;
    }

    /**
     * Insert a new shipment of a locked order.
     */
    private function create(sOrder $order, ShipmentData $data, array $fields): sOrderShipment
    {
        $status = $fields['status'];
        $items = array_key_exists('items', $fields) ? $this->normalizeItems($order, $fields['items']) : null;
        if (in_array($status, sOrderShipment::ACTIVE_STATUSES, true)) {
            $this->assertAllocation($order, $items, null);
        }

        $shipment = new sOrderShipment();
        $shipment->fill([
            'order_id' => $order->id,
            'sequence' => (int)sOrderShipment::query()->where('order_id', $order->id)->max('sequence') + 1,
            'status' => $status,
            'carrier' => $fields['carrier'] ?? null,
            'service' => $fields['service'] ?? null,
            'tracking_number' => $fields['tracking_number'] ?? null,
            'tracking_url' => $fields['tracking_url'] ?? null,
            'items' => $items,
            'source' => $fields['source'],
            'external_ref' => $fields['external_ref'],
            'metadata' => $fields['metadata'] ?: null,
        ]);
        $this->applyDates($shipment, $data);
        $shipment->save();

        return $shipment;
    }

    /**
     * Apply data to a locked existing shipment of a locked order.
     */
    private function applyExisting(sOrder $order, sOrderShipment $shipment, ShipmentData $data, array $fields): sOrderShipment
    {
        if ((int)$shipment->order_id !== (int)$order->id) {
            throw new ShipmentConflictException('The shipment reference belongs to another order.');
        }

        $metadata = is_array($shipment->metadata) ? $shipment->metadata : [];
        $from = (string)$shipment->status;
        $status = $this->transition($shipment, $from, $fields['status']);
        if ($status === sOrderShipment::STATUS_CANCELED && in_array($from, sOrderShipment::SENT_STATUSES, true)) {
            // A void after sending is a correction: its allocation is released, the order is not moved back.
            $metadata['voided_after'] = $from;
            $this->warning('sCommerce shipment voided after it was sent', ['shipment_id' => $shipment->id, 'order_id' => $order->id, 'status' => $from]);
        }
        $shipment->status = $status;

        foreach (['carrier', 'service', 'tracking_number', 'tracking_url'] as $column) {
            if (array_key_exists($column, $fields)) {
                $shipment->{$column} = $fields[$column];
            }
        }
        $itemsChanged = false;
        if (array_key_exists('items', $fields)) {
            $items = $this->normalizeItems($order, $fields['items']);
            $itemsChanged = $items !== $shipment->items;
            $shipment->items = $items;
        }
        if ($itemsChanged && in_array($status, sOrderShipment::ACTIVE_STATUSES, true)) {
            $this->assertAllocation($order, $shipment->items, (int)$shipment->id);
        }
        $metadata = array_replace($metadata, $fields['metadata']);
        $shipment->metadata = $metadata ?: null;
        $this->applyDates($shipment, $data);
        $shipment->save();

        return $shipment;
    }

    /**
     * Allowed target status, or the current one when the move is not allowed.
     */
    private function transition(sOrderShipment $shipment, string $from, string $to): string
    {
        if ($from === $to) {
            return $from;
        }
        $forward = sOrderShipment::FORWARD_STATUSES;
        $allowed = !in_array($from, sOrderShipment::FINAL_STATUSES, true)
            && (!in_array($to, $forward, true) || array_search($to, $forward, true) > array_search($from, $forward, true));
        if (!$allowed) {
            $this->warning('sCommerce shipment ignored a status transition', ['shipment_id' => $shipment->id, 'from' => $from, 'to' => $to]);
            return $from;
        }

        return $to;
    }

    /**
     * Set given dates and default `shipped_at`/`delivered_at` to now when the status reaches them.
     */
    private function applyDates(sOrderShipment $shipment, ShipmentData $data): void
    {
        $local = static fn (\DateTimeImmutable $date) => $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        if ($data->shippedAt) {
            $shipment->shipped_at = $local($data->shippedAt);
        }
        if ($data->deliveredAt) {
            $shipment->delivered_at = $local($data->deliveredAt);
        }
        $status = (string)$shipment->status;
        if ($status === sOrderShipment::STATUS_DELIVERED && $shipment->delivered_at === null) {
            $shipment->delivered_at = now();
        }
        if (in_array($status, sOrderShipment::SENT_STATUSES, true) && $shipment->shipped_at === null) {
            $shipment->shipped_at = $shipment->delivered_at ?? now();
        }
    }

    /**
     * Validate scalar fields; keys of optional fields are present only when given.
     *
     * @return array<string, mixed>
     */
    private function normalize(ShipmentData $data): array
    {
        $statuses = array_keys(array_flip(array_merge(sOrderShipment::FORWARD_STATUSES, sOrderShipment::FINAL_STATUSES)));
        if (!in_array($data->status, $statuses, true)) {
            throw new \InvalidArgumentException("Unknown shipment status '{$data->status}'.");
        }
        $source = strtolower(trim($data->source));
        if (!preg_match('/\A[a-z0-9_.\-]{1,32}\z/', $source)) {
            throw new \InvalidArgumentException('Shipment source must contain 1-32 lowercase letters, digits, dots, dashes or underscores.');
        }
        $reference = $data->externalRef === null ? null : trim($data->externalRef);
        if ($reference !== null && ($reference === '' || strlen($reference) > 191)) {
            throw new \InvalidArgumentException('Shipment external reference must contain 1-191 characters.');
        }
        $json = json_encode($data->metadata, JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false || strlen($json) > self::MAX_METADATA_BYTES) {
            throw new \InvalidArgumentException('Shipment metadata must be JSON-encodable and at most ' . self::MAX_METADATA_BYTES . ' bytes.');
        }

        $fields = ['status' => $data->status, 'source' => $source, 'external_ref' => $reference, 'metadata' => $data->metadata];
        foreach (['carrier' => [$data->carrier, 64], 'service' => [$data->service, 64], 'tracking_number' => [$data->trackingNumber, 128], 'tracking_url' => [$data->trackingUrl, 512]] as $column => [$value, $max]) {
            if ($value === null) {
                continue;
            }
            $value = trim($value);
            if (in_array($column, ['carrier', 'service'], true)) {
                $value = mb_strtolower($value);
            }
            if (mb_strlen($value) > $max) {
                throw new \InvalidArgumentException("Shipment $column must be at most $max characters.");
            }
            $fields[$column] = $value === '' ? null : $value;
        }
        $url = $fields['tracking_url'] ?? null;
        if ($url !== null && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            throw new \InvalidArgumentException('Shipment tracking URL must be an http(s) URL.');
        }
        if ($data->items !== null) {
            $fields['items'] = $data->items;
        }

        return $fields;
    }

    /**
     * Resolve items against the order snapshot: one entry per line with server-side identity.
     *
     * @return list<array{line: int, line_key: ?string, product_id: int, sku: string, quantity: int}>
     */
    private function normalizeItems(sOrder $order, array $items): array
    {
        $lines = is_array($order->products) ? $order->products : [];
        $resolved = [];
        foreach ($items as $item) {
            $quantity = is_array($item) ? self::positiveInt($item['quantity'] ?? null) : null;
            $index = is_array($item) ? $this->lineIndex($lines, $item) : null;
            if ($quantity === null || $index === null) {
                throw new \InvalidArgumentException('Each shipment item needs an order line (`line` or `line_key`) and a positive integer `quantity`.');
            }
            $line = $lines[$index];
            if (!OrderLineIdentity::requiresShipping($line)) {
                throw new \InvalidArgumentException("Order line $index is not shipped.");
            }
            if ((isset($item['product_id']) && (int)$item['product_id'] !== self::productId($line))
                || (isset($item['sku']) && !in_array((string)$item['sku'], array_filter([(string)($line['sku'] ?? ''), (string)($line['product_sku'] ?? '')]), true))) {
                throw new \InvalidArgumentException("Shipment item does not match order line $index.");
            }
            $resolved[$index] ??= [
                'line' => $index,
                'line_key' => isset($line['line_key']) && is_string($line['line_key']) && $line['line_key'] !== '' ? $line['line_key'] : null,
                'product_id' => self::productId($line),
                'sku' => (string)($line['sku'] ?? $line['product_sku'] ?? ''),
                'quantity' => 0,
            ];
            $resolved[$index]['quantity'] += $quantity;
        }
        if ($resolved === []) {
            throw new \InvalidArgumentException('Shipment items must not be empty; use null for the whole order.');
        }
        ksort($resolved);

        return array_values($resolved);
    }

    /**
     * Index of the order line an item refers to, or null.
     */
    private function lineIndex(array $lines, array $item): ?int
    {
        $key = isset($item['line_key']) && is_string($item['line_key']) && $item['line_key'] !== '' ? $item['line_key'] : null;
        if (array_key_exists('line', $item) && $item['line'] !== null) {
            $index = filter_var($item['line'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($index === false || !isset($lines[$index]) || !is_array($lines[$index])) {
                return null;
            }
            $lineKey = $lines[$index]['line_key'] ?? null;

            return $key === null || !is_string($lineKey) || $lineKey === '' || $lineKey === $key ? $index : null;
        }
        if ($key === null) {
            return null;
        }
        $matches = array_keys(array_filter($lines, static fn ($line) => is_array($line) && ($line['line_key'] ?? null) === $key));

        // The manager can add the same product twice; an ambiguous key needs the line index.
        return count($matches) === 1 ? (int)$matches[0] : null;
    }

    /**
     * Shipped quantities of other active shipments plus `$items` must fit the order.
     *
     * @param list<array{line: int, quantity: int}>|null $items Normalized items; null = whole order.
     */
    private function assertAllocation(sOrder $order, ?array $items, ?int $exceptId): void
    {
        $lines = is_array($order->products) ? $order->products : [];
        $ordered = self::shippableQuantities($lines);
        if ($ordered === []) {
            throw new \InvalidArgumentException('The order has no lines that require shipping.');
        }
        $others = sOrderShipment::query()->where('order_id', $order->id)->whereIn('status', sOrderShipment::ACTIVE_STATUSES)
            ->when($exceptId !== null, static fn ($query) => $query->whereKeyNot($exceptId))->get();
        if ($items === null) {
            if ($others->isNotEmpty()) {
                throw new ShipmentConflictException('A whole-order shipment cannot overlap other shipments of the order.');
            }
            return;
        }

        $allocated = array_fill_keys(array_keys($ordered), 0);
        foreach ($others as $other) {
            if ($other->items === null) {
                throw new ShipmentConflictException('The order is already shipped as a whole.');
            }
            foreach (self::matchingItems($lines, $other->items) as $index => $quantity) {
                $allocated[$index] = ($allocated[$index] ?? 0) + $quantity;
            }
        }
        foreach ($items as $item) {
            if (($allocated[$item['line']] ?? 0) + $item['quantity'] > ($ordered[$item['line']] ?? 0)) {
                throw new ShipmentConflictException("Shipped quantity of order line {$item['line']} exceeds the ordered quantity.");
            }
        }
    }

    /**
     * Billable quantity of each line that requires shipping: the actual quantity of a measured
     * line when set, else the ordered one (base units for measured lines).
     *
     * @return array<int, int>
     */
    private static function shippableQuantities(array $lines): array
    {
        $ordered = [];
        foreach ($lines as $index => $line) {
            $quantity = is_array($line) ? self::positiveInt(isset($line['actual_quantity']) ? MeasuredQuantity::effective($line) : ($line['quantity'] ?? null)) : null;
            if ($quantity !== null && is_int($index) && OrderLineIdentity::requiresShipping($line)) {
                $ordered[$index] = $quantity;
            }
        }

        return $ordered;
    }

    /**
     * Stored items that still match their order line, summed by line index.
     *
     * @return array<int, int>
     */
    private static function matchingItems(array $lines, mixed $items): array
    {
        $quantities = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $index = is_array($item) ? filter_var($item['line'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : false;
            $quantity = is_array($item) ? self::positiveInt($item['quantity'] ?? null) : null;
            if ($index === false || $quantity === null || !isset($lines[$index]) || !is_array($lines[$index])) {
                continue;
            }
            $key = $item['line_key'] ?? null;
            $lineKey = $lines[$index]['line_key'] ?? null;
            if (is_string($key) && $key !== '' && is_string($lineKey) && $lineKey !== '' && $key !== $lineKey) {
                continue;
            }
            $quantities[$index] = ($quantities[$index] ?? 0) + $quantity;
        }

        return $quantities;
    }

    private static function productId(array $line): int
    {
        return (int)($line['product_id'] ?? $line['id'] ?? 0);
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (!is_int($value) && !is_string($value) && !(is_float($value) && floor($value) === $value)) {
            return null;
        }
        $value = filter_var(is_float($value) ? (int)$value : $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $value === false ? null : $value;
    }

    private function lockOrder(sOrder $order): sOrder
    {
        $locked = $order->exists ? sOrder::withTrashed()->whereKey($order->id)->lockForUpdate()->first() : null;
        if (!$locked) {
            throw new \InvalidArgumentException('The order must be saved before recording shipments.');
        }

        return $locked;
    }

    private function findLocked(string $source, string $reference): ?sOrderShipment
    {
        return sOrderShipment::query()->where('source', $source)->where('external_ref', $reference)->lockForUpdate()->first();
    }

    /**
     * Whether a query error is a duplicate of the `(source, external_ref)` unique index.
     */
    private function isReferenceConflict(QueryException $exception): bool
    {
        if (!in_array((string)$exception->getCode(), ['23000', '23505'], true)) {
            return false;
        }
        $message = $exception->getMessage();

        // MySQL/MariaDB/PostgreSQL name the index; SQLite names its columns.
        return str_contains($message, 'uniq_shipment_source_ref')
            || (str_contains($message, 'UNIQUE constraint failed') && str_contains($message, '.external_ref'));
    }

    private static function setting(string $key, mixed $default): mixed
    {
        try {
            return sCommerce::config($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }

    private function warning(string $message, array $context): void
    {
        try {
            Log::channel('scommerce')->warning($message, $context);
        } catch (Throwable) {
            // Logging must not break a shipment write.
        }
    }
}
