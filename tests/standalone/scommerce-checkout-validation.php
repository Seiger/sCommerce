<?php

if ($installedCheckout = getenv('SCOMMERCE_TEST_CHECKOUT')) require $installedCheckout;
require __DIR__ . '/fixtures/checkout-harness.php';

use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Checkout\sCheckout;
use Seiger\sCommerce\Models\sOrder;

$product = new FakeProduct(1, 'RULES', 10);
$shop->products = [1 => $product];
$shop->config['basic.deliveries_on'] = 0;
$shop->config['basic.payments_on'] = 0;
$instance = $checkout([['id' => 1, 'quantity' => 1, 'priceAsFloat' => 10]]);
$baseRules = $instance->getValidationRules([]);
$check(isset($baseRules['user.first_name']), 'Default checkout rules are preserved');
$check($baseRules === $instance->getValidationRules([], 'default'), 'Explicit default context matches the omitted argument');
$quickRules = $instance->getValidationRules([], 'quick');
$check(isset($quickRules['quantity'], $quickRules['phone'], $quickRules['email']) && !isset($quickRules['user.first_name']), 'Quick context selects quick input rules');
$before = sOrder::count();
$result = $instance->processOrder();
$check($result['success'] && sOrder::count() === $before + 1, 'Normal checkout accepts valid standard fields without plugins');
$result = sCheckout::quickOrder(['productId' => 1, 'phone' => '123456789']);
$check($result['success'] && $result['order']->products[0]['quantity'] === 1 && (float) $result['order']->cost === 10.0, 'Omitted quick quantity defaults to one');

$seen = [];
Event::listen('sCommerce.CheckoutValidationRules', static function (array $params) use (&$seen): void {
    $seen[] = $params;
    $params['rules']['products'][] = static function ($field, $items, $fail): void {
        foreach ($items as $item) {
            if (($item['quantity'] ?? 0) < 3) $fail('Project quantity policy');
        }
    };
});
$rules = $instance->getValidationRules(['user' => ['email' => 'test@shop.test']]);
$check(isset($rules['user.first_name'], $rules['user.email']), 'Plugins extend the standard rule set');
$check($instance->setOrderData(['comment' => 'Partial update'])['success'], 'Partial form validation ignores absent product lines');
$before = sOrder::count();
$charges = count(RecordingPayment::$charges);
$result = $checkout([['id' => 1, 'quantity' => 1, 'priceAsFloat' => 10]])->processOrder();
$check(!$result['success'] && str_contains($result['message'], 'Project quantity policy'), 'Direct processOrder validates plugin rules');
$check(sOrder::count() === $before && count(RecordingPayment::$charges) === $charges, 'Failure precedes order and payment');
$result = sCheckout::quickOrder(['productId' => 1, 'quantity' => 1, 'phone' => '123456789', 'products' => [['id' => 1, 'quantity' => 999]]]);
$check(!$result['success'] && $result['message'] === 'Project quantity policy', 'Quick rules use prepared products instead of forged input');
$check(sOrder::count() === $before, 'Invalid quick order is not persisted');
$result = sCheckout::quickOrder(['productId' => 1, 'quantity' => 3, 'phone' => '123456789']);
$check($result['success'] && sOrder::count() === $before + 1, 'Valid quick order succeeds');
$result = $checkout([['id' => 1, 'quantity' => 3, 'priceAsFloat' => 10]])->processOrder();
$check($result['success'] && sOrder::count() === $before + 2, 'Valid normal order succeeds');
$check(in_array('quick', array_column($seen, 'context'), true) && in_array('default', array_column($seen, 'context'), true), 'Both checkout types share the same rule event');
$before = sOrder::count();
$result = $checkout([['id' => 1, 'quantity' => 3, 'priceAsFloat' => 10]], ['user' => ['first_name' => '']])->processOrder();
$check(!$result['success'] && sOrder::count() === $before, 'Commerce standard rules also run before persistence');
// Every quick request must dispatch the rules once, with raw quantities visible to plugins.
foreach (['1.5', 1.5, 'abc', [], 0, -1, '999999999999999999999999'] as $rawQuantity) {
    $seenBefore = count($seen);
    $ordersBefore = sOrder::count();
    $result = sCheckout::quickOrder(['productId' => 1, 'quantity' => $rawQuantity, 'phone' => '123456789']);
    $check(!$result['success'] && sOrder::count() === $ordersBefore, 'Invalid raw quantity cannot create a quick order');
    $check(count($seen) === $seenBefore + 1, 'Quick order invokes the rule event once');
    $payload = $seen[array_key_last($seen)];
    $check($payload['data']['quantity'] === $rawQuantity && $payload['data']['products'][0]['quantity'] === $rawQuantity, 'Both input and prepared line retain the raw quantity');
}
$seenBefore = count($seen);
$result = sCheckout::quickOrder(['productId' => 1, 'quantity' => '3', 'phone' => '123456789']);
$check($result['success'] && count($seen) === $seenBefore + 1, 'Valid string quantity is validated once');
$check($result['order']->products[0]['quantity'] === 3 && (float) $result['order']->cost === 30.0, 'Accepted quantity becomes integer before totals and persistence');
$_SESSION['sCart'] = [1 => [0 => 3]];
// Product quick orders resolve the cart facade for stock checks; a request starts with the session cart.
Illuminate\Support\Facades\Facade::clearResolvedInstance('sCart');
$seenBefore = count($seen);
$result = sCheckout::quickOrder(['productId' => 0, 'phone' => '123456789']);
$check($result['success'] && count($seen) === $seenBefore + 1, 'Cart quick checkout also validates once');
$check($result['order']->products[0]['quantity'] === 3 && (float) $result['order']->cost === 30.0, 'Cart quick checkout preserves server quantities and total');

echo "OK: {$checks} checkout validation rule checks; isolated SQLite.\n";
