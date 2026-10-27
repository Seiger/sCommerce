<?php namespace Seiger\sCommerce\Cart;

use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Facades\sCommerce;

/**
 * Split of one cart entry into order lines with their own price and stock allocation.
 *
 * A cart entry is a product with an option and a quantity. A `sCommerce.SplitCartLine` listener,
 * for example a package that tracks stock lots, may divide its quantity into parts: units of an
 * older lot at its own price, units close to their expiry at a markdown price and the rest at the
 * regular price. Each part becomes one cart and order line with the line key
 * `{productId}:{optionId}:{part key}`, its price, an optional label and an opaque `allocation`
 * that stock providers read when they commit the order. sCommerce does not interpret allocations.
 *
 * @since 1.5.0
 */
final class CartLineParts
{
    public const EVENT = 'sCommerce.SplitCartLine';

    /** Keys a part adds to its order line; they are set on the server only. */
    public const LINE_KEYS = ['part_key', 'part_label', 'allocation'];

    /** Cart-only key of a part line: the quantity of the whole cart entry. */
    public const CART_QUANTITY = 'cart_quantity';

    private const KEY_PATTERN = '/^[A-Za-z0-9._-]{1,40}$/';
    private const LABEL_MAX = 255;

    /**
     * Parts of a cart entry from the first listener that returns an array, or null when no listener splits it.
     *
     * @param object $product Product of the entry.
     * @param int $optionId Cart option id.
     * @param int $quantity Entry quantity; base units for products sold by measure.
     * @param array<string, mixed> $pricing Resolved price of the entry, see {@see \Seiger\sCommerce\Services\sPriceResolver::resolve()}.
     * @param string $currency Display currency of the prices.
     * @return list<array{key: string, quantity: int, priceAsFloat: float|null, oldPriceAsFloat: float|null, label: string|null, allocation: array|null}>|null
     * @throws \UnexpectedValueException When the listener response is not a valid split of `$quantity`.
     * @since 1.5.0
     */
    public static function resolve(object $product, int $optionId, int $quantity, array $pricing, string $currency): ?array
    {
        if ($quantity < 1) {
            return null;
        }
        foreach (Event::dispatch(self::EVENT, [[
            'product' => $product,
            'optionId' => $optionId,
            'quantity' => $quantity,
            'currency' => $currency,
            'pricing' => $pricing,
        ]]) as $response) {
            if (is_array($response)) {
                return self::normalize($response, $quantity, (int)($product->id ?? 0));
            }
        }

        return null;
    }

    /**
     * Pricing of a part: the entry pricing, or the part price with its old price (default none).
     *
     * @param array<string, mixed> $pricing Entry pricing.
     * @param array{priceAsFloat: float|null, oldPriceAsFloat: float|null} $part
     * @return array<string, mixed>
     * @since 1.5.0
     */
    public static function pricing(array $pricing, array $part, string $currency): array
    {
        if ($part['priceAsFloat'] === null) {
            return $pricing;
        }
        $old = (float)($part['oldPriceAsFloat'] ?? 0);

        return array_replace($pricing, [
            'priceAsFloat' => $part['priceAsFloat'],
            'oldPriceAsFloat' => $old,
            'price' => sCommerce::convertPrice($part['priceAsFloat'], $currency, $currency),
            'oldPrice' => $old > 0 ? sCommerce::convertPrice($old, $currency, $currency) : '',
        ]);
    }

    /**
     * Mark a cart line built for a part.
     *
     * @param array<string, mixed> $line Line built for the part quantity.
     * @param array{key: string, label: string|null, allocation: array|null} $part
     * @param int $cartQuantity Quantity of the whole cart entry, the value a quantity control changes.
     * @return array<string, mixed>
     * @since 1.5.0
     */
    public static function apply(array $line, array $part, int $cartQuantity): array
    {
        $line['line_key'] = $line['line_key'] . ':' . $part['key'];
        $line['part_key'] = $part['key'];
        $line[self::CART_QUANTITY] = $cartQuantity;
        if ($part['label'] !== null) {
            $line['part_label'] = $part['label'];
        }
        if ($part['allocation'] !== null) {
            $line['allocation'] = $part['allocation'];
        }

        return $line;
    }

    /**
     * Keep part keys of edited order lines server-side: posted values are dropped, and a line
     * whose `line_key` matches a stored line gets the stored part keys back.
     *
     * @param array<int, mixed> $stored Stored order lines.
     * @param array<int, mixed> $edited Lines posted by the manager form.
     * @return array<int, mixed>
     * @since 1.5.0
     */
    public static function restoreStored(array $stored, array $edited): array
    {
        $byKey = [];
        foreach ($stored as $line) {
            if (is_array($line) && is_string($line['line_key'] ?? null) && !isset($byKey[$line['line_key']])) {
                $byKey[$line['line_key']] = array_intersect_key($line, array_flip(self::LINE_KEYS));
            }
        }
        foreach ($edited as $index => $line) {
            if (is_array($line)) {
                $key = is_string($line['line_key'] ?? null) ? $line['line_key'] : null;
                $edited[$index] = array_diff_key($line, array_flip([...self::LINE_KEYS, self::CART_QUANTITY])) + ($key !== null ? $byKey[$key] ?? [] : []);
            }
        }

        return $edited;
    }

    /**
     * @return list<array{key: string, quantity: int, priceAsFloat: float|null, oldPriceAsFloat: float|null, label: string|null, allocation: array|null}>
     */
    private static function normalize(array $response, int $quantity, int $productId): array
    {
        $fail = static fn (string $reason) => new \UnexpectedValueException(self::EVENT . " returned an invalid split of product {$productId}: {$reason}.");
        if ($response === [] || !array_is_list($response)) {
            throw $fail('expected a non-empty list of parts');
        }
        $parts = [];
        $total = 0;
        foreach ($response as $index => $part) {
            if (!is_array($part)) {
                throw $fail("part {$index} is not an array");
            }
            $key = $part['key'] ?? null;
            if (!is_string($key) || !preg_match(self::KEY_PATTERN, $key) || isset($parts[$key])) {
                throw $fail("part {$index} needs a unique key of 1-40 letters, digits, '.', '_' or '-'");
            }
            $units = $part['quantity'] ?? null;
            if (!is_int($units) || $units < 1) {
                throw $fail("part {$key} needs a positive integer quantity");
            }
            $price = self::amount($part, 'priceAsFloat') ?? throw $fail("part {$key} has an invalid priceAsFloat");
            $old = self::amount($part, 'oldPriceAsFloat') ?? throw $fail("part {$key} has an invalid oldPriceAsFloat");
            if ($price === false && $old !== false) {
                throw $fail("part {$key} sets oldPriceAsFloat without priceAsFloat");
            }
            $label = $part['label'] ?? null;
            if ($label !== null && (!is_string($label) || mb_strlen($label) > self::LABEL_MAX)) {
                throw $fail("part {$key} has a label that is not a string of up to " . self::LABEL_MAX . ' characters');
            }
            $allocation = $part['allocation'] ?? null;
            if ($allocation !== null) {
                try {
                    // Allocations are stored in the order JSON.
                    json_encode($allocation, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $allocation = false;
                }
                if (!is_array($allocation)) {
                    throw $fail("part {$key} has an allocation that is not a JSON-encodable array");
                }
            }
            $label = $label === null || trim($label) === '' ? null : trim($label);
            $parts[$key] = ['key' => $key, 'quantity' => $units, 'priceAsFloat' => $price === false ? null : $price,
                'oldPriceAsFloat' => $old === false ? null : $old, 'label' => $label, 'allocation' => $allocation];
            $total += $units;
        }
        if ($total !== $quantity) {
            throw $fail("parts hold {$total} units instead of {$quantity}");
        }

        return array_values($parts);
    }

    /**
     * A non-negative finite amount, false when absent, null when invalid.
     */
    private static function amount(array $part, string $key): float|false|null
    {
        if (!array_key_exists($key, $part)) {
            return false;
        }
        $value = $part[$key];
        if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value < 0) {
            return null;
        }

        return (float)$value;
    }
}
