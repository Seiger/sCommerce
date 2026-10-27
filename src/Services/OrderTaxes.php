<?php namespace Seiger\sCommerce\Services;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Models\sOrder;

/**
 * Tax data of a new order, supplied by `sCommerce.ResolveOrderTaxes` listeners.
 *
 * sCommerce never computes taxes and never changes `cost` from these values. Listeners may
 * set the per-line keys below in the `products` snapshot and the order `totals`; other changes
 * to the lines are ignored.
 *
 * @since 1.5.0
 */
final class OrderTaxes
{
    public const EVENT = 'sCommerce.ResolveOrderTaxes';

    /** @var array<string, string> Per-line keys and their types. */
    public const LINE_KEYS = [
        'tax_rate' => 'float',
        'tax_code' => 'code',
        'unit_price_net' => 'float',
        'unit_price_gross' => 'float',
        'tax_amount' => 'float',
        'prices_include_tax' => 'bool',
        'retail_excise_amount' => 'float',
    ];

    /** @var array<string, string> Keys of `s_orders.totals` and their types. */
    public const TOTAL_KEYS = [
        'subtotal_net' => 'float',
        'subtotal_gross' => 'float',
        'shipping_net' => 'float',
        'shipping_gross' => 'float',
        'shipping_tax_rate' => 'float',
        'discount_gross' => 'float',
        'tax_total' => 'float',
        'grand_total' => 'float',
        'retail_excise_total' => 'float',
        'prices_include_tax' => 'bool',
    ];

    /**
     * Dispatch the event for an unsaved order and keep the supported values.
     *
     * @param sOrder $order Unsaved order with `products` and `cost`.
     * @param string $source Event scope source of the checkout path.
     * @since 1.5.0
     */
    public static function apply(sOrder $order, string $source): void
    {
        $lines = is_array($order->products) ? $order->products : [];
        $edited = $lines;
        $totals = [];
        Event::dispatch(self::EVENT, [['order' => $order, 'lines' => &$edited, 'totals' => &$totals, 'source' => $source]]);

        $changed = false;
        foreach ($lines as $index => $line) {
            if (!is_array($line) || !is_array($edited[$index] ?? null)) {
                continue;
            }
            foreach (self::LINE_KEYS as $key => $type) {
                if (!array_key_exists($key, $edited[$index]) || ($edited[$index][$key] ?? null) === ($line[$key] ?? null)) {
                    continue;
                }
                $value = self::normalize($edited[$index][$key], $type);
                if ($value !== null) {
                    $lines[$index][$key] = $value;
                    $changed = true;
                }
            }
        }
        if ($changed) {
            $order->products = $lines;
        }

        $normalized = [];
        foreach (self::TOTAL_KEYS as $key => $type) {
            $value = is_array($totals) && array_key_exists($key, $totals) ? self::normalize($totals[$key], $type) : null;
            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }
        if ($normalized === []) {
            return;
        }
        if (!$order->hasOptionalColumn('totals')) {
            Log::channel('scommerce')->warning('sCommerce order totals were not stored: run the sCommerce migrations', ['source' => $source]);
            return;
        }
        $order->totals = $normalized;
        if (isset($normalized['grand_total']) && abs($normalized['grand_total'] - (float)$order->cost) > 0.01) {
            Log::channel('scommerce')->warning('sCommerce order totals.grand_total differs from the order cost', [
                'source' => $source, 'grand_total' => $normalized['grand_total'], 'cost' => (float)$order->cost,
            ]);
        }
    }

    /**
     * Set `retail_excise_amount` (the excise included in the line total, for the receipt) on lines
     * with a `retail_excise_rate`. Called for new orders and after manager edits of the lines.
     * Measured lines use their billable total, see {@see MeasuredQuantity::lineSum()}.
     *
     * @param array<int, mixed> $lines Order `products` snapshot.
     * @return array<int, mixed>
     * @since 1.5.0
     */
    public static function applyRetailExcise(array $lines): array
    {
        foreach ($lines as $index => $line) {
            $rate = is_array($line) ? (float)($line['retail_excise_rate'] ?? 0) : 0.0;
            if ($rate <= 0) {
                continue;
            }
            $price = $line['priceNumber'] ?? $line['priceAsFloat'] ?? $line['price'] ?? 0;
            $lines[$index]['retail_excise_amount'] = VatDisplay::excise(is_numeric($price) ? MeasuredQuantity::lineSum($line, (float)$price) : 0.0, $rate);
        }

        return $lines;
    }

    private static function normalize(mixed $value, string $type): float|bool|string|null
    {
        return match ($type) {
            'float' => is_numeric($value) && is_finite((float)$value) ? round((float)$value, 4) : null,
            'bool' => is_bool($value) ? $value : (in_array($value, [0, 1, '0', '1'], true) ? (bool)$value : null),
            'code' => is_scalar($value) && !is_bool($value) && ($code = trim((string)$value)) !== '' && mb_strlen($code) <= 32 ? $code : null,
            default => null,
        };
    }
}
