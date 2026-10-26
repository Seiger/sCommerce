<?php

// php tests/standalone/scommerce-fulfilment.php
// Isolated service/virtual product checks: delivery-less checkout, inventory, the fulfilment
// override and generic order meta. In-memory SQLite, fake products, no CMS/site bootstrap.
require __DIR__ . '/fixtures/checkout-harness.php';

use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Cart\sCart;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\sCommerce;

$shop->products = [
    1 => new FakeProduct(1, 'BOOK', 25.0),
    2 => new FakeProduct(2, 'INSTALL', 80.0, sProduct::MODE_SERVICE, inventory: 0),
    3 => new FakeProduct(3, 'LICENSE', 15.0, sProduct::MODE_VIRTUAL, inventory: 0),
    4 => new FakeProduct(4, 'MUG', 9.0, inventory: 2),
];

// Modes and defaults.
$modes = sProduct::listMode();
$check(isset($modes[sProduct::MODE_VIRTUAL], $modes[sProduct::MODE_SERVICE]) && $modes[sProduct::MODE_SERVICE] === __('sCommerce::global.mode_service'), 'Virtual and service modes are selectable');
$check((new sProduct())->forceFill(['mode' => sProduct::MODE_SERVICE])->fulfilment() === ['shipping' => false, 'inventory' => false]
    && (new sProduct())->forceFill(['mode' => sProduct::MODE_GROUP])->fulfilment() === ['shipping' => true, 'inventory' => true], 'Default fulfilment by mode');

// 1. A services-only cart with deliveries on needs no delivery method.
$lines = $cartLines([2 => [0 => 1], 3 => [0 => 2]]);
$check($lines[0]['requires_shipping'] === false && $lines[0]['tracks_inventory'] === false && $lines[1]['requires_shipping'] === false, 'Service and virtual lines are flagged');
$services = $checkout($lines);
$check($services->requiresDelivery() === false && !isset($services->getValidationRules([])['delivery.method']), 'Delivery rule dropped before the validation event');
$seenRules = null;
Event::listen('sCommerce.CheckoutValidationRules', static function (array $payload) use (&$seenRules): void {
    $seenRules = array_keys($payload['rules']);
});
$services->getValidationRules(['delivery' => ['method' => 'courier']]);
$check(!in_array('delivery.method', $seenRules, true), 'Listeners see the rules without the delivery method');
$result = $services->processOrder();
$order = sOrder::query()->find($result['order']->id);
$check($result['success'] === true && $order->delivery_info === ['method' => '', 'required' => false, 'cost' => 0] && (float)$order->cost === 110.0, 'Services checkout succeeds without delivery');

// 2. A mixed cart still requires delivery.
$mixed = $checkout($cartLines([1 => [0 => 1], 2 => [0 => 1]]));
$check($mixed->requiresDelivery() === true && isset($mixed->getValidationRules([])['delivery.method']), 'Mixed cart requires delivery');
$orders = sOrder::query()->count();
$result = $mixed->processOrder();
$check($result['success'] === false && sOrder::query()->count() === $orders, 'Mixed cart without a delivery method is refused');
$shop->config['basic.deliveries_on'] = 0;
$check($mixed->requiresDelivery() === false, 'Disabled deliveries never require delivery');
$result = $mixed->processOrder();
$check($result['success'] === true && sOrder::query()->find($result['order']->id)->delivery_info === [], 'Disabled deliveries keep the previous empty delivery_info');
$shop->config['basic.deliveries_on'] = 1;

// 3. Inventory clamps only tracked lines.
$shop->config['product.inventory_on'] = 1;
$_SESSION['sCart'] = [];
$response = (new sCart())->addProduct(2, 0, 3);
$check($response['success'] === true && $_SESSION['sCart'][2][0] === 3, 'A service with inventory 0 is added with the requested quantity');
$response = (new sCart())->addProduct(4, 0, 5);
$check($response['success'] === true && $_SESSION['sCart'][4][0] === 2, 'Physical products are still clamped to inventory');
$shop->config['product.inventory_on'] = 0;

// 4. The fulfilment event can make a simple product non-shippable.
Event::listen('sCommerce.ResolveProductFulfilment', static function (array $payload): ?array {
    return (int)$payload['product']->id === 1 && $payload['optionId'] === 9 ? ['shipping' => false] : null;
});
$giftCard = $cartLines([1 => [9 => 1]]);
$check($giftCard[0]['requires_shipping'] === false && $giftCard[0]['tracks_inventory'] === true, 'Override replaces only the given flag');
$check($checkout($giftCard)->requiresDelivery() === false, 'Overridden line needs no delivery');

// 5. BeforeOrderCreate listeners store project fields in meta; OrderCreated carries them.
Event::listen('sCommerce.BeforeOrderCreate', static function (array $payload): void {
    if ($payload['source'] === 'storefront' && !$payload['order']->exists) {
        $payload['order']->meta = array_merge($payload['order']->meta ?? [], [
            'booking' => ['date' => $payload['data']['booking_date'] ?? '', 'slot' => '09:00-11:00'],
            'integration' => ['environment' => 'test'],
        ]);
    }
});
$events = [];
$result = $checkout($cartLines([2 => [0 => 1]]), ['booking_date' => '2026-11-02'])->processOrder();
$order = sOrder::query()->find($result['order']->id);
$check($order->meta['booking'] === ['date' => '2026-11-02', 'slot' => '09:00-11:00'], 'Booking meta persisted');
$check($order->meta['integration']['environment'] === 'live' && !$order->isTest(), 'Listeners cannot rebind the checkout context');
$created = array_values(array_filter($events, static fn ($event) => $event[0] === 'sCommerce.OrderCreated'));
$check(count($created) === 1 && $created[0][1]['order']->meta['booking']['slot'] === '09:00-11:00' && $created[0][1]['environment'] === 'live', 'OrderCreated carries meta');

// 6. Old orders without the flags are shippable.
$check($checkout([['id' => 1, 'title' => 'Old', 'quantity' => 1, 'priceAsFloat' => 5]])->requiresDelivery() === true, 'Old lines require delivery');

// initCheckout exposes the flag for templates.
$_SESSION['sCart'] = [3 => [0 => 1]];
$init = (new ReflectionClass(Seiger\sCommerce\Checkout\sCheckout::class))->newInstanceWithoutConstructor()->initCheckout();
$check($init['requiresDelivery'] === false && !isset($_SESSION['sCheckout']['requiresDelivery']), 'initCheckout exposes requiresDelivery without storing it');
$check(sCommerce::supportsIntegration('fulfilment-modes'), 'Feature key fulfilment-modes');

echo "$checks fulfilment checks passed (isolated SQLite; no site database)\n";
