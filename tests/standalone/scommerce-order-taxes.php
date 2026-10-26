<?php

// php tests/standalone/scommerce-order-taxes.php
// Isolated tax snapshot checks: listener data persisted on lines and totals, cost untouched.
// In-memory SQLite, fake products, no CMS/site bootstrap.
require __DIR__ . '/fixtures/checkout-harness.php';

use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Checkout\sCheckout;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Services\OrderTaxes;
use Seiger\sCommerce\sCommerce;

$shop->products = [1 => new FakeProduct(1, 'BOOK', 24.6), 2 => new FakeProduct(2, 'MUG', 12.3)];
$shop->config['basic.deliveries_on'] = 0;

// Without listeners nothing changes.
$lines = $cartLines([1 => [0 => 1], 2 => [0 => 2]]);
$result = $checkout($lines)->processOrder();
$order = sOrder::query()->find($result['order']->id);
$stored = array_map(static fn ($line) => array_diff_key($line, array_flip(Seiger\sCommerce\Services\VatDisplay::keys())), $lines);
$check($result['success'] && $order->totals === null && $order->products === json_decode(json_encode($stored), true), 'No listener: no totals, lines unchanged');

// A listener supplies per-line tax data and totals; other line changes are ignored.
$sources = [];
Event::listen(OrderTaxes::EVENT, static function (array $payload) use (&$sources): void {
    $sources[] = $payload['source'];
    foreach ($payload['lines'] as &$line) {
        $gross = (float)$line['priceAsFloat'];
        $line['tax_rate'] = 23;
        $line['tax_code'] = 'PL-23';
        $line['unit_price_net'] = round($gross / 1.23, 2);
        $line['unit_price_gross'] = $gross;
        $line['tax_amount'] = '' . round(($gross - $gross / 1.23) * $line['quantity'], 2);
        $line['prices_include_tax'] = 1;
        $line['priceAsFloat'] = 0.01;
        $line['quantity'] = 99;
    }
    unset($line);
    $payload['lines'][] = ['id' => 999, 'title' => 'Injected'];
    $payload['totals'] = ['subtotal_net' => 40, 'subtotal_gross' => 49.2, 'tax_total' => '9.20', 'grand_total' => 49.2,
        'prices_include_tax' => true, 'unknown' => 'dropped', 'discount_gross' => 'n/a'];
});
$result = $checkout($lines)->processOrder();
$order = sOrder::query()->find($result['order']->id);
$first = $order->products[0];
$check($first['tax_rate'] === 23 && $first['tax_code'] === 'PL-23' && $first['unit_price_net'] === 20 && $first['unit_price_gross'] === 24.6
    && $first['tax_amount'] === 4.6 && $first['prices_include_tax'] === true, 'Per-line tax data persisted and normalized');
$check((float)$first['priceAsFloat'] === 24.6 && $first['quantity'] === 1 && count($order->products) === 2, 'Listeners cannot change prices, quantities or lines');
$check($order->totals === ['subtotal_net' => 40, 'subtotal_gross' => 49.2, 'tax_total' => 9.2, 'grand_total' => 49.2, 'prices_include_tax' => true], 'Totals keep supported valid keys');
$check((float)$order->cost === 49.2 && $sources === ['storefront'], 'Cost is not changed');

// A grand total that differs from the cost is logged, the cost stays.
Event::listen(OrderTaxes::EVENT, static function (array $payload): void {
    $payload['totals']['grand_total'] = 50;
    $payload['lines'][0]['tax_code'] = str_repeat('X', 33);
});
$app['log']->records = [];
$result = $checkout($lines)->processOrder();
$order = sOrder::query()->find($result['order']->id);
$warnings = array_filter($app['log']->records, static fn ($record) => $record[1] === 'sCommerce order totals.grand_total differs from the order cost');
$check(count($warnings) === 1 && (float)$order->cost === 49.2 && $order->totals['grand_total'] === 50, 'Mismatch logged, cost kept');
$check(!isset($order->products[0]['tax_code']) && $order->products[0]['tax_rate'] === 23, 'Overlong tax codes are ignored');

// Quick orders dispatch the same event.
$sources = [];
$quick = sCheckout::quickOrder(['productId' => 2, 'quantity' => 1, 'email' => 'quick@shop.test']);
$check($quick['success'] && $sources === ['storefront_quick'] && sOrder::query()->find($quick['order']->id)->products[0]['tax_rate'] === 23, 'Quick order taxes persisted');
$check(sCommerce::supportsIntegration('line-tax-data'), 'Feature key line-tax-data');

echo "$checks order tax checks passed (isolated SQLite; no site database)\n";
