<?php namespace Seiger\sCommerce\Services;

use Illuminate\Support\Facades\DB;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderShipment;

/**
 * Actual quantities of measured lines: the length cut or the weight weighed after ordering.
 *
 * The line keeps its ordered `quantity`; `actual_quantity` (base units) with `_at`, `_by` and
 * `_source` is added, and the line is billed, shipped and taken from stock with it. The
 * actual quantity is a measurement, so it has no step; it may exceed the ordered quantity by
 * the line's tolerance, and more only when the caller confirms that the customer agreed.
 *
 * @since 1.5.0
 */
final class OrderActualQuantity
{
    public const SOURCES = ['manager', 'erp', 'api'];

    /** Key of processed operation keys in `s_orders.meta`. */
    public const META_KEY = 'actual_quantity_ops';

    /** Processed operation keys kept per order. */
    public const MAX_OPERATIONS = 50;

    /**
     * Set the actual quantity of a measured line, once per operation key.
     *
     * Recomputes the line sum, retail excise, line weight and order cost, records a history entry
     * and one `sCommerce.OrderUpdated`, and adjusts locally committed stock. The same operation key
     * with the same value returns the order unchanged.
     *
     * @param sOrder|int $order Order or its id.
     * @param string $lineKey `line_key` of the measured line.
     * @param int $actual Actual quantity in base units.
     * @param string $source `manager`, `erp` or `api`.
     * @param int $actorId Manager id, or 0.
     * @param string $operationKey Idempotency key of the caller, at most 191 characters.
     * @param bool $overTolerance The customer agreed to a quantity above the tolerance; the caller checks permission.
     * @throws MeasuredQuantityException When the change is refused; `status` is 404, 409 or 422.
     * @throws StockShortageException When committed stock cannot cover the increase.
     * @since 1.5.0
     */
    public function set(sOrder|int $order, string $lineKey, int $actual, string $source, int $actorId,
                        string $operationKey, bool $overTolerance = false): sOrder
    {
        $operationKey = trim($operationKey);
        if (!in_array($source, self::SOURCES, true) || $operationKey === '' || strlen($operationKey) > 191) {
            throw new \InvalidArgumentException('Actual quantities need a known source and an operation key of 1-191 characters.');
        }
        $orderId = $order instanceof sOrder ? (int)$order->getKey() : $order;
        if (!(new sOrder())->hasOptionalColumn('meta')) {
            throw new MeasuredQuantityException('actual_quantity_unavailable', [], 409);
        }

        return EventScope::run($source, $actorId, fn (): sOrder => DB::transaction(function () use ($orderId, $lineKey, $actual, $source, $actorId, $operationKey, $overTolerance): sOrder {
            /** @var sOrder|null $locked */
            $locked = sOrder::withTrashed()->whereKey($orderId)->lockForUpdate()->first();
            if (!$locked) {
                throw new MeasuredQuantityException('order_not_found', [], 404);
            }
            $meta = is_array($locked->meta) ? $locked->meta : [];
            $operations = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];
            if (isset($operations[$operationKey])) {
                $done = $operations[$operationKey];
                if (($done['line_key'] ?? null) === $lineKey && (int)($done['actual'] ?? 0) === $actual) {
                    return $locked;
                }
                throw new MeasuredQuantityException('actual_quantity_conflict', [], 409);
            }
            if ($locked->trashed() || (int)$locked->status === sOrder::ORDER_STATUS_CANCELED) {
                throw new MeasuredQuantityException('actual_quantity_order_closed', [], 409);
            }
            $lines = is_array($locked->products) ? $locked->products : [];
            $index = null;
            foreach ($lines as $position => $line) {
                if (is_array($line) && ($line['line_key'] ?? null) === $lineKey) {
                    $index = $position;
                    break;
                }
            }
            if ($index === null) {
                throw new MeasuredQuantityException('actual_quantity_line');
            }
            $line = $lines[$index];
            $rules = MeasuredQuantity::fromLine($line);
            if (!$rules->isMeasured()) {
                throw new MeasuredQuantityException('actual_quantity_piece');
            }
            if ($actual < 1) {
                throw new MeasuredQuantityException('measured_quantity_invalid');
            }
            if ($actual > $rules->max) {
                throw new MeasuredQuantityException('measured_quantity_above_max', ['max' => MeasuredQuantity::label($rules->max, $rules)]);
            }
            $ordered = (int)($line['quantity'] ?? 0);
            $limit = intdiv($ordered * (10000 + $rules->tolerance), 10000);
            if (!$overTolerance && $actual > $limit) {
                throw new MeasuredQuantityException('actual_quantity_tolerance_exceeded');
            }
            if ($this->shipped($locked, (int)$index, $lineKey)) {
                throw new MeasuredQuantityException('actual_quantity_shipped', [], 409);
            }

            $previous = isset($line['actual_quantity']) ? (int)$line['actual_quantity'] : null;
            $line['actual_quantity'] = $actual;
            $line['actual_quantity_at'] = gmdate('Y-m-d\TH:i:s\Z');
            $line['actual_quantity_by'] = $actorId;
            $line['actual_quantity_source'] = $source;
            $lines[$index] = OrderLineEditor::refreshSum(MeasuredQuantity::refreshLine($line, $locked->lang ?: null), (string)$locked->currency);
            $lines = OrderTaxes::applyRetailExcise($lines);

            $stored = is_array($locked->products) ? $locked->products : [];
            $locked->cost = OrderCostRecalculator::recalculate($locked->cost ?? 0, $stored, $lines, (string)$locked->currency);
            $locked->products = $lines;
            $history = is_array($locked->history) ? $locked->history : [];
            $history[] = ['actual_quantity_set' => ['line_key' => $lineKey, 'old' => $previous, 'new' => $actual, 'ordered' => $ordered,
                'source' => $source, 'over_tolerance' => $actual > $limit],
                'timestamp' => now()->toDateTimeString(), 'user_id' => $actorId];
            $locked->history = $history;
            $operations[$operationKey] = ['line_key' => $lineKey, 'actual' => $actual];
            $meta[self::META_KEY] = array_slice($operations, -self::MAX_OPERATIONS, null, true);
            $locked->meta = $meta;
            $locked->save();

            (new StockCommitter())->adjust($locked);

            return $locked->refresh();
        }));
    }

    /**
     * Whether a sent shipment (shipped, in transit or delivered) covers the line.
     */
    private function shipped(sOrder $order, int $index, string $lineKey): bool
    {
        if (!OrderShipments::available()) {
            return false;
        }
        $shipments = sOrderShipment::query()->where('order_id', $order->id)->whereIn('status', sOrderShipment::SENT_STATUSES)->get();
        foreach ($shipments as $shipment) {
            if ($shipment->items === null) {
                return true;
            }
            foreach (is_array($shipment->items) ? $shipment->items : [] as $item) {
                $key = is_array($item) ? ($item['line_key'] ?? null) : null;
                if (is_array($item) && (is_string($key) && $key !== '' ? $key === $lineKey : (int)($item['line'] ?? -1) === $index)) {
                    return true;
                }
            }
        }

        return false;
    }
}
