<?php namespace Seiger\sCommerce\Services;

use Seiger\sCommerce\Facades\sCommerce;

/**
 * Order cost after a manager edits the products of an order.
 *
 * Only the products part of the cost changes; what checkout added on top of the products
 * (delivery cost, surcharges, discounts) is kept.
 *
 * @since 1.5.0
 */
final class OrderCostRecalculator
{
    /**
     * @param float|int|string $oldCost Stored order cost.
     * @param array<int, array<string, mixed>> $oldProducts Stored order products.
     * @param array<int, array<string, mixed>> $newProducts Edited order products.
     * @param string $currency Order currency.
     * @since 1.5.0
     */
    public static function recalculate(float|int|string $oldCost, array $oldProducts, array $newProducts, string $currency): float
    {
        $extra = round((float)$oldCost - self::productsCost($oldProducts, $currency), 2);

        return max(0.0, round(self::productsCost($newProducts, $currency) + $extra, 2));
    }

    /**
     * Sum of price × quantity, using `priceNumber` when present, else the formatted `price`,
     * plus the returnable container deposits of the lines.
     *
     * @param array<int, mixed> $products
     * @since 1.5.0
     */
    public static function productsCost(array $products, string $currency): float
    {
        $total = 0.0;
        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }
            $price = isset($product['priceNumber']) && is_numeric($product['priceNumber'])
                ? (float)$product['priceNumber']
                : (is_numeric($product['price'] ?? null) ? (float)$product['price'] : sCommerce::convertPriceNumber($product['price'] ?? 0, $currency, $currency));
            $total += $price * intval($product['quantity'] ?? 1);
        }

        return round($total + ContainerDeposit::total($products), 2);
    }
}
