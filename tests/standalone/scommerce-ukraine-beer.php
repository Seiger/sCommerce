<?php

// php tests/standalone/scommerce-ukraine-beer.php
// Selling beer in Ukraine: prices in UAH include 20% VAT and the 5% retail excise, which is charged
// on the price with VAT (excise = P × 5 / 105); the returnable bottle deposit is outside both
// (art. 189.2 of the Tax Code). In-memory SQLite, fake products, no CMS/site bootstrap.
require __DIR__ . '/fixtures/checkout-harness.php';

use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Cart\sCart;
use Seiger\sCommerce\Checkout\sCheckout;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Services\OrderTaxes;
use Seiger\sCommerce\Services\VatDisplay;

$shop->currency = 'UAH';
$shop->config = array_replace($shop->config, [
    'basic.deliveries_on' => 0,
    'product.vat_display_on' => 1,
    'product.vat_rate' => 20,
    'product.retail_excise_on' => 1,
    'product.container_deposit_on' => 1,
    'product.container_exchange_on' => 1,
    'product.container_terms' => 'Пляшка є зворотною тарою (п. 189.2 ПКУ)',
]);
// The excise applies to beer only, set per product as a project would.
Event::listen(VatDisplay::EXCISE_EVENT, static fn (array $payload) => $payload['product']->sku === 'BEER-05' ? 5 : null);
$beer = new FakeProduct(1, 'BEER-05', 52.50, title: 'Lager 0.5 l');
$beer->currency = 'UAH';
$beer->deposit = 3.0;
$chips = new FakeProduct(2, 'CHIPS', 30.0, title: 'Chips');
$chips->currency = 'UAH';
$shop->products = [1 => $beer, 2 => $chips];

// 1. One bottle: 2.50 UAH excise inside 52.50; without VAT and excise 41.67.
$check(VatDisplay::excise(52.50, 5) === 2.5 && VatDisplay::net(52.50, 20, 5) === 41.67, 'Unit excise and net price');
$check(VatDisplay::excise(52.50, 0) === 0.0 && VatDisplay::net(30.0, 20) === 25.0, 'No excise without a rate');

// 2. Cart: four bottles, two empty bottles handed over, and chips.
$_SESSION['sCart'] = [1 => [0 => 4], 2 => [0 => 1]];
$exchange = (new sCart())->setContainerExchange(1, 0, 2);
$cart = $exchange['miniCart'];
$items = array_column($cart['items'], null, 'id');
$check($exchange['success'] && $items[1]['retailExciseRate'] === 5.0 && $items[1]['retailExciseAmountAsFloat'] === 2.5 && $items[1]['priceNetAsFloat'] === 41.67,
    'Beer line shows the excise and the price without VAT');
$check($items[1]['sumAsFloat'] === 210.0 && $items[1]['sumRetailExciseAsFloat'] === 10.0 && $items[1]['sumNetAsFloat'] === 166.67, 'Beer line totals');
$check($items[1]['deposit_unit'] === 3.0 && $items[1]['exchange_quantity'] === 2 && $items[1]['deposit_quantity'] === 2 && $items[1]['deposit_amount'] === 6.0,
    'Two exchanged bottles: deposit for the other two only');
$check($items[2]['retailExciseAmount'] === '' && $items[2]['sumNetAsFloat'] === 25.0 && !isset($items[2]['deposit_amount']), 'Chips carry neither excise nor deposit');
$check($cart['totalGoods'] === 240.0 && $cart['totalDeposit'] === 6.0 && $cart['totalSum'] === 246.0, 'The customer pays goods plus deposit');
$check($cart['totalRetailExcise'] === 10.0 && $cart['totalRetailExciseFormatted'] === '10.00 UAH' && $cart['totalSumNet'] === 197.67,
    'The deposit is outside the excise and VAT');

// 3. Checkout: the order keeps the excise and deposit for the receipt; display values are not stored.
$result = $checkout($cart['items'], ['cost' => $cart['totalSum'], 'currency' => 'UAH'])->processOrder();
$order = sOrder::query()->find($result['order']->id);
$lines = array_column($order->products, null, 'product_id');
$check($result['success'] && (float)$order->cost === 246.0 && $order->currency === 'UAH', 'Order cost in UAH');
$check($lines[1]['retail_excise_rate'] == 5 && $lines[1]['retail_excise_amount'] == 10 && !isset($lines[2]['retail_excise_amount']), 'Order lines keep the excise');
$check($lines[1]['deposit_amount'] == 6 && $lines[1]['deposit_returnable'] === true && $lines[1]['deposit_terms'] === 'Пляшка є зворотною тарою (п. 189.2 ПКУ)',
    'Order lines keep the returnable deposit and its terms');
$check(!array_intersect_key($lines[1], array_flip(VatDisplay::keys())), 'Visitor display values are not order data');

// 4. Quick order of two bottles, one empty bottle handed over.
$quick = sCheckout::quickOrder(['productId' => 1, 'quantity' => 2, 'exchange' => 1, 'phone' => '+380671234567']);
$line = sOrder::query()->find($quick['order']->id)->products[0];
$check($quick['success'] && (float)$quick['order']->cost === 105.0 + 3.0 && $line['retail_excise_amount'] == 5 && $line['deposit_amount'] == 3,
    'Quick order: 105 UAH beer with 5 UAH excise, 3 UAH deposit');

// 5. The manager changes the quantity: the excise follows the line total.
$edited = OrderTaxes::applyRetailExcise([array_replace($lines[1], ['quantity' => 6])]);
$check($edited[0]['retail_excise_amount'] === 15.0, 'Six bottles: 315 UAH with 15 UAH excise');

echo "$checks Ukrainian beer checks passed (isolated SQLite; no site database)\n";
