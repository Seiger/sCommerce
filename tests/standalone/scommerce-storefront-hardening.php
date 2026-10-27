<?php

// php tests/standalone/scommerce-storefront-hardening.php
// Isolated checks of anonymous storefront requests that could cost the shop money or leak data:
// unpublished products, stale checkout prices, delivery cost currency and address, retries, stock,
// quantity limits, order payloads and the order rate limit. In-memory SQLite, no CMS/site bootstrap.
require __DIR__ . '/fixtures/checkout-harness.php';

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Seiger\sCommerce\Cart\sCart;
use Seiger\sCommerce\Checkout\sCheckout;
use Seiger\sCommerce\Delivery\CourierDelivery;
use Seiger\sCommerce\Models\sDeliveryMethod;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Services\MeasuredQuantity;
use Seiger\sCommerce\Services\StorefrontGuard;

$shop->products = [1 => new FakeProduct(1, 'TEA', 10.0), 2 => new FakeProduct(2, 'DRAFT', 0.0)];
$shop->products[2]->published = 0;
$shop->config['basic.deliveries_on'] = 0;
$request = static function (array $input = [], string $ip = '127.0.0.1'): void {
    app()->instance('request', Request::create('/', 'POST', $input, [], [], ['REMOTE_ADDR' => $ip]));
};
// A request starts with the session cart; the facade would keep the first one resolved.
$freshCart = static fn () => Facade::clearResolvedInstance('sCart');

// 1. Unpublished products cannot be bought, and errors never return the product row.
$_SESSION['sCart'] = [];
$request(['productId' => 2]);
$check((new sCart())->addProduct()['success'] === false && $_SESSION['sCart'] === [], 'Unpublished products are not added to the cart');
$_SESSION['sCart'] = [1 => [0 => 1], 2 => [0 => 1]];
$check(array_column((new sCart())->getMiniCart()['items'], 'id') === [1], 'Unpublished lines are left out of the cart');
$before = sOrder::count();
$check(sCheckout::quickOrder(['productId' => 2, 'email' => 'q@shop.test'])['message'] === 'Product not found.' && sOrder::count() === $before,
    'Unpublished products cannot be quick ordered');
$noContact = sCheckout::quickOrder(['productId' => 1]);
$check($noContact['success'] === false && $noContact['product'] === ['id' => 1, 'title' => 'Product 1', 'link' => ''], 'A failed quick order returns only public product fields');
$check(sCheckout::quickOrder(['productId' => 2])['product'] === [], 'Nothing about unpublished products');

// 2. Product ids: missing means the cart, anything but a whole number is unknown; never a TypeError.
$_SESSION['sCart'] = [];
$freshCart();
$check(sCheckout::quickOrder(['email' => 'q@shop.test'])['message'] === 'Cart is empty. Cannot create a quick order.', 'Without a product id the cart is ordered');
foreach (['abc', ['1'], '-1', '1.5', 1.5, true] as $productId) {
    $check(sCheckout::quickOrder(['productId' => $productId, 'email' => 'q@shop.test'])['message'] === 'Product not found.', 'Malformed product ids are unknown products');
}

// 3. Quantity limits and negative prices.
$_SESSION['sCart'] = [];
$request(['productId' => 1, 'quantity' => 5000000]);
$added = (new sCart())->addProduct();
$check($added['quantityAdjusted'] === true && $_SESSION['sCart'][1][0] === MeasuredQuantity::PIECE_MAX, 'Cart quantities stop at the piece maximum');
$_SESSION['sCart'] = [1 => [0 => PHP_INT_MAX]];
$check((new sCart())->getMiniCart()['items'][0]['quantity'] === MeasuredQuantity::PIECE_MAX, 'Session quantities are capped too');
$tooMany = sCheckout::quickOrder(['productId' => 1, 'quantity' => 2000000, 'email' => 'q@shop.test']);
$check($tooMany['success'] === false && $tooMany['message'] === 'The quantity may not be greater than 1000000.', 'Quick orders refuse quantities above the maximum');
$request(['productId' => ['1']]);
$check((new sCart())->removeProduct()['success'] === false, 'Malformed ids are not removed (no TypeError)');
$request(['productId' => '1']);
$check((new sCart())->removeProduct()['success'] === true && !isset($_SESSION['sCart'][1]), 'Numeric ids are removed');
$shop->products[3] = new FakeProduct(3, 'BROKEN', -5.0);
$_SESSION['sCart'] = [3 => [0 => 2]];
$check((new sCart())->getMiniCart()['totalSum'] === 0.0, 'A negative price never lowers the cart total');
$check((float)sCheckout::quickOrder(['productId' => 3, 'quantity' => 2, 'email' => 'q@shop.test'])['order']->cost === 0.0, 'Nor the quick order cost');
unset($shop->products[3]);

// 4. Stock: quick orders and checkout check it like the cart does.
$shop->config['product.inventory_on'] = 1;
$shop->products[1]->inventory = 2;
$before = sOrder::count();
$short = sCheckout::quickOrder(['productId' => 1, 'quantity' => 3, 'email' => 'q@shop.test']);
$check($short['error_code'] === 'out_of_stock' && sOrder::count() === $before, 'Quick orders above stock are refused');
$_SESSION['sCart'] = [1 => [0 => 3]];
$freshCart();
$check(sCheckout::quickOrder(['productId' => 0, 'email' => 'q@shop.test'])['error_code'] === 'out_of_stock', 'Cart quick orders check stock');
$check($checkout([['id' => 1, 'product_id' => 1, 'option_id' => 0, 'quantity' => 3, 'priceAsFloat' => 10]])->processOrder()['error_code'] === 'out_of_stock'
    && sOrder::count() === $before, 'Checkout checks stock before saving');
$check(sCheckout::quickOrder(['productId' => 1, 'quantity' => 2, 'email' => 'q@shop.test'])['success'] === true, 'Orders within stock pass');
$shop->config['product.inventory_on'] = 0;
$shop->products[1]->inventory = 100;

// 5. Checkout re-prices the lines it kept since it started.
$lines = $cartLines([1 => [0 => 2]]);
$stale = $checkout($lines);
$shop->products[1]->price = 12.0;
$_SESSION['sCheckout'] = ['started' => true];
$before = sOrder::count();
$changed = $stale->processOrder();
$check($changed['success'] === false && $changed['error_code'] === 'cart_changed' && $changed['cost'] === 24.0 && sOrder::count() === $before,
    'A price change since the checkout started fails with the new total');
$check($_SESSION['sCheckout']['cost'] === 24.0 && $_SESSION['sCheckout']['products'][0]['priceAsFloat'] === 12.0, 'The checkout keeps the current lines');
$confirmed = $stale->processOrder();
$check($confirmed['success'] && (float)$confirmed['order']->cost === 24.0, 'The confirmed retry is charged the current price');
$check($checkout($lines, ['cost' => 1])->processOrder()['error_code'] === 'cart_changed', 'A total that does not match the lines is never charged');
$lines = $cartLines([1 => [0 => 1]]);
$hidden = $checkout($lines);
$shop->products[1]->published = 0;
$check($hidden->processOrder()['error_code'] === 'cart_changed' && $hidden->processOrder()['message'] === 'Invalid products in Order.', 'Products unpublished since are removed');
$shop->products[1]->published = 1;
$switched = $checkout($cartLines([1 => [0 => 1]]));
$shop->currency = 'UAH';
$check($switched->processOrder()['error_code'] === 'cart_changed' && $_SESSION['sCheckout']['currency'] === 'UAH', 'A currency switch re-prices the checkout');
$shop->currency = 'EUR';

// 6. Delivery: cost in the order currency, recalculated for every address, added once per attempt.
$db->schema()->create('s_delivery_methods', static function ($table): void {
    $table->id();
    $table->uuid('uuid')->nullable();
    $table->string('name')->unique();
    $table->string('class');
    $table->boolean('active')->default(true);
    $table->integer('position')->default(0);
    $table->text('title')->nullable();
    $table->text('description')->nullable();
    $table->decimal('cost', 9, 2)->default(0);
    $table->char('currency', 3)->default('USD');
    $table->text('settings')->nullable();
    $table->timestamps();
});
$method = new sDeliveryMethod(['name' => 'courier', 'class' => CourierDelivery::class, 'active' => 1]);
$method->forceFill(['cost' => 100, 'currency' => 'UAH', 'settings' => json_encode(['cities' => [['city' => 'Київ', 'extra_cost' => 50]]], JSON_UNESCAPED_UNICODE)])->save();
$courier = new CourierDelivery();
$check($courier->getCostCurrency() === 'UAH' && $courier->calculateCost(['user' => ['address' => ['city' => ' КИЇВ ']]]) === 150.0
    && $courier->calculateCost(['delivery' => ['courier' => ['city' => 'Львів']]]) === 100.0, 'Cities match case-insensitively in Cyrillic');
$shop->config['basic.deliveries_on'] = 1;
$shop->rates = ['UAH_EUR' => 0.025];
$lines = $cartLines([1 => [0 => 1]]);
$withCourier = static function (array $extra = []) use ($checkout, $lines, $courier): sCheckout {
    $instance = $checkout($lines, array_replace_recursive(['delivery' => ['method' => 'courier'], 'user' => ['address' => ['city' => 'Київ']]], $extra));
    $instance->registerDeliveryMethod($courier);
    return $instance;
};
$order = $withCourier()->processOrder()['order'];
$check((float)$order->cost === 12.0 + 3.75 && $order->delivery_info['cost'] === 3.75, '150 UAH delivery is charged as 3.75 EUR, not 150 EUR');
$stalePrice = $withCourier(['delivery' => ['cost' => 0.01]])->processOrder()['order'];
$check((float)$stalePrice->cost === 15.75, 'Checkout recalculates the delivery instead of trusting the stored cost');
$moving = $withCourier();
$check($moving->setOrderData(['delivery' => ['method' => 'courier']])['delivery']['cost'] === 3.75
    && $moving->setOrderData(['user' => ['address' => ['city' => 'Львів']]])['delivery']['cost'] === 2.5, 'An address change recalculates the delivery');
$retry = $withCourier();
$fail = true;
Event::listen('sCommerce.BeforeOrderCreate', static function () use (&$fail): void {
    if ($fail) {
        $fail = false;
        throw new InvalidArgumentException('Temporary failure');
    }
});
$_SESSION['sCheckout'] = ['started' => true];
$check($retry->processOrder()['success'] === false && $_SESSION['sCheckout'] === ['started' => true], 'A failed attempt leaves the checkout session as it was');
$check((float)$retry->processOrder()['order']->cost === 15.75, 'The retry adds the delivery once');
$shop->rates = [];
$shop->config['basic.deliveries_on'] = 0;

// 7. The storefront gets the order without manager notes, history or meta.
$order = sOrder::query()->latest('id')->first();
$order->manager_notes = [['comment' => 'Internal: VIP, call the owner']];
$public = $order->toCustomerArray();
$check(isset($public['identifier'], $public['products'], $public['cost']) && array_key_exists('order_number', $public)
    && !array_intersect_key($public, array_flip(['manager_notes', 'history', 'meta', 'manager_info', 'marketing'])), 'Customer payload keeps internal fields out');
$routes = file_get_contents($package . '/src/Http/routes.php');
$check(substr_count($routes, '$placeOrder(') === 2 && str_contains($routes, "->toCustomerArray()") && str_contains($routes, 'StorefrontGuard::orderAllowed()')
    && str_contains($routes, '429'), 'Order routes are limited and return the customer payload');

// 8. Orders per client.
$shop->config['orders.rate_limit'] = 2;
$request();
$check(StorefrontGuard::orderAllowed(), 'First order allowed');
StorefrontGuard::orderPlaced();
StorefrontGuard::orderPlaced();
$check(!StorefrontGuard::orderAllowed(), 'The limit stops the next order');
$request([], '10.0.0.2');
$check(StorefrontGuard::orderAllowed(), 'Other clients are not affected');
$shop->config['orders.rate_limit'] = 0;
$request();
$check(StorefrontGuard::orderAllowed(), 'Limit 0 disables it');
$shop->config['orders.rate_limit'] = 2;
$cache = $app['cache'];
$app->offsetUnset('cache');
$check(StorefrontGuard::orderAllowed(), 'Without a cache store orders are allowed');
$app->instance('cache', $cache);

echo "$checks storefront hardening checks passed (isolated SQLite; no site database)\n";
