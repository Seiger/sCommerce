<?php
// Reuse the isolated fake manager, SQLite and real Blade harness. No site bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/scommerce-bulk-status.php';

use Seiger\sCommerce\Controllers\OrderBulkMenuController;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Illuminate\Http\Request;

$app->eventFailure = false;
$app->events = [];
$app->instance(Illuminate\Contracts\View\Factory::class, $view);
$app->instance('db.schema', $db->schema());
$app->instance('sCommerce', new class extends Seiger\sCommerce\sCommerce {
    public function __construct() { $this->currencies = collect([['alpha' => 'UAH', 'symbol' => '₴', 'show' => 1, 'position' => 'after']]); }
});
Illuminate\Support\Facades\Facade::clearResolvedInstance('sCommerce');
$app->instance('log', new class {
    public function channel($name) { return $this; }
    public function info($message, $context = []) {}
    public function error($message, $context = []) {
        if (($context['action'] ?? '') === 'print') fwrite(STDERR, (string)($context['exception'] ?? $message));
    }
});
Illuminate\Support\Facades\Facade::clearResolvedInstance('log');
$db->schema()->table('s_orders', function ($table) {
    foreach (['cost', 'currency', 'payment_info', 'user_info', 'delivery_info', 'products', 'reference', 'comment'] as $field) $table->text($field)->nullable();
});
$db->schema()->create('s_order_payments', function ($table) {
    $table->increments('id'); $table->integer('order_id'); $table->integer('sequence');
    foreach (['kind', 'status', 'currency', 'provider', 'provider_ref', 'metadata'] as $field) $table->text($field)->nullable();
    $table->decimal('amount', 12, 2); $table->timestamp('captured_at')->nullable(); $table->timestamps();
});
for ($id = 6; $id <= 15; $id++) $db->table('s_orders')->insert(['id' => $id, 'status' => 1, 'payment_status' => 0]);
$db->table('s_orders')->update(['cost' => '100.00', 'currency' => 'UAH', 'payment_status' => 0, 'payment_info' => '{"method":"cash"}']);
$db->table('s_orders')->where('id', 2)->update(['payment_status' => 2]);
$db->table('s_orders')->where('id', 3)->update(['payment_status' => 3]);
sOrderPayment::create(['order_id' => 2, 'sequence' => 1, 'kind' => 'deposit', 'status' => 'captured', 'amount' => 40, 'currency' => 'UAH', 'provider' => 'manual', 'metadata' => ['keep' => true]]);
$controller = new OrderBulkMenuController();
$call = static fn(array $input = [], string $method = 'POST') => $controller->handle(Request::create('/', $method, $input + ['ids' => [1, 2, 3], 'action' => 'paid', '_token' => 'test-bulk-token']));
foreach (['GET', 'PUT', 'DELETE'] as $method) $check($call([], $method)->getStatusCode() === 405, 'Menu requires POST');
foreach ([null, '', 'bad', ['test-bulk-token']] as $token) $check($call(['_token' => $token])->getStatusCode() === 403, 'Menu CSRF fail closed');
$_SESSION['mgrValidated'] = false;
$check($call()->getStatusCode() === 403, 'Menu requires session');
$_SESSION['mgrValidated'] = true;
$app->permitted = false;
$check($call()->getStatusCode() === 403, 'Menu requires permissions');
$app->permitted = true;
foreach ([[], [1, 1], [0], [['id'=>1]], ['1 OR 1=1'], range(1,501)] as $ids) $check($call(['ids' => $ids])->getStatusCode() === 422, 'Menu invalid selection');
$check($call(['action' => 'forceDelete'])->getStatusCode() === 422, 'Unsupported operations rejected');
foreach (['paid', 'delete', 'print'] as $action) foreach ([[1,999], [1,4]] as $ids) $check($call(compact('action','ids'))->getStatusCode() === 409, 'Missing/deleted selection rejected');
$result = $call();
$check($result->getStatusCode() === 200 && $result->getData(true)['updated'] === 2 && $result->getData(true)['unchanged'] === 1, 'Paid skips already paid');
$check((float)sOrderPayment::where('order_id', 1)->sum('amount') === 100.0 && (float)sOrderPayment::where('order_id', 2)->sum('amount') === 100.0, 'Only outstanding balance recorded');
$check((float)sOrderPayment::where('order_id', 2)->orderByDesc('sequence')->first()->amount === 60.0, 'Partial completion records remaining 60');
$check(sOrderPayment::first()->metadata === ['keep' => true], 'Existing ledger metadata remains unchanged');
$check(sOrderPayment::where('order_id', 1)->first()->metadata['manager_user_id'] === 42, 'Manual ledger audit records manager');
$check(sOrder::find(1)->payment_status === 3 && sOrder::find(1)->status === 3, 'Payment changes without order status change');
$history = sOrder::find(1)->history;
$check(end($history)['payment_status'] === 3 && end($history)['user_id'] === 42, 'Payment history appended');
$count = sOrderPayment::count();
$check($call()->getData(true)['updated'] === 0 && sOrderPayment::count() === $count, 'Retry is idempotent');
$check($app->events === [['sCommerceAfterOrderSave',1],['sCommerceAfterOrderSave',2]], 'Events after commit only');
$db->table('s_orders')->where('id',5)->update(['payment_info'=>'{"method":"stripe"}']);
$check($call(['ids'=>[6,5]])->getStatusCode() === 422 && sOrder::find(6)->payment_status === 0, 'Online method rejects whole batch');
sOrderPayment::create(['order_id'=>7,'sequence'=>1,'kind'=>'final','status'=>'pending','amount'=>100,'currency'=>'UAH','provider'=>'stripe']);
$check($call(['ids'=>[6,7]])->getStatusCode() === 422 && sOrderPayment::where('order_id',6)->count() === 0, 'Provider ledger rejects and rolls back manual entries');
$db->table('s_orders')->where('id',8)->update(['payment_status'=>6]);
$check($call(['ids'=>[8]])->getStatusCode() === 422, 'Refund requires individual review');
$failId = 10;
sOrder::saving(static function ($order) use (&$failId) { if ($order->id === $failId) throw new RuntimeException('Synthetic save failure'); });
$check($call(['ids'=>[9,10]])->getStatusCode() === 500 && sOrder::find(9)->payment_status === 0 && sOrderPayment::whereIn('order_id',[9,10])->count() === 0, 'Payment and history rollback together');
$check($call(['ids'=>[9,10], 'action'=>'delete'])->getStatusCode() === 500 && !sOrder::withTrashed()->find(9)->trashed(), 'Delete batch rollback');
$failId = 0;
sOrder::deleting(static fn($order) => $order->id === 14 ? false : null);
$check($call(['ids'=>[9,14], 'action'=>'delete'])->getStatusCode() === 500 && !sOrder::withTrashed()->find(9)->trashed(), 'Canceled deletion rolls back all selected rows');
$app->eventFailure = true;
$warningResponse = $call(['ids'=>[13]]);
$check($warningResponse->getStatusCode() === 200 && sOrder::find(13)->payment_status === 3 && str_contains($warningResponse->getData(true)['message'], 'additional handler failed'), 'Listener failure reports committed payment with warning');
$app->eventFailure = false;
$count++;
$check($call(['ids'=>[1,2], 'action'=>'delete'])->getStatusCode() === 200, 'Soft deletion succeeds');
$check(sOrder::find(1) === null && sOrder::withTrashed()->find(1)->trashed() && sOrderPayment::count() === $count+1, 'Deleted orders retained and payment ledger preserved');
$history = sOrder::withTrashed()->find(1)->history;
$check(end($history)['action'] === 'bulk_soft_delete' && end($history)['user_id'] === 42, 'Deletion audit saved');
$check($call(['ids'=>[1], 'action'=>'delete'])->getStatusCode() === 409, 'Repeated delete does not hard-delete');
sOrder::withTrashed()->find(1)->restore();
$check(sOrder::find(1)->payment_status === 3, 'Restoration keeps prior payment state');
$db->table('s_orders')->where('id',1)->update(['user_info'=>'{"first_name":"<script>alert(1)</script>","phone":"+380981111111"}', 'products'=>json_encode([['title'=>'<img src=x onerror=alert(1)>','sku'=>'SKU-1','quantity'=>2,'price'=>25]]), 'created_at'=>'2026-08-31 07:05:00']);
$files->makeDirectory($cache);
$db->table('s_orders')->where('id',1)->update(['delivery_info'=>json_encode(['method'=>'nova_post', 'cost'=>0, 'nova_post'=>['city'=>'Харків', 'warehouse'=>'Відділення № 2', 'city_ref'=>'private-city-uuid', 'warehouse_ref'=>'private-warehouse-uuid', 'ttn'=>'000123456789']])]);
try {
    $response = $call(['ids'=>[3,1], 'action'=>'print']);
    $check($response->getStatusCode() === 200, 'Actual Blade print document renders');
    $html = $response->getData(true)['html'];
    $check(substr_count($html,'<article>') === 2 && strpos($html,'Customer order #3') < strpos($html,'Customer order #1'), 'Print selected orders only, in selection order');
    $check(str_contains($html,'31.08.2026 07:05') && str_contains($html,'SKU-1') && str_contains($html,'50.00₴'), 'Print includes timestamp, saved products and line totals');
    $check(!str_contains($html,'<script>') && !str_contains($html,'<img ') && str_contains($html,'&lt;script&gt;'), 'Print escapes stored text without remote resources');
    $check(str_contains($html,'window.print()') && str_contains($response->headers->get('Cache-Control'),'no-store'), 'Print control and privacy headers');
    $check(!str_contains($html,'sCommerce::global.'), 'Print labels resolve');
    $check(str_contains($html, 'Харків') && str_contains($html, 'Відділення № 2') && str_contains($html, '000123456789'), 'Readable address and tracking retained');
    $check(!str_contains($html, 'city_ref') && !str_contains($html, 'private-city-uuid') && !str_contains($html, 'warehouse_ref') && !str_contains($html, 'cost: 0'), 'Internal delivery metadata excluded');
    $check(str_contains($html, 'class="index">1</td>') && str_contains($html, '<colgroup>') && str_contains($html, 'class="signature"'), 'Numbered table and signature line render');
    $check(!str_contains($html, 'VAT') && !str_contains($html, 'ПДВ'), 'No tax classification invented');
} finally { $files->deleteDirectory($cache); }
$db->schema()->drop('s_order_payments');
$check($call(['ids'=>[11]])->getStatusCode() === 200 && sOrder::find(11)->payment_status === 3, 'Legacy without ledger supported');
$db->table('s_orders')->where('id',12)->update(['payment_status'=>2]);
$check($call(['ids'=>[12]])->getStatusCode() === 422, 'Legacy partial without balance rejected');
foreach (['uk','en','ru','de','fr','pl'] as $locale) {
    $labels = require $package.'/lang/'.$locale.'/global.php';
    foreach (['bulk_paid','bulk_print','bulk_delete','bulk_paid_confirm','bulk_delete_confirm','bulk_paid_ineligible','bulk_menu_forbidden','bulk_menu_invalid','bulk_menu_missing','bulk_menu_error','bulk_menu_success','bulk_menu_warning','bulk_menu_pending','bulk_print_blocked'] as $key) $check(!empty($labels[$key]), 'Bulk menu localization');
    preg_match_all('/sCommerce::global\.([a-z_]+)/', file_get_contents($package.'/views/ordersPrint.blade.php'), $matches);
    $check(array_diff(array_unique($matches[1]), array_keys($labels)) === [], 'Print labels available in every locale');
}
echo "$checks combined status/menu checks passed (isolated SQLite and Blade; no site database)\n";
