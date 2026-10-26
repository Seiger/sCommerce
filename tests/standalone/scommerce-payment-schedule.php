<?php

// php tests/standalone/scommerce-payment-schedule.php
// Isolated payment schedule (deposit + final) checks through the real checkout entry points:
// in-memory SQLite, fake payment providers, no CMS/site bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function evo() { return Illuminate\Container\Container::getInstance(); }
if (!function_exists('app')) {
    function app($abstract = null, array $parameters = []) { return $abstract === null ? evo() : evo()->make($abstract, $parameters); }
}
require __DIR__ . '/bootstrap.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Seiger\sCommerce\Checkout\sCheckout;
use Seiger\sCommerce\Interfaces\InstallmentPaymentMethodInterface;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Payment\BasePaymentMethod;
use Seiger\sCommerce\Payment\LedgerEntry;
use Seiger\sCommerce\Payment\PaymentFlow;
use Seiger\sCommerce\Payment\PaymentSchedule;
use Seiger\sCommerce\Services\OrderReferenceGenerator;
use Seiger\sCommerce\sCommerce;

abstract class RecordingMethod extends BasePaymentMethod
{
    /** @var list<array<string, mixed>> Data passed to processPayment(). */
    public static array $charges = [];
    public static array|bool $outcome = true;
    public function getType(): string { return static::class; }
    public function payButton(int|string|array $data): string { return ''; }
    public function validatePayment(array $data): bool { return true; }
    public function defineSettings(): array { return []; }
    public function defineCredentials(): array { return []; }
    public function processPayment(array $data): array|bool
    {
        self::$charges[] = $data;
        return self::$outcome;
    }
}
class InstallmentPsp extends RecordingMethod implements InstallmentPaymentMethodInterface
{
    public function getName(): string { return 'instpsp'; }
}
class FullCostPsp extends RecordingMethod
{
    public function getName(): string { return 'fullpsp'; }
}

$app = new class extends Container {
    public function getLoginUserID($context) { return 0; }
    public function getConfig($key, $default = null) { return $default; }
    public function getLocale() { return 'en'; }
    public function logEvent(...$arguments) {}
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
$app->instance('validator', new Illuminate\Validation\Factory($app['translator'], $app));
$app->instance('sCommerce', new class {
    public function config($key, $default = null) { return match ($key) { 'basic.deliveries_on' => 0, 'basic.payments_on' => 1, default => str_starts_with($key, 'notifications.') ? 0 : $default }; }
});
$app->instance(OrderReferenceGenerator::class, new class extends OrderReferenceGenerator {
    public function generate(?int $orderId = null): string { return ''; }
});
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
    $table->string('reference')->nullable();
    $table->integer('user_id')->default(0);
    $table->text('user_info')->nullable();
    $table->text('delivery_info')->nullable();
    $table->text('payment_info')->nullable();
    $table->text('products')->nullable();
    $table->decimal('cost', 12, 5)->default(0);
    $table->char('currency', 3)->default('USD');
    $table->integer('status')->default(1);
    $table->integer('payment_status')->default(0);
    $table->boolean('do_not_call')->default(false);
    $table->text('comment')->nullable();
    $table->string('lang')->nullable();
    $table->string('domain')->nullable();
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

$checks = 0;
$check = static function (bool $passed, string $label) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException($label);
    }
    $checks++;
};
$flow = new PaymentFlow();
$schedule = null;
Event::listen(PaymentSchedule::EVENT, static function (array $params) use (&$schedule): void {
    if ($schedule !== null) {
        $params['schedule'] = $schedule;
    }
});
$checkout = static function (string $method = 'instpsp', float $cost = 100): sCheckout {
    $checkout = (new ReflectionClass(sCheckout::class))->newInstanceWithoutConstructor();
    $checkout->registerPaymentMethod(new InstallmentPsp());
    $checkout->registerPaymentMethod(new FullCostPsp());
    Closure::bind(function () use ($method, $cost): void {
        $this->orderData = [
            'products' => [['id' => 1, 'title' => 'Service', 'quantity' => 1, 'price' => $cost]],
            'cost' => $cost,
            'currency' => 'EUR',
            'user' => ['id' => 0, 'first_name' => 'Customer', 'email' => 'customer@shop.test'],
            'payment' => ['method' => $method],
        ];
    }, $checkout, sCheckout::class)();
    return $checkout;
};
$_SESSION = [];
$charge = static fn () => end(RecordingMethod::$charges);

// 3. No listener: no payment_request and the full cost (BC).
$result = $checkout()->processOrder();
$check($result['success'] === true && count(RecordingMethod::$charges) === 1, 'Order placed and charged once');
$check(!isset($charge()['payment_request']) && (float)$charge()['cost'] === 100.0, 'Without a schedule the method charges the cost');
$check(!isset($result['order']->payment_info['schedule']), 'No schedule stored');

// 1. Schedule 30/70: deposit at checkout, final by payment link.
$schedule = [['kind' => 'deposit', 'amount' => 30, 'due' => 'checkout', 'label' => 'Booking deposit'], ['kind' => 'final', 'amount' => '70.00', 'due' => 'manual']];
RecordingMethod::$outcome = ['success' => true, 'schedule' => 'overwritten by provider', 'redirectTo' => 'https://psp.test/pay'];
$result = $checkout()->processOrder();
$order = $result['order'];
$request = $charge()['payment_request'];
$check($result['success'] === true && $result['redirectTo'] === 'https://psp.test/pay', 'Scheduled checkout succeeds');
$check($request['kind'] === 'deposit' && $request['amount'] === 30.0 && $request['currency'] === 'EUR' && $request['scheduleItem'] === '1'
    && $request['orderIdentifier'] === $order->identifier && $request['key'] === "installment/{$order->id}/1/3000", 'Checkout requests the deposit');
$stored = $order->fresh()->payment_info['schedule'];
$check(count($stored) === 2 && (float)$stored[0]['amount'] === 30.0 && array_diff_key($stored[0], ['amount' => 0]) === ['id' => '1', 'kind' => 'deposit', 'due' => 'checkout', 'label' => 'Booking deposit']
    && $stored[1]['due'] === 'manual' && $order->fresh()->payment_info['redirectTo'] === 'https://psp.test/pay', 'Schedule survives merging the provider result');
$flow->record($order, new LedgerEntry('deposit', 'captured', 30, 'EUR', 'instpsp', 'pi_deposit'));
$check((int)$order->fresh()->payment_status === sOrder::PAYMENT_STATUS_PARTIALLY_PAID, 'Deposit capture projects PARTIALLY_PAID');
$schedule = null;
RecordingMethod::$outcome = ['success' => true];
$payment = $checkout()->payOrder('instpsp', ['order' => $order->identifier, 'payment_request' => ['kind' => 'final', 'amount' => 1]]);
$request = $charge()['payment_request'];
$check(($payment['success'] ?? null) === true && $request['kind'] === 'final' && $request['amount'] === 70.0 && $request['scheduleItem'] === '2', 'Pay link requests the final 70 regardless of the client amount');
$summary = $flow->summary($order->fresh());
$check($summary->outstandingMinor() === 7000 && $summary->nextInstallment['amountMinor'] === 7000 && $summary->nextInstallment['kind'] === 'final', 'Summary exposes the balance due and next installment');
$flow->record($order, new LedgerEntry('final', 'captured', 70, 'EUR', 'instpsp', 'pi_final'));
$calls = count(RecordingMethod::$charges);
$payment = $checkout()->payOrder('instpsp', ['order' => $order->identifier]);
$check($payment['success'] === false && $payment['code'] === 409 && count(RecordingMethod::$charges) === $calls, 'Nothing is charged once the schedule is paid');
// Refunds do not reopen a paid installment.
$flow->recordRefunded($order, 20, 'EUR', 'instpsp', 're_1', 'pi_final');
$summary = $flow->summary($order->fresh());
$check($summary->outstandingMinor() === 2000 && $summary->nextInstallment === null, 'A refund needs a new explicit collection');
// A client-sent request on an unscheduled order is replaced by the unpaid rest.
$plain = sOrder::query()->whereKey(1)->first();
$checkout()->payOrder('instpsp', ['order' => $plain->identifier, 'payment_request' => ['amount' => 1]]);
$check($charge()['payment_request']['amount'] === 100.0 && $charge()['payment_request']['kind'] === 'final'
    && $charge()['payment_request']['key'] === "installment/{$plain->id}/1/10000", 'Client payment_request is never passed through');
$calls = count(RecordingMethod::$charges);
$checkout('fullpsp')->payOrder('fullpsp', ['order' => $plain->identifier, 'payment_request' => ['amount' => 1]]);
$check(count(RecordingMethod::$charges) === $calls + 1 && !isset($charge()['payment_request']), 'Methods without installment support get no payment_request (BC)');

// 5. Without a schedule the pay link asks for the unpaid rest only.
$flow->record($plain, new LedgerEntry('final', 'captured', 40, 'EUR', 'instpsp', 'pi_plain_40'));
$checkout()->payOrder('instpsp', ['order' => $plain->identifier]);
$check($charge()['payment_request']['amount'] === 60.0 && $charge()['payment_request']['key'] === "installment/{$plain->id}/1/6000", 'Pay link requests the unpaid 60 of 100');
$flow->recordAuthorized($plain, 'final', 60, 'EUR', 'instpsp', 'pi_plain_60');
$calls = count(RecordingMethod::$charges);
$payment = $checkout()->payOrder('instpsp', ['order' => $plain->identifier]);
$check($payment['code'] === 409 && count(RecordingMethod::$charges) === $calls, 'An open authorization is not requested again');
$flow->record($plain, new LedgerEntry('final', 'captured', 60, 'EUR', 'instpsp', 'pi_plain_60'));
$payment = $checkout()->payOrder('instpsp', ['order' => $plain->identifier]);
$check($payment['code'] === 409 && count(RecordingMethod::$charges) === $calls, 'A paid order is not charged again');
// A surcharge after payment is requested as the rest.
$plain->cost = 112.5;
$plain->save();
$checkout()->payOrder('instpsp', ['order' => $plain->identifier]);
$check($charge()['payment_request']['amount'] === 12.5 && (int)$plain->fresh()->payment_status === sOrder::PAYMENT_STATUS_PARTIALLY_PAID,
    'A cost increase re-projects the status and requests the difference');
$plain->cost = 90;
$plain->save();
$summary = $flow->summary($plain->fresh());
$check($summary->overpaidMinor() === 1000 && $summary->outstandingMinor() === 0 && (int)$plain->fresh()->payment_status === sOrder::PAYMENT_STATUS_PAID,
    'A lowered cost shows the overpayment and stays paid');

// 2. An invalid schedule fails before the order is saved or charged.
$schedule = [['kind' => 'deposit', 'amount' => 30, 'due' => 'checkout'], ['kind' => 'final', 'amount' => 60, 'due' => 'manual']];
$orders = sOrder::query()->count();
$calls = count(RecordingMethod::$charges);
$result = $checkout()->processOrder();
$check($result['success'] === false && $result['message'] === __('sCommerce::global.payment_schedule_invalid'), 'Invalid schedule fails checkout');
$check(sOrder::query()->count() === $orders && count(RecordingMethod::$charges) === $calls, 'No order and no charge for an invalid schedule');
$warning = array_values(array_filter($app['log']->records, static fn ($record) => $record[1] === 'sCommerce rejected an invalid payment schedule'));
$check(count($warning) === 1 && str_contains($warning[0][2]['reason'], 'add up') && !isset($warning[0][2]['order']), 'Rejection logged with the reason only');
foreach ([[['kind' => 'refund', 'amount' => 100]], [['kind' => 'final', 'amount' => 0], ['kind' => 'final', 'amount' => 100]],
             [['kind' => 'final', 'amount' => 100, 'due' => 'tomorrow']], ['not an item']] as $invalid) {
    try {
        PaymentSchedule::normalize($invalid, 100);
        $check(false, 'Invalid schedule accepted: ' . json_encode($invalid));
    } catch (InvalidArgumentException) {
        $checks++;
    }
}

// 4. A method without installment support cannot accept a scheduled checkout.
$schedule = [['kind' => 'deposit', 'amount' => 50], ['kind' => 'final', 'amount' => 50, 'due' => 'manual']];
$result = $checkout('fullpsp')->processOrder();
$check($result['success'] === false && $result['message'] === __('sCommerce::global.payment_schedule_unsupported')
    && sOrder::query()->count() === $orders && count(RecordingMethod::$charges) === $calls, 'Full-cost method refuses a schedule before saving');

// A deposit due manually is not charged at checkout.
$schedule = [['kind' => 'deposit', 'amount' => 40, 'due' => 'manual'], ['kind' => 'final', 'amount' => 60, 'due' => 'on_status:7']];
$result = $checkout()->processOrder();
$order = $result['order'];
$check($result['success'] === true && count(RecordingMethod::$charges) === $calls, 'Manual deposit: nothing charged at checkout');
$schedule = null;
$checkout()->payOrder('instpsp', ['order' => $order->identifier]);
$check($charge()['payment_request']['amount'] === 40.0 && $charge()['payment_request']['kind'] === 'deposit', 'Manual deposit payable by pay link');
// A partial capture of an installment requests only its rest; pending payments are reserved.
$flow->record($order, new LedgerEntry('deposit', 'captured', 15, 'EUR', 'instpsp', 'pi_part'));
$checkout()->payOrder('instpsp', ['order' => $order->identifier]);
$check($charge()['payment_request']['amount'] === 25.0 && $charge()['payment_request']['key'] === "installment/{$order->id}/1/2500", 'Partially paid installment requests its rest');
$flow->record($order, new LedgerEntry('deposit', 'pending', 25, 'EUR', 'instpsp', 'pi_pending'));
$calls = count(RecordingMethod::$charges);
$payment = $checkout()->payOrder('instpsp', ['order' => $order->identifier]);
$check($payment['success'] === false && count(RecordingMethod::$charges) === $calls, 'A pending payment reserves its installment; the final is not due yet');
// The final is payable only from the configured order status.
$order->status = sOrder::ORDER_STATUS_DELIVERED;
$order->save();
$payment = $checkout()->payOrder('instpsp', ['order' => $order->identifier]);
$check(($payment['success'] ?? null) === true && $charge()['payment_request']['amount'] === 60.0 && $charge()['payment_request']['kind'] === 'final', 'Status-gated final payable once the status is reached');
$order->status = sOrder::ORDER_STATUS_COMPLETED;
$order->history = [['status' => sOrder::ORDER_STATUS_DELIVERED]];
$check(PaymentSchedule::isDue(['due' => 'on_status:7'], $order) && !PaymentSchedule::isDue(['due' => 'on_status:9'], $order), 'Status from history counts');
$payment = $checkout()->payOrder('fullpsp', ['order' => $order->identifier]);
$check($payment['success'] === false && $payment['code'] === 422, 'Pay link refuses a method without installment support');

// The final installment absorbs cost changes; the deposit stays as agreed.
$items = PaymentSchedule::normalize([['kind' => 'deposit', 'amount' => 30], ['kind' => 'final', 'amount' => 70, 'due' => 'manual']], 100);
$check(PaymentSchedule::next($items, 3000, 8500, 11500)['amountMinor'] === 8500, 'A surcharge of 15 raises the final to 85');
$check(PaymentSchedule::next($items, 3000, 5500, 8500)['amountMinor'] === 5500, 'A lighter item lowers the final to 55');
$check(PaymentSchedule::next($items, 0, 2000, 2000)['amountMinor'] === 2000 && PaymentSchedule::next($items, 0, 2000, 2000)['kind'] === 'deposit',
    'A cost below the deposit caps the deposit');
$check(PaymentSchedule::next($items, 3000, 0, 2500) === null, 'Nothing is requested once the lowered cost is paid');
$check(PaymentSchedule::next($items, 10000, 1500, 11500)['amountMinor'] === 1500 && PaymentSchedule::next($items, 10000, 1500, 11500)['id'] === '2',
    'A surcharge after full payment is requested as the final');
$check(PaymentSchedule::next($items, 10000, 2000, 10000) === null, 'A refund still does not reopen the final');

// Repeated kinds keep stable item ids.
$items = PaymentSchedule::normalize([['kind' => 'deposit', 'amount' => 20], ['kind' => 'deposit', 'amount' => '30.5'], ['kind' => 'final', 'amount' => 49.5]], '100.00000');
$next = PaymentSchedule::next($items, 2000, 8000);
$check(array_column($items, 'id') === ['1', '2', '3'] && $next['id'] === '2' && $next['amountMinor'] === 3050, 'Second deposit is next after the first');
$check(PaymentSchedule::next($items, 0, 1000)['amountMinor'] === 1000, 'Requests are capped at the balance due');

// Quick orders resolve the schedule before their first save.
$source = file_get_contents($package . '/src/Checkout/sCheckout.php');
$quick = substr($source, strpos($source, 'public static function quickOrder'), strpos($source, 'public function payOrder') - strpos($source, 'public static function quickOrder'));
$check(strpos($quick, 'PaymentSchedule::resolve($order)') !== false && strpos($quick, 'PaymentSchedule::resolve($order)') < strpos($quick, "EventScope::run('storefront_quick'"), 'Quick order resolves the schedule before saving');
$check(sCommerce::supportsIntegration('payment-schedule'), 'Feature key payment-schedule');

echo "$checks payment schedule checks passed\n";
