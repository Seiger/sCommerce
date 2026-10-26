<?php namespace Seiger\sCommerce\Services;

use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Facades\sCommerce;

/**
 * Stable identity and fulfilment flags of an order line.
 *
 * Accounting and ERP systems map a line to an item or variant by these snapshot keys:
 * `line_key`, `product_id`, `option_id`, `sku` (effective, possibly a variant SKU),
 * `product_sku`, `gtin`, plus the tax placeholders `unit_price_net` and `tax_rate`.
 *
 * @since 1.5.0
 */
final class OrderLineIdentity
{
    public const EVENT = 'sCommerce.ResolveLineIdentity';

    /**
     * Identity of a product line. Listeners of `sCommerce.ResolveLineIdentity` may override
     * `sku` and `gtin` only.
     *
     * @param object $product Product model or object with `id`, `sku` and `gtin`.
     * @param int $optionId Cart option id; 0 = none.
     * @return array{line_key: string, product_id: int, option_id: int, sku: string, product_sku: string, gtin: ?string, unit_price_net: ?float, tax_rate: ?float}
     * @since 1.5.0
     */
    public function resolve(object $product, int $optionId = 0): array
    {
        $productId = (int)($product->id ?? 0);
        $productSku = (string)($product->sku ?? '');
        $gtin = trim((string)($product->gtin ?? ''));
        $canonical = [
            'line_key' => $productId . ':' . $optionId,
            'product_id' => $productId,
            'option_id' => $optionId,
            'product_sku' => $productSku,
        ];
        $identity = $canonical + [
            'sku' => $productSku,
            'gtin' => $gtin !== '' ? $gtin : null,
            'unit_price_net' => null,
            'tax_rate' => null,
        ];

        Event::dispatch(self::EVENT, [['product' => $product, 'optionId' => $optionId, 'identity' => &$identity]]);

        $sku = is_scalar($identity['sku'] ?? null) ? trim((string)$identity['sku']) : '';
        $gtin = is_scalar($identity['gtin'] ?? null) ? trim((string)$identity['gtin']) : '';

        return [
            'line_key' => $canonical['line_key'],
            'product_id' => $productId,
            'option_id' => $optionId,
            'sku' => $sku !== '' ? $sku : $productSku,
            'product_sku' => $productSku,
            'gtin' => $gtin !== '' ? $gtin : null,
            'unit_price_net' => null,
            'tax_rate' => null,
        ];
    }

    /**
     * Identity plus the fulfilment flags `requires_shipping` and `tracks_inventory`, and
     * `retail_excise_rate` for products with a retail excise.
     *
     * @param object $product Product model; objects without `fulfilment()` are shipped and tracked.
     * @param int $optionId Cart option id; 0 = none.
     * @return array<string, mixed>
     * @since 1.5.0
     */
    public function snapshot(object $product, int $optionId = 0): array
    {
        $fulfilment = method_exists($product, 'fulfilment') ? $product->fulfilment($optionId) : ['shipping' => true, 'inventory' => true];

        $snapshot = $this->resolve($product, $optionId) + [
            'requires_shipping' => (bool)($fulfilment['shipping'] ?? true),
            'tracks_inventory' => (bool)($fulfilment['inventory'] ?? true),
        ];
        $exciseRate = VatDisplay::exciseRate($product, $optionId);
        if ($exciseRate > 0) {
            $snapshot['retail_excise_rate'] = $exciseRate;
        }

        return $snapshot;
    }

    /**
     * Resolve identity on the server for edited lines that have none (lines added in the manager).
     *
     * Lines with a `line_key` keep their stored identity. Other lines get the current identity
     * of product `id` with their `option_id` (default 0), and its returnable container deposit in
     * `$currency`; lines of unknown products stay unchanged.
     *
     * @param array<int, mixed> $lines Order `products` snapshot.
     * @param string|null $currency Order currency; the current currency by default.
     * @return array<int, mixed>
     * @since 1.5.0
     */
    public function fillMissing(array $lines, ?string $currency = null): array
    {
        foreach ($lines as $index => $line) {
            if (!is_array($line) || (isset($line['line_key']) && is_string($line['line_key']) && $line['line_key'] !== '')) {
                continue;
            }
            $productId = filter_var($line['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $product = $productId ? sCommerce::getProduct($productId) : null;
            if (!$product || !(int)($product->id ?? 0)) {
                continue;
            }
            $optionId = filter_var($line['option_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 0;
            // Existing editable values (title, price, quantity) win; identity keys are always server-side.
            $lines[$index] = array_merge($line, $this->snapshot($product, $optionId));
            if (!isset($line['deposit_unit'])) {
                $lines[$index] += ContainerDeposit::lineFields(ContainerDeposit::unit($product, $optionId, $currency),
                    max(0, (int)($line['quantity'] ?? 1)), max(0, (int)($line['exchange_quantity'] ?? 0)));
            }
        }

        return $lines;
    }

    /**
     * Whether a stored line is shipped; lines of orders before 1.5.0 are.
     *
     * @param array<string, mixed> $line
     * @since 1.5.0
     */
    public static function requiresShipping(array $line): bool
    {
        return !array_key_exists('requires_shipping', $line) || (bool)$line['requires_shipping'];
    }

    /**
     * Whether a stored line counts against inventory; lines of orders before 1.5.0 do.
     *
     * @param array<string, mixed> $line
     * @since 1.5.0
     */
    public static function tracksInventory(array $line): bool
    {
        return !array_key_exists('tracks_inventory', $line) || (bool)$line['tracks_inventory'];
    }
}
