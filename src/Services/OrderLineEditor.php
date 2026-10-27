<?php namespace Seiger\sCommerce\Services;

use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sOrder;

/**
 * Apply the products a manager edited in the order form.
 *
 * The browser posts the whole `products` list. Identity, measured rules and actual quantities
 * stay server-side: lines with a stored `line_key` keep their stored keys, other lines get them
 * from the current product. Measured quantities are validated strictly; nothing is rounded.
 * Delivery cost and other non-product amounts of the order cost are kept.
 *
 * @since 1.5.0
 */
final class OrderLineEditor
{
    /**
     * Set the edited lines and the recalculated cost on the order, without saving it.
     *
     * @param sOrder $order Order with its stored `products`.
     * @param array<int, mixed> $edited Lines posted by the manager form.
     * @return bool Whether the products changed.
     * @throws MeasuredQuantityException When a measured quantity breaks its rules; the order is unchanged.
     * @since 1.5.0
     */
    public function apply(sOrder $order, array $edited): bool
    {
        $stored = is_array($order->products) ? $order->products : [];
        $currency = (string)$order->currency;
        $lines = app(OrderLineIdentity::class)->fillMissing(MeasuredQuantity::restoreStoredRules($stored, $edited), $currency);
        foreach ($lines as $index => $line) {
            if (!is_array($line) || !isset($line['measure'])) {
                continue;
            }
            $rules = MeasuredQuantity::fromLine($line);
            $quantity = $line['quantity'] ?? null;
            $quantity = is_int($quantity) || is_string($quantity) ? filter_var($quantity, FILTER_VALIDATE_INT) : false;
            if ($quantity === false) {
                throw new MeasuredQuantityException('measured_quantity_invalid');
            }
            $line['quantity'] = MeasuredQuantity::normalize($quantity, $rules, MeasuredQuantity::MODE_STRICT);
            $line['unit_price_label'] ??= MeasuredQuantity::unitPriceLabel(is_scalar($line['price'] ?? null) ? (string)$line['price'] : '', $rules, $order->lang ?: null);
            $lines[$index] = self::refreshSum(MeasuredQuantity::refreshLine($line, $order->lang ?: null), $currency);
        }
        $lines = ContainerDeposit::applyToLines(OrderTaxes::applyRetailExcise($lines));

        $order->products = $lines;
        $order->cost = OrderCostRecalculator::recalculate($order->getOriginal('cost') ?? $order->cost ?? 0, $stored, $lines, $currency);

        return json_encode($stored) !== json_encode($lines);
    }

    /**
     * Recalculate `sumAsFloat` and `sum` of a measured line from its billable quantity.
     *
     * @param array<string, mixed> $line
     * @return array<string, mixed>
     * @since 1.5.0
     */
    public static function refreshSum(array $line, string $currency): array
    {
        $price = $line['priceNumber'] ?? $line['priceAsFloat'] ?? null;
        $price = is_numeric($price) ? (float)$price : sCommerce::convertPriceNumber($line['price'] ?? 0, $currency, $currency);
        $line['sumAsFloat'] = round(MeasuredQuantity::lineSum($line, $price), 3);
        $line['sum'] = sCommerce::convertPrice($line['sumAsFloat'], $currency);

        return $line;
    }
}
