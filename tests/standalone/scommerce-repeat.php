<?php

// Existing isolated SQLite/Blade harness; never boots a site or calls providers.
require __DIR__ . '/scommerce-bulk-menu.php';

use Illuminate\Http\Request;
use Seiger\sCommerce\Controllers\OrderRepeatController;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Services\OrderRepeater;

$checks = 0;
$app->eventFailure = false;
$app->events = [];
$db->schema()->create('s_order_payments', static function ($table): void { $table->id(); });
$db->table('s_order_payments')->insert(['id' => 1]);
$db->schema()->table('s_orders', static function ($table): void {
    $table->string('identifier')->nullable()->unique();
    $table->string('uuid')->nullable()->unique();
    foreach (['lang', 'domain', 'manager_info', 'manager_notes'] as $field) $table->text($field)->nullable();
    foreach (['user_id', 'is_quick', 'do_not_call'] as $field) $table->integer($field)->default(0);
});
$product = ['id' => 5, 'title' => 'Original item', 'sku' => 'SKU-1', 'link' => '', 'coverSrc' => '',
    'price' => '12.50', 'priceNumber' => 12.5, 'quantity' => 2];
$source = (new sOrder())->forceFill([
    'identifier' => 'original-private-key', 'reference' => '100001', 'uuid' => 'original-uuid',
    'status' => 8, 'payment_status' => 3, 'currency' => 'UAH', 'cost' => 75, 'lang' => 'uk', 'domain' => 'default',
    'user_id' => 12, 'user_info' => ['first_name' => 'Original', 'phone' => '+380981111111', 'token' => 'private'],
    'products' => [$product], 'payment_info' => ['method' => 'cash', 'invoice' => 'private', 'url' => 'private'],
    'delivery_info' => ['method' => 'nova_post', 'cost' => 50, 'ttn' => 'old-ttn',
        'nova_post' => ['city' => 'Kyiv', 'warehouse_ref' => 'wh-1', 'tracking_number' => 'old-ttn']],
    'history' => [['status' => 8]], 'manager_notes' => [['comment' => 'Old note']],
    'is_quick' => true, 'do_not_call' => true,
]);
$source->save();
$sourceId = $source->id;
$before = $source->getRawOriginal();
$countBefore = sOrder::withTrashed()->count();
$draft = (new OrderRepeater())->draft($source);
$check(!$draft->exists && $draft->id === null && $draft->reference === null && $draft->identifier === null, 'Opening is an unnumbered draft');
$check(sOrder::withTrashed()->count() === $countBefore, 'Opening does not write');
$check($draft->status === 1 && $draft->payment_status === 0 && !$draft->is_quick, 'Draft states reset');
$check($draft->cost === 25.0, 'Draft sum excludes previous shipping charge');
$check($draft->payment_info === ['method' => 'cash'], 'No invoice, payment URL or provider state');
$check(!isset($draft->user_info['token']), 'Only customer contact fields copied');
$check($draft->delivery_info['nova_post'] === ['city' => 'Kyiv', 'warehouse_ref' => 'wh-1'], 'Address retained without tracking');
$check(!isset($draft->delivery_info['ttn']) && $draft->delivery_info['cost'] === 0, 'No old shipment');
$check($draft->history === [] && $draft->manager_notes === [], 'Old history and notes excluded');
$module = file_get_contents($package . '/module/sCommerceModule.php');
$start = strpos($module, 'case "order":');
$initialize = substr($module, $start, strpos($module, '$domains = null;', $start) - $start);
$app->instance('request', Request::create('/', 'GET', ['i' => 0, 'repeat_from' => $sourceId]));
$iUrl = '';
$data = [];
eval('use Seiger\\sCommerce\\Models\\sOrder; switch ("order") {' . $initialize . 'break;}');
$check(!$item->exists && $data['repeatSourceId'] === $sourceId && strlen($data['repeatToken']) === 64, 'Actual module opens the copy form');
$check(sOrder::withTrashed()->count() === $countBefore, 'Actual GET creates no order');
$controller = new OrderRepeatController();
$payload = ['repeat_from' => $sourceId, 'repeat_token' => str_repeat('a', 64), '_token' => 'test-bulk-token',
    'products_data' => json_encode([$product]), 'user_info' => ['first_name' => 'Edited'], 'note' => 'New note',
    'status' => 8, 'payment_status' => 3, 'identifier' => 'attacker', 'cost' => 1];
$call = static fn (array $changes = [], string $method = 'POST') => $controller->handle(Request::create('/', $method, array_replace($payload, $changes)));
foreach (['GET', 'PUT', 'DELETE'] as $method) $check($call([], $method)->getStatusCode() === 405, 'Only POST can persist');
foreach ([null, '', [], 'stale'] as $token) $check($call(['_token' => $token])->getStatusCode() === 403, 'CSRF fails closed');
$_SESSION['mgrValidated'] = false;
$check($call()->getStatusCode() === 403, 'Manager session required');
$_SESSION['mgrValidated'] = true;
$app->permitted = false;
$check($call()->getStatusCode() === 403, 'Permission required');
$app->permitted = true;
foreach ([[], -1, 'x', 0] as $id) $check($call(['repeat_from' => $id])->getStatusCode() === 422, 'Invalid source');
foreach (['', [], 'short'] as $token) $check($call(['repeat_token' => $token])->getStatusCode() === 422, 'Valid draft token required');
foreach (['broken', '[]', '{}', '[null]'] as $json) $check($call(['products_data' => $json])->getStatusCode() === 422, 'Reject malformed or empty product lists');
foreach ([0, -1, '1.5', [], 1000001] as $quantity) {
    $check($call(['products_data' => json_encode([array_replace($product, ['quantity' => $quantity])])])->getStatusCode() === 422, 'Invalid quantity');
}
foreach ([-1, [], 'garbage', 99999999] as $price) {
    $check($call(['products_data' => json_encode([array_replace($product, ['priceNumber' => $price])])])->getStatusCode() === 422, 'Invalid price');
}
$check($call(['user_info' => ['email' => 'bad']])->getStatusCode() === 422, 'Invalid email');
$check($call(['products_data' => json_encode([array_replace($product, ['link' => 'javascript:alert(1)'])])])->getStatusCode() === 422, 'Reject executable product links');
$check($call(['repeat_from' => 999999])->getStatusCode() === 404, 'Missing source rejected');
$source->delete();
$check($call()->getStatusCode() === 404, 'Deleted source rejected');
$source->restore();
$before = $source->fresh()->getRawOriginal();
$check(sOrder::withTrashed()->count() === $countBefore, 'Rejected requests create nothing');
$paymentsBefore = $db->table('s_order_payments')->count();
$response = $call();
$check($response->getStatusCode() === 200, 'Save succeeds: ' . $response->getContent());
$result = $response->getData(true);
$created = sOrder::query()->latest('id')->first();
$check($result['created'] && $created->id !== $sourceId, 'New order persisted');
$check($created->identifier !== $source->identifier && $created->uuid !== $source->uuid && $created->reference !== $source->reference, 'Identifiers regenerated');
$check($created->status === 1 && $created->payment_status === 0, 'Submitted old statuses ignored');
$check((float) $created->cost === 25.0 && $created->user_info['first_name'] === 'Edited', 'Edited customer and computed total saved');
$check($created->history[0]['repeated_from'] === $sourceId && $created->history[0]['user_id'] === 42, 'New creation audit identifies source and manager');
$check($created->manager_notes[0]['comment'] === 'New note', 'Only new note saved');
$check($db->table('s_order_payments')->count() === $paymentsBefore, 'No payments created');
$check($source->fresh()->getRawOriginal() === $before, 'Source unchanged');
$check($app->events === [['sCommerceAfterOrderSave', $created->id]], 'One post-commit event');
$again = $call()->getData(true);
$check(!$again['created'] && $again['url'] === $result['url'], 'Retry returns same order');
$check(sOrder::withTrashed()->count() === $countBefore + 1 && count($app->events) === 1, 'Retry does not duplicate rows or events');
$_SESSION['_token'] = 'rotated-token';
$rotated = $call(['_token' => 'rotated-token'])->getData(true);
$check(!$rotated['created'] && str_ends_with($rotated['url'], '&i=' . $created->id), 'Retry stable after CSRF rotation');
$_SESSION['_token'] = 'test-bulk-token';
$app->eventFailure = true;
$check($call(['repeat_token' => str_repeat('b', 64)])->getStatusCode() === 200, 'Listener failure after commit does not report rollback');
$app->eventFailure = false;

// Render the actual copy form and compile its embedded JS.
$app->instance('request', Request::create('/', 'GET', ['i' => 0, 'repeat_from' => $sourceId]));
$html = $view->file(__DIR__ . '/fixtures/scommerce-new-order.blade.php', [
    'item' => $draft, 'domains' => null, 'payment' => false, 'delivery' => false,
    'repeatSourceId' => $sourceId, 'repeatToken' => str_repeat('a', 64),
])->render();
$check(str_contains($html, 'get=orderRepeatSave') && str_contains($html, 'name="repeat_from"'), 'Copy form posts to dedicated save endpoint');
$check(str_contains($html, 'name="repeat_token"') && str_contains($html, 'name="_token"'), 'Form carries retry and CSRF tokens');
$check(str_contains($html, 'name="payment_status" disabled') && str_contains($html, 'name="status" disabled'), 'Copy statuses visibly locked');
$check(str_contains($html, 'repeatBusy') && str_contains($html, 'event.preventDefault()'), 'Async copy save handler rendered');
$beforeFailure = sOrder::withTrashed()->count();
$eventsBeforeFailure = count($app->events);
$app->instance(Seiger\sCommerce\Services\OrderReferenceGenerator::class, new class {
    /**
     * Simulate a numbering failure after insertion to exercise transaction rollback.
     *
     * @param int|null $orderId Newly inserted order.
     * @return string
     * @since 1.4.0
     */
    public function generate(?int $orderId = null): string
    {
        throw new RuntimeException('Synthetic numbering failure');
    }
});
$check($call(['repeat_token' => str_repeat('c', 64)])->getStatusCode() === 500, 'Numbering failure reported');
$check(sOrder::withTrashed()->count() === $beforeFailure && count($app->events) === $eventsBeforeFailure, 'Failed creation rolls back without save events');
echo "$checks repeat-order checks passed (isolated SQLite/Blade, no site database)\n";
