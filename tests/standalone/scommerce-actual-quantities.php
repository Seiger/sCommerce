<?php

// php tests/standalone/scommerce-actual-quantities.php
// Isolated checks of actual quantities of measured lines (cut or weighed after ordering):
// totals, tolerance, idempotency, refusals, stock, shipments, authorization ceiling and the
// manager endpoint. In-memory SQLite, the shared checkout harness; no CMS/site bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!function_exists('config')) {
    function config($key = null, $default = null) { return app('config')->get($key, $default); }
}
require __DIR__ . '/fixtures/checkout-harness.php';

use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Seiger\sCommerce\Controllers\OrderActualQuantityController;
use Seiger\sCommerce\Interfaces\StockProviderInterface;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Services\MeasuredQuantity;
use Seiger\sCommerce\Services\MeasuredQuantityException;
use Seiger\sCommerce\Services\OrderActualQuantity;
use Seiger\sCommerce\Services\OrderCostRecalculator;
use Seiger\sCommerce\Services\OrderLineIdentity;
use Seiger\sCommerce\Services\OrderPaymentAction;
use Seiger\sCommerce\Services\OrderShipments;
use Seiger\sCommerce\Services\StockCommitter;
use Seiger\sCommerce\Shipping\ShipmentData;

$app->instance('config', new Repository($app['config']->getAttributes()));
$schema->create('s_products', static function ($table): void {
    $table->id();
    $table->string('sku')->default('');
    $table->smallInteger('mode')->default(0);
    $table->smallInteger('availability')->default(1);
    $table->integer('inventory')->default(0);
    $table->timestamps();
});
foreach (['2026_10_27_000000_add_measured_quantities_to_products', '2026_10_20_000003_add_inventory_committed_at_to_orders', '2026_10_20_000007_create_order_shipments_table'] as $name) {
    (require $package . "/database/migrations/$name.php")->up();
}
$shop->products = [
    10 => tap(new FakeProduct(10, 'CABLE', 1.2, title: 'Cable'), static function ($p) { $p->measure = 2; $p->price_unit = 'm'; $p->quantity_step = 10; $p->quantity_min = 1000; $p->quantity_tolerance = 500; }),
    11 => new FakeProduct(11, 'PIECE', 12.5),
    12 => tap(new FakeProduct(12, 'NAILS', 6.0, title: 'Nails'), static function ($p) { $p->measure = 1; $p->quantity_step = 100; $p->quantity_min = 100; $p->weight = 0; }),
];
foreach ([10 => 10000, 11 => 50, 12 => 5000] as $id => $inventory) {
    $db->table('s_products')->insert(['id' => $id, 'sku' => "P$id", 'inventory' => $inventory]);
}
$stock = static fn (int $id): int => (int)DB::table('s_products')->where('id', $id)->value('inventory');
$commitMode = static function (string $mode) use ($app): void {
    $app['config']->set('seiger.settings.sCommerce.product', ['inventory_on' => 2, 'inventory_commit' => $mode, 'inventory_release_on_cancel' => 1]);
};
$lineOf = static function (int $productId, int $quantity, array $extra = []) use ($shop): array {
    $product = $shop->products[$productId];
    $line = $extra + ['id' => $productId, 'title' => $product->title, 'price' => number_format($product->price, 2, '.', ''), 'priceAsFloat' => $product->price,
        'quantity' => $quantity, 'weight' => $product->weight] + app(OrderLineIdentity::class)->snapshot($product);
    return $line + MeasuredQuantity::lineFields(MeasuredQuantity::rules($product), $quantity, $line['price'], $product->weight);
};
$newOrder = static function (array $lines, array $attributes = []): sOrder {
    $order = new sOrder();
    $order->forceFill($attributes + ['identifier' => bin2hex(random_bytes(8)), 'currency' => 'EUR', 'status' => 1, 'payment_status' => 0, 'lang' => 'en',
        'products' => $lines, 'cost' => OrderCostRecalculator::productsCost($lines, 'EUR') + 5]);
    $order->save();
    return $order;
};
$refused = static function (callable $call, string $key, ?int $status = null): bool {
    try {
        $call();
    } catch (MeasuredQuantityException $exception) {
        return $exception->messageKey === $key && ($status === null || $exception->status === $status);
    }
    return false;
};
$service = new OrderActualQuantity();
$commitMode(StockCommitter::MODE_NONE);

// 1. Within tolerance: line, excise, weight, cost and history follow; one OrderUpdated with source and actor.
$order = $newOrder([$lineOf(10, 2530, ['retail_excise_rate' => 5]), $lineOf(11, 2)]);
$check((float)$order->cost === 3.04 + 25.0 + 5.0, 'Order cost before');
$events = [];
$updated = $service->set($order, '10:0', 2553, 'manager', 7, 'op-1');
$line = $updated->products[0];
$check($line['quantity'] === 2530 && $line['actual_quantity'] === 2553 && $line['actual_quantity_by'] === 7 && $line['actual_quantity_source'] === 'manager'
    && preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $line['actual_quantity_at']) === 1, 'Actual keys; the ordered quantity is kept');
$check($line['actual_quantity_label'] === '2.553 m' && $line['quantity_label'] === '2.53 m' && $line['sumAsFloat'] === 3.06 && $line['line_weight'] === 319
    && $line['retail_excise_amount'] === Seiger\sCommerce\Services\VatDisplay::excise(3.06, 5), 'Sum, excise and weight follow the actual quantity');
$check((float)$updated->cost === 3.06 + 25.0 + 5.0, 'Cost follows; the delivery is kept');
$entry = end($updated->history);
$check(($entry['actual_quantity_set'] ?? null) === ['line_key' => '10:0', 'old' => null, 'new' => 2553, 'ordered' => 2530, 'source' => 'manager', 'over_tolerance' => false]
    && $entry['user_id'] === 7, 'History entry with the old and new values');
$orderUpdates = array_values(array_filter($events, static fn ($event) => $event[0] === 'sCommerce.OrderUpdated'));
$check(count($orderUpdates) === 1 && $orderUpdates[0][1]['source'] === 'manager' && $orderUpdates[0][1]['actorId'] === 7, 'Exactly one OrderUpdated with source and actor');
$check($service->set($order, '10:0', 2533, 'erp', 0, 'erp-1')->products[0]['actual_quantity'] === 2533, 'A step is not required: the actual quantity is a measurement');

// 2. Tolerance: 5% over 2530 mm is 2656 mm.
$check($service->set($order, '10:0', 2656, 'manager', 7, 'op-2')->products[0]['actual_quantity'] === 2656, 'Up to the tolerance');
$check($refused(fn () => $service->set($order, '10:0', 2657, 'manager', 7, 'op-3'), 'actual_quantity_tolerance_exceeded'), 'Above the tolerance is refused');
$over = $service->set($order, '10:0', 2800, 'manager', 7, 'op-4', true);
$check($over->products[0]['actual_quantity'] === 2800 && end($over->history)['actual_quantity_set']['over_tolerance'] === true, 'Allowed when the customer agreed');
$check($service->set($order, '10:0', 1200, 'manager', 7, 'op-5')->products[0]['actual_quantity'] === 1200, 'Below is always allowed');

// 3. Idempotency by operation key.
$historyCount = count(sOrder::query()->find($order->id)->history);
$events = [];
$again = $service->set($order, '10:0', 1200, 'manager', 7, 'op-5');
$check(count($again->history) === $historyCount && $events === [], 'The same key and value returns the order unchanged');
$check($refused(fn () => $service->set($order, '10:0', 1300, 'manager', 7, 'op-5'), 'actual_quantity_conflict', 409), 'The same key with another value conflicts');
// A double submit: the second request waits for the order lock and finds the key.
$first = $service->set($order->id, '10:0', 1400, 'api', 0, 'double');
$second = $service->set($order->id, '10:0', 1400, 'api', 0, 'double');
$check(count($second->history) === count($first->history) && $second->products[0]['actual_quantity'] === 1400, 'A double submit has one effect');
for ($i = 0; $i < 60; $i++) {
    $service->set($order, '10:0', 1500 + $i, 'erp', 0, "bulk-$i");
}
$check(count(sOrder::query()->find($order->id)->meta[OrderActualQuantity::META_KEY]) === OrderActualQuantity::MAX_OPERATIONS, 'Operation keys are bounded');

// 4. Refusals.
$check($refused(fn () => $service->set($order, '11:0', 3, 'manager', 7, 'piece'), 'actual_quantity_piece'), 'Piece lines are refused');
$check($refused(fn () => $service->set($order, '99:0', 3, 'manager', 7, 'unknown'), 'actual_quantity_line'), 'Unknown lines are refused');
$check($refused(fn () => $service->set($order, '10:0', 0, 'manager', 7, 'zero'), 'measured_quantity_invalid'), 'Zero is refused; removing a line is a product edit');
$cancelled = $newOrder([$lineOf(10, 2530)], ['status' => sOrder::ORDER_STATUS_CANCELED]);
$check($refused(fn () => $service->set($cancelled, '10:0', 2540, 'manager', 7, 'c'), 'actual_quantity_order_closed', 409), 'Cancelled orders are refused');
$deleted = $newOrder([$lineOf(10, 2530)]);
$deleted->delete();
$check($refused(fn () => $service->set($deleted->id, '10:0', 2540, 'manager', 7, 'd'), 'actual_quantity_order_closed', 409), 'Deleted orders are refused');
$shipped = $newOrder([$lineOf(10, 2530), $lineOf(12, 1000)]);
(new OrderShipments())->upsert($shipped, new ShipmentData(status: 'shipped', trackingNumber: 'T1', items: [['line' => 0, 'quantity' => 2530]], source: 'manual', externalRef: 'S1'));
$check($refused(fn () => $service->set($shipped, '10:0', 2540, 'manager', 7, 's'), 'actual_quantity_shipped', 409)
    && $service->set($shipped, '12:0', 990, 'manager', 7, 's2')->products[1]['actual_quantity'] === 990, 'Lines in a sent shipment are refused, others are not');
$failed = false;
try {
    $service->set($order, '10:0', 2540, 'storefront', 7, 'x');
} catch (InvalidArgumentException $exception) {
    $failed = !$exception instanceof MeasuredQuantityException;
}
$check($failed, 'Unknown sources are refused');

// 5. Stock: a local commit follows the actual quantity in both directions.
$commitMode(StockCommitter::MODE_ORDER_CREATED);
$committed = $newOrder([$lineOf(10, 2530), $lineOf(12, 1000)]);
$check($stock(10) === 7470 && $stock(12) === 4000, 'Ordered quantities committed');
$service->set($committed, '10:0', 2553, 'manager', 7, 'stock-1');
$service->set($committed, '12:0', 950, 'erp', 0, 'stock-2');
$check($stock(10) === 7447 && $stock(12) === 4050, 'Stock follows actual quantities up and down');
$committed = sOrder::query()->find($committed->id);
$committed->status = sOrder::ORDER_STATUS_CANCELED;
$committed->save();
$check($stock(10) === 10000 && $stock(12) === 5000, 'Release restores exactly the committed total');
final class ProviderCommits implements StockProviderInterface
{
    public function available(sProduct $product, int $modificationId = 0, array $context = []): ?int { return null; }
    public function commit(sOrder $order): bool { return true; }
    public function release(sOrder $order): bool { return true; }
}
$app->instance(StockProviderInterface::class, new ProviderCommits());
$provided = $newOrder([$lineOf(10, 2530)]);
$service->set($provided, '10:0', 2600, 'manager', 7, 'provider');
$check($stock(10) === 10000, 'Provider-committed orders make no local write');
$app->offsetUnset(StockProviderInterface::class);
$commitMode(StockCommitter::MODE_NONE);
$service->set($newOrder([$lineOf(10, 2530)]), '10:0', 2600, 'manager', 7, 'none');
$check($stock(10) === 10000, 'inventory_commit = none makes no write');
$commitMode(StockCommitter::MODE_ORDER_CREATED);
DB::table('s_products')->where('id', 10)->update(['inventory' => 2540]);
$short = $newOrder([$lineOf(10, 2530)]);
$check($stock(10) === 10, 'Short stock committed');
$shortage = false;
try {
    $service->set($short, '10:0', 2600, 'manager', 7, 'short');
} catch (Seiger\sCommerce\Services\StockShortageException) {
    $shortage = !isset(sOrder::query()->find($short->id)->products[0]['actual_quantity']);
}
$check($shortage, 'A stock shortage rolls the actual quantity back');
$commitMode(StockCommitter::MODE_NONE);

// 6. Shipments cover the actual quantity.
$app['config']->set('seiger.settings.sCommerce.shipping', ['sync_order_status' => 1, 'partial_policy' => 'on_complete']);
$cut = $newOrder([$lineOf(10, 2530)]);
$cut = $service->set($cut, '10:0', 2553, 'manager', 7, 'cut');
$shipments = new OrderShipments();
$shipments->upsert($cut, new ShipmentData(status: 'delivered', trackingNumber: 'C1', items: [['line' => 0, 'quantity' => 2530]], source: 'manual', externalRef: 'C1'));
$cut = sOrder::query()->find($cut->id);
$check(!OrderShipments::coverage($cut)['fullyDelivered'] && (int)$cut->status !== sOrder::ORDER_STATUS_DELIVERED, 'The ordered quantity does not cover a longer cut');
$shipments->upsert($cut, new ShipmentData(status: 'delivered', trackingNumber: 'C2', items: [['line' => 0, 'quantity' => 23]], source: 'manual', externalRef: 'C2'));
$cut = sOrder::query()->find($cut->id);
$check(OrderShipments::coverage($cut)['fullyDelivered'] && (int)$cut->status === sOrder::ORDER_STATUS_DELIVERED, 'Full actual coverage delivers the order (and triggers offline settlement)');

// 7. Authorization ceiling: cost plus the tolerance of measured lines, half up.
$ceiling = $newOrder([$lineOf(10, 2500, ['priceAsFloat' => 1.24, 'price' => '1.24']), $lineOf(11, 1)]);
$check($ceiling->authorizationCeiling() === round((float)$ceiling->cost + 0.16, 2), 'Ceiling adds 5% of 3.10 = 0.155, half up 0.16');
$ceiling = $newOrder([$lineOf(10, 2525)]);
$check($ceiling->authorizationCeiling() === round((float)$ceiling->cost + 0.15, 2), 'Ceiling adds 5% of 3.03 = 0.1515, 0.15');
$check($newOrder([$lineOf(12, 1000), $lineOf(11, 1)])->authorizationCeiling() === 6.0 + 12.5 + 5.0, 'Without tolerance the ceiling is the cost');

// 8. Manager endpoint: POST only, CSRF, nonce, permissions and validation.
$app->mgrUser = 7;
$app->permissions = ['exec_module'];
$_SESSION = ['mgrValidated' => 1, '_token' => 'csrf-token'];
$target = $newOrder([$lineOf(10, 2530)]);
$nonce = OrderPaymentAction::issueNonce((int)$target->id, 0, OrderActualQuantityController::NONCE_ACTION);
$post = static function (array $input, string $method = 'POST') use ($target, $nonce) {
    $request = Request::create('/', $method, $input + ['_token' => 'csrf-token', 'order' => (string)$target->id, 'nonce' => $nonce, 'line_key' => '10:0']);
    return (new OrderActualQuantityController())->handle($request);
};
$check($post(['amount' => '2.553'], 'GET')->getStatusCode() === 405, 'GET is refused');
$check($post(['amount' => '2.553', '_token' => 'stale'])->getStatusCode() === 403, 'CSRF is checked');
$check($post(['amount' => '2.553', 'nonce' => 'other'])->getStatusCode() === 403, 'The nonce is checked');
$app->permissions = [];
$check($post(['amount' => '2.553'])->getStatusCode() === 403, 'exec_module is required');
$app->permissions = ['exec_module'];
$check($post(['amount' => '2,553'])->getStatusCode() === 422 && $post(['amount' => '2.5531'])->getStatusCode() === 422
    && $post(['amount' => '2.553', 'line_key' => '99:0'])->getStatusCode() === 422, 'Invalid input is 422');
$check($post(['amount' => '3'])->getStatusCode() === 422, 'Above the tolerance without the flag is 422');
$check($post(['amount' => '3', 'over_tolerance' => '1'])->getStatusCode() === 403, 'Exceeding the tolerance needs the permission');
$response = $post(['amount' => '2.553']);
$check($response->getStatusCode() === 200 && $response->getData(true)['success'] === true
    && sOrder::query()->find($target->id)->products[0]['actual_quantity'] === 2553, 'A valid amount is saved');
$saves = count(sOrder::query()->find($target->id)->history);
$check($post(['amount' => '2.553'])->getStatusCode() === 200 && count(sOrder::query()->find($target->id)->history) === $saves && $saves === 1, 'A resubmitted form records once');
$app->permissions = ['exec_module', OrderActualQuantityController::PERMISSION_OVER_TOLERANCE];
$check($post(['amount' => '3', 'over_tolerance' => '1'])->getStatusCode() === 200 && sOrder::query()->find($target->id)->products[0]['actual_quantity'] === 3000, 'With the permission and the flag');
$module = file_get_contents($package . '/module/sCommerceModule.php');
$check(str_contains($module, 'case "orderActualQuantitySave":') && str_contains($module, 'OrderActualQuantityController::NONCE_ACTION'), 'Module routes the endpoint and issues the nonce');
$permission = file_get_contents($package . '/database/migrations/2026_10_27_000001_add_scommerce_actual_quantity_permission.php');
$check(str_contains($permission, "'scommerce_order_actual_over_tolerance'"), 'Permission migration');

echo "$checks actual quantity checks passed (isolated SQLite; no site database)\n";
