<?php

// php tests/standalone/scommerce-bulk-status.php
// Isolated regression checks: in-memory SQLite, fake manager, no CMS/site bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function evo() { return Illuminate\Container\Container::getInstance(); }
require __DIR__ . '/bootstrap.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Seiger\sCommerce\Controllers\OrderBulkStatusController;
use Seiger\sCommerce\Models\sOrder;

$app = new class extends Container {
    public bool $permitted = true;
    public bool $eventFailure = false;
    public array $events = [];
    public function getLoginUserID($context) { return 42; }
    public function hasPermission($permission) { return $this->permitted && $permission === 'exec_module'; }
    public function getConfig($key, $default = null) { return $default; }
    public function invokeEvent($name, $params) {
        if ($this['db']->connection()->transactionLevel() !== 0) {
            throw new RuntimeException('Event must run after commit');
        }
        $this->events[] = [$name, $params['item']->id];
        if ($this->eventFailure) { throw new RuntimeException('Test listener failure'); }
    }
};
Container::setInstance($app);
Facade::setFacadeApplication($app);
$app->instance('log', new class { public function error($message, $context = []) {} });
$package = dirname(__DIR__, 2);
$loader = new ArrayLoader();
$loader->addMessages('en', 'global', require $package . '/lang/en/global.php', 'sCommerce');
$app->instance('translator', new Translator($loader, 'en'));
$db = new Capsule($app);
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->setEventDispatcher(new Dispatcher($app));
$db->setAsGlobal();
$db->bootEloquent();
$app->instance('db', $db->getDatabaseManager());
$db->schema()->create('s_orders', function ($table) {
    $table->increments('id');
    $table->integer('status');
    $table->integer('payment_status');
    $table->text('history')->nullable();
    $table->timestamps();
    $table->timestamp('deleted_at')->nullable();
});
foreach ([1, 2, 3, 4, 5] as $id) {
    $db->table('s_orders')->insert(['id' => $id, 'status' => $id === 3 ? 2 : 1, 'payment_status' => 3,
        'history' => json_encode([['note' => 'preserve']]), 'deleted_at' => $id === 4 ? '2026-01-01 00:00:00' : null]);
}
$_SESSION = ['mgrValidated' => true, '_token' => 'test-bulk-token'];
$controller = new OrderBulkStatusController();
$call = static fn (array $input = [], string $method = 'POST') => $controller->handle(Request::create('/', $method, $input + ['ids' => [1, 2], 'status' => 2, '_token' => 'test-bulk-token']));
$checks = 0;
$check = static function (bool $passed, string $label) use (&$checks) {
    if (!$passed) { throw new RuntimeException($label); }
    $checks++;
};
$check($call([], 'GET')->getStatusCode() === 405, 'GET must not mutate');
$check($call([], 'PUT')->getStatusCode() === 405, 'PUT must not mutate');
foreach ([null, '', 'stale', ['test-bulk-token']] as $token) {
    $check($call(['_token' => $token])->getStatusCode() === 403, 'Reject missing/stale/array CSRF');
}
unset($_SESSION['_token']);
$check($call()->getStatusCode() === 403, 'Missing server token must fail closed');
$_SESSION['_token'] = 'test-bulk-token';
$_SESSION['mgrValidated'] = false;
$check($call()->getStatusCode() === 403, 'Manager session required');
$_SESSION['mgrValidated'] = true;
$app->permitted = false;
$check($call()->getStatusCode() === 403, 'Permission checked on server');
$app->permitted = true;
foreach ([[], '1,2', [1, 1], [1, '1'], [0], [-1], [['id' => 1]], ['1 OR 1=1'], range(1, 501)] as $ids) {
    $check($call(['ids' => $ids])->getStatusCode() === 422, 'Invalid ID selection rejected');
}
foreach ([0, -1, 99, null, '1.5', [2]] as $status) {
    $check($call(['status' => $status])->getStatusCode() === 422, 'Invalid/deleted status rejected');
}
foreach ([[1, 999], [1, 4]] as $ids) {
    $check($call(['ids' => $ids])->getStatusCode() === 409, 'Missing/deleted orders reject entire batch');
}
$check($db->table('s_orders')->where('id', 1)->value('status') === 1, 'Rejected requests do not update existing rows');
$result = $call(['ids' => ['1', '2', '3']])->getData(true);
$check($result['success'] && $result['updated'] === 2 && $result['unchanged'] === 1, 'Selected orders updated, no-ops counted');
$order = sOrder::findOrFail(1);
$check($order->status === 2 && $order->payment_status === 3, 'Only order status is changed');
$check(count($order->history) === 2 && $order->history[0]['note'] === 'preserve', 'Existing history preserved');
$check($order->history[1]['status'] === 2 && $order->history[1]['user_id'] === 42 && !empty($order->history[1]['timestamp']), 'History records manager and timestamp');
$check(count(sOrder::findOrFail(3)->history) === 1, 'No-op does not create history');
$check(sOrder::findOrFail(5)->status === 1, 'Unselected orders unchanged');
$check($app->events === [['sCommerceAfterOrderSave', 1], ['sCommerceAfterOrderSave', 2]], 'One event per changed order, after commit');
$result = $call(['ids' => [1, 2, 3]])->getData(true);
$check($result['updated'] === 0 && count($app->events) === 2 && count(sOrder::findOrFail(1)->history) === 2, 'Repeat/no-op is idempotent');
sOrder::saving(static function ($order) {
    if ($order->id === 2 && $order->status === 7) { throw new RuntimeException('Simulated database failure'); }
    if ($order->id === 2 && $order->status === 8) { return false; }
});
foreach ([7, 8] as $status) {
    $check($call(['status' => $status])->getStatusCode() === 500, 'Failed/canceled save reports failure');
    $check(sOrder::findOrFail(1)->status === 2 && sOrder::findOrFail(2)->status === 2 && count(sOrder::findOrFail(1)->history) === 2, 'Failed save rolls back whole batch and histories');
    $check(count($app->events) === 2, 'No external events on rollback');
}
$app->eventFailure = true;
$response = $call(['status' => 3]);
$check($response->getStatusCode() === 200 && sOrder::findOrFail(1)->status === 3 && sOrder::findOrFail(2)->status === 3, 'Post-commit listener failure must not masquerade as rollback');
$check(str_contains($response->getData(true)['message'], 'additional handler failed') && count($app->events) === 4, 'Listener failure warned, other listeners attempted');

foreach (['uk', 'en', 'ru', 'de', 'fr', 'pl'] as $locale) {
    $labels = include $package . '/lang/' . $locale . '/global.php';
    preg_match_all('~sCommerce::global[.]([a-zA-Z0-9_]+)~', file_get_contents($package . '/src/Controllers/OrderBulkStatusController.php') . file_get_contents($package . '/views/ordersTab.blade.php'), $matches);
    $check(array_diff(array_unique($matches[1]), array_keys($labels)) === [], 'All bulk/table translations exist: ' . $locale);
}
$app->instance('request', Request::create('/', 'GET', ['get' => 'orders']));
$app->instance('sCommerce', new class {
    public function config($key, $default = null) { return $default; }
    public function moduleUrl() { return 'index.php?a=112&_token=test-bulk-token&id=test'; }
    public function convertPrice($cost, $currency = null) { return '10.00'; }
});
class_alias(Seiger\sCommerce\Facades\sCommerce::class, 'sCommerce');
$files = new Illuminate\Filesystem\Filesystem();
$cache = sys_get_temp_dir() . '/scommerce-bulk-status-' . bin2hex(random_bytes(8));
$files->makeDirectory($cache);
try {
    $compiler = new Illuminate\View\Compilers\BladeCompiler($files, $cache);
    $compiler->directive('svg', static fn ($expression) => '<svg aria-hidden="true"></svg>');
    $engines = new Illuminate\View\Engines\EngineResolver();
    $engines->register('blade', fn () => new Illuminate\View\Engines\CompilerEngine($compiler, $files));
    $finder = new Illuminate\View\FileViewFinder($files, [$package . '/views']);
    $finder->addNamespace('sCommerce', $package . '/views');
    $view = new Illuminate\View\Factory($engines, $finder, new Dispatcher($app));
    $view->setContainer($app);
    $app->instance('view', $view);
    $view->share('__env', $view);
    $items = new class([sOrder::findOrFail(1)]) extends Illuminate\Support\Collection {
        public function render() { return ''; }
    };
    $html = $view->make('sCommerce::ordersTab', [
        'items' => $items, 'domains' => null, 'statuses' => sOrder::listOrderStatuses(),
        'paymentStatuses' => sOrder::listPaymentStatuses(),
    ])->render();
    $check(str_contains($html, 'data-orders-bulk-form') && str_contains($html, 'method="post"'), 'Actual Blade renders bulk POST form');
    $check(str_contains($html, 'name="_token" value="test-bulk-token"'), 'Bulk form renders CSRF token');
    preg_match_all('/type="radio" name="status" value="(\d+)"/', $html, $radioMatches);
    $check(count($radioMatches[1]) === 13 && !in_array('0', $radioMatches[1], true), 'Status picker contains all non-deletion statuses');
    $check(str_contains($html, 'data-orders-bulk-message') && str_contains($html, 'scom-orders-status--confirmed'), 'Feedback and shared badges render');
    $check((bool) preg_match('/<button[^>]*data-orders-bulk-export[^>]*get=ordersBulkExport[^>]*>/', $html), 'Actual Blade renders export endpoint');
    $check(!str_contains($html, "@lang('sCommerce::global.bulk_status"), 'No uncompiled bulk translation directives');
} finally {
    // Only remove the random cache directory created by this isolated test.
    $files->deleteDirectory($cache);
}
echo "$checks bulk status checks passed (isolated SQLite and real Blade; no site database)\n";
