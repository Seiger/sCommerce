<?php

// php tests/standalone/scommerce-payment-actions.php
// Isolated checks of manager payment operations (capture, refund, cancel, offline records): in-memory SQLite,
// fake payment providers and a fake manager, no CMS/site bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function evo() { return Illuminate\Container\Container::getInstance(); }
require __DIR__ . '/bootstrap.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Seiger\sCommerce\Controllers\OrderPaymentActionController;
use Seiger\sCommerce\Interfaces\CancelablePaymentMethodInterface;
use Seiger\sCommerce\Interfaces\CapturablePaymentMethodInterface;
use Seiger\sCommerce\Interfaces\DelegatedPaymentOperationsInterface;
use Seiger\sCommerce\Interfaces\OfflineSettlementPaymentMethodInterface;
use Seiger\sCommerce\Interfaces\PaymentOperationLookupInterface;
use Seiger\sCommerce\Interfaces\RefundablePaymentMethodInterface;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Models\sPaymentOperation;
use Seiger\sCommerce\Payment\BasePaymentMethod;
use Seiger\sCommerce\Payment\LedgerConflictException;
use Seiger\sCommerce\Payment\LedgerEntry;
use Seiger\sCommerce\Payment\PaymentFlow;
use Seiger\sCommerce\Payment\PaymentOperationRequest;
use Seiger\sCommerce\Payment\PaymentOperationResult;
use Seiger\sCommerce\Services\OrderCostRecalculator;
use Seiger\sCommerce\Services\OrderPaymentAction;
use Seiger\sCommerce\sCommerce as Commerce;

abstract class FakeMethod extends BasePaymentMethod
{
    /** @var list<array{0: string, 1: string, 2: string, 3: ?float}> action, class, key, amount */
    public static array $calls = [];
    /** @var list<PaymentOperationResult|Closure> Queued provider answers; default: succeeded. */
    public static array $answers = [];
    public function getType(): string { return static::class; }
    public function payButton(int|string|array $data): string { return ''; }
    public function validatePayment(array $data): bool { return true; }
    public function processPayment(array $data): array|bool { return true; }
    public function defineSettings(): array { return []; }
    public function defineCredentials(): array { return []; }
    protected function answer(string $action, string $key, ?float $amount): PaymentOperationResult
    {
        self::$calls[] = [$action, static::class, $key, $amount];
        $answer = array_shift(self::$answers) ?? new PaymentOperationResult('succeeded', $action . '_' . count(self::$calls));
        return $answer instanceof Closure ? $answer($key) : $answer;
    }
}
class FakePsp extends FakeMethod implements CapturablePaymentMethodInterface, RefundablePaymentMethodInterface, CancelablePaymentMethodInterface
{
    public function getName(): string { return 'fakepsp'; }
    public function supportsPartialCapture(): bool { return false; }
    public function supportsPartialRefund(): bool { return true; }
    public function capture(sOrder $order, sOrderPayment $payment, ?float $amount, string $idempotencyKey): PaymentOperationResult { return $this->answer('capture', $idempotencyKey, $amount); }
    public function refund(sOrder $order, sOrderPayment $payment, float $amount, string $reason, string $idempotencyKey): PaymentOperationResult { return $this->answer('refund', $idempotencyKey, $amount); }
    public function cancel(sOrder $order, sOrderPayment $payment, string $reason, string $idempotencyKey): PaymentOperationResult { return $this->answer('cancel', $idempotencyKey, null); }
}
class FakeLookupPsp extends FakePsp implements PaymentOperationLookupInterface
{
    public static ?PaymentOperationResult $found = null;
    public static array $lookups = [];
    public function getName(): string { return 'lookuppsp'; }
    public function findPaymentOperation(PaymentOperationRequest $request): ?PaymentOperationResult
    {
        self::$lookups[] = $request->idempotencyKey;
        return self::$found;
    }
}
class FakeFullRefundPsp extends FakePsp
{
    public function getName(): string { return 'fullpsp'; }
    public function supportsPartialRefund(): bool { return false; }
}
class FakePlain extends FakeMethod
{
    public function getName(): string { return 'plain'; }
}
class FakePartialPsp extends FakePsp
{
    public function getName(): string { return 'partialpsp'; }
    public function supportsPartialCapture(): bool { return true; }
}
class FakeOffline extends FakeMethod implements OfflineSettlementPaymentMethodInterface
{
    public function getName(): string { return 'offline'; }
}
/** Stands in for an aFina-backed method with its own operation store. */
class FakeDelegated extends FakeMethod implements DelegatedPaymentOperationsInterface
{
    public static array $store = [];
    public static int $providerCalls = 0;
    public function getName(): string { return 'afina'; }
    public function supportsPaymentOperation(string $action): bool { return $action === 'refund'; }
    public function runPaymentOperation(PaymentOperationRequest $request): PaymentOperationResult
    {
        if (isset(self::$store[$request->idempotencyKey])) {
            return self::$store[$request->idempotencyKey];
        }
        self::$providerCalls++;
        $reference = 're_afina_' . self::$providerCalls;
        (new PaymentFlow())->recordRefunded($request->order, (float)$request->amount, $request->currency, (string)$request->payment->provider,
            $reference, (string)$request->payment->provider_ref, ['fact_id' => 'fact-' . self::$providerCalls]);
        return self::$store[$request->idempotencyKey] = new PaymentOperationResult('succeeded', $reference);
    }
}

$app = new class extends Container {
    public array $permissions = ['exec_module', 'scommerce_payment_capture', 'scommerce_payment_refund', 'scommerce_payment_cancel', 'scommerce_payment_record'];
    public function getLoginUserID($context) { return 42; }
    public function hasPermission($permission) { return in_array($permission, $this->permissions, true); }
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
    $table->string('mode')->default('');
    $table->timestamps();
});
(require $package . '/database/migrations/2026_03_01_000000_create_order_payments_table.php')->up();
(require $package . '/database/migrations/2026_10_20_000002_create_payment_operations_table.php')->up();
foreach ([FakePsp::class, FakeLookupPsp::class, FakeFullRefundPsp::class, FakePlain::class, FakeDelegated::class, FakePartialPsp::class, FakeOffline::class] as $class) {
    $db->table('s_payment_methods')->insert(['name' => (new ReflectionClass($class))->newInstanceWithoutConstructor()->getName(), 'class' => $class, 'identifier' => '']);
}

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
$newOrder = static function (string $method = 'fakepsp', float $cost = 100): sOrder {
    $order = new sOrder();
    $order->forceFill(['identifier' => bin2hex(random_bytes(8)), 'cost' => $cost, 'currency' => 'EUR', 'status' => 1, 'payment_status' => 0,
        'payment_info' => ['method' => $method]]);
    $order->save();
    return $order;
};
$paid = static function (string $method = 'fakepsp', float $amount = 100, string $status = 'captured') use ($newOrder, $flow): array {
    $order = $newOrder($method, $amount);
    $payment = $flow->record($order, new LedgerEntry('final', $status, $amount, 'EUR', $method, 'pi_' . bin2hex(random_bytes(4))))->payment;
    return [$order, $payment];
};
$_SESSION = ['mgrValidated' => true, '_token' => 'test-payment-token'];
$controller = new OrderPaymentActionController();
$post = static function (array $input, string $method = 'POST') use ($controller): array {
    $response = $controller->handle(Request::create('/', $method, $input + ['_token' => 'test-payment-token']));
    return [$response->getStatusCode(), json_decode($response->getContent(), true)];
};
$form = static function (sOrder $order, sOrderPayment $payment, string $action, array $extra = [], ?string $nonce = null): array {
    return ['action' => $action, 'order' => (string)$order->id, 'payment' => (string)$payment->id,
        'nonce' => $nonce ?? OrderPaymentAction::issueNonce((int)$order->id, (int)$payment->id, $action)] + $extra;
};
$refundRows = static fn (sOrder $order) => sOrderPayment::query()->where('order_id', $order->id)->where('kind', 'refund')->get();

// 1. A refund within the available amount calls the provider once and records a refund row.
[$order, $payment] = $paid();
$events = [];
$input = $form($order, $payment, 'refund', ['amount' => '30.00', 'reason' => 'Damaged']);
[$status, $body] = $post($input);
$refunds = $refundRows($order);
$check($status === 200 && $body['success'] === true && $body['state'] === 'succeeded', 'Refund succeeds');
$check(count(FakeMethod::$calls) === 1 && FakeMethod::$calls[0][0] === 'refund' && FakeMethod::$calls[0][3] === 30.0
    && FakeMethod::$calls[0][2] === "refund/{$order->id}/{$payment->id}/{$input['nonce']}", 'Provider receives amount and the stable operation key');
$check($refunds->count() === 1 && $refunds[0]->status === 'refunded' && (float)$refunds[0]->amount === 30.0 && $refunds[0]->provider === 'fakepsp', 'Refund row recorded');
$check(PaymentFlow::normalizeMetadata($refunds[0]->getAttributes()['metadata'])[PaymentFlow::CONTEXT_KEY]['parent_provider_ref'] === $payment->provider_ref, 'Refund row links its parent payment');
$check((int)$order->fresh()->payment_status === sOrder::PAYMENT_STATUS_PARTIALLY_REFUNDED, 'Order projection PARTIALLY_REFUNDED');
$recorded = array_values(array_filter($events, static fn ($event) => $event[0] === 'sCommerce.PaymentRecorded'));
$check(count($recorded) === 1 && $recorded[0][1]['source'] === 'manager_payment_action' && $recorded[0][1]['actorId'] === 42, 'PaymentRecorded carries the manager action context');
$operation = sPaymentOperation::query()->where('order_id', $order->id)->sole();
$check($operation->state === 'succeeded' && $operation->ledger_payment_id === $refunds[0]->id && $operation->reason === 'Damaged'
    && $operation->actor_id === 42 && $operation->context['source'] === 'manager_payment_action', 'Operation stored with result and context');

// 2. Resubmitting the same nonce returns the stored result without a second provider call.
[$status, $body] = $post($input);
$check($status === 200 && $body['state'] === 'succeeded' && count(FakeMethod::$calls) === 1 && $refundRows($order)->count() === 1, 'Same nonce: no second call or row');
// Same operation key with another amount is a conflict.
[$status] = $post(['amount' => '31.00'] + $input);
$check($status === 409 && count(FakeMethod::$calls) === 1, 'Same nonce with another amount conflicts');

// 3. Over-refund and refund of an authorized row are refused before the provider.
[$status, $body] = $post($form($order, $payment, 'refund', ['amount' => '70.01']));
$check($status === 422 && $body['message'] === __('sCommerce::global.payment_action_ineligible') && count(FakeMethod::$calls) === 1, 'Refund above the available amount is refused');
[$authOrder, $authorized] = $paid('fakepsp', 50, 'authorized');
[$status] = $post($form($authOrder, $authorized, 'refund', ['amount' => '10']));
$check($status === 422 && count(FakeMethod::$calls) === 1, 'Authorized row cannot be refunded');
[$status] = $post($form($order, $payment, 'refund', ['amount' => '-5']));
$check($status === 422, 'Negative amount refused');

// 4. A method without the interface is ineligible.
[$plainOrder, $plainPayment] = $paid('plain');
[$status, $body] = $post($form($plainOrder, $plainPayment, 'refund', ['amount' => '10']));
$check($status === 422 && $body['message'] === __('sCommerce::global.payment_action_ineligible') && count(FakeMethod::$calls) === 1, 'Method without capability is ineligible');
[$fullOrder, $fullPayment] = $paid('fullpsp');
[$status] = $post($form($fullOrder, $fullPayment, 'refund', ['amount' => '10']));
$check($status === 422 && count(FakeMethod::$calls) === 1, 'Partial refund refused when the provider cannot do it');

// 5. Guards: method, session, CSRF, permissions, nonce.
[$status] = $post($form($order, $payment, 'refund', ['amount' => '1']), 'GET');
$check($status === 405, 'GET is rejected');
[$status] = $post(['_token' => 'stale'] + $form($order, $payment, 'refund', ['amount' => '1']));
$check($status === 403, 'Stale CSRF token is rejected');
$app->permissions = ['exec_module'];
[$status, $body] = $post($form($order, $payment, 'refund', ['amount' => '1']));
$check($status === 403 && $body['message'] === __('sCommerce::global.payment_action_forbidden'), 'Missing refund permission is rejected');
$app->permissions = ['scommerce_payment_refund'];
[$status] = $post($form($order, $payment, 'refund', ['amount' => '1']));
$check($status === 403, 'Missing exec_module is rejected');
$app->permissions = ['exec_module', 'scommerce_payment_capture', 'scommerce_payment_refund', 'scommerce_payment_cancel', 'scommerce_payment_record'];
$_SESSION['mgrValidated'] = false;
[$status] = $post($form($order, $payment, 'refund', ['amount' => '1']));
$check($status === 403, 'Manager session is required');
$_SESSION['mgrValidated'] = true;
[$status] = $post($form($order, $payment, 'refund', ['amount' => '1'], 'forged-nonce'));
$check($status === 403, 'Unknown nonce is rejected');
[$status] = $post($form($order, $payment, 'refund', ['amount' => '1'], OrderPaymentAction::issueNonce((int)$order->id, (int)$payment->id, 'cancel')));
$check($status === 403, 'Nonce of another action is rejected');
$check(count(FakeMethod::$calls) === 1, 'Rejected requests never reach the provider');

// 6. A pending refund is completed by the provider webhook on the same reference.
[$order, $payment] = $paid();
FakeMethod::$answers[] = new PaymentOperationResult('pending', 're_pending');
[$status, $body] = $post($form($order, $payment, 'refund', ['amount' => '20']));
$refunds = $refundRows($order);
$check($status === 202 && $body['state'] === 'pending' && $refunds->count() === 1 && $refunds[0]->status === 'pending', 'Pending refund recorded as a pending row');
$check((int)$order->fresh()->payment_status === sOrder::PAYMENT_STATUS_PAID, 'Pending refund does not change the projection');
$flow->recordRefunded($order, 20, 'EUR', 'fakepsp', 're_pending', $payment->provider_ref);
$refunds = $refundRows($order);
$check($refunds->count() === 1 && $refunds[0]->status === 'refunded', 'Webhook completes the same row');
$check((new OrderPaymentAction())->recover() >= 1 && sPaymentOperation::query()->where('order_id', $order->id)->value('state') === 'succeeded', 'Recovery syncs the operation from the ledger');
// Pending without a provider reference reserves the amount but writes no ledger row.
FakeMethod::$answers[] = new PaymentOperationResult('pending');
[$status] = $post($form($order, $payment, 'refund', ['amount' => '50']));
[$status2] = $post($form($order, $payment, 'refund', ['amount' => '30.01']));
$check($status === 202 && $refundRows($order)->count() === 1 && $status2 === 422, 'Reserved pending refund limits the next refund');

// 9. Concurrency and crash recovery.
[$order, $payment] = $paid();
$inner = [];
$first = $form($order, $payment, 'refund', ['amount' => '60']);
FakeMethod::$answers[] = static function (string $key) use (&$inner, $post, $form, $order, $payment, $first): PaymentOperationResult {
    // While the provider call runs, another nonce and the same nonce are submitted.
    $inner[] = $post($form($order, $payment, 'refund', ['amount' => '50']))[0];
    $inner[] = $post($form($order, $payment, 'refund', ['amount' => '40']))[0];
    $inner[] = $post($first)[0];
    return new PaymentOperationResult('succeeded', 're_concurrent');
};
$calls = count(FakeMethod::$calls);
[$status] = $post($first);
$check($status === 200 && $inner === [422, 200, 409], 'In-flight reservation blocks over-refund; same nonce reports in progress');
$check(count(FakeMethod::$calls) === $calls + 2 && (int)$order->fresh()->payment_status === sOrder::PAYMENT_STATUS_REFUNDED, 'Exactly the two allowed refunds reached the provider');

// A provider error after sending leaves the result unknown and is never retried blindly.
[$order, $payment] = $paid();
FakeMethod::$answers[] = static fn () => throw new RuntimeException('Gateway timeout');
$input = $form($order, $payment, 'refund', ['amount' => '10']);
[$status, $body] = $post($input);
$calls = count(FakeMethod::$calls);
[$status2, $body2] = $post($input);
$check($status === 202 && $body['state'] === 'unknown' && $status2 === 202 && $body2['state'] === 'unknown' && count(FakeMethod::$calls) === $calls, 'Unknown result is not repeated without a lookup');
[$status] = $post($form($order, $payment, 'refund', ['amount' => '90.01']));
$check($status === 422, 'Unknown refund keeps its reservation');

// A crash after provider success: the lookup recovers the result without a second call.
[$order, $payment] = $paid('lookuppsp');
$input = $form($order, $payment, 'refund', ['amount' => '25']);
FakeMethod::$answers[] = static fn () => throw new Error('Process killed');
$post($input);
FakeLookupPsp::$found = new PaymentOperationResult('succeeded', 're_recovered');
$calls = count(FakeMethod::$calls);
[$status, $body] = $post($input);
$refunds = $refundRows($order);
$check($status === 200 && $body['state'] === 'succeeded' && count(FakeMethod::$calls) === $calls && FakeLookupPsp::$lookups === [$input['action'] . "/{$order->id}/{$payment->id}/{$input['nonce']}"], 'Lookup recovers without calling the provider again');
$check($refunds->count() === 1 && $refunds[0]->provider_ref === 're_recovered', 'Recovered refund recorded once');
// An interrupted in-flight operation becomes unknown after the timeout, then the provider is asked.
[$order, $payment] = $paid('lookuppsp');
$input = $form($order, $payment, 'refund', ['amount' => '5']);
FakeMethod::$answers[] = static function () use ($post, $input): PaymentOperationResult {
    sPaymentOperation::query()->update(['updated_at' => now()->subHour()]);
    throw new Error('Stop before the result');
};
$post($input);
sPaymentOperation::query()->where('operation_key', 'like', 'refund/' . $order->id . '/%')->update(['state' => 'in_flight', 'updated_at' => now()->subHour()]);
FakeLookupPsp::$found = null;
$calls = count(FakeMethod::$calls);
[$status, $body] = $post($input);
$check($status === 200 && $body['state'] === 'succeeded' && count(FakeMethod::$calls) === $calls + 1
    && sPaymentOperation::query()->where('operation_key', 'like', 'refund/' . $order->id . '/%')->value('attempts') === 2, 'Request never received by the provider is resent with the same key');

// Capture and cancel: only `succeeded` applies a transition.
[$order, $payment] = $paid('fakepsp', 80, 'authorized');
FakeMethod::$answers[] = new PaymentOperationResult('pending');
[$status, $body] = $post($form($order, $payment, 'capture'));
$check($status === 202 && $payment->fresh()->status === 'authorized', 'Pending capture keeps the authorization');
[$status] = $post($form($order, $payment, 'capture'));
$check($status === 422, 'A pending capture blocks another capture');
sPaymentOperation::query()->where('order_id', $order->id)->update(['state' => 'failed']);
FakeMethod::$answers[] = new PaymentOperationResult('failed', null, 'Card issuer declined');
[$status, $body] = $post($form($order, $payment, 'cancel'));
$check($status === 200 && $body['success'] === false && str_contains($body['message'], 'Card issuer declined') && $payment->fresh()->status === 'authorized', 'Failed cancel changes nothing');
[$status] = $post($form($order, $payment, 'capture'));
$check($status === 200 && $payment->fresh()->status === 'captured' && (int)$order->fresh()->payment_status === sOrder::PAYMENT_STATUS_PAID
    && sOrderPayment::query()->where('order_id', $order->id)->count() === 1, 'Capture moves the same row to captured');
$check(end(FakeMethod::$calls)[3] === null, 'A capture without an amount captures the authorization');
[$order, $payment] = $paid('fakepsp', 15, 'authorized');
[$status] = $post($form($order, $payment, 'cancel', ['reason' => 'Customer request']));
$check($status === 200 && $payment->fresh()->status === 'canceled' && (int)$order->fresh()->payment_status === sOrder::PAYMENT_STATUS_CANCELED, 'Cancel voids the authorization');

// 10. A delegated (aFina-backed) method runs its own pipeline once and yields one fact.
[$order, $payment] = $paid('afina');
$input = $form($order, $payment, 'refund', ['amount' => '40']);
$calls = count(FakeMethod::$calls);
[$status, $body] = $post($input);
[$status2] = $post($input);
$flow->recordRefunded($order, 40, 'EUR', 'afina', 're_afina_1', $payment->provider_ref); // the provider webhook arrives later
$check($status === 200 && $status2 === 200 && FakeDelegated::$providerCalls === 1 && count(FakeMethod::$calls) === $calls, 'Delegated pipeline runs once');
$check($refundRows($order)->count() === 1 && sPaymentOperation::query()->where('order_id', $order->id)->count() === 0, 'One refund fact and no second operation store');
[$status] = $post($form($order, $payment, 'cancel'));
$check($status === 422, 'Delegated method advertises only its supported actions');

// 11. Partial capture (weighed goods): the captured amount replaces the authorization.
[$order, $payment] = $paid('partialpsp', 50, 'authorized');
$order->cost = 42.3;
$order->save();
$panel = (new OrderPaymentAction())->panel($order->fresh());
$check($panel['partialCapture'] === true && $panel['rows'][0]['capturable'] === '42.30', 'Capture suggests the weighed cost below the authorization');
$calls = count(FakeMethod::$calls);
[$status] = $post($form($order, $payment, 'capture', ['amount' => '42,30']));
$row = $payment->fresh();
$check($status === 200 && count(FakeMethod::$calls) === $calls + 1 && end(FakeMethod::$calls)[0] === 'capture' && end(FakeMethod::$calls)[3] === 42.3, 'Provider receives the capture amount');
$check($row->status === 'captured' && (float)$row->amount === 42.3
    && PaymentFlow::normalizeMetadata($row->getAttributes()['metadata'])[PaymentFlow::CONTEXT_KEY]['authorized_amount'] === '50.00', 'Row amount becomes the captured amount; the authorization is kept');
$check((int)$order->fresh()->payment_status === sOrder::PAYMENT_STATUS_PAID && sOrderPayment::query()->where('order_id', $order->id)->count() === 1, 'Paid at the weighed cost');
$flow->recordAuthorized($order, 'final', 50, 'EUR', 'partialpsp', $payment->provider_ref);
$flow->record($order, new LedgerEntry('final', 'captured', 42.3, 'EUR', 'partialpsp', $payment->provider_ref));
$check($payment->fresh()->status === 'captured' && (float)$payment->fresh()->amount === 42.3 && sOrderPayment::query()->where('order_id', $order->id)->count() === 1,
    'Late authorization and capture webhooks change nothing');
[$order, $payment] = $paid('partialpsp', 50, 'authorized');
[$status] = $post($form($order, $payment, 'capture', ['amount' => '50.01']));
$check($status === 422 && $payment->fresh()->status === 'authorized', 'Capture above the authorization is refused');
[$order, $payment] = $paid('fakepsp', 50, 'authorized');
$calls = count(FakeMethod::$calls);
[$status] = $post($form($order, $payment, 'capture', ['amount' => '40']));
$check($status === 422 && count(FakeMethod::$calls) === $calls, 'Lower amount refused when the provider captures in full only');
$check((new OrderPaymentAction())->panel($order)['partialCapture'] === false, 'Full-capture methods get no amount field');
[$status] = $post($form($order, $payment, 'capture', ['amount' => '50.00']));
$check($status === 200 && $payment->fresh()->status === 'captured' && (float)$payment->fresh()->amount === 50.0, 'The full amount is accepted by a full-capture method');
// A provider webhook may report the partial capture first.
[$order, $payment] = $paid('partialpsp', 80, 'authorized');
$flow->record($order, new LedgerEntry('final', 'captured', 61.15, 'EUR', 'partialpsp', $payment->provider_ref));
$check((float)$payment->fresh()->amount === 61.15 && $payment->fresh()->status === 'captured', 'A capture webhook records a partial capture');
try {
    $flow->record($order, new LedgerEntry('final', 'captured', 70, 'EUR', 'partialpsp', $payment->provider_ref));
    $check(false, 'A second capture amount is accepted');
} catch (LedgerConflictException) {
    $checks++;
}

// 12. Offline methods record received and returned money (partial cash payments, weighed goods).
$offlineForm = static fn (sOrder $order, string $action, string $amount, string $note = '', ?string $nonce = null): array => [
    'action' => $action, 'order' => (string)$order->id, 'payment' => '0', 'amount' => $amount, 'reason' => $note,
    'nonce' => $nonce ?? OrderPaymentAction::issueNonce((int)$order->id, 0, $action)];
$cashOrder = $newOrder('offline', 100);
$panel = (new OrderPaymentAction())->panel($cashOrder);
$check(array_keys($panel['orderActions']) === ['receive'] && $panel['rows'] === [] && $panel['totals']['due'] === '100.00', 'An unpaid cash order offers Record payment only');
$calls = count(FakeMethod::$calls);
$events = [];
$input = $offlineForm($cashOrder, 'receive', '40,00', 'Receipt 17');
[$status, $body] = $post($input);
$rows = sOrderPayment::query()->where('order_id', $cashOrder->id)->get();
$check($status === 200 && $body['message'] === __('sCommerce::global.payment_record_success') && $rows->count() === 1 && $rows[0]->kind === 'final'
    && $rows[0]->status === 'captured' && (float)$rows[0]->amount === 40.0 && $rows[0]->provider === 'offline', 'Received 40 of 100 recorded');
$metadata = PaymentFlow::normalizeMetadata($rows[0]->getAttributes()['metadata']);
$check($metadata['note'] === 'Receipt 17' && $metadata[PaymentFlow::CONTEXT_KEY]['source'] === 'manager_payment_action' && $metadata[PaymentFlow::CONTEXT_KEY]['actor_id'] === 42
    && !str_contains((string)$rows[0]->provider_ref, $input['nonce']), 'Note and manager recorded; the nonce is not stored');
$check((int)$cashOrder->fresh()->payment_status === sOrder::PAYMENT_STATUS_PARTIALLY_PAID && count(FakeMethod::$calls) === $calls
    && count(array_filter($events, static fn ($event) => $event[0] === 'sCommerce.PaymentRecorded')) === 1, 'Partially paid, one PaymentRecorded, no provider call');
[$status] = $post($input);
$check($status === 200 && sOrderPayment::query()->where('order_id', $cashOrder->id)->count() === 1, 'A resubmitted form records once');
[$status] = $post(['amount' => '41'] + $input);
$check($status === 409, 'The same form with another amount conflicts');
[$status, $body] = $post($offlineForm($cashOrder, 'receive', '60.01'));
$check($status === 422 && $body['message'] === __('sCommerce::global.payment_action_ineligible'), 'A receipt above the balance due is refused');
[$status] = $post($offlineForm($cashOrder, 'return', '1'));
[$status2] = $post(['payment' => '5'] + $offlineForm($cashOrder, 'receive', '1'));
[$status3] = $post(['amount' => ''] + $offlineForm($cashOrder, 'receive', '1'));
$check($status === 200 && $status2 === 422 && $status3 === 422, 'Return within the received amount; payment id and amount required');
// The cost drops after payment (a lighter item): the overpayment is shown and returned in cash.
$post($offlineForm($cashOrder, 'receive', '61'));
$cashOrder->cost = 92.5;
$cashOrder->save();
$panel = (new OrderPaymentAction())->panel($cashOrder->fresh());
$check($panel['totals']['overpaid'] === '7.50' && $panel['totals']['returnable'] === '100.00' && array_keys($panel['orderActions']) === ['return'], 'Overpaid 7.50; only Record return offered');
[$status] = $post($offlineForm($cashOrder, 'return', '7.50', 'Change returned'));
$summary = $flow->summary($cashOrder->fresh());
$returns = $refundRows($cashOrder);
$check($status === 200 && $returns->count() === 2 && $returns[1]->status === 'refunded' && (float)$returns[1]->amount === 7.5
    && $summary->overpaidMinor() === 0 && $summary->outstandingMinor() === 0 && (int)$cashOrder->fresh()->payment_status === sOrder::PAYMENT_STATUS_PAID, 'The return settles the overpayment');
[$status] = $post($offlineForm($cashOrder, 'return', '92.51'));
$check($status === 422, 'A return above the money received is refused');
// A receipt within an open deposit is a deposit; methods without the marker cannot record.
$scheduled = $newOrder('offline', 100);
$scheduled->payment_info = ['method' => 'offline', 'schedule' => [['id' => '1', 'kind' => 'deposit', 'amount' => 30, 'due' => 'checkout'], ['id' => '2', 'kind' => 'final', 'amount' => 70, 'due' => 'on_status:7']]];
$scheduled->save();
$post($offlineForm($scheduled, 'receive', '30'));
$post($offlineForm($scheduled, 'receive', '20'));
$check(sOrderPayment::query()->where('order_id', $scheduled->id)->orderBy('sequence')->pluck('kind')->all() === ['deposit', 'final'], 'Receipts follow the schedule kinds');
[$cardOrder] = $paid('fakepsp', 100, 'authorized');
[$status] = $post($offlineForm($cardOrder, 'receive', '10'));
$check($status === 422 && (new OrderPaymentAction())->panel($cardOrder)['orderActions'] === [], 'Online methods cannot record offline payments');
$app->permissions = ['exec_module', 'scommerce_payment_refund'];
[$status] = $post($offlineForm($scheduled, 'receive', '10'));
$check($status === 403 && (new OrderPaymentAction())->panel($scheduled)['orderActions'] === [], 'Recording needs its own permission');
$app->permissions = ['exec_module', 'scommerce_payment_capture', 'scommerce_payment_refund', 'scommerce_payment_cancel', 'scommerce_payment_record'];
[$status] = $post($offlineForm($scheduled, 'receive', '10', '', OrderPaymentAction::issueNonce((int)$scheduled->id, 0, 'return')));
$check($status === 403, 'A nonce of the other offline action is rejected');

// 13. "Partially paid" chosen in the order form needs an amount and is refused.
$manual = $newOrder('offline', 100);
try {
    $flow->applyManagerOverride($manual, sOrder::PAYMENT_STATUS_PARTIALLY_PAID, 42);
    $check(false, 'Partially paid override accepted');
} catch (InvalidArgumentException $exception) {
    $check($exception->getMessage() === __('sCommerce::global.payment_partial_use_record') && sOrderPayment::query()->where('order_id', $manual->id)->count() === 0,
        'Partially paid is refused without recording the full cost');
}
$module = file_get_contents($package . '/module/sCommerceModule.php');
$check(str_contains($module, "\$paymentNotice = 'partial_use_record';") && str_contains($module, "\$back .= '&payment_notice=' . \$paymentNotice;")
    && str_contains(file_get_contents($package . '/views/index.blade.php'), "request()->query('payment_notice') === 'partial_use_record'"), 'The order form shows the refusal after the redirect');

// 14. Editing products keeps the delivery cost and other non-product amounts.
$old = [['priceNumber' => 10, 'quantity' => 2], ['price' => '5.50', 'quantity' => 1]];
$check(OrderCostRecalculator::recalculate('35.50000', $old, [['priceNumber' => 10, 'quantity' => 3]], 'EUR') === 40.0, 'Delivery 10 kept after a product change');
$check(OrderCostRecalculator::recalculate(20.5, $old, [['priceNumber' => 10, 'quantity' => 3]], 'EUR') === 25.0, 'A checkout discount of 5 is kept');
$check(OrderCostRecalculator::recalculate(25.5, $old, [], 'EUR') === 0.0, 'Removing all products leaves no negative cost');
$check(str_contains(file_get_contents($package . '/module/sCommerceModule.php'), 'OrderLineEditor())->apply(')
    && str_contains(file_get_contents($package . '/src/Services/OrderLineEditor.php'), 'OrderCostRecalculator::recalculate('), 'The order form uses the recalculator');

// 7. The panel offers buttons only for capable methods and permitted managers.
$app->instance('sCommerce', new class {
    public function moduleUrl() { return 'index.php?a=112&id=test'; }
});
class_alias(Seiger\sCommerce\Facades\sCommerce::class, 'sCommerce');
$files = new Filesystem();
$cache = sys_get_temp_dir() . '/scommerce-payment-actions-' . bin2hex(random_bytes(8));
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
    $render = static function (sOrder $order) use ($view): string {
        return $view->make('sCommerce::partials.orderPayments', ['item' => $order, 'panel' => (new OrderPaymentAction())->panel($order)])->render() . $view->yieldPushContent('scripts.bot');
    };
    [$order, $payment] = $paid();
    [$authOrder, $authorized] = $paid('fakepsp', 20, 'authorized');
    $html = $render($order);
    $check(str_contains($html, 'data-payment-action="refund"') && !str_contains($html, 'data-payment-action="capture"') && str_contains($html, 'value="100.00"'), 'Captured row offers refund of the full available amount');
    $html = $render($authOrder);
    $check(str_contains($html, 'data-payment-action="capture"') && str_contains($html, 'data-payment-action="cancel"') && !str_contains($html, 'data-payment-action="refund"'), 'Authorized row offers capture and cancel');
    $app->permissions = ['exec_module', 'scommerce_payment_cancel'];
    $html = $render($authOrder);
    $check(!str_contains($html, 'data-payment-action="capture"') && str_contains($html, 'data-payment-action="cancel"'), 'Buttons follow the manager permissions');
    $app->permissions = ['exec_module', 'scommerce_payment_capture', 'scommerce_payment_refund', 'scommerce_payment_cancel', 'scommerce_payment_record'];
    $html = $render($plainOrder);
    $check(str_contains($html, 'data-payment-ledger') && !str_contains($html, 'data-payment-action='), 'Methods without capabilities show read-only rows');
    preg_match('/data-payment-action="refund" data-nonce="([0-9a-f]{32})"/', $render($order), $match);
    $check(isset($match[1]) && OrderPaymentAction::nonceMatches($match[1], (int)$order->id, (int)$payment->id, 'refund'), 'Rendered nonce is bound to the row and action');
    $unpaid = $newOrder('offline', 80);
    $html = $render($unpaid);
    preg_match('/data-payment-action="receive" data-nonce="([0-9a-f]{32})"/', $html, $match);
    $check(str_contains($html, 'data-payment="0"') && str_contains($html, 'data-payment-form="receive"') && str_contains($html, 'value="80.00"')
        && !str_contains($html, 'data-payment-ledger') && isset($match[1]) && OrderPaymentAction::nonceMatches($match[1], (int)$unpaid->id, 0, 'receive'),
        'An offline order without payments shows Record payment with the balance due');
    $html = $render($cashOrder->fresh());
    $check(str_contains($html, 'data-payment-totals') && str_contains($html, 'data-payment-form="return"') && !str_contains($html, 'data-payment-overpaid'), 'Totals and Record return rendered');
    [$weighed] = $paid('partialpsp', 50, 'authorized');
    $weighed->cost = 45.6;
    $weighed->save();
    $html = $render($weighed->fresh());
    $check(str_contains($html, 'data-payment-form="capture"') && str_contains($html, 'value="45.60"'), 'Partial capture renders the amount field');
    $html = $render($scheduled->fresh());
    $check(!str_contains($html, 'data-payment-final-adjusted'), 'An unchanged schedule shows no adjusted final');
    $scheduled->cost = 112;
    $scheduled->save();
    $html = $render($scheduled->fresh());
    $check(str_contains($html, 'data-payment-final-adjusted>82.00<'), 'A surcharge shows the adjusted final installment');
} finally {
    $files->deleteDirectory($cache);
}

// Permissions migration is idempotent and assigns the administrator role.
foreach (['permissions_groups' => ['name', 'lang_key'], 'permissions' => ['key', 'name', 'lang_key', 'group_id', 'disabled'], 'role_permissions' => ['role_id', 'permission']] as $table => $columns) {
    $schema->create($table, static function ($blueprint) use ($columns): void {
        $blueprint->increments('id');
        foreach ($columns as $column) {
            $blueprint->string($column)->nullable();
        }
        $blueprint->timestamps();
    });
}
$permissions = require $package . '/database/migrations/2026_10_20_000000_add_scommerce_payment_permissions.php';
$permissions->up();
$permissions->up();
$keys = array_values(array_unique(OrderPaymentAction::PERMISSIONS));
$check(count($keys) === 4 && $db->table('permissions')->whereIn('key', $keys)->count() === 4 && $db->table('role_permissions')->where('role_id', 1)->whereIn('permission', $keys)->count() === 4
    && $db->table('permissions_groups')->count() === 1, 'Permissions created once and assigned to role 1');
$check($db->table('permissions')->where('key', 'scommerce_payment_refund')->value('lang_key') === 'sCommerce::global.permission_payment_refund', 'Permission label is translatable');
$permissions->down();
$check($db->table('permissions')->count() === 0 && $db->table('role_permissions')->count() === 0 && $db->table('permissions_groups')->count() === 0, 'down() removes the permissions');

// 8. Every new language key exists in all six locales.
$keys = ['payments_ledger', 'payment_capture', 'payment_refund', 'payment_cancel', 'payment_action_forbidden', 'payment_action_ineligible',
    'payment_action_success', 'payment_action_pending', 'payment_action_failed', 'payment_action_unknown', 'payment_action_conflict',
    'payment_action_in_progress', 'payment_action_sending', 'payment_action_run', 'payment_refund_amount', 'payment_refund_reason',
    'payment_refundable', 'payment_operations_open', 'payment_kind', 'payment_amount', 'payment_provider', 'payment_reference',
    'payment_captured_at', 'payment_schedule', 'payment_due_checkout', 'payment_due_manual', 'payment_due_on_status',
    'payment_schedule_invalid', 'payment_schedule_unsupported', 'payment_nothing_due', 'payment_settlement', 'settle_on_statuses',
    'settle_on_statuses_help', 'permission_payment_capture', 'permission_payment_refund', 'permission_payment_cancel',
    'payment_reconciliation_worker', 'payment_reconciliation_worker_desc', 'payment_reconciliation_worker_done',
    'permission_payment_record', 'payment_order_total', 'payment_paid_total', 'payment_balance_due', 'payment_overpaid', 'payment_capture_amount',
    'payment_capture_amount_help', 'payment_record', 'payment_record_return', 'payment_record_amount', 'payment_return_amount', 'payment_returnable',
    'payment_record_note', 'payment_record_success', 'payment_partial_use_record'];
foreach (['uk', 'en', 'ru', 'de', 'fr', 'pl'] as $locale) {
    $labels = require $package . '/lang/' . $locale . '/global.php';
    foreach ($keys as $key) {
        $check(isset($labels[$key]) && trim($labels[$key]) !== '', "Translation $locale.$key exists");
    }
    $check(str_contains($labels['payment_due_on_status'], ':status') && str_contains($labels['payment_reconciliation_worker_done'], ':settled')
        && str_contains($labels['payment_reconciliation_worker_done'], ':resolved'), "Placeholders kept in $locale");
}
$check(Commerce::supportsIntegration('payment-capabilities'), 'Feature key payment-capabilities');

echo "$checks payment action checks passed\n";
