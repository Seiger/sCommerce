<?php

// Run: php tests/standalone/scommerce-new-order.php
// No CMS boot or site database: only an in-memory SQLite database and temp views.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function evo()
{
    return Illuminate\Container\Container::getInstance();
}

require __DIR__ . '/bootstrap.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Seiger\sCommerce\Models\sOrder;

$app = new class extends Container {
    public function getConfig($key, $default = null) { return $default; }
};
Container::setInstance($app);
Facade::setFacadeApplication($app);
$app->instance('sCommerce', new class {
    public function config($key, $default = null) { return $key === 'basic.main_currency' ? 'UAH' : $default; }
    public function moduleUrl() { return 'index.php?a=112&id=test'; }
    public function convertPrice($cost, $currency = null) { return number_format((float)$cost, 2) . ' ' . $currency; }
});
class_alias(Seiger\sCommerce\Facades\sCommerce::class, 'sCommerce');
$loader = new ArrayLoader();
$loader->addMessages('en', 'global', require dirname(__DIR__, 2) . '/lang/en/global.php', 'sCommerce');
$app->instance('translator', new Translator($loader, 'en'));
$_SESSION = ['_token' => 'test-only-csrf-token'];

$db = new Capsule($app);
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->setAsGlobal();
$db->bootEloquent();
$db->schema()->create('s_orders', function ($table) {
    $table->increments('id');
    $table->integer('status');
    $table->integer('payment_status');
    $table->string('currency');
    $table->timestamp('deleted_at')->nullable();
});
$db->table('s_orders')->insert(['id' => 31, 'status' => 8, 'payment_status' => 3, 'currency' => 'EUR']);
$db->getConnection()->enableQueryLog();

$package = dirname(__DIR__, 2);
$module = file_get_contents($package . '/module/sCommerceModule.php');
$start = strpos($module, 'case "order":');
$end = strpos($module, '$domains = null;', $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Order form initialization not found');
}
// Execute the real module initialization, rather than copying its defaults here.
$initialize = substr($module, $start, $end - $start);
$loadOrder = static function (int $id) use ($app, $initialize): sOrder {
    $app->instance('request', Request::create('/', 'GET', ['i' => $id]));
    $iUrl = '';
    eval('use Seiger\sCommerce\Models\sOrder; switch ("order") {' . $initialize . 'break;}');
    return $item;
};
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};

$draft = $loadOrder(0);
$check(!$draft->exists && $draft->id === null && $draft->reference === null, 'Opening must not persist or number an order');
$check($db->getConnection()->getQueryLog() === [], 'Opening a new order must not query or write the orders table');
$check($draft->status === sOrder::ORDER_STATUS_NEW, 'Draft status must be NEW, not deleted or null');
$check($draft->payment_status === sOrder::PAYMENT_STATUS_PENDING, 'Draft payment must be pending');
$check($draft->currency === 'UAH' && $draft->cost === 0, 'Use configured currency and zero cost');
foreach (['user_info', 'delivery_info', 'payment_info', 'products', 'manager_info', 'manager_notes', 'history'] as $field) {
    $check($draft->$field === [], "$field must be an empty array");
}
$existing = $loadOrder(31);
$check($existing->exists && $existing->status === 8 && $existing->payment_status === 3 && $existing->currency === 'EUR', 'Existing order values must be preserved');
foreach ([999, -1] as $id) {
    try {
        $loadOrder($id);
        throw new RuntimeException('Missing nonzero ID must not become a draft');
    } catch (ModelNotFoundException $exception) {
        $checks++;
    }
}

$app->instance('request', Request::create('/', 'GET', ['i' => 0]));
$files = new Filesystem();
$cache = sys_get_temp_dir() . '/scommerce-new-order-' . bin2hex(random_bytes(8));
$files->makeDirectory($cache);
try {
    $compiler = new BladeCompiler($files, $cache);
    $engines = new EngineResolver();
    $engines->register('blade', fn () => new CompilerEngine($compiler, $files));
    $finder = new FileViewFinder($files, [$package . '/views']);
    $finder->addNamespace('sCommerce', $package . '/views');
    $view = new Factory($engines, $finder, new Dispatcher($app));
    $view->setContainer($app);
    $app->instance('view', $view);
    $view->share('__env', $view);

    set_error_handler(static function ($severity, $message, $file, $line) {
        if (error_reporting() & $severity) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    });
    foreach ([null, collect(), collect(['other-site' => (object)['domain' => 'other.test']]), collect(['default' => (object)['domain' => 'shop.test', 'site_color' => '#000000']])] as $domains) {
        $html = $view->file(__DIR__ . '/fixtures/scommerce-new-order.blade.php', ['item' => $draft, 'domains' => $domains, 'payment' => false, 'delivery' => false])->render();
        $check(str_contains($html, 'const orderId = 0;'), 'New-order JavaScript must have a valid zero ID');
        $check(str_contains($html, 'value="[]"'), 'Product data must be an empty JSON array');
        $check(str_contains($html, 'name="_token" value="test-only-csrf-token"'), 'Save form must keep CSRF protection');
        $check((bool)preg_match('/<option value="1"\s+selected/', $html), 'New status must be selected');
    }
    $draft->history = [
        ['action' => 'bulk_soft_delete', 'timestamp' => '2026-09-03 09:05:00', 'user_id' => 0],
        ['action' => '<script>unknown_action</script>', 'user_id' => 0],
    ];
    foreach (['en', 'uk', 'ru', 'de', 'fr', 'pl'] as $locale) {
        $labels = require $package . '/lang/' . $locale . '/global.php';
        $loader->addMessages($locale, 'global', $labels, 'sCommerce');
        $app['translator']->setLocale($locale);
        $html = $view->file(__DIR__ . '/fixtures/scommerce-new-order.blade.php', ['item' => $draft, 'domains' => null, 'payment' => false, 'delivery' => false])->render();
        $check(str_contains($html, $labels['order_history_deleted']), 'Deleted-order history must have a translated label in ' . $locale);
        $check(!str_contains($html, 'bulk_soft_delete'), 'Do not expose the deletion action code in history');
        $check(str_contains($html, 'action: &lt;script&gt;unknown_action&lt;/script&gt;'), 'Unknown history actions must keep their escaped fallback');
    }
    $check($draft->history[0]['action'] === 'bulk_soft_delete', 'Rendering must preserve the stored history event');
    restore_error_handler();
    $check($db->table('s_orders')->count() === 1, 'Rendering must not create orders');
} finally {
    // Delete only this test-created temporary view cache.
    $files->deleteDirectory($cache);
}

echo "$checks new-order initialization and Blade rendering checks passed\n";
