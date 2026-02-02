<?php namespace Seiger\sCommerce\Services;

use Seiger\sCommerce\sCommerce;

/**
 * Order reference formatting rules.
 *
 * Current format:
 * - `s_orders.reference` stores ONLY the numeric order number (with pad_left applied), without prefix.
 * - Integrations / API output add `prefix_default` in front of `s_orders.reference`.
 *
 * Legacy format (kept for compatibility):
 * - `s_orders.reference` could contain a prefix, e.g. "b2cukr_107293".
 */
final class OrderReferenceFormatter
{
    /**
     * Extract trailing digits from a reference string.
     *
     * Examples:
     * - "107293" => "107293"
     * - "b2cukr_107293" => "107293"
     * - "ABC" => null
     */
    public static function extractTrailingDigits(?string $reference): ?string
    {
        $reference = trim((string)$reference);
        if ($reference === '') {
            return null;
        }

        if (!preg_match('/(\d+)\s*$/', $reference, $m)) {
            return null;
        }

        return $m[1];
    }

    public static function prefixDefault(): string
    {
        return (string)sCommerce::config('orders.reference.prefix_default', 'ORD_');
    }

    public static function startDefault(): int
    {
        return (int)sCommerce::config('orders.reference.start_default', 0);
    }

    public static function padLeft(): int
    {
        return (int)sCommerce::config('orders.reference.pad_left', 0);
    }

    /**
     * UI/search order number (WITHOUT prefix).
     */
    public static function orderNumber(?string $reference, ?int $padLeft = null): ?string
    {
        $digits = self::extractTrailingDigits($reference);
        if ($digits === null) {
            return null;
        }

        $start = self::startDefault();
        $numeric = (int)ltrim($digits, '0');
        if ($start > 0 && $numeric > 0 && $numeric < $start) {
            $digits = (string)($start + $numeric);
        }

        $padLeft = $padLeft ?? self::padLeft();
        if ($padLeft > 0) {
            $digits = str_pad($digits, $padLeft, '0', STR_PAD_LEFT);
        }

        return $digits;
    }

    /**
     * API/integration reference (prefix + numeric reference).
     */
    public static function referenceWithPrefix(?string $reference, ?string $prefix = null, ?int $padLeft = null): ?string
    {
        $orderNumber = self::orderNumber($reference, $padLeft);
        if ($orderNumber === null) {
            return null;
        }

        $prefix = $prefix ?? self::prefixDefault();

        return $prefix . $orderNumber;
    }
}
