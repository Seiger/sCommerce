<?php

// php tests/standalone/scommerce-checkout-context.php
// Isolated trusted checkout context checks: live and entitled test users on one live site,
// spoofed flags, refusal before side effects, pay links and immutability.
// In-memory SQLite, fake products and payment method, no CMS/site bootstrap.
require __DIR__ . '/fixtures/checkout-harness.php';

use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Checkout\CheckoutContext;
use Seiger\sCommerce\Checkout\sCheckout;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Payment\LedgerEntry;
use Seiger\sCommerce\Payment\PaymentFlow;
use Seiger\sCommerce\sCommerce;

$shop->products = [1 => new FakeProduct(1, 'BOOK', 25.0)];
$shop->config['basic.deliveries_on'] = 0;
$shop->config['notifications.email_template_admin_order_on'] = 1;
$shop->config['notifications.email_template_customer_order_on'] = 1;
$shop->config['notifications.email_template_admin_fast_order_on'] = 1;
$shop->config['notifications.email_template_customer_fast_order_on'] = 1;

// The project resolver: user 7 is an entitled test user; user 9 is entitled but its sandbox is not ready.
$seenData = [];
Event::listen(CheckoutContext::EVENT, static function (array $payload) use (&$seenData): void {
    $seenData[] = $payload['data'];
    $actor = $payload['context']['actorId'];
    if ($actor === 7 || $actor === 9) {
        $payload['context']['environment'] = 'test';
        $payload['context']['reference'] = ['collector' => 'demo', 'connection' => 'sandbox-1'];
        $payload['context']['testRecipients'] = ['qa@shop.test', 'not-an-email'];
    }
    if ($actor === 9) {
        $payload['context']['refusal'] = 'Test payments are not ready for this collector.';
    }
});
$place = static function (int $user, array $extra = []) use ($app, $checkout): array {
    $app->webUser = $user;
    RecordingPayment::$charges = [];
    $GLOBALS['shop']->mails = [];
    $result = $checkout([['id' => 1, 'title' => 'Book', 'sku' => 'BOOK', 'quantity' => 1, 'priceAsFloat' => 25.0]], $extra)->processOrder();
    $app->webUser = 0;
    return $result;
};
$recipients = static fn (): array => array_merge(...array_map(static fn ($mail) => $mail[0], $GLOBALS['shop']->mails ?: [[[]]]));

// 1. Guests and ordinary users are live; live notifications reach their normal recipients.
$result = $place(0);
$live = sOrder::query()->find($result['order']->id);
$check($result['success'] && CheckoutContext::of($live) === ['environment' => 'live', 'actorId' => 0, 'source' => 'storefront'], 'Guest checkout is live');
$check(RecordingPayment::$charges[0]['checkout_context']['environment'] === 'live', 'Payment method receives the context');
$check($recipients() === ['admin@shop.test', 'customer@shop.test'], 'Live notifications unchanged');
$events = [];
$result = $place(8, ['environment' => 'test', 'test' => 1, 'meta' => ['integration' => ['environment' => 'test']]]);
$ordinary = sOrder::query()->find($result['order']->id);
$check(!$ordinary->isTest() && $ordinary->meta['integration']['actorId'] === 8, 'Spoofed checkout flags are ignored');
$check(!isset(end($seenData)['environment']) && !isset(end($seenData)['test']) && !isset(end($seenData)['meta']), 'Resolvers never see client flags');
$check($events[0][1]['environment'] === 'live', 'OrderCreated reports the live environment');

// 2. An entitled test user on the same live site gets an isolated test order.
$result = $place(7);
$test = sOrder::query()->find($result['order']->id);
$context = CheckoutContext::of($test);
$check($result['success'] && $test->isTest() && $context['environment'] === 'test' && $context['actorId'] === 7
    && $context['reference'] === ['collector' => 'demo', 'connection' => 'sandbox-1'], 'Test context persisted at creation');
$check($context['testRecipients'] === ['qa@shop.test'] && RecordingPayment::$charges[0]['checkout_context']['environment'] === 'test', 'Test context reaches the payment method');
$check($recipients() === ['qa@shop.test', 'qa@shop.test'], 'Test notifications reach only the designated test recipients');
$result = $place(8);
$check(!sOrder::query()->find($result['order']->id)->isTest() && $recipients() === ['admin@shop.test', 'customer@shop.test'], 'The next ordinary user is live again');

// 3. Refusal happens before any side effect, and only for the affected user.
$orders = sOrder::query()->count();
$result = $place(9);
$check($result['success'] === false && $result['message'] === 'Test payments are not ready for this collector.', 'Resolver refusal is reported');
$check(sOrder::query()->count() === $orders && RecordingPayment::$charges === [] && $shop->mails === [], 'Refused checkout has no order, charge or notification');
$check($place(8)['success'] === true, 'Ordinary checkout stays available');
$invalid = static function (array $payload): void { $payload['context']['environment'] = 'sandbox'; };
Event::listen(CheckoutContext::EVENT, $invalid);
$orders = sOrder::query()->count();
$result = $place(8);
$check($result['success'] === false && $result['message'] === __('sCommerce::global.checkout_context_unavailable') && sOrder::query()->count() === $orders, 'Unknown environment is refused, never treated as live');
Event::forget(CheckoutContext::EVENT);
Event::listen(CheckoutContext::EVENT, static function (array $payload): void { $payload['context']['environment'] = 'test'; });
$result = $place(0);
$check($result['success'] === false && sOrder::query()->count() === $orders, 'Guests cannot get a test context');

// 4. Quick orders resolve the same context before saving and gate notifications.
$app->webUser = 7;
$shop->mails = [];
$quick = sCheckout::quickOrder(['productId' => 1, 'quantity' => 1, 'email' => 'quick@shop.test', 'environment' => 'live']);
$app->webUser = 0;
$check($quick['success'] && sOrder::query()->find($quick['order']->id)->isTest() && $recipients() === [], 'Quick test order: test context, no normal notifications');
$check($quick['order']->products[0]['weight'] === '0.0125', 'Quick order stores attribute-backed delivery weight');
Event::forget(CheckoutContext::EVENT);

// 5. Pay links use the persisted context, whoever opens them.
$pay = static function (sOrder $order, int $user, array $data = []) use ($app, $checkout): array {
    $app->webUser = $user;
    RecordingPayment::$charges = [];
    $checkout([])->payOrder('card', ['order' => $order->identifier] + $data);
    $app->webUser = 0;
    return RecordingPayment::$charges[0] ?? [];
};
$check($pay($test, 0, ['checkout_context' => ['environment' => 'live'], 'environment' => 'live'])['checkout_context']['environment'] === 'test', 'Logged-out pay link keeps the test binding');
$check($pay($live, 7)['checkout_context']['environment'] === 'live', 'A test user opening a live pay link stays live');
$check(!isset($pay($live, 0, ['sandbox' => 1])['sandbox']), 'Client sandbox flag is discarded');

// 6. The binding is immutable; other meta keys stay editable.
$test->meta = ['integration' => ['environment' => 'live'], 'booking' => ['slot' => '10:00']];
$test->save();
$test = $test->fresh();
$check($test->isTest() && $test->meta['booking']['slot'] === '10:00', 'meta.integration cannot change after creation');
$live->meta = ['integration' => ['environment' => 'test']];
$live->save();
$check(!$live->fresh()->isTest(), 'A live order cannot become a test order');
$legacy = (new sOrder())->forceFill(['identifier' => 'legacy', 'cost' => 5, 'currency' => 'EUR']);
$legacy->save();
$legacy->meta = ['integration' => ['environment' => 'test']];
$legacy->save();
$check(!$legacy->fresh()->isTest() && CheckoutContext::of($legacy->fresh()) === ['environment' => 'live'], 'Orders without a context stay live');

// 7. Operational lists and metrics exclude test orders by default.
$testIds = sOrder::query()->test()->pluck('id')->all();
$check($testIds === [(int)$test->id, (int)$quick['order']->id], 'Test view lists only test orders');
$check(sOrder::query()->live()->count() === sOrder::query()->count() - 2 && !in_array($test->id, sOrder::query()->live()->pluck('id')->all()), 'Live view excludes test orders');
$check(CheckoutContext::environmentOf(['integration' => ['environment' => 'unknown']]) === 'test', 'A damaged binding never counts as live');

// 8. Ledger entries must match the order environment.
$flow = new PaymentFlow();
try {
    $flow->record($live->fresh(), new LedgerEntry('final', 'captured', 25, 'EUR', 'card', 'pi_test_on_live', null, ['environment' => 'test']));
    $check(false, 'Test receipt accepted on a live order');
} catch (InvalidArgumentException) {
    $checks++;
}
$check($flow->record($test, new LedgerEntry('final', 'captured', 25, 'EUR', 'card', 'pi_sandbox', null, ['environment' => 'test']))->created, 'Matching test receipt recorded');
$check($flow->record($live->fresh(), new LedgerEntry('final', 'captured', 25, 'EUR', 'card', 'pi_live'))->created, 'Entries without an environment keep working');

// 9. Checkout consults the context before its first side effect in every path.
$source = file_get_contents($package . '/src/Checkout/sCheckout.php');
$process = substr($source, strpos($source, 'public function processOrder'), strpos($source, 'public static function quickOrder') - strpos($source, 'public function processOrder'));
$check(strpos($process, 'CheckoutContext::resolve(') < strpos($process, 'validatePayment(') && strpos($process, 'CheckoutContext::resolve(') < strpos($process, '$this->saveOrder()'), 'Checkout resolves the context first');
$check(substr_count($process, 'sCommerce::notifyEmail') === 0 && substr_count($source, 'sCommerce::notifyEmail') === 1, 'All notifications go through the context gate');
$check(sCommerce::supportsIntegration('checkout-context'), 'Feature key checkout-context');

echo "$checks checkout context checks passed (isolated SQLite; no site database)\n";
