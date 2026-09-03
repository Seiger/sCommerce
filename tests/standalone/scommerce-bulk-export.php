<?php
// Isolated CSV regression checks: synthetic orders, in-memory SQLite, no site bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function evo() { return Illuminate\Container\Container::getInstance(); }
require __DIR__ . '/bootstrap.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Seiger\sCommerce\Controllers\OrderBulkExportController;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Services\OrderCsvExporter;

$app = new class extends Container {
    public bool $permitted = true;
    public int $managerId = 42;
    public function getLoginUserID($context) { return $this->managerId; }
    public function hasPermission($permission) { return $this->permitted && $permission === 'exec_module'; }
    public function getConfig($key, $default = null) { return $default; }
};
Container::setInstance($app);
Facade::setFacadeApplication($app);
$app->instance('config', new Illuminate\Config\Repository());
$currencyConfig = ['UAH' => ['alpha' => 'UAH', 'exp' => 2, 'decimals' => '.', 'thousands' => '&nbsp;', 'show' => 1, 'symbol' => '₴', 'position' => 'after']];
$app['config']->set('seiger.settings.sCommerce.currencies', $currencyConfig);
// Run the real price formatter without booting the site's controller/cache/database.
$app->instance('sCommerce', new class($currencyConfig) extends Seiger\sCommerce\sCommerce {
    public function __construct(array $currencies) { $this->currencies = collect(array_values($currencies)); }
    public function setTestCurrencies(array $currencies): void { $this->currencies = collect(array_values($currencies)); }
});
$app->instance('log', new class { public function error($message, $context = []) {} });
$package = dirname(__DIR__, 2);
$loader = new ArrayLoader();
foreach (['en', 'uk'] as $locale) {
    $loader->addMessages($locale, 'global', require $package . '/lang/' . $locale . '/global.php', 'sCommerce');
}
$app->instance('translator', new Translator($loader, 'en'));
$db = new Capsule($app);
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->setAsGlobal();
$db->bootEloquent();
$db->schema()->create('s_orders', function ($table) {
    $table->increments('id');
    foreach (['reference', 'user_info', 'delivery_info', 'cost', 'currency', 'domain', 'history'] as $field) $table->text($field)->nullable();
    $table->integer('status');
    $table->integer('payment_status');
    $table->timestamps();
    $table->timestamp('deleted_at')->nullable();
});
foreach ([1, 2, 3, 4] as $id) {
    $db->table('s_orders')->insert([
        'id' => $id, 'reference' => $id === 2 ? 'ORD_000002' : null, 'status' => 1, 'payment_status' => 3,
        'user_info' => json_encode(['first_name' => $id === 1 ? '=HYPERLINK("evil")' : "Олена; \"Тест\"\nДругий рядок", 'phone' => '+380981111111', 'email' => 'test@example.invalid']),
        'delivery_info' => json_encode(['method' => 'nova_post', 'nova_post' => ['name' => 'Нова Пошта', 'ttn' => '00123456789012']]),
        'cost' => '1234567890.12345678', 'currency' => 'UAH', 'domain' => 'main', 'history' => '[{"note":"unchanged"}]',
        'created_at' => '2026-08-31 13:28:00', 'deleted_at' => $id === 4 ? '2026-09-01 00:00:00' : null,
    ]);
}
$before = $db->table('s_orders')->orderBy('id')->get()->toJson();
$_SESSION = ['mgrValidated' => true, '_token' => 'test-export-token'];
$controller = new OrderBulkExportController();
$call = static fn (array $input = [], string $method = 'POST') => $controller->handle(Request::create('/', $method, $input + ['ids' => [2, 1], '_token' => 'test-export-token']));
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks) { if (!$ok) throw new RuntimeException($label); $checks++; };
foreach (['GET', 'PUT', 'DELETE'] as $method) $check($call([], $method)->getStatusCode() === 405, 'Only POST allowed');
foreach ([null, '', 'old', ['test-export-token']] as $token) $check($call(['_token' => $token])->getStatusCode() === 403, 'Invalid CSRF rejected');
unset($_SESSION['_token']);
$check($call()->getStatusCode() === 403, 'Missing server token rejected');
$_SESSION['_token'] = 'test-export-token';
$_SESSION['mgrValidated'] = false;
$check($call()->getStatusCode() === 403, 'Manager session required');
$_SESSION['mgrValidated'] = true;
$app->managerId = 0;
$check($call()->getStatusCode() === 403, 'Manager identity required');
$app->managerId = 42;
$app->permitted = false;
$check($call()->getStatusCode() === 403, 'Permission required');
$app->permitted = true;
foreach ([[], '1,2', [1, 1], [1, '1'], [0], [-1], [true], [['id' => 1]], ['1 OR 1=1'], range(1, 501)] as $ids) {
    $check($call(['ids' => $ids])->getStatusCode() === 422, 'Invalid IDs rejected');
}
foreach ([[1, 999], [1, 4]] as $ids) $check($call(['ids' => $ids])->getStatusCode() === 409, 'Missing/deleted selection rejects whole export');
$response = $call();
$check($response->getStatusCode() === 200, 'Export succeeds');
$check($response->headers->get('Content-Type') === 'text/csv; charset=UTF-8', 'CSV MIME type');
$check(str_contains($response->headers->get('Content-Disposition'), 'attachment; filename="sCommerce_orders_'), 'Attachment filename');
$check(str_contains($response->headers->get('Cache-Control'), 'no-store') && $response->headers->get('X-Content-Type-Options') === 'nosniff', 'Sensitive response not cached/sniffed');
$parse = static function (string $csv): array {
    $stream = fopen('php://memory', 'w+');
    fwrite($stream, substr($csv, 3)); rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, null, ';', '"', '')) !== false) $rows[] = $row;
    fclose($stream); return $rows;
};
$csv = $response->getContent();
$check(str_starts_with($csv, "\xEF\xBB\xBF"), 'UTF-8 BOM');
$rows = $parse($csv);
$check(count($rows) === 3 && count($rows[0]) === 10, 'Header + selected orders only; no domain without multisite');
$check(!in_array('Currency', $rows[0], true) && count($rows[1]) === count($rows[0]), 'No separate currency column; headers and values aligned');
$check($rows[1][0] === '000002' && $rows[2][0] === '1', 'Displayed references and selection order preserved');
$check($rows[1][1] === "Олена; \"Тест\"\nДругий рядок", 'Unicode, semicolon, quotes and multiline round-trip');
$check($rows[2][1] === "'=HYPERLINK(\"evil\")" && $rows[1][2] === '+38 (098) 111-11-11', 'Formulas stay guarded; phones use list formatting without apostrophe');
$check($rows[1][5] === '1 234 567 890.12₴' && $rows[1][6] === sOrder::getOrderStatusName(1), 'Currency stays inside amount; status follows amount');
$check($rows[1][4] === '2026-08-31 13:28:00' && $rows[1][9] === '00123456789012', 'Timestamp and tracking preserved');
$check($rows[1][8] === 'Нова Пошта' && $rows[1][7] === sOrder::getPaymentStatusName(3), 'Readable delivery and payment labels');
$exporter = new OrderCsvExporter();
$order = sOrder::findOrFail(1);
foreach (['9.75' => '9.75₴', '3.08' => '3.08₴', '5925.77' => '5 925.77₴'] as $cost => $expected) {
    $order->cost = $cost;
    $check($parse($exporter->render([$order]))[1][5] === $expected, 'Date-like decimals include the list currency marker');
}
$currencyConfig['UAH']['show'] = 0;
$app['config']->set('seiger.settings.sCommerce.currencies', $currencyConfig);
$app['sCommerce']->setTestCurrencies($currencyConfig);
$check($parse($exporter->render([$order]))[1][5] === '5 925.77 UAH', 'Hidden symbol falls back to currency code exactly like the list');
$currencyConfig['UAH']['show'] = 1;
$app['config']->set('seiger.settings.sCommerce.currencies', $currencyConfig);
$app['sCommerce']->setTestCurrencies($currencyConfig);
foreach (['+380981111111' => '+38 (098) 111-11-11', '380981111111' => '+38 (098) 111-11-11', '+38 (050) 000-00-00' => '+38 (050) 000-00-00', '0981111111' => '+38 (098) 111-11-11', '' => '', '+49 (151) 12345678' => '+49 (151) 12345678'] as $phone => $expected) {
    $order->user_info = ['phone' => $phone];
    $check($parse($exporter->render([$order]))[1][2] === $expected, 'Ukrainian phones use readable grouping; empty/foreign phones preserved');
}
foreach (['+HYPERLINK("evil")', '=1+1', '@SUM(1)', '+380981111111&cmd', "\t+380981111111"] as $phone) {
    $order->user_info = ['phone' => $phone];
    $check($parse($exporter->render([$order]))[1][2] === "'" . $phone, 'Non-phone formulas in phone field stay guarded');
}
$rows = $parse($exporter->render([$order], ['nova_post' => ['title' => 'Carrier title']], ['main' => 'shop.example.invalid']));
$check(count($rows[0]) === 11 && count($rows[1]) === 11 && $rows[1][10] === 'shop.example.invalid' && $rows[1][8] === 'Carrier title', 'Optional domain and configured delivery name');
foreach (['=1+1', '+cmd', '-cmd', '@SUM(1)', " \t=1", "\tplain", "\rplain", "\nplain"] as $value) {
    $order->user_info = ['email' => $value];
    $rows = $parse($exporter->render([$order]));
    $check($rows[1][3] === "'" . $value, 'Dangerous spreadsheet prefixes neutralized');
}
$order->user_info = ['email' => 'backslash\\";quoted'];
$check($parse($exporter->render([$order]))[1][3] === 'backslash\\";quoted', 'Backslash before quote round-trips without proprietary escaping');
$app['translator']->setLocale('uk');
$rows = $parse($call()->getContent());
$check($rows[0][0] === 'Номер замовлення' && $rows[0][9] === 'ТТН' && !in_array('Валюта', $rows[0], true), 'Localized CSV header without currency column');
$check($before === $db->table('s_orders')->orderBy('id')->get()->toJson(), 'Export never changes orders/history');
foreach (['uk', 'en', 'ru', 'de', 'fr', 'pl'] as $locale) {
    $labels = require $package . '/lang/' . $locale . '/global.php';
    foreach (['export_tracking', 'bulk_export_pending', 'bulk_export_success', 'bulk_export_forbidden', 'bulk_export_invalid', 'bulk_export_missing', 'bulk_export_error'] as $key) {
        $check(!empty($labels[$key]), 'Export translation exists: ' . $locale . '/' . $key);
    }
}
echo "$checks order CSV checks passed (synthetic data, no site database)\n";
