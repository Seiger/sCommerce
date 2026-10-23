<?php

// php tests/standalone/scommerce-webhook-dispatch.php
// Isolated payment-callback dispatch checks: in-memory SQLite, no CMS/site bootstrap or router.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function evo() { return Illuminate\Container\Container::getInstance(); }
if (!function_exists('app')) {
    function app($abstract = null, array $parameters = []) { return $abstract === null ? evo() : evo()->make($abstract, $parameters); }
}
if (!function_exists('request')) {
    function request() { return app('request'); }
}
if (!function_exists('response')) {
    function response($content = '', $status = 200, array $headers = []) { return new Illuminate\Http\Response($content, $status, $headers); }
}
if (!function_exists('config')) {
    function config($key = null, $default = null) { return app('config')->get($key, $default); }
}
if (!function_exists('route')) {
    function route($name, $parameters = [], $absolute = true) { return $name . '?' . http_build_query($parameters); }
}
require __DIR__ . '/bootstrap.php';

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Interfaces\WebhookPaymentMethodInterface;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Models\sPaymentMethod;
use Seiger\sCommerce\Payment\CashPayment;
use Seiger\sCommerce\sCommerce;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

$app = new Container();
Container::setInstance($app);
Facade::setFacadeApplication($app);
$app->instance('log', new class {
    public array $records = [];
    public function channel($name = null) { return $this; }
    public function __call($level, $arguments) { $this->records[] = [$level, $arguments[0], $arguments[1] ?? []]; }
});
$app->instance('config', new Repository(['seiger' => ['settings' => ['sCommerce' => ['basic' => ['legacy_webhook_signatures' => 1]]]]]));
$app->instance('db.transactions', new DatabaseTransactionsManager());
$db = new Capsule($app);
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->setEventDispatcher(new Dispatcher($app));
$db->setAsGlobal();
$db->bootEloquent();
$app->instance('db', $db->getDatabaseManager());
$app->instance('db.schema', $db->schema());
$package = dirname(__DIR__, 2);
$db->schema()->create('s_payment_methods', static function ($table): void {
    $table->id();
    $table->uuid('uuid')->nullable();
    $table->string('name');
    $table->string('class');
    $table->string('identifier')->default('');
    $table->boolean('active')->default(true);
    $table->integer('position')->default(0);
    foreach (['title', 'description', 'credentials', 'settings'] as $column) {
        $table->text($column)->nullable();
    }
    $table->string('mode')->default('');
    $table->timestamps();
});
$db->schema()->create('s_orders', static function ($table): void {
    $table->bigIncrements('id');
    $table->string('uuid')->nullable();
    $table->string('identifier')->nullable();
    $table->decimal('cost', 12, 5)->default(0);
    $table->char('currency', 3)->default('USD');
    $table->integer('status')->default(1);
    $table->integer('payment_status')->default(0);
    $table->timestamps();
    $table->timestamp('deleted_at')->nullable();
});
(require $package . '/database/migrations/2026_03_01_000000_create_order_payments_table.php')->up();

$checks = 0;
$check = static function (bool $passed, string $label) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException($label);
    }
    $checks++;
};

class StubWebhookPayment extends CashPayment implements WebhookPaymentMethodInterface
{
    public static array $calls = [];
    public function getName(): string { return 'stub'; }
    public function matchesWebhook(Request $request): bool { return $request->headers->has('X-Stub'); }
    public function handleWebhook(Request $request): HttpResponse
    {
        self::$calls[] = ['identifier' => $this->getIdentifier(), 'request' => $request, 'scope' => EventScope::current()];
        return new Response('stub:' . $this->getIdentifier(), 202, ['X-Handled' => 'yes']);
    }
}
class DuckPayment extends CashPayment
{
    public function getName(): string { return 'duck'; }
    public function canHandleWebhook($request): bool { return $request->input('duck') === '1'; }
    public function handleWebhook() { return response('duck', 200); }
}
class StripeNamedPayment extends CashPayment
{
    public function getName(): string { return 'stripe'; }
}
class BoomPayment extends CashPayment implements WebhookPaymentMethodInterface
{
    public function getName(): string { return 'boom'; }
    public function matchesWebhook(Request $request): bool { return false; }
    public function handleWebhook(Request $request): HttpResponse { throw new RuntimeException('handler exploded'); }
}
class LegacyShop extends sCommerce
{
    public bool $legacyCalled = false;
    // A subclass written for 1.4 keeps the original one-parameter signature.
    public function handlePaymentCallback(?string $method = null): HttpResponse
    {
        $this->legacyCalled = true;
        return parent::handlePaymentCallback($method);
    }
}

// The complete webhook example from the English docs is the fixture, so it is syntax-checked and exercised.
$docs = file_get_contents($package . '/docs/en/developers/payments/index.md');
preg_match_all('/```php\n(.*?)```/s', $docs, $blocks);
$example = current(array_filter($blocks[1], static fn ($block) => str_contains($block, 'class ExamplePspPayment')));
$check(is_string($example) && str_starts_with($example, '<?php namespace EvolutionCMS\Main\Payment;'), 'Docs contain the webhook example');
$file = tempnam(sys_get_temp_dir(), 'scommerce-docs-example');
file_put_contents($file, $example);
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);
unlink($file);
$check($exitCode === 0, 'Docs webhook example passes php -l');
eval(substr($example, strlen('<?php ')));
foreach (['update(', "'paid_at'", "'status' => 'paid'", '->total', 'routes/web.php'] as $stale) {
    $check(!str_contains($docs, $stale), 'Payments docs no longer use ' . $stale);
}

$rows = [
    ['stub', StubWebhookPayment::class, '', 1], ['stub', StubWebhookPayment::class, 'a', 2], ['stub', StubWebhookPayment::class, 'b', 3],
    ['duck', DuckPayment::class, '', 4], ['stripe', StripeNamedPayment::class, '', 5], ['boom', BoomPayment::class, '', 6],
    ['nohandler', CashPayment::class, '', 7], ['examplepsp', EvolutionCMS\Main\Payment\ExamplePspPayment::class, '', 8],
];
foreach ($rows as [$name, $class, $identifier, $position]) {
    $method = new sPaymentMethod(['name' => $name, 'class' => $class, 'identifier' => $identifier, 'position' => $position, 'active' => $name !== 'duck']);
    $method->credentials = $name === 'examplepsp' ? json_encode(['webhook_secret' => 'whsec_test']) : '[]';
    $method->settings = '[]';
    $method->save();
}
$shop = new sCommerce();
$call = static function (Request $request, ?string $method = null, ?string $identifier = null, ?sCommerce $service = null) use ($app, $shop): HttpResponse {
    $app->instance('request', $request);
    $service ??= $shop;
    return $identifier === null ? $service->handlePaymentCallback($method) : $service->handleIdentifiedPaymentCallback($method, $identifier);
};
$notices = static fn () => array_values(array_filter($app['log']->records, static fn ($record) => $record[0] === 'notice'));

// 1. Interface method by URL receives the Request; its Response is returned verbatim.
$request = Request::create('/payment-callback/stub', 'POST', ['x' => 1]);
$response = $call($request, 'stub');
$check($response->getStatusCode() === 202 && $response->getContent() === 'stub:stub' && $response->headers->get('X-Handled') === 'yes', 'Interface response returned verbatim');
$check(StubWebhookPayment::$calls[0]['request'] === $request && StubWebhookPayment::$calls[0]['scope']['source'] === 'payment_callback', 'Handler receives the request inside a payment_callback scope');

// 2 and 7. Identified rows.
$check($call(Request::create('/', 'POST'), 'stub', 'b')->getContent() === 'stub:stubb', 'Identified route reaches identifier b');
$check($call(Request::create('/', 'POST'), 'stub', 'a')->getContent() === 'stub:stuba', 'Identified route reaches identifier a');
$before = count(StubWebhookPayment::$calls);
$check($call(Request::create('/', 'POST'), 'stub', 'zzz')->getStatusCode() === 404 && count(StubWebhookPayment::$calls) === $before, 'Unknown identifier never falls back to another account');
$legacyShop = new LegacyShop();
$check($call(Request::create('/', 'POST'), 'stub', 'b', $legacyShop)->getContent() === 'stub:stubb' && !$legacyShop->legacyCalled, 'Identified route uses its own entry point on subclasses');
$check($call(Request::create('/', 'POST'), 'stub', null, $legacyShop)->getContent() === 'stub:stub' && $legacyShop->legacyCalled, 'One-parameter subclass signature still works');

// 3. Resolution by matchesWebhook() without a URL method.
$check($call(Request::create('/', 'POST', [], [], [], ['HTTP_X_STUB' => '1']))->getContent() === 'stub:stub', 'Resolved by matchesWebhook()');

// 4. Duck-typed legacy method, inactive row.
$app['log']->records = [];
$check($call(Request::create('/', 'POST', ['duck' => '1']))->getContent() === 'duck', 'Duck-typed matcher and handler still work, even when inactive');
$call(Request::create('/', 'POST', ['duck' => '1']));
$check(count($notices()) === 2 && str_contains($notices()[0][1], 'DuckPayment') && str_contains($notices()[1][1], 'without arguments'), 'Deprecation notices logged once per request');

// 5. Legacy signatures.
$stripeRequest = Request::create('/', 'POST', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=x']);
$check($call($stripeRequest)->getStatusCode() === 501, 'Legacy Stripe signature resolves the stripe row while enabled');
$app['config']->set('seiger.settings.sCommerce.basic.legacy_webhook_signatures', 0);
$check($call($stripeRequest)->getStatusCode() === 400, 'Disabled legacy signatures leave the request unresolved');
$app['config']->set('seiger.settings.sCommerce.basic.legacy_webhook_signatures', 1);

// 6. Missing handler and exceptions.
$check($call(Request::create('/', 'POST'), 'nohandler')->getStatusCode() === 501, 'Method without a handler returns 501');
$app['log']->records = [];
$response = $call(Request::create('/', 'POST'), 'boom');
$check($response->getStatusCode() === 500 && $response->getContent() === 'CALLBACK ERROR', 'Handler exception returns 500');
$check($app['log']->records[0][0] === 'error' && $app['log']->records[0][2]['error'] === 'handler exploded', 'Handler exception logged');
$check($call(Request::create('/', 'POST'), 'unknown')->getStatusCode() === 400, 'Unresolved method returns 400');

// Callback URLs.
$check(sCommerce::paymentCallbackUrl(new StubWebhookPayment('')) === 'payment.callback?method=stub', 'Callback URL without identifier');
$check(sCommerce::paymentCallbackUrl(new StubWebhookPayment('b')) === 'payment.callback.identified?method=stub&identifier=b', 'Identified callback URL');

// The docs example end to end.
$order = new sOrder();
$order->forceFill(['identifier' => 'ord-key-1', 'cost' => 25, 'currency' => 'EUR']);
$order->save();
$events = [];
Event::listen('sCommerce.*', static function (string $name, array $payload) use (&$events): void { $events[] = [$name, $payload[0]]; });
$signed = static function (array $event, string $secret = 'whsec_test'): Request {
    $payload = json_encode($event + ['order_key' => 'ord-key-1', 'currency' => 'eur', 'livemode' => false]);
    return Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_EXAMPLE_SIGNATURE' => hash_hmac('sha256', $payload, $secret)], $payload);
};
$paid = $signed(['id' => 'evt_1', 'type' => 'payment.succeeded', 'payment_id' => 'pay_1', 'amount' => 2500]);
$response = $call($paid, 'examplepsp');
$row = sOrderPayment::where('provider_ref', 'pay_1')->first();
$check($response->getStatusCode() === 200 && $response->getContent() === 'OK', 'Example webhook acknowledged');
$check($row->provider === 'examplepsp-test' && $row->status === 'captured' && (float)$row->amount === 25.0 && $row->metadata['event_id'] === 'evt_1', 'Example records the capture under the test namespace');
$check((int)$order->fresh()->payment_status === sOrder::PAYMENT_STATUS_PAID, 'Example capture projects PAID');
$recorded = $events[array_search('sCommerce.PaymentRecorded', array_column($events, 0), true)][1];
$check($recorded['source'] === 'payment_callback', 'Ledger event carries the payment_callback source');
$check($call($signed(['id' => 'evt_1b', 'type' => 'payment.succeeded', 'payment_id' => 'pay_1', 'amount' => 2500]))->getContent() === 'OK'
    && sOrderPayment::count() === 1, 'Replayed callback resolved by matchesWebhook() changes nothing');
$check($call($signed(['id' => 'evt_x', 'type' => 'payment.succeeded', 'payment_id' => 'pay_2', 'amount' => 2500], 'wrong'), 'examplepsp')->getStatusCode() === 400
    && sOrderPayment::count() === 1, 'Invalid signature rejected without writes');
$call($signed(['id' => 'evt_2', 'type' => 'refund.succeeded', 'payment_id' => 'pay_1', 'refund_id' => 're_1', 'amount' => 1000]), 'examplepsp');
$check((int)$order->fresh()->payment_status === sOrder::PAYMENT_STATUS_PARTIALLY_REFUNDED, 'Example refund projects PARTIALLY_REFUNDED');
$check($call($signed(['id' => 'evt_3', 'type' => 'dispute.created', 'payment_id' => 'pay_unknown']), 'examplepsp')->getStatusCode() === 503, 'Dispute before its payment asks for a retry');
$app['log']->records = [];
$response = $call($signed(['id' => 'evt_4', 'type' => 'payment.succeeded', 'payment_id' => 'pay_1', 'amount' => 9900]), 'examplepsp');
$check($response->getContent() === 'REJECTED' && $app['log']->records[0][0] === 'error', 'Conflicting replay rejected and logged');
$check(sCommerce::supportsIntegration('webhook-interface'), 'Feature detection');

echo "$checks webhook dispatch checks passed (isolated SQLite; no site database)\n";
