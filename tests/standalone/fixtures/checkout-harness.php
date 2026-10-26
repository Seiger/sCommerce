<?php

// Shared isolated checkout harness: in-memory SQLite, fake shop facade, fake products and
// recording payment methods. No CMS/site bootstrap. Required by the checkout/order-line scripts.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function evo() { return Illuminate\Container\Container::getInstance(); }
if (!function_exists('app')) {
    function app($abstract = null, array $parameters = []) { return $abstract === null ? evo() : evo()->make($abstract, $parameters); }
}
if (!function_exists('request')) {
    function request($key = null, $default = null) { return $key === null ? evo()->make('request') : evo()->make('request')->input($key, $default); }
}
require dirname(__DIR__) . '/bootstrap.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use Seiger\sCommerce\Cart\sCart;
use Seiger\sCommerce\Checkout\sCheckout;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Payment\BasePaymentMethod;
use Seiger\sCommerce\Services\OrderReferenceGenerator;
use Seiger\sCommerce\Services\sPriceResolver;

/** Storefront product without database accessors; fulfilment uses the real sProduct rules. */
final class FakeProduct
{
    public string $link = '';
    public string $coverSrc = '';
    public int $category = 0;
    public float $oldPrice = 0.0;
    public float $deposit = 0.0;
    public string $currency = 'EUR';

    public function __construct(public int $id, public string $sku, public float $price = 10.0, public int $mode = sProduct::MODE_SIMPLE,
                                public ?string $gtin = null, public int $inventory = 100, public string $title = '')
    {
        $this->title = $title ?: "Product $id";
    }

    public function only(array $keys): array { return array_intersect_key(get_object_vars($this), array_flip($keys)); }

    /**
     * Supply attribute-backed measurements to real cart and quick-order payload builders.
     *
     * @since 1.4.0
     * @return array<string, string> Synthetic delivery values; no site data is read.
     */
    public function physicalValues(): array
    {
        return ['weight' => '0.0125', 'width' => '0.0000', 'height' => '0.0000', 'length' => '0.0000', 'volume' => '0.0000'];
    }
    public function attribute(string $alias) { return null; }
    public function toArray(): array { return get_object_vars($this); }
    public function fulfilment(int $optionId = 0): array
    {
        return (new sProduct())->forceFill(['id' => $this->id, 'mode' => $this->mode])->fulfilment($optionId);
    }
}

/** Records every processPayment() call with its data. */
class RecordingPayment extends BasePaymentMethod
{
    /** @var list<array<string, mixed>> */
    public static array $charges = [];
    public function getName(): string { return 'card'; }
    public function getType(): string { return 'card'; }
    public function payButton(int|string|array $data): string { return ''; }
    public function validatePayment(array $data): bool { return true; }
    public function defineSettings(): array { return []; }
    public function defineCredentials(): array { return []; }
    public function processPayment(array $data): array|bool
    {
        self::$charges[] = $data;
        return ['success' => true];
    }
}

$app = new class extends Container {
    public int $webUser = 0;
    public int $mgrUser = 0;
    /** @var list<string> */
    public array $permissions = [];
    public function getLoginUserID($context = 'web') { return $context === 'web' ? $this->webUser : ($context === 'mgr' ? $this->mgrUser : 0); }
    public function hasPermission($permission) { return in_array($permission, $this->permissions, true); }
    public function getConfig($key, $default = null) { return $default; }
    public function getLocale() { return 'en'; }
    public function getUserInfo($id) { return $id ? ['id' => $id, 'email' => "user$id@shop.test", 'first_name' => "User $id"] : []; }
    public function getUserSettings() { return []; }
    public function logEvent(...$arguments) {}
};
Container::setInstance($app);
Facade::setFacadeApplication($app);
$app->instance('log', new class {
    public array $records = [];
    public function channel($name = null) { return $this; }
    public function __call($level, $arguments) { $this->records[] = [$level, $arguments[0], $arguments[1] ?? []]; }
});
$package = dirname(__DIR__, 3);
$loader = new ArrayLoader();
$loader->addMessages('en', 'global', require $package . '/lang/en/global.php', 'sCommerce');
$translator = new Translator($loader, 'en');
$app->instance('translator', $translator);
$app->instance('validator', new ValidationFactory($translator, $app));
$app->instance('request', Request::create('/', 'POST'));
$shop = new class {
    public array $config = ['basic.deliveries_on' => 1, 'basic.payments_on' => 1, 'product.inventory_on' => 0];
    /** @var array<int, FakeProduct> */
    public array $products = [];
    /** @var list<array{0: list<string>, 1: string}> */
    public array $mails = [];
    public function config($key, $default = null) { return array_key_exists($key, $this->config) ? $this->config[$key] : (str_starts_with($key, 'notifications.') ? 0 : $default); }
    public function getProduct($id, $lang = null) { return $this->products[(int)$id] ?? null; }
    public function getProducts($ids) { return collect(array_values(array_intersect_key($this->products, array_flip($ids)))); }
    public function currentCurrency() { return 'EUR'; }
    public function convertPrice($value, $currency = null, $display = null) { return number_format((float)$value, 2, '.', '') . ' EUR'; }
    public function convertPriceNumber($value, $from = null, $to = null) { return (float)$value; }
    public function resolveProductPricing($product, $currency = null) { return app(sPriceResolver::class)->resolve($product, $currency); }
    public function adminNotificationEmails() { return ['admin@shop.test']; }
    public function notifyEmail($to, $template, $data = []) { $this->mails[] = [array_values((array)$to), $template]; }
    public function marketingTags() { return []; }
};
$app->instance('sCommerce', $shop);
$app->bind('sCart', static fn () => new sCart());
// Option ids raise the price so option lines are distinguishable.
$app->bind(sPriceResolver::class, static fn () => new class {
    public function resolve(object $product, ?string $currency = null, string $priceMode = 'auto', int $optionId = 0): array
    {
        // Real sProduct rows are priced from their columns; reading ->price there would recurse.
        $price = ($product instanceof sProduct ? (float)$product->price_regular : (float)$product->price) + $optionId;
        $old = $product instanceof sProduct ? (float)$product->price_special : (float)($product->oldPrice ?? 0);
        return ['priceMode' => 'auto', 'priceAsFloat' => $price, 'oldPriceAsFloat' => $old, 'price' => number_format($price, 2, '.', ''),
            'oldPrice' => $old > 0 ? number_format($old, 2, '.', '') : ''];
    }
});
$app->instance(OrderReferenceGenerator::class, new class extends OrderReferenceGenerator {
    public function generate(?int $orderId = null): string { return ''; }
});
$app->instance('db.transactions', new DatabaseTransactionsManager());
$db = new Capsule($app);
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
$db->setEventDispatcher(new Dispatcher($app));
$db->setAsGlobal();
$db->bootEloquent();
$app->instance('db', $db->getDatabaseManager());
$app->instance('db.schema', $db->schema());
$schema = $db->schema();
$schema->create('s_orders', static function ($table): void {
    $table->bigIncrements('id');
    $table->string('uuid')->nullable();
    $table->string('identifier')->nullable();
    $table->string('reference')->nullable();
    $table->integer('user_id')->default(0);
    $table->text('user_info')->nullable();
    $table->text('delivery_info')->nullable();
    $table->text('payment_info')->nullable();
    $table->text('products')->nullable();
    $table->text('manager_notes')->nullable();
    $table->decimal('cost', 12, 5)->default(0);
    $table->char('currency', 3)->default('USD');
    $table->integer('status')->default(1);
    $table->integer('payment_status')->default(0);
    $table->boolean('do_not_call')->default(false);
    $table->boolean('is_quick')->default(false);
    $table->text('comment')->nullable();
    $table->string('lang')->nullable();
    $table->string('domain')->nullable();
    $table->text('history')->nullable();
    $table->timestamps();
    $table->timestamp('deleted_at')->nullable();
});
$schema->create('s_payment_methods', static function ($table): void {
    $table->increments('id');
    $table->string('uuid')->nullable();
    $table->string('name');
    $table->string('class');
    $table->string('identifier')->default('');
    $table->boolean('active')->default(true);
    $table->integer('position')->default(0);
    $table->text('title')->nullable();
    $table->text('description')->nullable();
    $table->text('settings')->nullable();
    $table->text('credentials')->nullable();
    $table->timestamps();
});
(require $package . '/database/migrations/2026_03_01_000000_create_order_payments_table.php')->up();
(require $package . '/database/migrations/2026_10_20_000004_add_totals_to_orders.php')->up();
(require $package . '/database/migrations/2026_10_20_000005_add_meta_to_orders.php')->up();

$checks = 0;
$check = static function (bool $passed, string $label) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException($label);
    }
    $checks++;
};
/** @var list<array{0: string, 1: array<string, mixed>}> $events Dispatched sCommerce.* domain events. */
$events = [];
Event::listen('sCommerce.Order*', static function (string $name, array $payload) use (&$events): void {
    $events[] = [$name, $payload[0]];
});
/**
 * Checkout without the database-backed constructor.
 *
 * @param list<array<string, mixed>> $products Order lines, e.g. from sCart::getMiniCart().
 */
$checkout = static function (array $products, array $extra = []): sCheckout {
    $checkout = (new ReflectionClass(sCheckout::class))->newInstanceWithoutConstructor();
    $checkout->registerPaymentMethod(new RecordingPayment());
    $cost = array_sum(array_map(static fn ($line) => (float)($line['priceAsFloat'] ?? 0) * (int)($line['quantity'] ?? 1), $products));
    Closure::bind(function () use ($products, $cost, $extra): void {
        $this->orderData = array_replace_recursive([
            'products' => $products,
            'cost' => $cost,
            'currency' => 'EUR',
            'user' => ['id' => 0, 'email' => 'customer@shop.test'],
            'payment' => ['method' => 'card'],
        ], $extra);
    }, $checkout, sCheckout::class)();
    return $checkout;
};
/** Cart lines built by the real sCart from `[productId => [optionId => quantity]]`. */
$cartLines = static function (array $cart): array {
    $_SESSION['sCart'] = $cart;
    return (new sCart())->getMiniCart()['items'];
};
$_SESSION = [];
