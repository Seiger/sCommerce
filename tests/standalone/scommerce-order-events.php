<?php

// php tests/standalone/scommerce-order-events.php
// Isolated checks of sCommerce.Order* domain events: in-memory SQLite, fake manager, no CMS/site bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function evo() { return Illuminate\Container\Container::getInstance(); }
require __DIR__ . '/bootstrap.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Seiger\sCommerce\Controllers\OrderBulkMenuController;
use Seiger\sCommerce\Controllers\OrderBulkStatusController;
use Seiger\sCommerce\Events\DomainEventRecorder;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Events\OrderCreated;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\sCommerce;

$app = new class extends Container {
    public array $legacyEvents = [];
    public function getLoginUserID($context) { return $context === 'mgr' ? 42 : 0; }
    public function hasPermission($permission) { return $permission === 'exec_module'; }
    public function getConfig($key, $default = null) { return $default; }
    public function invokeEvent($name, $params) { $this->legacyEvents[] = [$name, $params['item']->id]; }
};
Container::setInstance($app);
Facade::setFacadeApplication($app);
$app->instance('log', new class {
    public array $errors = [];
    public function channel($name = null) { return $this; }
    public function error($message, $context = []) { $this->errors[] = [$message, $context]; }
    public function info($message, $context = []) {}
});
$package = dirname(__DIR__, 2);
$loader = new ArrayLoader();
$loader->addMessages('en', 'global', require $package . '/lang/en/global.php', 'sCommerce');
$app->instance('translator', new Translator($loader, 'en'));
// Bound before the connection exists, so DB::afterCommit() defers to commit as in Evolution CMS.
$app->instance('db.transactions', new DatabaseTransactionsManager());
$db = new Capsule($app);
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->setEventDispatcher(new Dispatcher($app));
$db->setAsGlobal();
$db->bootEloquent();
$app->instance('db', $db->getDatabaseManager());
$db->schema()->create('s_orders', static function ($table): void {
    $table->increments('id');
    $table->string('uuid')->nullable()->unique();
    $table->string('identifier')->nullable();
    $table->string('reference')->nullable();
    $table->integer('user_id')->default(0);
    foreach (['user_info', 'delivery_info', 'payment_info', 'products', 'history', 'manager_notes'] as $column) {
        $table->text($column)->nullable();
    }
    $table->decimal('cost', 12, 5)->default(0);
    $table->string('currency', 3)->default('USD');
    $table->integer('status')->default(1);
    $table->integer('payment_status')->default(0);
    $table->boolean('is_quick')->default(false);
    $table->string('domain')->default('default');
    $table->timestamps();
    $table->timestamp('deleted_at')->nullable();
});

$checks = 0;
$check = static function (bool $passed, string $label) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException($label);
    }
    $checks++;
};
$events = [];
$levels = [];
Event::listen('sCommerce.*', static function (string $name, array $payload) use (&$events, &$levels, $db): void {
    $levels[] = $db->getConnection()->transactionLevel();
    $events[] = [$name, $payload[0]];
});
$names = static function () use (&$events): array {
    return array_map(static fn (array $event) => $event[0], $events);
};
$reset = static function () use (&$events, &$levels): void {
    $events = [];
    $levels = [];
};
$newOrder = static function (array $attributes = []): sOrder {
    $order = new sOrder();
    $order->forceFill($attributes + [
        'identifier' => bin2hex(random_bytes(8)), 'user_info' => ['first_name' => 'Test'], 'products' => [],
        'payment_info' => ['method' => 'cash'], 'cost' => 25, 'currency' => 'EUR', 'status' => 1, 'payment_status' => 0,
        'history' => [], 'domain' => 'default',
    ]);
    $order->save();
    return $order;
};

// 1. Outside any scope: one OrderCreated with a generated UUID and system context.
$order = $newOrder();
$check($names() === ['sCommerce.OrderCreated'], 'Saving a new order dispatches exactly one OrderCreated');
$payload = $events[0][1];
$check(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $payload['orderUuid']) === 1, 'New order receives a UUID v4');
$check($payload['orderUuid'] === $db->table('s_orders')->where('id', $order->id)->value('uuid'), 'Payload UUID is persisted');
$check($payload['source'] === 'system' && $payload['actorId'] === 0 && $payload['correlationId'] === null, 'Default system context');
$check($payload['event'] instanceof OrderCreated && $payload['orderId'] === $order->id && $payload['status'] === 1
    && $payload['paymentStatus'] === 0 && $payload['cost'] === 25.0 && $payload['currency'] === 'EUR'
    && $payload['isQuick'] === false && $payload['siteKey'] === 'default', 'Payload summarizes the order');
$expectedKeys = ['event', 'order', 'orderId', 'orderUuid', 'reference', 'status', 'paymentStatus', 'cost', 'currency', 'isQuick',
    'siteKey', 'environment', 'source', 'actorId', 'correlationId', 'causationId', 'integrationActor', 'eventId', 'occurredAt'];
$check(array_keys($payload) === $expectedKeys, 'Documented payload keys');
$check($payload['environment'] === 'live', 'Orders without a checkout context are live');
$check(preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/', $payload['occurredAt']) === 1, 'occurredAt is UTC with milliseconds');
$check($payload['order'] instanceof sOrder && $payload['order']->id === $order->id && $payload['order'] !== $order, 'Order is a detached copy');
$payload['order']->status = 13;
$check($events[0][1]['event']->toArray()['order']->status === 1, 'Mutating the copy does not change the event snapshot');
$withUuid = $newOrder(['uuid' => 'kept-uuid']);
$check($withUuid->uuid === 'kept-uuid', 'An explicit UUID is not replaced');
$reset();

// 2. Rollback dispatches nothing.
try {
    DB::transaction(static function () use ($newOrder, $order): void {
        $newOrder();
        $order->status = 2;
        $order->save();
        throw new RuntimeException('rollback');
    });
} catch (RuntimeException) {
}
$check($events === [], 'No event for rolled-back writes');
$order->refresh();

// 3. Inside a transaction nothing is dispatched until commit.
DB::transaction(static function () use ($order, &$events, $check): void {
    $order->status = 2;
    $order->save();
    $check($events === [], 'Nothing dispatched before commit');
});
$check($names() === ['sCommerce.OrderUpdated', 'sCommerce.OrderStatusChanged'] && $levels === [0, 0], 'Dispatched after commit outside the transaction');
$reset();
$order->save();
$check($events === [], 'Saving without changes dispatches nothing');

// 4. One scope: create, reference and payment_info saves produce one final OrderCreated.
$scope = EventScope::begin('storefront', 7, 'flow-1');
$created = null;
DB::transaction(static function () use ($newOrder, &$created): void {
    $created = $newOrder();
    $created->reference = '100001';
    $created->save();
});
$created->payment_info = ['method' => 'cash', 'invoice' => 'INV-1'];
$created->history = [['payment_status' => 1]];
$created->save();
$check($events === [], 'Scope buffers committed events');
$scope->end();
$scope->end();
$check($names() === ['sCommerce.OrderCreated'], 'Created + updates coalesce into one OrderCreated');
$payload = $events[0][1];
$check($payload['reference'] === '100001' && $payload['order']->payment_info === ['method' => 'cash', 'invoice' => 'INV-1'], 'OrderCreated carries the final snapshot');
$check($payload['source'] === 'storefront' && $payload['actorId'] === 7 && $payload['correlationId'] === 'flow-1', 'Scope context captured');
$check(!EventScope::active() && EventScope::current()['source'] === 'system', 'Context cleared after the scope');
$reset();

// 5. Cancellation: OrderUpdated, OrderStatusChanged and OrderCancelled in order.
$order = $newOrder();
$reset();
$order->status = sOrder::ORDER_STATUS_CANCELED;
$order->save();
$check($names() === ['sCommerce.OrderUpdated', 'sCommerce.OrderStatusChanged', 'sCommerce.OrderCancelled'], 'Cancellation event order');
$check(array_keys($events[0][1]['changes']) === ['status'] && $events[0][1]['changes']['status']['new'] === 9, 'Changes contain status without updated_at');
$status = $events[1][1];
$check($status['oldStatus'] === 1 && $status['newStatus'] === 9 && $status['oldStatusKey'] === 'new' && $status['newStatusKey'] === 'canceled', 'Status transition and keys');
$check($events[2][1]['previousStatus'] === 1, 'OrderCancelled carries the previous status');
$reset();

// 6. Payment status projection.
$order->payment_status = sOrder::PAYMENT_STATUS_PAID;
$order->save();
$check($names() === ['sCommerce.OrderUpdated', 'sCommerce.OrderPaymentStatusChanged'], 'Payment status change events');
$payment = $events[1][1];
$check($payment['oldPaymentStatus'] === 0 && $payment['newPaymentStatus'] === 3
    && $payment['oldPaymentStatusKey'] === 'pending' && $payment['newPaymentStatusKey'] === 'paid', 'Payment status keys');
$reset();

// 7. Coalescing inside one scope.
$order = $newOrder();
$reset();
EventScope::run('manager', 5, static function () use ($order): void {
    foreach ([2, 3] as $status) {
        $order->status = $status;
        $order->save();
    }
});
$check($names() === ['sCommerce.OrderUpdated', 'sCommerce.OrderStatusChanged'], 'Repeated updates coalesce per type');
$check($events[0][1]['changes']['status'] === ['old' => 1, 'new' => 3] && $events[1][1]['oldStatus'] === 1 && $events[1][1]['newStatus'] === 3, 'First old to last new');
$reset();
EventScope::run('manager', 5, static function () use ($order): void {
    foreach ([4, 3] as $status) {
        $order->status = $status;
        $order->save();
    }
});
$check($events === [], 'A change that returns to the old value is dropped');
EventScope::run('manager', 5, static function () use ($order): void {
    $order->status = 9;
    $order->save();
    $order->status = 3;
    $order->save();
});
$check($names() === ['sCommerce.OrderCancelled'], 'OrderCancelled is never merged away');
$reset();
$orderA = $newOrder();
$orderB = $newOrder();
$reset();
EventScope::run('manager', 5, static function () use ($orderA, $orderB): void {
    $orderB->status = 2;
    $orderB->save();
    // Recorded as payment status first; dispatched by rank.
    $orderA->payment_status = 3;
    $orderA->save();
    $orderA->status = 2;
    $orderA->save();
});
$check($names() === ['sCommerce.OrderUpdated', 'sCommerce.OrderStatusChanged', 'sCommerce.OrderUpdated', 'sCommerce.OrderStatusChanged', 'sCommerce.OrderPaymentStatusChanged'], 'Aggregates in first-seen order, events by rank');
$check($events[0][1]['orderId'] === $orderB->id && $events[2][1]['orderId'] === $orderA->id, 'Aggregates keep first-seen order');
$reset();
// Laravel runs after-commit callbacks of a nested transaction before earlier ones of the outer transaction.
EventScope::run('manager', 5, static function () use ($orderA, $orderB): void {
    DB::transaction(static function () use ($orderA, $orderB): void {
        $orderA->status = 5;
        $orderA->save();
        DB::transaction(static function () use ($orderB): void {
            $orderB->status = 5;
            $orderB->save();
        });
    });
});
$check($events[0][1]['orderId'] === $orderA->id && $events[2][1]['orderId'] === $orderB->id, 'Recording order wins over nested commit callback order');
$reset();

// 8. Deletion.
$order->delete();
$check($names() === ['sCommerce.OrderDeleted'] && $events[0][1]['forced'] === false, 'Soft delete dispatches OrderDeleted');
$reset();
$order->forceDelete();
$check($names() === ['sCommerce.OrderDeleted'] && $events[0][1]['forced'] === true, 'Force delete is marked forced');
$reset();

// 9. A failing listener is logged and later events are still dispatched.
$order = $newOrder();
$reset();
$failing = static function (): void { throw new RuntimeException('listener failure'); };
Event::listen('sCommerce.OrderUpdated', $failing);
$order->status = 2;
$order->save();
Event::forget('sCommerce.OrderUpdated');
$check(in_array('sCommerce.OrderStatusChanged', $names(), true), 'Next event dispatched after a listener failure');
$errors = $app['log']->errors;
$check(count($errors) === 1 && $errors[0][0] === 'sCommerce event listener failed' && $errors[0][1]['event'] === 'sCommerce.OrderUpdated'
    && $errors[0][1]['id'] === 'order:' . $order->id, 'Listener failure logged');
$reset();

// Scope nesting, context and transactions.
$outer = EventScope::begin('afina', 9, null, 'cause-1', 'afina');
$inner = EventScope::begin('manager');
$check(EventScope::current() === ['source' => 'manager', 'actorId' => 9, 'correlationId' => null, 'causationId' => 'cause-1', 'integrationActor' => 'afina'], 'Nested scopes inherit unspecified context');
$order->status = 3;
$order->save();
$inner->end();
$check($events === [], 'Only the outermost scope flushes');
$outer->end();
$check($events[0][1]['source'] === 'manager' && $events[0][1]['integrationActor'] === 'afina' && $events[0][1]['causationId'] === 'cause-1', 'Context captured at record time');
$reset();
$outer = EventScope::begin('manager', 5);
EventScope::begin('system');
$outer->end();
$check(!EventScope::active(), 'Ending an outer scope closes forgotten inner scopes');
$reset();
DB::transaction(static function () use ($order): void {
    EventScope::run('erp', 3, static function () use ($order): void {
        $order->status = 4;
        $order->save();
    });
});
$check(count($events) === 2 && $events[0][1]['source'] === 'erp' && $levels === [0, 0], 'Scope ending before commit publishes at commit with captured context');
$reset();
try {
    EventScope::run('manager', 5, static function () use ($newOrder, &$created): void {
        $created = $newOrder();
        DB::transaction(static function () use ($created): void {
            $created->status = 9;
            $created->save();
            throw new RuntimeException('rollback mutation');
        });
    });
} catch (RuntimeException $exception) {
    $check($exception->getMessage() === 'rollback mutation', 'run() rethrows');
}
$check($names() === ['sCommerce.OrderCreated'] && $events[0][1]['order']->status === 1, 'Committed creation is published, rolled-back mutation is not');
$check(!EventScope::active(), 'run() closes the scope after an exception');
$reset();
foreach ([['', 0], ['manager', 0, str_repeat('a', 65)], ['manager', 0, null, '']] as $arguments) {
    try {
        EventScope::begin(...$arguments);
        $check(false, 'Invalid scope context rejected');
    } catch (InvalidArgumentException) {
        $check(!EventScope::active(), 'Invalid scope context rejected');
    }
}
$check(DomainEventRecorder::instance() === DomainEventRecorder::instance(), 'Recorder is a shared singleton');
$check(sOrder::orderStatusKey(5) === 'ready_for_shipment' && sOrder::paymentStatusKey(11) === 'authorized' && sOrder::orderStatusKey(99) === 'unknown', 'Untranslated status keys');
$check(sOrder::listOrderStatuses()[9] === __('sCommerce::global.order_status_canceled'), 'Translated status labels unchanged');

// 10. Bulk controllers: one event per changed order with manager context; legacy hook unchanged.
$_SESSION = ['mgrValidated' => true, '_token' => 'test-token'];
$bulkA = $newOrder();
$bulkB = $newOrder(['status' => 2]);
$reset();
$response = (new OrderBulkStatusController())->handle(Request::create('/', 'POST', ['ids' => [$bulkA->id, $bulkB->id], 'status' => 2, '_token' => 'test-token']));
$check($response->getStatusCode() === 200 && $response->getData(true)['updated'] === 1, 'Bulk status succeeds');
$statusEvents = array_values(array_filter($events, static fn (array $event) => $event[0] === 'sCommerce.OrderStatusChanged'));
$check(count($statusEvents) === 1 && $statusEvents[0][1]['orderId'] === $bulkA->id, 'OrderStatusChanged per changed order');
$check($statusEvents[0][1]['source'] === 'manager_bulk_status' && $statusEvents[0][1]['actorId'] === 42 && $levels === [0, 0], 'Bulk source, manager actor, after commit');
$check($app->legacyEvents === [['sCommerceAfterOrderSave', $bulkA->id]], 'Legacy hook unchanged');
$reset();
$check((new OrderBulkStatusController())->handle(Request::create('/', 'POST', ['ids' => [$bulkA->id, 999], 'status' => 3, '_token' => 'test-token']))->getStatusCode() === 409
    && $events === [] && !EventScope::active(), 'Rejected batch dispatches nothing and closes its scope');
$response = (new OrderBulkMenuController())->handle(Request::create('/', 'POST', ['ids' => [$bulkB->id], 'action' => 'delete', '_token' => 'test-token']));
$check($response->getStatusCode() === 200 && $names() === ['sCommerce.OrderUpdated', 'sCommerce.OrderDeleted'], 'Bulk delete dispatches history update and deletion');
$check($events[1][1]['source'] === 'manager_bulk_menu' && $events[1][1]['forced'] === false, 'Bulk menu context');
$reset();

// 11. Capability detection.
$check(sCommerce::INTEGRATION_EVENTS_VERSION === 1, 'Integration contract version');
$check(sCommerce::supportsIntegration('order-events') && !sCommerce::supportsIntegration('unknown-feature'), 'Feature detection');
$check(sCommerce::integrationFeatures()[0] === 'order-events', 'Feature list');

// Scoped entry points and removed integration routes (source checks).
$module = file_get_contents($package . '/module/sCommerceModule.php');
$orderSave = substr($module, strpos($module, 'case "orderSave":'), strpos($module, 'case "orderDelete":') - strpos($module, 'case "orderSave":'));
$check(preg_match('/if \(\$item\) \{\s+\$scope = EventScope::begin\(\'manager\'/', $orderSave) === 1
    && preg_match('/\$scope->end\(\);\s+if \(\$saved\) \{\s+evo\(\)->invokeEvent\(\'sCommerceAfterOrderSave\', compact\(\'item\'\)\);\s+\}\s+\}/', $orderSave) === 1, 'Manager save scope closes before the legacy hook, inside the found-order branch');
$check(str_contains($orderSave, "'&i=' . (\$item->id ?? 0)"), 'Unknown order id no longer dereferences null');
$checkout = file_get_contents($package . '/src/Checkout/sCheckout.php');
foreach (["EventScope::begin('storefront'", "EventScope::run('storefront_quick'", "EventScope::run('storefront_pay'"] as $needle) {
    $check(str_contains($checkout, $needle), 'Checkout path scoped: ' . $needle);
}
$check(str_contains(file_get_contents($package . '/src/sCommerce.php'), "EventScope::run('payment_callback'"), 'Payment callback scoped');
$check(!str_contains(file_get_contents($package . '/src/Http/routes.php'), 'IntegrationActionController'), 'Dead integration routes removed');
$check(!is_file($package . '/views/scripts/integrations.blade.php'), 'Unused integration script removed');
foreach (['src', 'plugins', 'module', 'views'] as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($package . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $source = file_get_contents($file->getPathname());
        $check(!str_contains($source, 'IntegrationActionController') && !str_contains($source, 'Models\\sIntegration'), 'No dead integration reference: ' . $file->getFilename());
    }
}
foreach (['src/Http/routes.php', 'plugins/sCommercePlugin.php', 'src/Integration/ProductsListingCache.php', 'src/Integration/ImportExportCSV.php'] as $path) {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($package . '/' . $path) . ' 2>&1', $output, $exitCode);
    $check($exitCode === 0, 'php -l ' . $path);
}
echo "$checks order event checks passed (isolated SQLite; no site database)\n";
