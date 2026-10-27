<?php
// Piece cart and order JSON of a checkout; tests/standalone/fixtures/piece-cart-master.json holds the output of master.
// Usage: php tests/standalone/fixtures/piece-cart-capture.php <package-root>
$root = $argv[1];
if (!function_exists('config')) {
    function config($key = null, $default = null) { return app('config')->get($key, $default); }
}
require $root . '/tests/standalone/fixtures/checkout-harness.php';

use Illuminate\Config\Repository;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sProduct;

$app->instance('config', new Repository($app['config']->getAttributes()));
$shop->config['basic.deliveries_on'] = 0;
$shop->products = [
    1 => new FakeProduct(1, 'PIECE-1', 12.5, sProduct::MODE_SIMPLE, '4006381333931'),
    2 => new FakeProduct(2, 'PIECE-2', 3.335, sProduct::MODE_SERVICE),
    3 => new FakeProduct(3, 'PIECE-3', 0.1),
];
$cart = (new Seiger\sCommerce\Cart\sCart());
$_SESSION['sCart'] = [1 => [0 => 3], 2 => [0 => 2, 5 => 1], 3 => [0 => 7]];
$mini = (new Seiger\sCommerce\Cart\sCart())->getMiniCart();
$result = $checkout($mini['items'], ['cost' => $mini['totalSum']])->processOrder();
$order = sOrder::query()->find($result['order']->id);
echo json_encode(['cart' => $mini, 'order_products' => $order->products, 'order_cost' => (string)$order->cost], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
