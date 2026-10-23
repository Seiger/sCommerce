<?php

// php tests/standalone/scommerce-payment-ledger.php
// Isolated payment ledger checks: in-memory SQLite by default, no CMS/site bootstrap.
// Set SCOMMERCE_TEST_DB_DRIVER=mysql (or pgsql) plus SCOMMERCE_TEST_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD
// to run the same checks against a disposable MySQL/MariaDB/PostgreSQL database (its tables are dropped).
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
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Payment\LedgerConflictException;
use Seiger\sCommerce\Payment\LedgerEntry;
use Seiger\sCommerce\Payment\PaymentFlow;
use Seiger\sCommerce\Payment\PaymentSummary;
use Seiger\sCommerce\sCommerce;

$app = new Container();
Container::setInstance($app);
Facade::setFacadeApplication($app);
$app->instance('log', new class {
    public array $records = [];
    public function channel($name = null) { return $this; }
    public function __call($level, $arguments) { $this->records[] = [$level, $arguments[0], $arguments[1] ?? []]; }
});
$app->instance('db.transactions', new DatabaseTransactionsManager());
$driver = getenv('SCOMMERCE_TEST_DB_DRIVER') ?: 'sqlite';
$db = new Capsule($app);
$db->addConnection($driver === 'sqlite' ? ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true] : [
    'driver' => $driver, 'host' => getenv('SCOMMERCE_TEST_DB_HOST') ?: '127.0.0.1', 'port' => getenv('SCOMMERCE_TEST_DB_PORT') ?: '3306',
    'database' => getenv('SCOMMERCE_TEST_DB_DATABASE') ?: 'scommerce_test', 'username' => getenv('SCOMMERCE_TEST_DB_USERNAME') ?: 'root',
    'password' => getenv('SCOMMERCE_TEST_DB_PASSWORD') ?: '', 'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4', 'collation' => $driver === 'pgsql' ? null : 'utf8mb4_unicode_ci',
]);
$db->setEventDispatcher(new Dispatcher($app));
$db->setAsGlobal();
$db->bootEloquent();
$app->instance('db', $db->getDatabaseManager());
$app->instance('db.schema', $db->schema());
$schema = $db->schema();
$schema->dropIfExists('s_order_payments');
$schema->dropIfExists('s_orders');
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
// The real ledger migration, including the named unique index and the foreign key.
(require dirname(__DIR__, 2) . '/database/migrations/2026_03_01_000000_create_order_payments_table.php')->up();

$checks = 0;
$check = static function (bool $passed, string $label) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException($label);
    }
    $checks++;
};
$throws = static function (callable $callback, string $class) {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception instanceof $class ? $exception : throw $exception;
    }
    return null;
};
$events = [];
Event::listen('sCommerce.*', static function (string $name, array $payload) use (&$events): void {
    $events[] = [$name, $payload[0]];
});
$names = static function () use (&$events): array {
    return array_map(static fn (array $event) => $event[0], $events);
};
$warnings = static fn () => array_values(array_filter($app['log']->records, static fn ($record) => $record[0] === 'warning'));
$newOrder = static function (float $cost = 100, string $currency = 'EUR'): sOrder {
    $order = new sOrder();
    $order->forceFill(['identifier' => bin2hex(random_bytes(8)), 'cost' => $cost, 'currency' => $currency, 'status' => 1, 'payment_status' => 0]);
    $order->save();
    return $order;
};
$flow = new PaymentFlow();
$entry = static fn (string $status, float $amount = 100, ?string $ref = 'pi_1', string $kind = 'final', string $provider = 'stripe', string $currency = 'EUR', ?string $parent = null, array $metadata = []) =>
    new LedgerEntry($kind, $status, $amount, $currency, $provider, $ref, $parent, $metadata);

// 1. Idempotent replay.
$order = $newOrder();
$events = [];
$first = $flow->record($order, $entry('captured'));
$second = $flow->record($order, $entry('captured'));
$check($first->created && $first->changed && $first->previousStatus === null, 'First record creates');
$check(!$second->created && !$second->changed && $second->previousStatus === 'captured' && $second->payment->id === $first->payment->id, 'Replay changes nothing');
$check(sOrderPayment::where('order_id', $order->id)->count() === 1, 'Replay keeps one row');
$check(count(array_keys($names(), 'sCommerce.PaymentRecorded')) === 1, 'Exactly one PaymentRecorded');
$recorded = $events[array_search('sCommerce.PaymentRecorded', $names(), true)][1];
$check(array_slice(array_keys($recorded), 0, 16) === ['event', 'payment', 'paymentId', 'orderId', 'orderUuid', 'sequence', 'kind', 'status', 'amount',
    'amountMinor', 'currency', 'provider', 'providerRef', 'parentProviderRef', 'metadata', 'source'], 'PaymentRecorded payload keys');
$check($recorded['amount'] === 100.0 &&$recorded['amountMinor'] === 10000 && $recorded['currency'] === 'EUR' && $recorded['providerRef'] === 'pi_1'
    && $recorded['orderUuid'] === $order->uuid && $recorded['sequence'] === 1 && $recorded['source'] === 'system', 'PaymentRecorded payload values');
$check(!empty($first->payment->captured_at) && (int)$order->payment_status === sOrder::PAYMENT_STATUS_PAID, 'Capture sets captured_at and projects PAID');

// 2. authorized -> captured on one reference.
$order = $newOrder();
$events = [];
$flow->recordAuthorized($order, 'final', 100, 'EUR', 'stripe', 'pi_2');
$check((int)$order->payment_status === sOrder::PAYMENT_STATUS_AUTHORIZED, 'Authorization projects AUTHORIZED');
$events = [];
$result = $flow->record($order, $entry('captured', 100, 'pi_2'));
$check(!$result->created && $result->changed && $result->previousStatus === 'authorized', 'Transition reported');
$check(sOrderPayment::where('order_id', $order->id)->count() === 1, 'Transition keeps one row');
$changed = array_values(array_filter($events, static fn ($event) => $event[0] === 'sCommerce.PaymentStatusChanged'));
$check(count($changed) === 1 && $changed[0][1]['oldStatus'] === 'authorized' && $changed[0][1]['newStatus'] === 'captured', 'PaymentStatusChanged authorized -> captured');
$projection = array_values(array_filter($events, static fn ($event) => $event[0] === 'sCommerce.OrderPaymentStatusChanged'));
$check(count($projection) === 1 && $projection[0][1]['oldPaymentStatus'] === 11 && $projection[0][1]['newPaymentStatus'] === 3, 'OrderPaymentStatusChanged authorized(11) -> paid(3)');

$scopedOrder = $newOrder();
$flow->recordAuthorized($scopedOrder, 'final', 100, 'EUR', 'stripe', 'pi_order');
$events = [];
EventScope::run('payment_callback', 0, fn () => $flow->record($scopedOrder, $entry('captured', 100, 'pi_order')));
$check($names() === ['sCommerce.PaymentStatusChanged', 'sCommerce.OrderUpdated', 'sCommerce.OrderPaymentStatusChanged'], 'Ledger change dispatched before its order projection');

// 3. Late failure after capture is ignored.
$events = [];
$app['log']->records = [];
$late = $flow->recordFailed($order, 'final', 100, 'EUR', 'stripe', 'pi_2');
$check(!$late->changed && $late->payment->status === 'captured' && $events === [], 'Late failure ignored without events');
$check(count($warnings()) === 1 && $warnings()[0][2]['from'] === 'captured' && $warnings()[0][2]['to'] === 'failed', 'Ignored transition logged');

// 4 and 13. Conflicting replays.
$other = $newOrder();
$check($throws(fn () => $flow->record($other, $entry('captured', 100, 'pi_2')), LedgerConflictException::class) !== null, 'Same reference on another order conflicts');
$check($throws(fn () => $flow->record($order, $entry('captured', 99.99, 'pi_2')), LedgerConflictException::class) !== null, 'Same reference with another amount conflicts');
$check($throws(fn () => $flow->record($order, $entry('captured', 100, 'pi_2', 'deposit')), LedgerConflictException::class) !== null, 'Same reference with another kind conflicts');
$check($throws(fn () => $flow->record($order, $entry('captured', 100, 'pi_2', 'final', 'stripe', 'CZK')), InvalidArgumentException::class) !== null, 'Currency other than the order currency rejected');
$live = $flow->record($other, $entry('captured', 100, 'pi_same', 'final', 'stripe-live'));
$test = $flow->record($other, $entry('authorized', 100, 'pi_same', 'final', 'stripe-test'));
$check($live->created && $test->created && $live->payment->id !== $test->payment->id, 'Account/environment namespaces never collide');
$check($throws(fn () => $flow->record($other, $entry('captured', 100, 'pi_x', 'final', 'Stripe Live!')), InvalidArgumentException::class) !== null, 'Invalid provider namespace rejected');

// 5 and 15. A concurrent duplicate between lookup and insert returns the existing row.
$order = $newOrder();
$race = true;
$db->getConnection()->listen(static function ($query) use (&$race, $db, $order): void {
    if ($race && str_contains($query->sql, 'provider_ref') && str_contains(strtolower($query->sql), 'select')) {
        $race = false;
        $db->table('s_order_payments')->insert(['order_id' => $order->id, 'sequence' => 1, 'kind' => 'final', 'status' => 'captured',
            'amount' => 100, 'currency' => 'EUR', 'provider' => 'stripe', 'provider_ref' => 'pi_race', 'created_at' => date('Y-m-d H:i:s')]);
    }
});
$raced = $flow->record($order, $entry('captured', 100, 'pi_race'));
$check(!$race && !$raced->created && !$raced->changed && sOrderPayment::where('provider_ref', 'pi_race')->count() === 1, 'Unique-index race returns the existing row (' . $driver . ')');
$check(sOrderPayment::where('order_id', $order->id)->count() === 1, 'No second row after the race');
$check($throws(function () use ($db) {
    $db->table('s_order_payments')->insert(['order_id' => 999999, 'sequence' => 1, 'kind' => 'final', 'status' => 'captured', 'amount' => 1,
        'currency' => 'EUR', 'provider' => 'x', 'provider_ref' => 'fk', 'created_at' => date('Y-m-d H:i:s')]);
}, Illuminate\Database\QueryException::class) !== null, 'Other integrity errors are real errors');

// 6. Deposit and final, then refunds.
$order = $newOrder();
$flow->record($order, $entry('captured', 30, 'pi_dep', 'deposit'));
$check((int)$order->payment_status === sOrder::PAYMENT_STATUS_PARTIALLY_PAID, 'Deposit 30 of 100 projects PARTIALLY_PAID');
$flow->record($order, $entry('captured', 70, 'pi_fin', 'final'));
$check((int)$order->payment_status === sOrder::PAYMENT_STATUS_PAID, 'Final 70 projects PAID');
$events = [];
$refund = $flow->recordRefunded($order, 20, 'EUR', 'stripe', 're_1', 'pi_fin');
$check((int)$order->payment_status === sOrder::PAYMENT_STATUS_PARTIALLY_REFUNDED, 'Refund 20 projects PARTIALLY_REFUNDED');
$refundEvent = $events[array_search('sCommerce.PaymentRecorded', $names(), true)][1];
$check($refundEvent['kind'] === 'refund' && $refundEvent['parentProviderRef'] === 'pi_fin' && $refundEvent['amount'] === 20.0, 'Refund event carries its parent');
$flow->recordRefunded($order, 80, 'EUR', 'stripe', 're_2', 'pi_dep');
$check((int)$order->payment_status === sOrder::PAYMENT_STATUS_REFUNDED, 'Refunds of 100 project REFUNDED');
$summary = $flow->summary($order);
$check($summary->capturedMinor === 10000 && $summary->refundedMinor === 10000 && $summary->netMinor() === 0 && $summary->outstandingMinor() === 10000, 'Summary totals in minor units');
$check(PaymentSummary::decimal(1050) === '10.50' && PaymentSummary::decimal(-5) === '-0.05', 'Decimal formatting');
$check(array_column(sOrderPayment::where('order_id', $order->id)->orderBy('sequence')->get()->toArray(), 'sequence') === [1, 2, 3, 4], 'Sequences increase per order');

// 14. Refund lifecycle and parent rules.
$order = $newOrder();
$flow->record($order, $entry('captured', 100, 'pi_r'));
$flow->record($order, $entry('pending', 40, 're_p', 'refund', 'stripe', 'EUR', 'pi_r'));
$check((int)$order->payment_status === sOrder::PAYMENT_STATUS_PAID, 'Pending refund changes no totals');
$done = $flow->record($order, $entry('refunded', 40, 're_p', 'refund', 'stripe', 'EUR', 'pi_r'));
$again = $flow->record($order, $entry('refunded', 40, 're_p', 'refund'));
$check($done->changed && !$again->changed && (int)$order->payment_status === sOrder::PAYMENT_STATUS_PARTIALLY_REFUNDED, 'Pending refund completes once');
$check(!$flow->record($order, $entry('captured', 40, 're_p', 'refund'))->changed, 'A refund never becomes a capture');
$check($throws(fn () => $flow->record($order, $entry('refunded', 40, 're_p', 'refund', 'stripe', 'EUR', 'pi_other')), LedgerConflictException::class) !== null, 'Parent of a refund is immutable');
$check($throws(fn () => $flow->recordRefunded($order, 5, 'EUR', 'stripe', 're_unknown', 'pi_missing'), InvalidArgumentException::class) !== null, 'Unknown parent rejected');
$check($throws(fn () => $flow->recordRefunded($order, 5, 'EUR', 'stripe', 're_foreign', 'pi_dep'), LedgerConflictException::class) !== null, 'Parent on another order rejected');
$check($throws(fn () => $flow->record($order, $entry('refunded', 5, 'pi_new', 'final')), InvalidArgumentException::class) !== null, 'A payment cannot start refunded');
$check($throws(fn () => $flow->record($order, $entry('captured', 5, 'pi_p', 'final', 'stripe', 'EUR', 'pi_r')), InvalidArgumentException::class) !== null, 'Only refunds have parents');

// Disputes, cancellation and late success.
$order = $newOrder();
$flow->record($order, $entry('captured', 50, 'pi_d1', 'deposit'));
$flow->record($order, $entry('captured', 50, 'pi_d2', 'final'));
$flow->recordDisputed($order, 'stripe', 'pi_d1');
$check((int)$order->payment_status === sOrder::PAYMENT_STATUS_DISPUTED, 'A dispute on a non-last payment projects DISPUTED');
$flow->record($order, $entry('captured', 50, 'pi_d1', 'deposit'));
$check((int)$order->payment_status === sOrder::PAYMENT_STATUS_PAID, 'A won dispute returns to PAID without new money');
$check($throws(fn () => $flow->recordCanceled($order, 'stripe', 'pi_unknown'), OutOfBoundsException::class) !== null, 'Cancel of an unknown reference is retryable');
$order = $newOrder();
$flow->recordFailed($order, 'final', 100, 'EUR', 'stripe', 'pi_late');
$check((int)$order->payment_status === sOrder::PAYMENT_STATUS_FAILED, 'Failure projects FAILED');
$flow->record($order, $entry('captured', 100, 'pi_late'));
$check((int)$order->payment_status === sOrder::PAYMENT_STATUS_PAID, 'Late success after failure is accepted');
$order = $newOrder();
$flow->recordAuthorized($order, 'final', 100, 'EUR', 'stripe', 'pi_void');
$flow->recordCanceled($order, 'stripe', 'pi_void');
$check((int)$order->payment_status === sOrder::PAYMENT_STATUS_CANCELED, 'Voided authorization projects CANCELED');

// 7. Legacy whole-row refund and partial refunds without an amount.
$order = $newOrder();
$flow->record($order, $entry('captured', 100, 'pi_legacy'));
$override = $flow->applyManagerOverride($order, sOrder::PAYMENT_STATUS_REFUNDED, 42);
$check((int)$order->payment_status === sOrder::PAYMENT_STATUS_REFUNDED, 'Legacy captured row set to refunded projects REFUNDED');
$check($override['payment_ledger_updated']['status'] === 'refunded' && $override['user_id'] === 42, 'Manager override returns a history entry');
$row = sOrderPayment::where('provider_ref', 'pi_legacy')->first();
$check($row->metadata['admin_override']['from'] === 'captured' && $row->metadata['admin_override']['manager_user_id'] === 42, 'Override audited in array metadata');
$order = $newOrder();
$flow->record($order, $entry('captured', 100, 'pi_partial'));
$flow->applyManagerOverride($order, sOrder::PAYMENT_STATUS_PARTIALLY_REFUNDED, 42);
$summary = $flow->summary($order);
$check($summary->refundedMinor === 0 && $summary->unresolvedLegacyRefunds === 1 && (int)$order->payment_status === sOrder::PAYMENT_STATUS_PARTIALLY_REFUNDED, 'Partial refund without an amount changes no totals');
$row = sOrderPayment::where('provider_ref', 'pi_partial')->first();
$metadata = $row->metadata;
$metadata[PaymentFlow::CONTEXT_KEY]['refunded_amount'] = '25.00';
$row->metadata = $metadata;
$row->save();
$check($flow->summary($order)->refundedMinor === 2500, 'Explicit legacy returned amount is used');
$order = $newOrder(80);
$created = $flow->applyManagerOverride($order, sOrder::PAYMENT_STATUS_PAID, 42);
$row = sOrderPayment::find($created['payment_ledger_created']['payment_id']);
$check($row->currency === 'EUR' && (float)$row->amount === 80.0 && $row->provider === 'manual' && (int)$order->payment_status === 3, 'Manager-created row uses the order currency, not UAH');

// 8. Currency isolation.
$order = $newOrder();
sOrderPayment::create(['order_id' => $order->id, 'sequence' => 1, 'kind' => 'final', 'status' => 'captured', 'amount' => 100, 'currency' => 'CZK', 'provider' => 'legacy']);
$app['log']->records = [];
$flow->syncOrderPaymentStatusFromLedger($order);
$flow->syncOrderPaymentStatusFromLedger($order);
$check((int)$order->payment_status === sOrder::PAYMENT_STATUS_PENDING && $flow->summary($order)->ignoredCurrencies === ['CZK'], 'Foreign-currency rows are ignored');
$check(count($warnings()) === 1 && $warnings()[0][2]['ignored_currencies'] === ['CZK'], 'Foreign currency warned once per order');

// 9. Historic metadata encodings.
$check(PaymentFlow::normalizeMetadata(json_encode(json_encode(['a' => 1]))) === ['a' => 1], 'Double-encoded metadata readable');
$check(PaymentFlow::normalizeMetadata('{"a":1}') === ['a' => 1] && PaymentFlow::normalizeMetadata(['a' => 1]) === ['a' => 1], 'JSON and array metadata readable');
$check(PaymentFlow::normalizeMetadata(null) === [] && PaymentFlow::normalizeMetadata('broken') === [], 'Empty and broken metadata are empty');
$order = $newOrder();
$db->table('s_order_payments')->insert(['order_id' => $order->id, 'sequence' => 1, 'kind' => 'final', 'status' => 'authorized', 'amount' => 100,
    'currency' => 'EUR', 'provider' => 'old', 'provider_ref' => 'evt_old', 'metadata' => json_encode(json_encode(['old' => true])), 'created_at' => date('Y-m-d H:i:s')]);
$check($flow->updateLastPaymentStatus($order, 'captured', 7, 'note'), 'Deprecated last-row update still works');
$row = sOrderPayment::where('provider_ref', 'evt_old')->first();
$check($row->metadata['old'] === true && $row->metadata['admin_override']['to'] === 'captured', 'Old double-encoded metadata kept and rewritten as an array');
$check(is_array(json_decode($row->getRawOriginal('metadata'), true)), 'New metadata is single-encoded');
$check($flow->recordCaptured($order, 'final', 100, 'EUR', 'old', 'evt_old') === 1, 'recordCaptured returns the existing sequence for a legacy reference');

// Metadata validation and reserved context.
$order = $newOrder();
$scoped = EventScope::run('afina', 5, fn () => $flow->record($order, $entry('captured', 100, 'pi_ctx', 'final', 'stripe', 'EUR', null,
    ['charge' => 'ch_1', PaymentFlow::CONTEXT_KEY => ['source' => 'forged']])), 'flow-1', 'evt-1', 'afina');
$context = $scoped->payment->metadata[PaymentFlow::CONTEXT_KEY];
$check($scoped->payment->metadata['charge'] === 'ch_1' && $context['source'] === 'afina' && $context['actor_id'] === 5
    && $context['correlation_id'] === 'flow-1' && $context['causation_id'] === 'evt-1' && $context['integration_actor'] === 'afina', 'Reserved context comes from the event scope');
$check($throws(fn () => $flow->record($order, $entry('captured', 1, 'pi_big', 'final', 'stripe', 'EUR', null, ['x' => str_repeat('a', PaymentFlow::MAX_METADATA_BYTES)])), InvalidArgumentException::class) !== null, 'Oversized metadata rejected');
$check($throws(fn () => $flow->record($order, $entry('captured', 0.001, 'pi_zero')), InvalidArgumentException::class) !== null, 'Zero amount rejected');
$check($flow->recordCaptured($order, 'manual', 1.5, '', 'manual') === 2 && $flow->recordCaptured($order, 'manual', 1.5, 'EUR', 'manual') === 3, 'recordCaptured without reference appends sequences');
$check(PaymentFlow::toMinor('12.345') === 1235 && PaymentFlow::toMinor('100.00000') === 10000 && PaymentFlow::toMinor(0.1 + 0.2) === 30, 'Decimal conversion to minor units');

// 10. In-memory projection.
$order = $newOrder();
$flow->record($order, $entry('captured', 100, 'pi_mem'));
$check((int)$order->payment_status === 3 && !$order->isDirty('payment_status') && (int)$db->table('s_orders')->where('id', $order->id)->value('payment_status') === 3, 'Caller order updated and clean');
$check(!str_contains(file_get_contents(dirname(__DIR__, 2) . '/src/Payment/PaymentFlow.php'), '->update('), 'PaymentFlow has no query-builder writes');

// 11. Outer rollback gives no events.
$order = $newOrder();
$events = [];
try {
    DB::transaction(function () use ($flow, $order, $entry): void {
        $flow->record($order, $entry('captured', 100, 'pi_rollback'));
        throw new RuntimeException('rollback');
    });
} catch (RuntimeException) {
}
$check($events === [] && sOrderPayment::where('provider_ref', 'pi_rollback')->count() === 0, 'Rolled-back ledger write dispatches nothing');
$check($flow->findByProviderRef('STRIPE', 'pi_1')?->provider_ref === 'pi_1' && $flow->findByProviderRef('stripe', 'nope') === null, 'findByProviderRef');
$check(sCommerce::supportsIntegration('payment-ledger-api'), 'Feature detection');

echo "$checks payment ledger checks passed ($driver; no site database)\n";
