<?php

// php tests/standalone/scommerce-offline-settlement.php
// Isolated offline settlement (cash on delivery, bank transfer) checks: in-memory SQLite,
// real Cash/BankInvoice methods, no CMS/site bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function evo() { return Illuminate\Container\Container::getInstance(); }
require __DIR__ . '/bootstrap.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Payment\BasePaymentMethod;
use Seiger\sCommerce\Payment\CashPayment;
use Seiger\sCommerce\Payment\LedgerEntry;
use Seiger\sCommerce\Payment\PaymentFlow;
use Seiger\sCommerce\Payment\PaymentMethodResolver;
use Seiger\sCommerce\Services\OfflineSettlement;
use Seiger\sCommerce\Services\OrderManualPayment;
use Seiger\sCommerce\sCommerce;

/** Online method without the offline marker; its settle_on_statuses must be ignored. */
class OnlineCard extends BasePaymentMethod
{
    public function getName(): string { return 'card'; }
    public function getType(): string { return 'card'; }
    public function payButton(int|string|array $data): string { return ''; }
    public function validatePayment(array $data): bool { return true; }
    public function processPayment(array $data): array|bool { return true; }
    public function defineSettings(): array { return []; }
    public function defineCredentials(): array { return []; }
}

$app = new class extends Container {
    public function getConfig($key, $default = null) { return $default; }
    public function getLocale() { return 'en'; }
};
Container::setInstance($app);
Facade::setFacadeApplication($app);
$app->instance('log', new class {
    public array $records = [];
    public function channel($name = null) { return $this; }
    public function __call($level, $arguments) { $this->records[] = [$level, $arguments[0], $arguments[1] ?? []]; }
});
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
    $table->char('currency', 3)->default('USD');
    $table->integer('status')->default(1);
    $table->integer('payment_status')->default(0);
    $table->text('history')->nullable();
    $table->text('payment_info')->nullable();
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
$settle = static function (string $name, array $statuses) use ($db): void {
    $db->table('s_payment_methods')->where('name', $name)->update(['settings' => json_encode($statuses ? ['settle_on_statuses' => array_map('strval', $statuses)] : [])]);
    PaymentMethodResolver::flush();
};
$db->table('s_payment_methods')->insert([
    ['name' => 'cash', 'class' => CashPayment::class, 'identifier' => '', 'settings' => null],
    ['name' => 'bank_invoice', 'class' => Seiger\sCommerce\Payment\BankInvoicePayment::class, 'identifier' => '', 'settings' => null],
    ['name' => 'card', 'class' => OnlineCard::class, 'identifier' => '', 'settings' => null],
]);

$checks = 0;
$check = static function (bool $passed, string $label) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException($label);
    }
    $checks++;
};
$events = [];
Event::listen('sCommerce.*', static function (string $name, array $payload) use (&$events): void {
    $events[] = [$name, $payload[0]];
});
$flow = new PaymentFlow();
$newOrder = static function (string $method = 'cash', float $cost = 100, ?string $uuid = null): sOrder {
    $order = new sOrder();
    $order->forceFill(['identifier' => bin2hex(random_bytes(8)), 'cost' => $cost, 'currency' => 'EUR', 'status' => 1, 'payment_status' => 0,
        'payment_info' => ['method' => $method]]);
    $order->save();
    if ($uuid === '') {
        DB::table('s_orders')->where('id', $order->id)->update(['uuid' => null]);
        $order = $order->fresh();
    }
    return $order;
};
$setStatus = static function (sOrder $order, int $status): sOrder {
    $order->status = $status;
    $order->save();
    return $order;
};
$rows = static fn (sOrder $order) => sOrderPayment::query()->where('order_id', $order->id)->orderBy('id')->get();
$settle('cash', [sOrder::ORDER_STATUS_DELIVERED]);

// 1. Cash on delivery: DELIVERED records one capture of the balance.
$order = $newOrder();
$events = [];
$setStatus($order, sOrder::ORDER_STATUS_SHIPPED);
$check($rows($order)->isEmpty(), 'Other statuses do not settle');
$setStatus($order, sOrder::ORDER_STATUS_DELIVERED);
$payments = $rows($order);
$check($payments->count() === 1 && $payments[0]->kind === 'final' && $payments[0]->status === 'captured' && (float)$payments[0]->amount === 100.0
    && $payments[0]->provider === 'cash' && $payments[0]->provider_ref === "settle/{$order->id}/7", 'One captured settlement row');
$metadata = PaymentFlow::normalizeMetadata($payments[0]->getAttributes()['metadata']);
$check($metadata['source'] === 'offline_settlement' && $metadata['trigger_status'] === 7 && $metadata[PaymentFlow::CONTEXT_KEY]['source'] === 'offline_settlement', 'Settlement metadata and scope');
$check((int)$order->fresh()->payment_status === sOrder::PAYMENT_STATUS_PAID, 'Order is PAID');
$names = array_column($events, 0);
$recorded = $events[array_search('sCommerce.PaymentRecorded', $names, true)][1] ?? null;
$check($recorded && $recorded['source'] === 'offline_settlement' && in_array('sCommerce.OrderPaymentStatusChanged', $names, true), 'PaymentRecorded and OrderPaymentStatusChanged dispatched');

// 2. Repeating the status or the settlement creates nothing.
$setStatus($order, sOrder::ORDER_STATUS_COMPLETED);
$setStatus($order, sOrder::ORDER_STATUS_DELIVERED);
$check((new OfflineSettlement())->settle((int)$order->id) === null && (new OfflineSettlement())->reconcile() === 0 && $rows($order)->count() === 1, 'Settlement is idempotent');

// 3. An empty setting disables settlement; 4. online methods are never settled.
$settle('bank_invoice', []);
$order = $setStatus($newOrder('bank_invoice'), sOrder::ORDER_STATUS_DELIVERED);
$check($rows($order)->isEmpty(), 'Empty setting settles nothing');
$settle('card', [sOrder::ORDER_STATUS_DELIVERED]);
$order = $setStatus($newOrder('card'), sOrder::ORDER_STATUS_DELIVERED);
$check($rows($order)->isEmpty() && (new OfflineSettlement())->reconcile() === 0, 'Online method is never settled');

// 5. Partial payments, manual payments, refunds, operations, legacy UUIDs and missed triggers.
$order = $newOrder('cash', 100, '');
$flow->record($order, new LedgerEntry('deposit', 'captured', 30, 'EUR', 'cash', 'deposit-1'));
$setStatus($order, sOrder::ORDER_STATUS_DELIVERED);
$payments = $rows($order);
$check($order->fresh()->uuid === null && $payments->count() === 2 && (float)$payments[1]->amount === 70.0 && (int)$order->fresh()->payment_status === sOrder::PAYMENT_STATUS_PAID, 'Only the balance is settled; legacy order without UUID');
$order = $newOrder();
DB::transaction(static fn () => (new OrderManualPayment())->apply($order, 7));
$setStatus($order, sOrder::ORDER_STATUS_DELIVERED);
$check($rows($order)->count() === 1 && $rows($order)[0]->provider === 'manual', 'Manually paid order is not settled again');
$order = $newOrder();
$flow->record($order, new LedgerEntry('final', 'captured', 50, 'EUR', 'cash', 'cash-1'));
$flow->recordRefunded($order, 50, 'EUR', 'cash', 'cash-refund-1', 'cash-1');
$setStatus($order, sOrder::ORDER_STATUS_DELIVERED);
$check($rows($order)->count() === 2 && (int)$order->fresh()->payment_status === sOrder::PAYMENT_STATUS_REFUNDED, 'Refunded money is not recollected automatically');
$order = $newOrder();
$authorization = $flow->record($order, new LedgerEntry('final', 'pending', 100, 'EUR', 'cash', 'cash-pending'))->payment;
DB::table('s_payment_operations')->insert(['operation_key' => 'cancel/x', 'order_id' => $order->id, 'payment_id' => $authorization->id, 'action' => 'cancel',
    'currency' => 'EUR', 'request_hash' => str_repeat('0', 64), 'state' => 'unknown', 'created_at' => now(), 'updated_at' => now()]);
$setStatus($order, sOrder::ORDER_STATUS_DELIVERED);
$check($rows($order)->count() === 1, 'An unfinished payment operation blocks settlement');
// Rolled-back status changes never settle; committed ones inside a scope settle once when it ends.
$order = $newOrder();
try {
    DB::transaction(static function () use ($order, $setStatus): void {
        $setStatus($order, sOrder::ORDER_STATUS_DELIVERED);
        throw new RuntimeException('rollback');
    });
} catch (RuntimeException) {
}
$check($rows($order)->isEmpty(), 'Rolled-back status change does not settle');
$order = $order->fresh();
$events = [];
EventScope::run('manager', 5, static function () use ($order, $setStatus, $rows, $check): void {
    DB::transaction(static fn () => $setStatus($order, sOrder::ORDER_STATUS_DELIVERED));
    $check($rows($order)->isEmpty(), 'Settlement waits for the end of the operation');
    $setStatus($order, sOrder::ORDER_STATUS_COMPLETED);
    $setStatus($order, sOrder::ORDER_STATUS_DELIVERED);
});
$check($rows($order)->count() === 1, 'Committed status change settles once after the scope');
$check(count(array_keys(array_column($events, 0), 'sCommerce.PaymentRecorded')) === 1, 'One flush with one PaymentRecorded');
// A status written around the model hooks is caught by reconciliation; a failing settlement is logged.
$order = $newOrder();
DB::table('s_orders')->where('id', $order->id)->update(['status' => sOrder::ORDER_STATUS_DELIVERED]);
$check($rows($order)->isEmpty() && (new OfflineSettlement())->reconcile() === 1 && $rows($order)->count() === 1, 'Reconciliation settles missed orders');
$check((new OfflineSettlement())->reconcile() === 0, 'Reconciliation is idempotent');

// Settings: Cash and Bank invoice expose settle_on_statuses.
$cash = new CashPayment();
$field = $cash->defineSettings()['settlement']['fields']['settle_on_statuses'] ?? null;
$check($field && $field['type'] === 'multiselect' && $field['name'] === 'settle_on_statuses[]' && !isset($field['options'][sOrder::ORDER_STATUS_DELETED])
    && isset($field['options'][sOrder::ORDER_STATUS_DELIVERED]), 'Settings field lists order statuses');
$check(json_decode($cash->prepareSettings(['info' => 'Pay the courier', 'settle_on_statuses' => ['7', '8']]), true) === ['info' => 'Pay the courier', 'settle_on_statuses' => ['7', '8']], 'Selected statuses are saved');
$check(OfflineSettlement::statusesOf(PaymentMethodResolver::forKey('cash')) === [7] && OfflineSettlement::statusesOf(PaymentMethodResolver::forKey('card')) === [], 'Statuses only for offline methods');
$check(is_file($package . '/views/partials/multiselectField.blade.php'), 'Multiselect settings field partial exists');
$check(sCommerce::supportsIntegration('offline-settlement'), 'Feature key offline-settlement');
$check(!array_filter($app['log']->records, static fn ($record) => $record[0] === 'error'), 'No settlement errors logged');

echo "$checks offline settlement checks passed\n";
