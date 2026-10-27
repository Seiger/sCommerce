<?php

// php tests/standalone/scommerce-line-parts.php
// Split cart entries (sCommerce.SplitCartLine): one product sold from stock lots at different prices
// in the cart, checkout, quick order, manager edits and repeats. In-memory SQLite, fake products.
require __DIR__ . '/fixtures/checkout-harness.php';

use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Cart\CartLineParts;
use Seiger\sCommerce\Cart\sCart;
use Seiger\sCommerce\Checkout\sCheckout;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Services\OrderLineEditor;
use Seiger\sCommerce\Services\OrderRepeater;
use Seiger\sCommerce\sCommerce;

$shop->config['basic.deliveries_on'] = 0;
$shop->products = [
    5 => new FakeProduct(5, 'MILK', 10.0, title: 'Milk'),
    6 => new FakeProduct(6, 'BREAD', 4.0, title: 'Bread'),
];

// 1. Without a listener nothing changes.
$plain = $cartLines([5 => [0 => 5]]);
$check(count($plain) === 1 && $plain[0]['line_key'] === '5:0' && $plain[0]['quantity'] === 5
    && array_intersect_key($plain[0], array_flip([...CartLineParts::LINE_KEYS, CartLineParts::CART_QUANTITY])) === [], 'Unsplit entries keep their line');

// A lot listener: units of the expiring lot 11 first at a markdown price, the rest of lot 12 at the regular price.
$lots = ['expiring' => 2];
Event::listen(CartLineParts::EVENT, static function (array $payload) use (&$lots): ?array {
    if ((int)$payload['product']->id !== 5) {
        return null;
    }
    $quantity = $payload['quantity'];
    $marked = min($lots['expiring'], $quantity);
    $parts = [];
    if ($marked > 0) {
        $parts[] = ['key' => 'lot-11', 'quantity' => $marked, 'priceAsFloat' => 7.0, 'oldPriceAsFloat' => $payload['pricing']['priceAsFloat'],
            'label' => 'Best before 27.10 18:00', 'allocation' => ['lots' => [['id' => 11, 'quantity' => $marked]]]];
    }
    if ($quantity > $marked) {
        $parts[] = ['key' => 'lot-12', 'quantity' => $quantity - $marked, 'allocation' => ['lots' => [['id' => 12, 'quantity' => $quantity - $marked]]]];
    }
    return $parts;
});

// 2. The cart prices each part: 2 × 7.00 + 3 × 10.00 = 44.00, and bread stays one line.
$_SESSION['sCart'] = [5 => [0 => 5], 6 => [0 => 1]];
$cart = (new sCart())->getMiniCart();
[$marked, $regular, $bread] = $cart['items'];
$check(count($cart['items']) === 3 && $marked['line_key'] === '5:0:lot-11' && $regular['line_key'] === '5:0:lot-12' && $bread['line_key'] === '6:0',
    'One line per part with distinct line keys');
$check($marked['quantity'] === 2 && (float)$marked['priceAsFloat'] === 7.0 && $marked['price'] === '7.00 EUR' && (float)$marked['oldPriceAsFloat'] === 10.0
    && $marked['oldPrice'] === '10.00 EUR' && (float)$marked['sumAsFloat'] === 14.0, 'Markdown part has its own price and old price');
$check($regular['quantity'] === 3 && (float)$regular['priceAsFloat'] === 10.0 && $regular['price'] === '10.00' && (float)$regular['sumAsFloat'] === 30.0,
    'A part without a price keeps the entry pricing');
$check($marked['cart_quantity'] === 5 && $regular['cart_quantity'] === 5 && $marked['part_key'] === 'lot-11' && $marked['part_label'] === 'Best before 27.10 18:00'
    && !isset($regular['part_label']) && $marked['allocation'] === ['lots' => [['id' => 11, 'quantity' => 2]]], 'Parts carry key, label, allocation and the entry quantity');
$check((float)$cart['totalSum'] === 48.0 && !isset($bread['part_key']), 'Totals add up the parts');

// 3. Checkout stores the parts as order lines; the cart control quantity is not order data.
$result = $checkout($cart['items'], ['cost' => 48.0])->processOrder();
$order = sOrder::query()->find($result['order']->id ?? 0);
$check($result['success'] === true && $order && (float)$order->cost === 48.0 && count($order->products) === 3, 'Order placed with the split lines');
$check(array_column($order->products, 'line_key') === ['5:0:lot-11', '5:0:lot-12', '6:0'] && $order->products[0]['allocation']['lots'][0]['id'] === 11
    && $order->products[0]['part_label'] === 'Best before 27.10 18:00' && !array_key_exists('cart_quantity', $order->products[0]), 'Order lines keep parts without cart_quantity');

// 4. When the split changes before checkout (the expiring lot sold out), the customer confirms the new total.
$lines = $cartLines([5 => [0 => 5]]);
$lots['expiring'] = 0;
$result = $checkout($lines, ['cost' => 44.0])->processOrder();
$check($result['success'] === false && $result['error_code'] === 'cart_changed' && (float)$result['cost'] === 50.0
    && array_column($result['products'], 'line_key') === ['5:0:lot-12'], 'A changed split is reported as cart_changed');
$lots['expiring'] = 2;

// 5. Container exchanges fill the parts in order.
$shop->config['product.container_deposit_on'] = 1;
$shop->config['product.container_exchange_on'] = 1;
$shop->products[5]->deposit = 1.5;
[$first, $second] = $cartLines([5 => [0 => ['quantity' => 5, 'exchange' => 3]]]);
$check($first['exchange_quantity'] === 2 && $first['deposit_quantity'] === 0 && $second['exchange_quantity'] === 1 && $second['deposit_quantity'] === 2,
    'Declared exchanges are spread over the parts');
$shop->config['product.container_deposit_on'] = 0;
$shop->config['product.container_exchange_on'] = 0;
$shop->products[5]->deposit = 0.0;

// 6. A quick order of one product is split like the cart.
$_SESSION = [];
$quick = sCheckout::quickOrder(['productId' => 5, 'quantity' => 4, 'email' => 'quick@shop.test']);
$quickOrder = sOrder::query()->find($quick['order']->id ?? 0);
$check($quick['success'] === true && array_column($quickOrder->products, 'quantity') === [2, 2] && (float)$quickOrder->cost === 34.0
    && $quickOrder->products[1]['line_key'] === '5:0:lot-12' && $quickOrder->products[1]['weight'] === 125, 'Quick order stores the parts');

// 7. Manager edits cannot forge part keys; new lines get none.
$posted = $order->products;
$posted[0]['allocation'] = ['lots' => [['id' => 99, 'quantity' => 2]]];
$posted[0]['part_label'] = 'Forged';
$posted[1]['quantity'] = 4;
unset($posted[1]['allocation']);
$posted[] = ['id' => 5, 'title' => 'Added', 'price' => '10.00', 'priceNumber' => 10, 'quantity' => 1, 'allocation' => ['lots' => [['id' => 99]]], 'cart_quantity' => 9];
(new OrderLineEditor())->apply($order, $posted);
$edited = $order->products;
$check($edited[0]['allocation']['lots'][0]['id'] === 11 && $edited[0]['part_label'] === 'Best before 27.10 18:00'
    && $edited[1]['allocation']['lots'][0]['id'] === 12 && $edited[1]['quantity'] === 4, 'Stored lines keep their server-side part keys');
$check($edited[3]['line_key'] === '5:0' && !isset($edited[3]['allocation']) && !isset($edited[3]['cart_quantity']), 'Lines added in the manager carry no allocation');

// 8. A repeated order bills the stored prices but claims no stock lot.
$draft = (new OrderRepeater())->draft(sOrder::query()->find($order->id));
$check(!isset($draft->products[0]['allocation']) && $draft->products[0]['part_label'] === 'Best before 27.10 18:00'
    && $draft->products[0]['line_key'] === '5:0:lot-11', 'Repeats drop allocations');

// 9. Invalid splits fail loudly instead of selling at a wrong price.
$invalid = [
    'not a list' => ['a' => ['key' => 'a', 'quantity' => 5]],
    'empty' => [],
    'wrong total' => [['key' => 'a', 'quantity' => 4]],
    'duplicate key' => [['key' => 'a', 'quantity' => 2], ['key' => 'a', 'quantity' => 3]],
    'bad key' => [['key' => 'lot 1', 'quantity' => 5]],
    'zero quantity' => [['key' => 'a', 'quantity' => 0], ['key' => 'b', 'quantity' => 5]],
    'string quantity' => [['key' => 'a', 'quantity' => '5']],
    'negative price' => [['key' => 'a', 'quantity' => 5, 'priceAsFloat' => -1.0]],
    'old price only' => [['key' => 'a', 'quantity' => 5, 'oldPriceAsFloat' => 12.0]],
    'long label' => [['key' => 'a', 'quantity' => 5, 'label' => str_repeat('x', 256)]],
    'scalar allocation' => [['key' => 'a', 'quantity' => 5, 'allocation' => 11]],
    'unencodable allocation' => [['key' => 'a', 'quantity' => 5, 'allocation' => ['ratio' => NAN]]],
];
$response = null;
Event::listen(CartLineParts::EVENT, static function (array $payload) use (&$response) {
    return (int)$payload['product']->id === 6 ? $response : null;
});
foreach ($invalid as $case => $response) {
    try {
        CartLineParts::resolve($shop->products[6], 0, 5, ['priceAsFloat' => 4.0], 'EUR');
        $check(false, "Invalid split accepted: {$case}");
    } catch (UnexpectedValueException) {
        $checks++;
    }
}
$response = [['key' => 'whole', 'quantity' => 5, 'label' => '  ']];
$check(CartLineParts::resolve($shop->products[6], 0, 5, ['priceAsFloat' => 4.0], 'EUR') === [['key' => 'whole', 'quantity' => 5, 'priceAsFloat' => null,
    'oldPriceAsFloat' => null, 'label' => null, 'allocation' => null]], 'A single part with a blank label is normalized');
$response = null;
$check(CartLineParts::resolve($shop->products[6], 0, 0, ['priceAsFloat' => 4.0], 'EUR') === null, 'Empty entries are not split');

$check(sCommerce::supportsIntegration('line-parts'), 'Feature key line-parts');

echo "{$checks} line part checks passed\n";
