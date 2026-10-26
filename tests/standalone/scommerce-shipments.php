<?php

// php tests/standalone/scommerce-shipments.php
// Isolated shipment and tracking checks: in-memory SQLite, a fake manager, no CMS/site bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function evo() { return Illuminate\Container\Container::getInstance(); }
if (!function_exists('config')) {
    function config($key = null, $default = null) { return app('config')->get($key, $default); }
}
require __DIR__ . '/bootstrap.php';

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Seiger\sCommerce\Controllers\OrderShipmentController;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Models\sOrderShipment;
use Seiger\sCommerce\Payment\CashPayment;
use Seiger\sCommerce\Payment\PaymentMethodResolver;
use Seiger\sCommerce\Services\OrderPaymentAction;
use Seiger\sCommerce\Services\OrderShipments;
use Seiger\sCommerce\Shipping\ShipmentConflictException;
use Seiger\sCommerce\Shipping\ShipmentData;
use Seiger\sCommerce\sCommerce;

$app = new class extends Container {
    public array $permissions = ['exec_module'];
    public function getConfig($key, $default = null) { return $default; }
    public function getLocale() { return 'en'; }
    public function getLoginUserID($context) { return 42; }
    public function hasPermission($permission) { return in_array($permission, $this->permissions, true); }
};
Container::setInstance($app);
Facade::setFacadeApplication($app);
$app->instance('log', new class {
    public array $records = [];
    public function channel($name = null) { return $this; }
    public function __call($level, $arguments) { $this->records[] = [$level, $arguments[0], $arguments[1] ?? []]; }
});
$app->instance('config', new Repository());
$package = dirname(__DIR__, 2);
$loader = new ArrayLoader();
$loader->addMessages('en', 'global', require $package . '/lang/en/global.php', 'sCommerce');
$app->instance('translator', new Translator($loader, 'en'));
$app->instance('db.transactions', new DatabaseTransactionsManager());
$db = new Capsule($app);
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
$db->setEventDispatcher(new Dispatcher($app));
$db->setAsGlobal();
$db->bootEloquent();
$app->instance('db', $db->getDatabaseManager());
$app->instance('db.schema', $db->schema());
$schema = $db->schema();
$schema->create('s_orders', static function ($table): void {
    $table->bigIncrements('id');
    $table->string('uuid')->nullable();
    $table->string('identifier')->nullable();
    $table->decimal('cost', 12, 5)->default(0);
    $table->char('currency', 3)->default('EUR');
    $table->integer('status')->default(1);
    $table->integer('payment_status')->default(0);
    $table->text('products')->nullable();
    $table->text('delivery_info')->nullable();
    $table->text('payment_info')->nullable();
    $table->text('history')->nullable();
    $table->timestamps();
    $table->timestamp('deleted_at')->nullable();
});
$schema->create('s_payment_methods', static function ($table): void {
    $table->increments('id');
    $table->string('uuid')->nullable();
    $table->string('name');
    $table->string('class');
    $table->string('identifier')->default('');
    $table->boolean('active')->default(true);
    $table->integer('position')->default(0);
    $table->text('title')->nullable();
    $table->text('description')->nullable();
    $table->text('settings')->nullable();
    $table->text('credentials')->nullable();
    $table->timestamps();
});
(require $package . '/database/migrations/2026_03_01_000000_create_order_payments_table.php')->up();
(require $package . '/database/migrations/2026_10_20_000002_create_payment_operations_table.php')->up();
(require $package . '/database/migrations/2026_10_20_000005_add_meta_to_orders.php')->up();
$migration = require $package . '/database/migrations/2026_10_20_000007_create_order_shipments_table.php';
$migration->up();
$migration->up();
// Cash on delivery settles the balance when the order becomes DELIVERED.
$db->table('s_payment_methods')->insert(['name' => 'cash', 'class' => CashPayment::class, 'identifier' => '', 'settings' => json_encode(['settle_on_statuses' => ['7']])]);
PaymentMethodResolver::flush();

$checks = 0;
$check = static function (bool $passed, string $label) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException($label);
    }
    $checks++;
};
$expect = static function (callable $callback, string $class, string $label) use ($check): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        $check($exception instanceof $class, $label . ' (' . $exception::class . ': ' . $exception->getMessage() . ')');
        return;
    }
    $check(false, $label . ' (no exception)');
};
$events = [];
Event::listen('sCommerce.*', static function (string $name, array $payload) use (&$events): void {
    $events[] = [$name, $payload[0]];
});
$named = static function (string $name) use (&$events): array {
    return array_values(array_map(static fn ($event) => $event[1], array_filter($events, static fn ($event) => $event[0] === $name)));
};
$sync = static function (int $on, string $policy = 'on_complete') use ($app): void {
    $app['config']->set('seiger.settings.sCommerce.shipping', ['sync_order_status' => $on, 'partial_policy' => $policy]);
};
$lines = [
    ['id' => 5, 'product_id' => 5, 'option_id' => 0, 'line_key' => '5:0', 'sku' => 'APL', 'product_sku' => 'APL', 'title' => 'Apples', 'quantity' => 2, 'requires_shipping' => true],
    ['id' => 6, 'sku' => 'BOX', 'title' => 'Box (legacy line)', 'quantity' => 1],
    ['id' => 7, 'product_id' => 7, 'option_id' => 0, 'line_key' => '7:0', 'sku' => 'SET', 'title' => 'Setup service', 'quantity' => 1, 'requires_shipping' => false],
];
$newOrder = static function (?array $products = null, int $status = sOrder::ORDER_STATUS_CONFIRMED) use ($lines): sOrder {
    $order = new sOrder();
    $order->forceFill(['identifier' => bin2hex(random_bytes(8)), 'cost' => 100, 'currency' => 'EUR', 'status' => $status, 'payment_status' => 0,
        'products' => $products ?? $lines, 'delivery_info' => ['method' => 'courier'], 'payment_info' => ['method' => 'cash']]);
    $order->save();
    return $order;
};
$service = new OrderShipments();
$parcel = static fn (string $ref, string $status, ?array $items, array $extra = []) => new ShipmentData(...array_merge(
    ['status' => $status, 'carrier' => 'Packeta', 'trackingNumber' => 'Z' . $ref, 'trackingUrl' => 'https://tracking.example/Z' . $ref,
        'items' => $items, 'source' => 'erp', 'externalRef' => $ref], $extra));
$captured = static fn (sOrder $order) => sOrderPayment::query()->where('order_id', $order->id)->where('status', 'captured')->count();
$warnings = static fn (string $message) => count(array_filter($app['log']->records, static fn ($record) => $record[0] === 'warning' && $record[1] === $message));
$sync(0);

// 1. Upserting the same (source, externalRef) twice stores one row and one created event.
$order = $newOrder();
$events = [];
$first = $service->upsert($order, $parcel('R1', 'pending', [['line' => 0, 'quantity' => 1]]));
$second = $service->upsert($order, $parcel('R1', 'pending', [['line' => 0, 'quantity' => 1]]));
$updates = $named('sCommerce.ShipmentUpdated');
$check($first->id === $second->id && sOrderShipment::query()->count() === 1 && $first->wasRecentlyCreated && !$second->wasRecentlyCreated, 'One row for a repeated reference');
$check(count($updates) === 1 && $updates[0]['created'] === true && $updates[0]['oldStatus'] === null && $updates[0]['newStatus'] === 'pending', 'One ShipmentUpdated(created=true)');
$payload = $updates[0];
$check($payload['shipmentUuid'] === $first->uuid && strlen($first->uuid) === 36 && $payload['orderId'] === (int)$order->id && $payload['orderUuid'] === $order->uuid
    && $payload['carrier'] === 'packeta' && $payload['trackingNumber'] === 'ZR1' && $payload['trackingUrl'] === 'https://tracking.example/ZR1'
    && $payload['shipmentSource'] === 'erp' && $payload['externalRef'] === 'R1' && $payload['source'] === 'system' && $payload['environment'] === 'live'
    && $payload['items'] === [['line' => 0, 'line_key' => '5:0', 'product_id' => 5, 'sku' => 'APL', 'quantity' => 1]]
    && $payload['fullyShipped'] === false && $payload['fullyDelivered'] === false && $payload['shipment'] instanceof sOrderShipment
    && preg_match('/\A[0-9a-f-]{36}\z/', $payload['eventId']) && str_ends_with($payload['occurredAt'], 'Z') && array_key_exists('integrationActor', $payload), 'Event payload');
$check($service->findByExternalRef('ERP', 'R1')?->id === $first->id && $service->findByExternalRef('erp', 'R2') === null && $order->shipments()->count() === 1, 'Lookup by reference and relation');
$expect(fn () => $service->upsert($newOrder(), $parcel('R1', 'pending', null)), ShipmentConflictException::class, 'A reference of another order is a conflict');

// 2. pending → shipped sets shipped_at; moves back are ignored with a warning.
$events = [];
$shipment = $service->setStatus($first, 'shipped');
$updates = $named('sCommerce.ShipmentUpdated');
$check($shipment->status === 'shipped' && $shipment->shipped_at !== null && $shipment->delivered_at === null, 'shipped_at set');
$check(count($updates) === 1 && $updates[0]['created'] === false && $updates[0]['oldStatus'] === 'pending' && $updates[0]['newStatus'] === 'shipped' && $updates[0]['shippedAt'] !== null, 'Status event old/new');
$events = [];
$shipment = $service->setStatus($shipment, 'pending');
$check($shipment->fresh()->status === 'shipped' && $named('sCommerce.ShipmentUpdated') === [] && $warnings('sCommerce shipment ignored a status transition') === 1, 'shipped → pending ignored');
$delivered = $service->upsert($order, $parcel('R1', 'delivered', null, ['deliveredAt' => new DateTimeImmutable('2026-10-21 10:00:00')]));
$check($delivered->status === 'delivered' && $delivered->delivered_at->format('Y-m-d H:i') === '2026-10-21 10:00' && $delivered->items !== null, 'Explicit delivery date; null items keep the allocation');
$check($service->setStatus($delivered, 'canceled')->fresh()->status === 'delivered', 'Delivered is final');
$expect(fn () => $service->upsert($order, new ShipmentData(status: 'lost')), InvalidArgumentException::class, 'Unknown status rejected');
$expect(fn () => $service->upsert($order, $parcel('R9', 'pending', [['line' => 0, 'quantity' => 1]], ['trackingUrl' => 'javascript:alert(1)'])), InvalidArgumentException::class, 'Only http(s) tracking URLs');

// 3. Projection: quantity coverage decides SHIPPED and DELIVERED; partial delivery never settles COD.
$sync(1);
$order = $newOrder();
$a = $service->upsert($order, $parcel('A', 'shipped', [['line' => 0, 'quantity' => 1]]));
$check((int)$order->fresh()->status === sOrder::ORDER_STATUS_CONFIRMED, 'One partial parcel does not ship the order');
$a = $service->setStatus($a, 'delivered');
$check((int)$order->fresh()->status === sOrder::ORDER_STATUS_CONFIRMED && $captured($order) === 0, 'A delivered parcel with unallocated goods: no DELIVERED, no COD capture');
$expect(fn () => $service->upsert($order, $parcel('X', 'pending', [['line' => 0, 'quantity' => 2]])), ShipmentConflictException::class, 'Over-allocation of a line rejected');
$expect(fn () => $service->upsert($order, $parcel('X', 'pending', null)), ShipmentConflictException::class, 'Whole-order shipment cannot overlap parcels');
$expect(fn () => $service->upsert($order, $parcel('X', 'pending', [['line' => 2, 'quantity' => 1]])), InvalidArgumentException::class, 'Service lines are not shipped');
$expect(fn () => $service->upsert($order, $parcel('X', 'pending', [['line' => 0, 'quantity' => 0]])), InvalidArgumentException::class, 'Positive quantities only');
$expect(fn () => $service->upsert($order, $parcel('X', 'pending', [['line' => 1, 'quantity' => 1, 'product_id' => 5]])), InvalidArgumentException::class, 'Line identity is checked');
$expect(fn () => $service->upsert($order, $parcel('X', 'pending', [['line' => 0, 'line_key' => '6:0', 'quantity' => 1]])), InvalidArgumentException::class, 'line_key must match the line index');
$events = [];
$b = $service->upsert($order, $parcel('B', 'shipped', [['line_key' => '5:0', 'quantity' => 1], ['line' => 1, 'sku' => 'BOX', 'quantity' => 1]]));
$check((int)$order->fresh()->status === sOrder::ORDER_STATUS_SHIPPED && $named('sCommerce.ShipmentUpdated')[0]['fullyShipped'] === true
    && $named('sCommerce.ShipmentUpdated')[0]['fullyDelivered'] === false, 'Full sent coverage ships the order');
$status = $named('sCommerce.OrderStatusChanged')[0] ?? null;
$check($status && $status['newStatus'] === sOrder::ORDER_STATUS_SHIPPED && array_slice($order->fresh()->history, -1)[0]['user_id'] === 0, 'Status changed by the system user');
$names = array_column($events, 0);
$check(array_search('sCommerce.ShipmentUpdated', $names, true) < array_search('sCommerce.OrderStatusChanged', $names, true), 'Shipment event precedes the order status event');
// Voiding a sent parcel releases its allocation without moving the order back.
$a = $service->setStatus($a->fresh(), 'canceled');
$check($a->status === 'delivered', 'Delivered parcel cannot be voided');
$b = $service->setStatus($b, 'canceled');
$check($b->status === 'canceled' && $b->metadata['voided_after'] === 'shipped' && $warnings('sCommerce shipment voided after it was sent') === 1
    && (int)$order->fresh()->status === sOrder::ORDER_STATUS_SHIPPED, 'Void after sending is a logged correction; no regression');
$check(OrderShipments::coverage($order->fresh())['fullyShipped'] === false, 'Canceled allocations do not count');
// Duplicate line indices in one shipment are summed; the last parcel delivers the order and settles COD.
$c = $service->upsert($order, $parcel('C', 'in_transit', [['line' => 0, 'quantity' => 1], ['line' => 1, 'quantity' => 1]]));
$expect(fn () => $service->upsert($order, $parcel('D', 'pending', [['line' => 1, 'quantity' => 1], ['line' => 1, 'quantity' => 1]])), ShipmentConflictException::class, 'Duplicate line items are summed');
$check($c->shipped_at !== null && (int)$order->fresh()->status === sOrder::ORDER_STATUS_SHIPPED, 'In transit counts as sent');
$events = [];
$service->setStatus($c, 'delivered');
$fresh = $order->fresh();
$check((int)$fresh->status === sOrder::ORDER_STATUS_DELIVERED && $captured($order) === 1 && (int)$fresh->payment_status === sOrder::PAYMENT_STATUS_PAID, 'Full delivered coverage delivers the order and settles COD');
$check($named('sCommerce.ShipmentUpdated')[0]['fullyDelivered'] === true, 'fullyDelivered in the event');
// Same product on two lines: identified by index; an ambiguous line_key needs the index.
$twice = [['id' => 5, 'line_key' => '5:0', 'sku' => 'APL', 'quantity' => 1], ['id' => 5, 'line_key' => '5:0', 'sku' => 'APL', 'quantity' => 1]];
$order = $newOrder($twice);
$expect(fn () => $service->upsert($order, $parcel('T1', 'shipped', [['line_key' => '5:0', 'quantity' => 1]])), InvalidArgumentException::class, 'Ambiguous line_key rejected');
$service->upsert($order, $parcel('T1', 'shipped', [['line' => 0, 'sku' => 'APL', 'quantity' => 1]]));
$check((int)$order->fresh()->status === sOrder::ORDER_STATUS_CONFIRMED, 'Duplicate SKU on another line is not covered');
$service->upsert($order, $parcel('T2', 'delivered', [['line' => 1, 'quantity' => 1]]));
$check((int)$order->fresh()->status === sOrder::ORDER_STATUS_SHIPPED, 'One line delivered, the other shipped: SHIPPED');
// Whole-order shipment; services-only orders are never delivered by an empty set.
$order = $newOrder();
$service->upsert($order, $parcel('W', 'delivered', null));
$check((int)$order->fresh()->status === sOrder::ORDER_STATUS_DELIVERED, 'items=null covers the whole order');
$order = $newOrder([$lines[2]]);
$expect(fn () => $service->upsert($order, $parcel('S', 'delivered', null)), InvalidArgumentException::class, 'Services-only order has nothing to ship');
$check(OrderShipments::coverage($order) === ['fullyShipped' => false, 'fullyDelivered' => false, 'anySent' => false], 'Services-only order is not covered');
// Optional on_first policy ships on the first parcel but never delivers early.
$sync(1, 'on_first');
$order = $newOrder();
$service->upsert($order, $parcel('F1', 'delivered', [['line' => 0, 'quantity' => 2]]));
$check((int)$order->fresh()->status === sOrder::ORDER_STATUS_SHIPPED && $captured($order) === 0, 'on_first: SHIPPED, not DELIVERED');
// With the projection off only an external projector changes the status.
$sync(0);
$order = $newOrder();
$service->upsert($order, $parcel('OFF', 'delivered', null));
$check((int)$order->fresh()->status === sOrder::ORDER_STATUS_CONFIRMED && $order->fresh()->delivery_info['tracking'][0]['status'] === 'delivered', 'Projection off: status kept, tracking written');

// 4. Orders at COMPLETED or later are never moved.
$sync(1);
$order = $newOrder(null, sOrder::ORDER_STATUS_COMPLETED);
$service->upsert($order, $parcel('DONE', 'delivered', null));
$check((int)$order->fresh()->status === sOrder::ORDER_STATUS_COMPLETED, 'COMPLETED order untouched');
$order = $newOrder(null, sOrder::ORDER_STATUS_CANCELED);
$service->upsert($order, $parcel('CXL', 'delivered', null));
$check((int)$order->fresh()->status === sOrder::ORDER_STATUS_CANCELED, 'CANCELED order untouched');

// 5. delivery_info.tracking reflects the latest state and keeps other keys.
$order = $newOrder();
$t = $service->upsert($order, $parcel('TR', 'ready', [['line' => 0, 'quantity' => 2]], ['trackingUrl' => null]));
$service->upsert($order, new ShipmentData(status: 'pending', source: 'erp', externalRef: 'NO-NUMBER', items: [['line' => 1, 'quantity' => 1]]));
$tracking = $order->fresh()->delivery_info['tracking'];
$check($order->fresh()->delivery_info['method'] === 'courier' && count($tracking) === 1 && $tracking[0]['carrier'] === 'packeta' && $tracking[0]['number'] === 'ZTR'
    && $tracking[0]['url'] === null && $tracking[0]['status'] === 'ready' && preg_match('/\A\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\z/', $tracking[0]['updated_at']), 'Tracking projection structure');
$service->upsert($order, $parcel('TR', 'in_transit', null, ['trackingNumber' => 'NEW1', 'trackingUrl' => 'https://t.example/NEW1']));
$tracking = $order->fresh()->delivery_info['tracking'];
$check($tracking[0]['number'] === 'NEW1' && $tracking[0]['url'] === 'https://t.example/NEW1' && $tracking[0]['status'] === 'in_transit', 'Tracking follows changes');
$service->setStatus($t->fresh(), 'canceled');
$check($order->fresh()->delivery_info['tracking'] === [], 'Canceled shipments leave the projection');

// 6. A rolled-back write gives no event and no row; a unique-reference race is recovered.
$order = $newOrder();
$events = [];
try {
    DB::transaction(static function () use ($service, $order, $parcel): void {
        $service->upsert($order, $parcel('RB', 'shipped', null));
        throw new RuntimeException('rollback');
    });
} catch (RuntimeException) {
}
$check($named('sCommerce.ShipmentUpdated') === [] && $service->findByExternalRef('erp', 'RB') === null && !isset($order->fresh()->delivery_info['tracking']), 'Rollback: no event, no row, no projection');
$raced = false;
// Another writer inserts the reference right after this writer's lookup missed it.
DB::connection()->listen(static function ($query) use (&$raced, $order): void {
    if (!$raced && str_starts_with($query->sql, 'select') && in_array('RACE', $query->bindings, true)) {
        $raced = true;
        DB::table('s_order_shipments')->insert(['uuid' => 'race-uuid', 'order_id' => $order->id, 'sequence' => 9, 'status' => 'pending', 'source' => 'erp',
            'external_ref' => 'RACE', 'items' => json_encode([['line' => 0, 'quantity' => 1]]), 'created_at' => now(), 'updated_at' => now()]);
    }
});
$won = $service->upsert($order, $parcel('RACE', 'shipped', [['line' => 0, 'quantity' => 1]]));
$check($raced && $won->uuid === 'race-uuid' && $won->status === 'shipped' && sOrderShipment::query()->where('external_ref', 'RACE')->count() === 1, 'Concurrent insert of the same reference is applied to the winner');
// Events inside a scope carry its context.
$events = [];
EventScope::run('erp', 0, static fn () => $service->upsert($newOrder(), $parcel('SC', 'pending', null)), 'flow-1', 'erp-event-1', 'aDolibarr');
$payload = $named('sCommerce.ShipmentUpdated')[0];
$check($payload['source'] === 'erp' && $payload['correlationId'] === 'flow-1' && $payload['causationId'] === 'erp-event-1' && $payload['integrationActor'] === 'aDolibarr', 'Scope context in the event');

// Manager endpoint: guards, create, update by tracking number or selection, conflicts.
$_SESSION = ['mgrValidated' => 1, '_token' => 'csrf-token'];
$order = $newOrder();
$nonce = OrderPaymentAction::issueNonce((int)$order->id, 0, OrderShipmentController::NONCE_ACTION);
$call = static function (array $input, string $method = 'POST') use ($order, $nonce) {
    $request = Request::create('/', $method, $input + ['_token' => 'csrf-token', 'order' => (string)$order->id, 'nonce' => $nonce]);
    return (new OrderShipmentController())->handle($request);
};
$check($call(['status' => 'shipped'], 'GET')->getStatusCode() === 405, 'POST only');
$check($call(['status' => 'shipped', '_token' => 'wrong'])->getStatusCode() === 403, 'CSRF token required');
$check($call(['status' => 'shipped', 'nonce' => 'other'])->getStatusCode() === 403, 'Form nonce required');
$app->permissions = [];
$check($call(['status' => 'shipped'])->getStatusCode() === 403, 'exec_module required');
$app->permissions = ['exec_module'];
$check($call(['status' => 'lost'])->getStatusCode() === 422 && $call(['status' => 'shipped', 'tracking_url' => 'ftp://x'])->getStatusCode() === 422, 'Invalid input rejected');
$events = [];
$response = $call(['status' => 'shipped', 'carrier' => 'DPD', 'tracking_number' => '123', 'tracking_url' => 'https://dpd.example/123']);
$row = $order->shipments()->first();
$check($response->getStatusCode() === 200 && $response->getData(true)['success'] === true && $row && $row->source === 'manual' && $row->items === null
    && $row->carrier === 'dpd' && $row->metadata['manager_id'] === 42 && (int)$order->fresh()->status === sOrder::ORDER_STATUS_SHIPPED, 'Form creates a whole-order shipment');
$payload = $named('sCommerce.ShipmentUpdated')[0];
$check($payload['source'] === OrderShipments::SOURCE_MANAGER && $payload['actorId'] === 42, 'Manager scope on the event');
$check($call(['status' => 'delivered', 'tracking_number' => '123'])->getStatusCode() === 200 && $order->shipments()->count() === 1
    && $row->fresh()->status === 'delivered' && $row->fresh()->carrier === 'dpd' && (int)$order->fresh()->status === sOrder::ORDER_STATUS_DELIVERED, 'Same tracking number updates the shipment');
$check($call(['status' => 'shipped', 'tracking_number' => '456'])->getStatusCode() === 409, 'Overlapping new shipment is a conflict');
$check($call(['status' => 'delivered', 'shipment' => '999'])->getStatusCode() === 422, 'Unknown shipment rejected');
$check($call(['status' => 'delivered', 'shipment' => (string)$row->id, 'tracking_url' => 'https://dpd.example/x'])->getStatusCode() === 200
    && $row->fresh()->tracking_url === 'https://dpd.example/x', 'Selected shipment updated');
$module = file_get_contents($package . '/module/sCommerceModule.php');
$check(str_contains($module, 'case "orderShipmentSave":') && str_contains($module, 'OrderShipmentController::NONCE_ACTION'), 'Module routes the form');

// 7. The order screen renders the shipments.
$app->instance('sCommerce', new class {
    public function moduleUrl() { return 'index.php?a=112&id=test'; }
});
class_alias(Seiger\sCommerce\Facades\sCommerce::class, 'sCommerce');
$files = new Filesystem();
$cache = sys_get_temp_dir() . '/scommerce-shipments-' . bin2hex(random_bytes(8));
$files->makeDirectory($cache);
try {
    $compiler = new BladeCompiler($files, $cache);
    $engines = new EngineResolver();
    $engines->register('blade', fn () => new CompilerEngine($compiler, $files));
    $finder = new FileViewFinder($files, [$package . '/views']);
    $finder->addNamespace('sCommerce', $package . '/views');
    $view = new Factory($engines, $finder, new Dispatcher($app));
    $view->setContainer($app);
    $app->instance('view', $view);
    $view->share('__env', $view);
    $order = $newOrder();
    $service->upsert($order, $parcel('V1', 'shipped', [['line' => 0, 'quantity' => 2]], ['trackingUrl' => 'https://track.example/V1?x=1&y=2']));
    $html = $view->make('sCommerce::partials.orderShipments', ['item' => $order->fresh(), 'panel' => ['rows' => $order->shipments()->get(), 'nonce' => 'abc']])->render()
        . $view->yieldPushContent('scripts.bot');
    $check(str_contains($html, 'data-shipments-table') && str_contains($html, 'Apples &times; 2') && str_contains($html, 'href="https://track.example/V1?x=1&amp;y=2"')
        && str_contains($html, '>ZV1</a>') && str_contains($html, 'Shipped') && str_contains($html, 'get=orderShipmentSave') && str_contains($html, 'data-nonce="abc"')
        && str_contains($html, 'data-shipment-save'), 'Shipments block and form rendered');
    DB::table('s_order_shipments')->where('external_ref', 'V1')->update(['tracking_url' => 'javascript:alert(1)']);
    $html = $view->make('sCommerce::partials.orderShipments', ['item' => $order->fresh(), 'panel' => ['rows' => $order->shipments()->get(), 'nonce' => null]])->render();
    $check(!str_contains($html, 'javascript:') && !str_contains($html, 'data-shipment-form'), 'Unsafe URLs are not linked; no form without permission');
} finally {
    $files->deleteDirectory($cache);
}
$check(str_contains(file_get_contents($package . '/views/orderTab.blade.php'), "@include('sCommerce::partials.orderShipments'"), 'Order tab includes the block');

// Settings: the shipping section is saved and has safe defaults.
$check(str_contains(file_get_contents($package . '/src/Controllers/sCommerceController.php'), "'products', 'shipping'];"), 'Settings form saves the shipping section');
$defaults = require $package . '/config/sCommerceSettings.php';
$check($defaults['shipping'] === ['partial_policy' => 'on_complete', 'sync_order_status' => 0], 'Projection is off by default');
$settings = file_get_contents($package . '/views/partials/settingsOrdersConfiguration.blade.php');
$check(str_contains($settings, 'name="shipping__sync_order_status"') && str_contains($settings, 'name="shipping__partial_policy"'), 'Settings fields');

// 8. Language keys exist in all six locales.
$keys = ['shipments', 'carrier', 'tracking_number', 'tracking_url', 'add_tracking', 'shipment_new', 'shipment_save', 'shipment_saved', 'shipment_invalid',
    'shipment_conflict', 'shipment_items', 'shipment_whole_order', 'shipment_shipped_at', 'shipment_delivered_at', 'shipment_source',
    'shipment_fully_shipped', 'shipment_fully_delivered', 'shipping_sync_order_status', 'shipping_sync_order_status_help', 'shipping_partial_policy',
    'shipping_partial_policy_help', 'shipping_partial_on_complete', 'shipping_partial_on_first'];
foreach (array_keys(sOrderShipment::listStatuses()) as $status) {
    $keys[] = 'shipment_status_' . $status;
}
foreach (['uk', 'en', 'ru', 'de', 'fr', 'pl'] as $locale) {
    $labels = require $package . '/lang/' . $locale . '/global.php';
    foreach ($keys as $key) {
        $check(isset($labels[$key]) && trim($labels[$key]) !== '', "Translation $locale.$key exists");
    }
}
$check(sCommerce::supportsIntegration('shipments'), 'Feature key shipments');
$check(!array_filter($app['log']->records, static fn ($record) => $record[0] === 'error'), 'No errors logged');

echo "$checks shipment checks passed\n";
