<?php

// php tests/standalone/scommerce-line-identity.php
// Isolated order-line identity checks through the real cart, quick order and manager-line
// resolution: in-memory SQLite, fake products, no CMS/site bootstrap.
require __DIR__ . '/fixtures/checkout-harness.php';

use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Checkout\sCheckout;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Services\OrderLineIdentity;
use Seiger\sCommerce\sCommerce;

$shop->products = [
    5 => new FakeProduct(5, 'SKU-5', 10.0, gtin: '4006381333931'),
    6 => new FakeProduct(6, 'SKU-6', 20.0),
];
$identity = new OrderLineIdentity();

// 1. Without listeners: product SKU, copied GTIN, no option, null tax placeholders.
$line = $identity->resolve($shop->products[5]);
$check($line === ['line_key' => '5:0', 'product_id' => 5, 'option_id' => 0, 'sku' => 'SKU-5', 'product_sku' => 'SKU-5',
    'gtin' => '4006381333931', 'unit_price_net' => null, 'tax_rate' => null], 'Default identity');
$check($identity->resolve($shop->products[6])['gtin'] === null, 'Empty GTIN is null');
$snapshot = $identity->snapshot($shop->products[5]);
$check($snapshot['requires_shipping'] === true && $snapshot['tracks_inventory'] === true, 'Simple products are shipped and tracked');

// 2. Two cart entries of one product with different options give two distinguishable lines.
$lines = $cartLines([5 => [0 => 1, 3 => 2]]);
$check(count($lines) === 2 && array_column($lines, 'option_id') === [0, 3] && array_column($lines, 'line_key') === ['5:0', '5:3'], 'Option lines stay distinct');
$check($lines[1]['id'] === 5 && $lines[1]['sku'] === 'SKU-5' && $lines[1]['quantity'] === 2 && (float)$lines[1]['priceAsFloat'] === 13.0, 'Existing cart keys keep their values');

// 3. Listeners may override sku and gtin only.
Event::listen(OrderLineIdentity::EVENT, static function (array $payload): void {
    if ((int)$payload['product']->id === 5 && $payload['optionId'] === 3) {
        $payload['identity']['sku'] = 'SKU-5-RED';
        $payload['identity']['gtin'] = '5901234123457';
        $payload['identity']['line_key'] = 'spoofed';
        $payload['identity']['product_id'] = 99;
        $payload['identity']['option_id'] = 7;
        $payload['identity']['product_sku'] = 'OTHER';
        $payload['identity']['unit_price_net'] = 1.0;
    }
});
$variant = $identity->resolve($shop->products[5], 3);
$check($variant['sku'] === 'SKU-5-RED' && $variant['gtin'] === '5901234123457', 'Variant SKU and GTIN applied');
$check($variant['line_key'] === '5:3' && $variant['product_id'] === 5 && $variant['option_id'] === 3 && $variant['product_sku'] === 'SKU-5'
    && $variant['unit_price_net'] === null, 'Canonical keys cannot be spoofed');
$check($cartLines([5 => [3 => 1]])[0]['sku'] === 'SKU-5-RED', 'Cart lines carry the effective SKU');

// 4. Quick order of a single product persists its option.
$result = sCheckout::quickOrder(['productId' => 6, 'optionId' => 2, 'quantity' => 3, 'email' => 'quick@shop.test']);
$order = sOrder::query()->find($result['order']->id);
$quick = $order->products[0];
$check($result['success'] === true && $quick['option_id'] === 2 && $quick['line_key'] === '6:2' && $quick['product_sku'] === 'SKU-6'
    && $quick['quantity'] === 3 && (float)$quick['priceAsFloat'] === 22.0 && (float)$order->cost === 66.0, 'Quick order stores the option line');

// 5. Manager edits: new lines are resolved on the server, existing lines keep their identity.
$stored = array_merge($identity->snapshot($shop->products[6]), ['title' => 'Stored', 'sku' => 'LEGACY-VARIANT', 'quantity' => 1]);
$edited = $identity->fillMissing([
    $stored,
    ['id' => 5, 'title' => 'Picked in manager', 'price' => '10.00', 'priceNumber' => 10, 'quantity' => 2, 'line_key' => ''],
    ['id' => 5, 'option_id' => '3', 'title' => 'Option', 'price' => '13.00', 'quantity' => 1],
    ['id' => 404, 'title' => 'Deleted product', 'quantity' => 1],
    'not a line',
]);
$check($edited[0] === $stored, 'Existing identity is kept');
$check($edited[1]['line_key'] === '5:0' && $edited[1]['gtin'] === '4006381333931' && $edited[1]['title'] === 'Picked in manager'
    && $edited[1]['quantity'] === 2 && $edited[1]['requires_shipping'] === true, 'New manager line gets identity on the server');
$check($edited[2]['line_key'] === '5:3' && $edited[2]['sku'] === 'SKU-5-RED', 'Submitted option id is honoured');
$check(!isset($edited[3]['line_key']) && $edited[4] === 'not a line', 'Unknown products and malformed lines stay unchanged');
$module = file_get_contents($package . '/module/sCommerceModule.php');
$save = substr($module, strpos($module, 'case "orderSave":'), strpos($module, 'case "orderDelete":') - strpos($module, 'case "orderSave":'));
$check(str_contains($save, 'OrderLineIdentity::class)->fillMissing($productsData, (string)$item->currency)'), 'Manager orderSave resolves missing identity');
$search = substr($module, strpos($module, 'case "orderSearchProducts":'), 2000);
$check(str_contains($search, 'OrderLineIdentity::class)->snapshot('), 'Manager product search returns identity');

// 6. Old orders without the keys fall back as documented.
$check(OrderLineIdentity::requiresShipping(['id' => 1]) && OrderLineIdentity::tracksInventory(['id' => 1]), 'Old lines are shipped and tracked');
$check(sCommerce::supportsIntegration('line-identity'), 'Feature key line-identity');

echo "$checks line identity checks passed (isolated SQLite; no site database)\n";
