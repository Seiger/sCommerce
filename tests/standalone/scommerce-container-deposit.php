<?php

// php tests/standalone/scommerce-container-deposit.php
// Isolated returnable container deposit checks (water bottles): first purchase with a deposit,
// exchange without a new deposit, returns from an order or without one, cash or credit.
// In-memory SQLite, fake products, no CMS/site bootstrap.
require __DIR__ . '/fixtures/checkout-harness.php';

use Illuminate\Http\Request;
use Seiger\sCommerce\Cart\sCart;
use Seiger\sCommerce\Checkout\sCheckout;
use Seiger\sCommerce\Controllers\ContainerReturnController;
use Seiger\sCommerce\Controllers\OrderPaymentActionController;
use Seiger\sCommerce\Models\sContainerReturn;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Payment\LedgerEntry;
use Seiger\sCommerce\Payment\PaymentActionException;
use Seiger\sCommerce\Payment\PaymentFlow;
use Seiger\sCommerce\Services\ContainerDeposit;
use Seiger\sCommerce\Services\OrderCostRecalculator;
use Seiger\sCommerce\Services\OrderPaymentAction;
use Seiger\sCommerce\sCommerce;

$schema->create('s_products', static function ($table): void {
    $table->bigIncrements('id');
    $table->string('sku')->default('');
});
(require $package . '/database/migrations/2026_10_20_000006_add_container_deposits.php')->up();
(require $package . '/database/migrations/2026_10_20_000002_create_payment_operations_table.php')->up();
$check($schema->hasColumn('s_products', 'deposit') && $schema->hasTable('s_container_returns'), 'Migration adds the deposit column and the returns journal');

$water = new FakeProduct(10, 'WATER-19', 120.0);
$water->deposit = 250.0;
$shop->products = [10 => $water, 11 => new FakeProduct(11, 'CUP', 5.0)];
$shop->config['basic.deliveries_on'] = 0;
$shop->config['product.container_terms'] = 'Bottles are returnable containers under clause 5 of the offer.';
// Switched off (the default): no deposits, no standalone returns.
$check(ContainerDeposit::unit($water) === 0.0 && !isset($cartLines([10 => [0 => 1]])[0]['deposit_unit']), 'Off: no deposit is charged');
$_SESSION = ['mgrValidated' => true, '_token' => 'csrf'];
$check((new ContainerReturnController())->save(Request::create('/', 'POST', ['_token' => 'csrf']))['message'] === 'container_return_invalid', 'Off: standalone returns refused');
$module = file_get_contents($package . '/module/sCommerceModule.php');
$check(str_contains($module, "sCommerce::config('product.container_deposit_on', 0) == 1 && Schema::hasTable('s_container_returns')")
    && str_contains(file_get_contents($package . '/views/productTab.blade.php'), "@if(sCommerce::config('product.container_deposit_on', 0) == 1)"), 'Off: no tab and no product field');
$_SESSION = [];
$shop->config['product.container_deposit_on'] = 1;
$flow = new PaymentFlow();
$rows = static fn (sOrder $order) => sOrderPayment::query()->where('order_id', $order->id)->orderBy('id')->get();

// First purchase: water 120 + bottle deposit 250 = 370.
$cart = (function () { $_SESSION['sCart'] = [10 => [0 => 1], 11 => [0 => 1]]; return (new sCart())->getMiniCart(); })();
$items = array_column($cart['items'], null, 'id');
$check($items[10]['deposit_unit'] === 250.0 && $items[10]['deposit_quantity'] === 1 && $items[10]['exchange_quantity'] === 0
    && $items[10]['deposit_amount'] === 250.0 && $items[10]['deposit_returnable'] === true
    && $items[10]['deposit_terms'] === 'Bottles are returnable containers under clause 5 of the offer.' && !isset($items[11]['deposit_unit']), 'Deposit line fields');
$check($cart['totalGoods'] === 125.0 && $cart['totalDeposit'] === 250.0 && $cart['totalSum'] === 375.0 && $cart['totalDepositFormatted'] === '250.00 EUR', 'Totals: goods plus deposit');
$shop->config['product.vat_rate'] = 20;
$shop->config['product.vat_display_on'] = 1;
$cart = (new sCart())->getMiniCart();
$items = array_column($cart['items'], null, 'id');
$check($items[10]['priceNetAsFloat'] === 100.0 && $cart['totalSumNet'] === 354.17, 'The deposit is outside VAT');
$shop->config['product.vat_rate'] = 0;
$_SESSION['sCart'] = [10 => [0 => 1]];
$result = $checkout((new sCart())->getMiniCart()['items'], ['cost' => 370])->processOrder();
$first = sOrder::query()->find($result['order']->id);
$check($result['success'] && (float)$first->cost === 370.0 && $first->products[0]['deposit_amount'] === 250 && $first->products[0]['deposit_returnable'] === true, 'First purchase pays 370');

// Customer declarations waive the deposit unseen: off unless the shop allows them.
$_SESSION['sCart'] = [10 => [0 => ['quantity' => 1, 'exchange' => 1]]];
$check((new sCart())->setContainerExchange(10, 0, 1)['success'] === false && (new sCart())->getMiniCart()['totalSum'] === 370.0
    && sCheckout::quickOrder(['productId' => 10, 'quantity' => 1, 'exchange' => 1, 'email' => 'quick@shop.test'])['order']->products[0]['deposit_amount'] === 250,
    'Without container_exchange_on, declared exchanges are ignored');
$check(str_contains(file_get_contents($package . '/src/Http/routes.php'), 'ContainerDeposit::exchangeEnabled()'), 'The exchange route needs container_exchange_on');
$shop->config['product.container_exchange_on'] = 1;

// Next purchase with an exchange: no new deposit.
$_SESSION['sCart'] = [10 => [0 => 1]];
$exchange = (new sCart())->setContainerExchange(10, 0, 5);
$check($exchange['success'] && $_SESSION['sCart'][10][0] === ['quantity' => 1, 'exchange' => 1] && $exchange['miniCart']['totalSum'] === 120.0
    && $exchange['miniCart']['items'][0]['deposit_amount'] === 0.0 && $exchange['miniCart']['items'][0]['exchange_quantity'] === 1, 'Exchange: pays 120');
(new sCart())->addProduct(10, 0, 3);
$cart = (new sCart())->getMiniCart();
$check($_SESSION['sCart'][10][0] === ['quantity' => 3, 'exchange' => 1] && $cart['items'][0]['deposit_quantity'] === 2 && $cart['totalSum'] === 860.0, 'Changing the quantity keeps the exchange');
$check(!(new sCart())->setContainerExchange(99, 0, 1)['success'], 'Unknown lines are refused');
$quick = sCheckout::quickOrder(['productId' => 10, 'quantity' => 2, 'exchange' => 1, 'email' => 'quick@shop.test']);
$check($quick['success'] && (float)sOrder::query()->find($quick['order']->id)->cost === 490.0, 'Quick order: 2 × 120 + one new deposit');

// The courier finds a different exchange: the manager edits it and the cost follows.
$edited = ContainerDeposit::applyToLines([array_replace($first->products[0], ['exchange_quantity' => 1])]);
$check($edited[0]['deposit_amount'] === 0.0 && OrderCostRecalculator::recalculate(370, $first->products, $edited, 'EUR') === 120.0, 'Manager exchange edit recalculates the cost');
$check(ContainerDeposit::applyToLines([array_replace($first->products[0], ['exchange_quantity' => 9])])[0]['exchange_quantity'] === 1, 'Exchange is capped at the quantity');

// Return from the order (cash): the deposit is refunded and the order keeps only the water.
$flow->record($first, new LedgerEntry('final', 'captured', 370, 'EUR', 'card', 'pi_first'));
$service = new ContainerDeposit();
$return = $service->returnContainers(['reference' => 'r1', 'sourceOrder' => $first, 'lineIndex' => 0, 'quantity' => 1, 'settlement' => 'cash'], 42);
$first = $first->fresh();
$refund = $rows($first)->last();
$check((float)$first->cost === 120.0 && $first->products[0]['deposit_returned_quantity'] === 1 && (int)$first->payment_status === sOrder::PAYMENT_STATUS_PAID, 'Order cost drops to 120 and stays paid');
$check($refund->kind === 'refund' && (float)$refund->amount === 250.0 && $refund->provider === ContainerDeposit::PROVIDER && $flow->summary($first)->outstandingMinor() === 0, 'Refund recorded in the order ledger');
$check($return->amount === '250.00' && $return->settlement === 'cash' && $return->source_order_id === $first->id && $return->actor_id === 42
    && $return->terms === 'Bottles are returnable containers under clause 5 of the offer.', 'Return journal row with the contract wording');
$again = $service->returnContainers(['reference' => 'r1', 'sourceOrder' => $first, 'lineIndex' => 0, 'quantity' => 1, 'settlement' => 'cash'], 42);
$check($again->id === $return->id && $rows($first)->count() === 2 && (float)$first->fresh()->cost === 120.0, 'A resubmitted return records once');
$refuse = static function (callable $call, string $key) use (&$checks): void {
    try {
        $call();
        throw new RuntimeException("Accepted: $key");
    } catch (PaymentActionException $exception) {
        if ($exception->messageKey !== $key) {
            throw new RuntimeException("Expected $key, got {$exception->messageKey}");
        }
        $checks++;
    }
};
$refuse(fn () => $service->returnContainers(['reference' => 'r2', 'sourceOrder' => $first, 'lineIndex' => 0, 'quantity' => 1, 'settlement' => 'cash'], 42), 'container_return_exceeds');
$unpaid = sOrder::query()->find($quick['order']->id);
$refuse(fn () => $service->returnContainers(['reference' => 'r3', 'sourceOrder' => $unpaid, 'lineIndex' => 0, 'quantity' => 1, 'settlement' => 'cash'], 42), 'container_return_unpaid');

// Standalone cash return: only the journal records the payout.
$payments = sOrderPayment::query()->count();
$standalone = $service->returnContainers(['reference' => 's1', 'product' => $water, 'quantity' => 2, 'settlement' => 'cash', 'customer' => ['name' => 'Olena', 'phone' => '+380501112233']], 42);
$check($standalone->amount === '500.00' && $standalone->source_order_id === null && $standalone->customer === ['name' => 'Olena', 'phone' => '+380501112233']
    && sOrderPayment::query()->count() === $payments, 'Standalone return: journal only');

// Credit toward a new order: the return pays part of it.
$new = (new sOrder())->forceFill(['identifier' => 'new-order', 'cost' => 370, 'currency' => 'EUR', 'products' => []]);
$new->save();
$credited = $service->returnContainers(['reference' => 's2', 'unit' => 250, 'quantity' => 1, 'settlement' => 'credit', 'creditOrder' => $new], 42);
$check($credited->credit_order_id === $new->id && $rows($new)->first()->provider === ContainerDeposit::PROVIDER && (float)$rows($new)->first()->amount === 250.0
    && $flow->summary($new->fresh())->outstandingMinor() === 12000 && (int)$new->fresh()->payment_status === sOrder::PAYMENT_STATUS_PARTIALLY_PAID, 'Credit pays 250 of the new order');
$refuse(fn () => $service->returnContainers(['reference' => 's3', 'unit' => 250, 'quantity' => 1, 'settlement' => 'credit', 'creditOrder' => $new], 42), 'container_credit_exceeds');
// A return from a paid order credited to another order moves the money between them.
$second = (new sOrder())->forceFill(['identifier' => 'second', 'cost' => 370, 'currency' => 'EUR', 'products' => [$first->products[0] + ['deposit_returned_quantity' => 0]]]);
$second->products = [array_replace($first->products[0], ['deposit_returned_quantity' => 0])];
$second->save();
$flow->record($second, new LedgerEntry('final', 'captured', 370, 'EUR', 'card', 'pi_second'));
$target = (new sOrder())->forceFill(['identifier' => 'target', 'cost' => 250, 'currency' => 'EUR', 'products' => []]);
$target->save();
$service->returnContainers(['reference' => 'r4', 'sourceOrder' => $second, 'lineIndex' => 0, 'quantity' => 1, 'settlement' => 'credit', 'creditOrder' => $target], 42);
$check((float)$second->fresh()->cost === 120.0 && (int)$second->fresh()->payment_status === sOrder::PAYMENT_STATUS_PAID
    && (int)$target->fresh()->payment_status === sOrder::PAYMENT_STATUS_PAID, 'Transfer: source keeps the water, target is paid');
$shop->config['product.container_terms'] = 'Changed later';
$check(sContainerReturn::query()->where('reference', 'r1')->value('terms') === 'Bottles are returnable containers under clause 5 of the offer.', 'Stored contract wording does not change');

// Manager endpoints: the order panel action and the standalone form.
$_SESSION = ['mgrValidated' => true, '_token' => 'csrf'];
$app->mgrUser = 7;
$app->permissions = ['exec_module', 'scommerce_payment_record'];
$third = (new sOrder())->forceFill(['identifier' => 'third', 'cost' => 370, 'currency' => 'EUR', 'products' => [array_replace($first->products[0], ['deposit_returned_quantity' => 0])]]);
$third->save();
$flow->record($third, new LedgerEntry('final', 'captured', 370, 'EUR', 'card', 'pi_third'));
$panel = (new OrderPaymentAction())->panel($third);
$check(isset($panel['orderActions']['containers']) && str_contains($panel['containerLines'][0], '(1 × 250.00)'), 'Panel offers the container return');
$post = static fn (array $input) => (new OrderPaymentActionController())->handle(Request::create('/', 'POST', $input + ['_token' => 'csrf', 'action' => 'containers', 'order' => $third->id]));
$check($post(['nonce' => 'forged', 'line' => 0, 'count' => 1])->getStatusCode() === 403, 'Forged nonce refused');
$response = $post(['nonce' => $panel['orderActions']['containers'], 'line' => 0, 'count' => 1, 'settlement' => 'cash']);
$check($response->getStatusCode() === 200 && (float)$third->fresh()->cost === 120.0, 'Panel return succeeds');
$check($post(['nonce' => $panel['orderActions']['containers'], 'line' => 0, 'count' => 1, 'settlement' => 'cash'])->getStatusCode() === 200
    && (float)$third->fresh()->cost === 120.0, 'Resubmitted panel return records once');
$app->permissions = ['exec_module'];
$check($post(['nonce' => $panel['orderActions']['containers'], 'line' => 0, 'count' => 1])->getStatusCode() === 403, 'Permission required');
$app->permissions = ['exec_module', 'scommerce_payment_record'];
$nonce = OrderPaymentAction::issueNonce(0, 0, ContainerReturnController::NONCE_ACTION);
$save = static fn (array $input) => (new ContainerReturnController())->save(Request::create('/', 'POST', $input + ['_token' => 'csrf', 'nonce' => $nonce]));
$check($save(['quantity' => 1, 'settlement' => 'cash'])['message'] === 'container_return_invalid', 'A standalone return needs a product or an amount');
$check($save(['quantity' => 3, 'product' => 10, 'settlement' => 'cash', 'customer' => ['name' => 'Petro']]) === ['success' => true, 'message' => 'container_return_success']
    && sContainerReturn::query()->latest('id')->first()->amount === '750.00', 'Standalone form records the return');
$check($save(['quantity' => 3, 'product' => 10, 'settlement' => 'cash'])['success'] && sContainerReturn::query()->where('amount', '750.00')->count() === 1, 'Resubmitted form records once');
// Products sold by measure never carry a deposit.
$shop->config['product.container_deposit_on'] = 1;
$bulk = new FakeProduct(77, 'BULK-WATER', 0.9);
$bulk->deposit = 0.5;
$bulk->measure = 3;
$check(ContainerDeposit::unit($bulk) === 0.0, 'Measured products have no deposit unit');
$skipped = ContainerDeposit::applyToLines([['deposit_unit' => 0.5, 'quantity' => 2000, 'measure' => 'volume', 'deposit_amount' => 0.0]]);
$check($skipped[0]['deposit_amount'] === 0.0 && !isset($skipped[0]['deposit_quantity']), 'Measured lines are skipped defensively');
$check(sCommerce::supportsIntegration('container-deposit'), 'Feature key container-deposit');

echo "$checks container deposit checks passed (isolated SQLite; no site database)\n";
