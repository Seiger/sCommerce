<?php

// php tests/standalone/scommerce-stock.php
// Isolated stock provider, stock writer and order stock commitment checks: in-memory SQLite,
// the shared checkout harness, no CMS/site bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!function_exists('config')) {
    function config($key = null, $default = null) { return app('config')->get($key, $default); }
}
require __DIR__ . '/fixtures/checkout-harness.php';

use Illuminate\Config\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Cart\sCart;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Interfaces\StockProviderInterface;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Services\StockCommitter;
use Seiger\sCommerce\Services\StockResolver;
use Seiger\sCommerce\Services\StockShortageException;
use Seiger\sCommerce\Services\StockWriter;
use Seiger\sCommerce\sCommerce;

/** Provider with configurable answers that counts its calls. */
final class FakeStockProvider implements StockProviderInterface
{
    public ?int $available = null;
    public bool $commits = true;
    public bool $releases = true;
    /** @var list<string> */
    public array $calls = [];
    public function available(sProduct $product, int $modificationId = 0, array $context = []): ?int { $this->calls[] = 'available'; return $this->available; }
    public function commit(sOrder $order): bool { $this->calls[] = "commit:{$order->id}"; return $this->commits; }
    public function release(sOrder $order): bool { $this->calls[] = "release:{$order->id}"; return $this->releases; }
}

// Keep the database settings Capsule stored in the container config.
$app->instance('config', new Repository($app['config']->getAttributes()));
$setting = static function (string $mode, int $release = 1) use ($app): void {
    $app['config']->set('seiger.settings.sCommerce.product', ['inventory_on' => 2, 'inventory_commit' => $mode, 'inventory_release_on_cancel' => $release]);
};
$schema->create('s_products', static function ($table): void {
    $table->id();
    $table->uuid('uuid')->nullable();
    $table->string('sku')->default('');
    $table->smallInteger('mode')->default(0);
    $table->smallInteger('availability')->default(0);
    $table->integer('inventory')->default(0);
    $table->timestamps();
});
$migration = require $package . '/database/migrations/2026_10_20_000003_add_inventory_committed_at_to_orders.php';
$migration->up();
$migration->up();
$check($schema->hasColumn('s_orders', 'inventory_committed_at'), 'Migration adds the marker column and is idempotent');

$stockEvents = [];
Event::listen('sCommerce.ProductStockChanged', static function (array $payload) use (&$stockEvents): void {
    $stockEvents[] = $payload;
});
$product = static function (string $sku, int $inventory, int $mode = sProduct::MODE_SIMPLE) use ($db): int {
    return (int)$db->table('s_products')->insertGetId(['sku' => $sku, 'inventory' => $inventory, 'mode' => $mode, 'availability' => 1]);
};
$stock = static fn (int $id): int => (int)DB::table('s_products')->where('id', $id)->value('inventory');
$line = static fn (int $productId, int $quantity, bool $tracks = true): array => ['id' => $productId, 'product_id' => $productId, 'sku' => "p$productId",
    'quantity' => $quantity, 'tracks_inventory' => $tracks, 'requires_shipping' => $tracks];
$newOrder = static function (array $lines, array $attributes = []): sOrder {
    $order = new sOrder();
    $order->forceFill($attributes + ['identifier' => bin2hex(random_bytes(8)), 'cost' => 100, 'currency' => 'EUR', 'status' => 1, 'payment_status' => 0, 'products' => $lines]);
    $order->save();
    return $order;
};
$state = static fn (sOrder $order): array => sOrder::withTrashed()->find($order->id)->meta[StockCommitter::META_KEY] ?? [];
$committed = static fn (sOrder $order): bool => sOrder::withTrashed()->find($order->id)->inventory_committed_at !== null;
$committer = new StockCommitter();
$writer = new StockWriter();
$resolver = new StockResolver();
$a = $product('A', 10);
$b = $product('B', 4);
$service = $product('S', 0, sProduct::MODE_SERVICE);
$load = static fn (int $id): sProduct => sProduct::query()->withoutGlobalScopes()->find($id);

// 1-2. Resolution order: provider, then listener, then local inventory.
$check($resolver->available($load($a)) === 10, 'Without provider or listener the local inventory is used');
$provider = new FakeStockProvider();
$app->instance(StockProviderInterface::class, $provider);
$provider->available = 5;
$check($resolver->available($load($b)) === 5, 'Provider overrides inventory');
$provider->available = null;
Event::listen('sCommerce.ResolveProductStock', static fn (array $payload) => $payload['product']->sku === 'B' ? 7 : null);
$check($resolver->available($load($b)) === 7 && $resolver->available($load($a)) === 10, 'Provider null falls back to the listener, then to inventory');
$app->offsetUnset(StockProviderInterface::class);

// 3. StockWriter records one event per real change; identical writes record nothing.
$stockEvents = [];
$check($writer->set($a, 12, 0, 'erp') === true && $stock($a) === 12, 'Writer sets inventory');
$event = $stockEvents[0] ?? [];
$check(count($stockEvents) === 1 && $event['productId'] === $a && $event['sku'] === 'A' && $event['oldQuantity'] === 10 && $event['newQuantity'] === 12
    && $event['stockSource'] === 'erp' && $event['source'] === 'system' && $event['oldAvailability'] === 1 && $event['newAvailability'] === 1, 'ProductStockChanged payload');
$check($writer->set($a, 12, 0, 'erp') === false && $writer->set($load($a), 12, 0, 'erp', 1) === false && count($stockEvents) === 1, 'Identical write records nothing');
$writer->set($a, 12, 0, 'erp', sProduct::AVAILABILITY_ON_ORDER);
$check(count($stockEvents) === 2 && $stockEvents[1]['oldAvailability'] === 1 && $stockEvents[1]['newAvailability'] === 2 && $stockEvents[1]['newQuantity'] === 12, 'Availability-only change records once');
$rejected = 0;
foreach ([fn () => $writer->set($a, 1, 0, 'erp', 5), fn () => $writer->set($a, 1, 3), fn () => $writer->set(99999, 1)] as $call) {
    try {
        $call();
    } catch (InvalidArgumentException) {
        $rejected++;
    }
}
$check($rejected === 3 && $stock($a) === 12, 'Invalid availability, modification ids and unknown products are rejected');
$stockEvents = [];
EventScope::run('erp_sync', 0, static function () use ($writer, $a): void {
    $writer->set($a, 9, 0, 'erp');
    $writer->set($a, 12, 0, 'erp');
});
$check($stockEvents === [], 'Changes that cancel out inside one scope are dropped');
EventScope::run('erp_sync', 0, static function () use ($writer, $a): void {
    $writer->set($a, 9, 0, 'erp');
    $writer->set($a, 10, 0, 'erp', sProduct::AVAILABILITY_IN_STOCK);
});
$check(count($stockEvents) === 1 && $stockEvents[0]['oldQuantity'] === 12 && $stockEvents[0]['newQuantity'] === 10 && $stockEvents[0]['newAvailability'] === 1
    && $stockEvents[0]['source'] === 'erp_sync', 'Scope merges first old to last new');
$stockEvents = [];
try {
    DB::transaction(static function () use ($writer, $a): void {
        $writer->set($a, 1, 0, 'erp');
        throw new RuntimeException('rollback');
    });
} catch (RuntimeException) {
}
$check($stock($a) === 10 && $stockEvents === [], 'Rolled-back stock write records nothing');
$model = $load($b);
$model->inventory = 6;
$model->stockSource = 'manager';
$model->save();
$check(count($stockEvents) === 1 && $stockEvents[0]['stockSource'] === 'manager' && $stockEvents[0]['newQuantity'] === 6, 'Direct model saves (manager form, CSV import) record the event');
$writer->set($b, 4);

// 7. With `none` nothing changes.
$setting(StockCommitter::MODE_NONE);
$order = $newOrder([$line($a, 2)]);
$check($stock($a) === 10 && !$committed($order) && $state($order) === [] && $committer->reconcile() === 0, 'Mode none changes nothing');
$order->status = sOrder::ORDER_STATUS_CANCELED;
$order->save();
$check($stock($a) === 10, 'Cancelling an uncommitted order restores nothing');

// 4. order_created: tracked lines are decremented once; listener failures do not matter.
$setting(StockCommitter::MODE_ORDER_CREATED);
Event::listen('sCommerce.OrderCreated', static function (): void { throw new RuntimeException('listener failure'); });
$stockEvents = [];
$order = $newOrder([$line($a, 2), $line($b, 1), $line($a, 1), $line($service, 3, false)]);
$check($stock($a) === 7 && $stock($b) === 3 && $stock($service) === 0 && $committed($order), 'Tracked lines decremented once, service lines skipped');
$check($state($order)['committed_by'] === 'local' && $state($order)['lines'] === [$a => 3, $b => 1] && !isset($state($order)['due']), 'Committed quantities stored');
$check(count($stockEvents) === 2 && $stockEvents[0]['stockSource'] === 'order' && $stockEvents[0]['source'] === StockCommitter::SOURCE, 'Commit records stock events');
$check($committer->commit($order) === false && $committer->reconcile() === 0 && $stock($a) === 7, 'Commit is idempotent');
$order->comment = 'edited';
$order->products = [$line($a, 9)];
$order->save();
$check($stock($a) === 7, 'Editing a committed order changes no stock');

// 6. Cancellation releases the committed quantities once, even from a stale instance.
$stale = sOrder::query()->find($order->id);
$order->status = sOrder::ORDER_STATUS_CANCELED;
$order->save();
$check($stock($a) === 10 && $stock($b) === 4 && !$committed($order) && $state($order)['released_by'] === 'local', 'Cancel releases what was committed');
$check($committer->release($order) === false && $committer->reconcile() === 0 && $stock($a) === 10, 'Release happens once');
$fresh = $newOrder([$line($b, 1)]);
$staleFresh = new sOrder();
$staleFresh->setRawAttributes(array_diff_key($fresh->getAttributes(), [StockCommitter::COLUMN => 1, 'meta' => 1]), true);
$staleFresh->exists = true;
$staleFresh->status = sOrder::ORDER_STATUS_CANCELED;
$staleFresh->save();
$check($stock($b) === 4 && !$committed($fresh), 'A stale instance without the marker still releases');

// Leaving the cancelled status commits again.
$order->status = sOrder::ORDER_STATUS_NEW;
$order->save();
$check($stock($a) === 1 && $committed($order) && $state($order)['lines'] === [$a => 9], 'Restored order commits its current lines again');

// Stale meta writes keep the stored commitment state.
$stale->meta = ['booking' => ['slot' => '09:00']];
$stale->save();
$check($state($order)['lines'] === [$a => 9] && sOrder::query()->find($order->id)->meta['booking']['slot'] === '09:00', 'Stale meta writes keep the commitment state');
$order->delete();
$check($stock($a) === 10 && !$committed($order), 'Soft delete releases');

// 9. Shortage: nothing changes, the order stays due and is retried.
$order = $newOrder([$line($a, 2), $line($b, 5)]);
$check($stock($a) === 10 && $stock($b) === 4 && !$committed($order) && $state($order)['due'] === StockCommitter::MODE_ORDER_CREATED, 'Shortage commits no line');
$check(in_array('sCommerce stock commit failed', array_column($app['log']->records, 1), true), 'Shortage is logged');
try {
    $committer->commit($order);
    $check(false, 'Direct commit reports the shortage');
} catch (StockShortageException) {
}
$check($committer->reconcile() === 0, 'Reconciliation retries without effect while stock is missing');
$writer->set($b, 6, 0, 'erp');
$check($committer->reconcile() === 1 && $stock($a) === 8 && $stock($b) === 1 && $committed($order), 'Reconciliation commits once stock arrives');
$order->forceDelete();
$check($stock($a) === 10 && $stock($b) === 6, 'Force delete releases from the captured row');

// Rolled-back orders and scoped checkouts.
try {
    DB::transaction(static function () use ($newOrder, $line, $a): void {
        $newOrder([$line($a, 1)]);
        throw new RuntimeException('rollback');
    });
} catch (RuntimeException) {
}
$check($stock($a) === 10, 'Rolled-back order commits nothing');
$scoped = null;
EventScope::run('storefront', 0, static function () use ($newOrder, $line, $a, $stock, $check, &$scoped): void {
    $scoped = $newOrder([$line($a, 1)]);
    $check($stock($a) === 10, 'Commit waits for the checkout scope to end');
});
$check($stock($a) === 9 && $committed($scoped), 'Commit runs when the checkout scope ends');
$scoped->status = sOrder::ORDER_STATUS_CANCELED;
$scoped->save();

// Test orders never touch live stock.
$test = $newOrder([$line($a, 1)], ['meta' => ['integration' => ['environment' => 'test']]]);
$check($stock($a) === 10 && !$committed($test) && $committer->commit($test) === false && $committer->reconcile() === 0, 'Test orders never commit');

// Checkout listeners cannot write the commitment state.
$spoofed = $newOrder([$line($a, 1)], ['meta' => [StockCommitter::META_KEY => ['committed_by' => 'provider', 'lines' => [$a => 99]]]]);
$check($state($spoofed)['lines'] === [$a => 1] && $stock($a) === 9, 'Commitment state is server-owned');
$spoofed->status = sOrder::ORDER_STATUS_CANCELED;
$spoofed->save();

// order_paid: only the payment status Paid commits.
$setting(StockCommitter::MODE_ORDER_PAID);
$order = $newOrder([$line($a, 2)]);
$order->payment_status = sOrder::PAYMENT_STATUS_PARTIALLY_PAID;
$order->save();
$check($stock($a) === 10 && !$committed($order), 'Unpaid order does not commit');
$order->payment_status = sOrder::PAYMENT_STATUS_PAID;
$order->save();
$check($stock($a) === 8 && $committed($order), 'Paid order commits');
$order->payment_status = sOrder::PAYMENT_STATUS_REFUNDED;
$order->save();
$check($stock($a) === 8, 'A refund alone does not release stock');

// Release on cancellation can be turned off.
$setting(StockCommitter::MODE_ORDER_PAID, 0);
$order->status = sOrder::ORDER_STATUS_CANCELED;
$order->save();
$check($stock($a) === 8 && $committed($order) && $committer->reconcile() === 0, 'Release turned off keeps stock');
$setting(StockCommitter::MODE_ORDER_PAID);
$check($committer->reconcile() === 1 && $stock($a) === 10, 'Reconciliation releases once release is turned on');

// 5. A provider that commits and releases replaces local changes.
$setting(StockCommitter::MODE_ORDER_CREATED);
$app->instance(StockProviderInterface::class, $provider);
$provider->calls = [];
$order = $newOrder([$line($a, 3)]);
$check($stock($a) === 10 && $committed($order) && $state($order)['committed_by'] === 'provider' && $provider->calls === ["commit:{$order->id}"], 'Provider commit skips local decrement');
$provider->releases = false;
$order->status = sOrder::ORDER_STATUS_CANCELED;
$order->save();
$check($stock($a) === 10 && $committed($order), 'Provider-committed order is never restored locally');
$provider->releases = true;
$check($committer->reconcile() === 1 && !$committed($order) && $stock($a) === 10 && $state($order)['released_by'] === 'provider', 'Provider release retried by reconciliation');
$provider->commits = false;
$order = $newOrder([$line($a, 3)]);
$check($stock($a) === 7 && $state($order)['committed_by'] === 'local', 'Provider that does not handle commit falls back to local');
$order->status = sOrder::ORDER_STATUS_CANCELED;
$order->save();
$check($stock($a) === 10 && end($provider->calls) === "commit:{$order->id}", 'Locally committed stock is restored locally without the provider');
$app->offsetUnset(StockProviderInterface::class);

// 8. The cart clamps to the resolved quantity.
$shop->config['product.inventory_on'] = 1;
$shop->products[50] = new FakeProduct(50, 'CART', 10.0, sProduct::MODE_SIMPLE, null, 100);
$shop->products[51] = new FakeProduct(51, 'SERVICE', 10.0, sProduct::MODE_SERVICE, null, 0);
Event::listen('sCommerce.ResolveProductStock', static fn (array $payload) => $payload['product']->sku === 'CART' ? 2 : null);
$_SESSION = [];
$response = (new sCart())->addProduct(50, 0, 5);
$check($response['success'] && $_SESSION['sCart'][50][0] === 2, 'Cart clamps to the resolved quantity');
$response = (new sCart())->addProduct(51, 0, 3);
$check($response['success'] && $_SESSION['sCart'][51][0] === 3, 'Services are not clamped');

// Settings, diagnostics, capability and translations.
$defaults = require $package . '/config/sCommerceSettings.php';
$check($defaults['product']['inventory_commit'] === 'none' && $defaults['product']['inventory_release_on_cancel'] === 1, 'Commitment is off by default');
$check(StockCommitter::configurationWarnings() === [], 'Valid settings have no warnings');
$app['config']->set('seiger.settings.sCommerce.product', ['inventory_on' => 0, 'inventory_commit' => 'sometimes']);
$check(StockCommitter::mode() === StockCommitter::MODE_NONE && count(StockCommitter::configurationWarnings()) === 1, 'Unknown mode is reported and disabled');
$setting(StockCommitter::MODE_ORDER_PAID);
$app['config']->set('seiger.settings.sCommerce.product.inventory_on', 0);
$check(count(StockCommitter::configurationWarnings()) === 1, 'Commitment with inventory turned off is reported');
$settings = file_get_contents($package . '/views/partials/settingsProductConfiguration.blade.php');
$check(str_contains($settings, 'name="product__inventory_commit"') && str_contains($settings, 'name="product__inventory_release_on_cancel"'), 'Settings fields');
$check(sCommerce::supportsIntegration('stock-provider'), 'Capability key');
$keys = ['inventory_commit', 'inventory_commit_help', 'inventory_commit_none', 'inventory_commit_order_created', 'inventory_commit_order_paid',
    'inventory_release_on_cancel', 'inventory_release_on_cancel_help', 'inventory_commit_warning_mode', 'inventory_commit_warning_inventory_off',
    'inventory_commit_warning_migration', 'stock_reconciliation_worker', 'stock_reconciliation_worker_desc', 'stock_reconciliation_worker_done'];
foreach (['uk', 'en', 'ru', 'de', 'fr', 'pl'] as $locale) {
    $messages = require $package . "/lang/$locale/global.php";
    foreach ($keys as $key) {
        $check(isset($messages[$key]) && $messages[$key] !== '', "Language key $key exists in $locale");
    }
}
$migration->down();
$check(!$schema->hasColumn('s_orders', 'inventory_committed_at'), 'Migration rolls back');

echo "OK: {$checks} stock checks passed\n";
