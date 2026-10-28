<?php

// php tests/standalone/scommerce-measured-quantities.php
// Isolated checks of products sold by weight, length, volume or count: integer arithmetic,
// rules, labels, cart, checkout, manager edits, stock and settings. In-memory SQLite, the
// shared checkout harness and the real migrations; no CMS/site bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!function_exists('config')) {
    function config($key = null, $default = null) { return app('config')->get($key, $default); }
}
require __DIR__ . '/fixtures/checkout-harness.php';

use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Seiger\sCommerce\Cart\sCart;
use Seiger\sCommerce\Checkout\sCheckout;
use Seiger\sCommerce\Interfaces\StockProviderInterface;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Services\MeasuredQuantity;
use Seiger\sCommerce\Services\MeasuredQuantityException;
use Seiger\sCommerce\Services\OrderCostRecalculator;
use Seiger\sCommerce\Services\OrderLineEditor;
use Seiger\sCommerce\Services\OrderTaxes;
use Seiger\sCommerce\Services\QuantityRules;
use Seiger\sCommerce\Services\StockCommitter;
use Seiger\sCommerce\Services\StockShortageException;
use Seiger\sCommerce\Services\VatDisplay;
use Seiger\sCommerce\sCommerce as Shop;

$app->instance('config', new Repository($app['config']->getAttributes()));
$shop->config['basic.deliveries_on'] = 0;
$vectors = json_decode(file_get_contents(__DIR__ . '/fixtures/measured-quantities.json'), true);
$refused = static function (callable $call, ?string $key = null): bool {
    try {
        $call();
    } catch (MeasuredQuantityException $exception) {
        return $key === null || $exception->messageKey === $key;
    }
    return false;
};
$rules = static fn (array $columns): QuantityRules => MeasuredQuantity::resolve((object)$columns);

// 1. Migration: additive, idempotent, reversible; piece products are unaffected.
$schema->create('s_products', static function ($table): void {
    $table->id();
    $table->uuid('uuid')->nullable();
    $table->string('sku')->default('');
    $table->smallInteger('mode')->default(0);
    $table->smallInteger('availability')->default(1);
    $table->integer('inventory')->default(0);
    $table->decimal('deposit', 12, 2)->default(0);
    $table->decimal('price_regular', 12, 5)->default(0);
    $table->timestamps();
});
$columns = MeasuredQuantity::PRODUCT_COLUMNS;
$migration = require $package . '/database/migrations/2026_10_27_000000_add_measured_quantities_to_products.php';
$migration->up();
$migration->up();
$check(array_filter($columns, static fn ($column) => !$schema->hasColumn('s_products', $column)) === [], 'Migration adds the columns and is idempotent');
$migration->down();
$check(array_filter($columns, static fn ($column) => $schema->hasColumn('s_products', $column)) === [], 'down() removes the columns');
$migration->up();
$pieceId = (int)$db->table('s_products')->insertGetId(['sku' => 'PIECE', 'inventory' => 5]);
$load = static fn (int $id): sProduct => sProduct::query()->withoutGlobalScopes()->find($id);
$check($load($pieceId)->measure === 0 && !$load($pieceId)->isMeasured() && !$load($pieceId)->quantityRules()->isMeasured()
    && $load($pieceId)->quantityRules() == MeasuredQuantity::piece(), 'Existing products stay piece products');
$check(MeasuredQuantity::rules(new FakeProduct(1, 'NO-COLUMNS'))->measure === MeasuredQuantity::PIECE, 'Objects without the columns are piece products');

// 2. Integer line totals: exact half-up rounding once, from the unrounded price.
foreach ($vectors['lineTotalMinor'] as [$price, $quantity, $factor, $expected]) {
    $check(MeasuredQuantity::lineTotalMinor($price, $quantity, $factor) === $expected, "lineTotalMinor $price × $quantity / $factor");
}
$check(MeasuredQuantity::lineTotalMinor(MeasuredQuantity::scalePrice('2.495'), 330, 100) === 823
    && MeasuredQuantity::lineTotalMinor(MeasuredQuantity::scalePrice('2.50'), 330, 100) === 825, 'A sub-cent price is not rounded to cents first');
for ($i = 0; $i < 3000; $i++) {
    $price = random_int(0, 2000000000);
    $quantity = random_int(0, 2000000000);
    $factor = [1, 10, 100, 1000, 1000000][$i % 5];
    $exact = bcmul((string)$price, (string)$quantity);
    $divisor = (string)($factor * 1000);
    $reference = bcadd(bcdiv($exact, $divisor, 0), bccomp(bcmul(bcmod($exact, $divisor), '2'), $divisor) >= 0 ? '1' : '0');
    if (MeasuredQuantity::lineTotalMinor($price, $quantity, $factor) !== (int)$reference) {
        $check(false, "Random line total $price × $quantity / $factor");
    }
}
$checks++;
$overflow = false;
try {
    MeasuredQuantity::lineTotalMinor(PHP_INT_MAX, PHP_INT_MAX, 1);
} catch (InvalidArgumentException) {
    $overflow = true;
}
$check($overflow, 'Results beyond 64 bits are refused');
$check(MeasuredQuantity::lineTotalMinor(10000000000, 1000000000, 1000) === 10000000000000, 'Products beyond 64 bits use bcmath');
foreach ($vectors['scalePrice'] as [$input, $expected]) {
    $check(MeasuredQuantity::scalePrice($input) === $expected, 'scalePrice ' . var_export($input, true));
}
$check(MeasuredQuantity::toPriceUnits(2530, 1000) === '2.530' && MeasuredQuantity::toPriceUnits(5, 1000) === '0.005'
    && MeasuredQuantity::toPriceUnits(1234567, 1000000) === '1.234567' && MeasuredQuantity::toPriceUnits(330, 1) === '330', 'Exact decimal strings');

// 3. Amounts in the price unit.
$cable = $rules(['measure' => sProduct::MEASURE_LENGTH, 'price_unit' => 'm', 'quantity_step' => 10, 'quantity_min' => 1000, 'quantity_tolerance' => 500]);
foreach ($vectors['parseAmount'] as [$input, $unitFactor, $expected]) {
    $target = new QuantityRules('length', 'mm', 'x', $unitFactor, 1, $unitFactor, 1, 1, 1000000, 0, 3, 'up');
    $parsed = null;
    try {
        $parsed = MeasuredQuantity::parseAmount($input, $target);
    } catch (MeasuredQuantityException) {
    }
    $check($parsed === $expected, 'parseAmount ' . json_encode($input));
}
$check($refused(fn () => MeasuredQuantity::parseAmount('2.5301', $cable), 'measured_quantity_precision'), 'Too many decimals are reported');

// 4. Price bases and accounting conversion.
$check(MeasuredQuantity::validBasis('weight', 1000, 1) && !MeasuredQuantity::validBasis('weight', 1000, 10) && MeasuredQuantity::validBasis('weight', 1, 100)
    && MeasuredQuantity::validBasis('weight', 1, 1000) && MeasuredQuantity::validBasis('length', 10, 100) && !MeasuredQuantity::validBasis('length', 10, 1000)
    && !MeasuredQuantity::validBasis('volume', 1, 5) && MeasuredQuantity::validBasis('count', 1, 1000000) && !MeasuredQuantity::validBasis('count', 1, 100)
    && !MeasuredQuantity::validBasis('length', null, 1), 'Allowed and refused price bases');
$per100g = $rules(['measure' => sProduct::MEASURE_WEIGHT, 'price_unit' => 'g', 'price_per' => 100]);
$check($per100g->factor === 100 && $per100g->unitFactor === 1 && $per100g->pricePer === 100, '€2.49 / 100 g has factor 100');
$check(MeasuredQuantity::forAccounting(330, '2.49', $per100g) === ['quantity' => '0.330', 'unit' => 'kg', 'basis' => 1, 'unit_price' => '24.90'], '330 g at €2.49 / 100 g is 0.330 kg × 24.90');
$check(MeasuredQuantity::forAccounting(2530, 1.2, $cable) === ['quantity' => '2.530', 'unit' => 'm', 'basis' => 1, 'unit_price' => '1.20'], 'Cable in metres');
$check(MeasuredQuantity::forAccounting(3, '12.5', MeasuredQuantity::piece())['unit_price'] === '12.50', 'Pieces keep their price');
for ($i = 0; $i < 500; $i++) {
    $measure = ['weight', 'length', 'volume'][$i % 3];
    [$unit, $unitFactor] = MeasuredQuantity::units($measure)[array_rand(MeasuredQuantity::units($measure))];
    $bases = array_values(array_filter(MeasuredQuantity::PRICE_BASES, static fn ($basis) => $unitFactor * $basis <= 1000));
    $basis = $bases[array_rand($bases)];
    $line = $rules(['measure' => array_search($measure, sProduct::MEASURE_ALIASES, true), 'price_unit' => $unit, 'price_per' => $basis]);
    $price = number_format(random_int(1, 99999999) / 100000, 5, '.', '');
    $quantity = random_int(1, 50000000);
    $accounting = MeasuredQuantity::forAccounting($quantity, $price, $line);
    if (MeasuredQuantity::lineTotalMinor(MeasuredQuantity::scalePrice($accounting['unit_price']), $quantity, 1000)
        !== MeasuredQuantity::lineTotalMinor(MeasuredQuantity::scalePrice($price), $quantity, $line->factor)) {
        $check(false, "Accounting total of $quantity at $price per $basis $unit");
    }
}
$checks++;

// 5. Count: services priced per thousand or per million.
$cpm = $rules(['measure' => sProduct::MEASURE_COUNT, 'price_per' => 1000]);
$tokens = $rules(['measure' => sProduct::MEASURE_COUNT, 'price_per' => 1000000]);
$check(MeasuredQuantity::lineTotalMinor(MeasuredQuantity::scalePrice('2.50'), 125000, $cpm->factor) === 31250
    && MeasuredQuantity::lineTotalMinor(MeasuredQuantity::scalePrice('2.50'), 123456, $cpm->factor) === 30864, 'CPM €2.50 per 1,000');
$check(MeasuredQuantity::lineTotalMinor(MeasuredQuantity::scalePrice('0.50'), 2500000, $tokens->factor) === 125
    && MeasuredQuantity::lineTotalMinor(MeasuredQuantity::scalePrice('0.50'), 1234567, $tokens->factor) === 62, 'Tokens $0.50 per 1,000,000');
$check(MeasuredQuantity::forAccounting(123456, '2.50', $cpm) === ['quantity' => '123.456', 'unit' => 'unit', 'basis' => 1000, 'unit_price' => '2.50']
    && MeasuredQuantity::forAccounting(1234567, '0.50', $tokens)['quantity'] === '1.234567', 'Count lines go to accounting in basis units');
$check($rules(['measure' => sProduct::MEASURE_COUNT, 'price_per' => 100])->pricePer === 1000, 'An invalid count basis falls back to the site default');
$countDefaults = MeasuredQuantity::DEFAULTS['count'];
$check(MeasuredQuantity::validateDefaults('count', ['max' => 5000000000] + $countDefaults, true) === ['measured_settings_max']
    && MeasuredQuantity::validateDefaults('count', ['max' => 5000000000] + $countDefaults, false) === []
    && MeasuredQuantity::validateDefaults('count', ['price_per' => 100] + $countDefaults, false) === ['measured_settings_price_per'], 'A max above 2e9 is refused only for products with stock');

// 6. Labels follow the language.
$loader->addMessages('uk', 'global', ['unit_m' => 'м', 'unit_g' => 'г', 'unit_kg' => 'кг', 'number_decimal_separator' => ',', 'number_thousands_separator' => ' ',
    'count_basis_1000' => '1 000'], 'sCommerce');
$check(MeasuredQuantity::label(2530, $cable) === '2.53 m' && MeasuredQuantity::label(2530, $cable, 'uk') === '2,53 м', 'Length labels in en and uk');
$check(MeasuredQuantity::unitPriceLabel('€2.49', $per100g) === '€2.49 / 100 g' && MeasuredQuantity::unitPriceLabel('2,49 €', $per100g, 'uk') === '2,49 € / 100 г', 'Unit price with a basis');
$check(MeasuredQuantity::unitPriceLabel('€1.20', $cable) === '€1.20 / m', 'A basis of 1 is omitted');
$check(MeasuredQuantity::label(125000, $cpm) === '125,000' && MeasuredQuantity::label(125000, $cpm, 'uk') === '125 000'
    && MeasuredQuantity::unitPriceLabel('€2.50', $cpm) === '€2.50 / 1,000' && MeasuredQuantity::unitPriceLabel('€2.50', $cpm, 'uk') === '€2.50 / 1 000', 'Count labels');
$check(MeasuredQuantity::label(2530, $cable, 'sk') === '2.53 m', 'A missing translation falls back to English');
$check(MeasuredQuantity::label(3000, $cable) === '3 m' && MeasuredQuantity::label(2535, $cable) === '2.535 m' && MeasuredQuantity::label(2500, $cable) === '2.5 m'
    && MeasuredQuantity::label(330, $rules(['measure' => sProduct::MEASURE_WEIGHT])) === '0.33 kg'
    && MeasuredQuantity::label(330, $per100g) === '330 g', 'Labels are exact without trailing zeros');
$check(sProduct::physicalLabelLocalized('weight', 1500, 'uk') === '1,5 кг' && sProduct::physicalLabel('weight', 1500) === '1.5kg', 'Localized physical labels; the code label stays');

// 7. Storefront rounding to the step (§12 fixtures) and strict validation.
$fixtures = [
    // [product columns, entered, available, up, nearest, down]
    [['measure' => 2, 'price_unit' => 'm', 'quantity_step' => 10, 'quantity_min' => 1000], 2533, null, 2540, 2530, 2530],
    [['measure' => 3, 'price_unit' => 'l', 'quantity_step' => 1000, 'quantity_min' => 1000], 2400, null, 3000, 2000, 2000],
    [['measure' => 1, 'price_unit' => 'kg', 'quantity_step' => 100, 'quantity_min' => 100], 1040, null, 1100, 1000, 1000],
    [['measure' => 1, 'price_unit' => 'kg', 'quantity_step' => 100, 'quantity_min' => 100], 1050, null, 1100, 1100, 1000],
    [['measure' => 2, 'price_unit' => 'm', 'quantity_step' => 100, 'quantity_min' => 1000], 400, null, 1000, 1000, 1000],
    [['measure' => 2, 'price_unit' => 'm', 'quantity_step' => 10, 'quantity_min' => 1000], 3000, 2535, 2530, 2530, 2530],
];
foreach ($fixtures as [$fixture, $entered, $available, $up, $nearest, $down]) {
    foreach (['up' => $up, 'nearest' => $nearest, 'down' => $down] as $mode => $expected) {
        $result = MeasuredQuantity::normalize($entered, $rules($fixture + ['quantity_rounding' => $mode]), MeasuredQuantity::MODE_CLAMP, $available);
        $check($result === $expected, "Rounding $mode of $entered gives $expected (got $result)");
    }
}
$check(MeasuredQuantity::normalize(5000000, $cable) === 1000000, 'Clamp caps at the maximum');
$check(MeasuredQuantity::normalize(2530, $cable, MeasuredQuantity::MODE_STRICT) === 2530, 'Strict accepts a valid quantity');
$check($refused(fn () => MeasuredQuantity::normalize(0, $cable, MeasuredQuantity::MODE_STRICT), 'measured_quantity_invalid')
    && $refused(fn () => MeasuredQuantity::normalize(900, $cable, MeasuredQuantity::MODE_STRICT), 'measured_quantity_below_min')
    && $refused(fn () => MeasuredQuantity::normalize(2533, $cable, MeasuredQuantity::MODE_STRICT), 'measured_quantity_step')
    && $refused(fn () => MeasuredQuantity::normalize(1000010, $cable, MeasuredQuantity::MODE_STRICT), 'measured_quantity_above_max'), 'Strict refuses with the right key');
try {
    MeasuredQuantity::normalize(900, $cable, MeasuredQuantity::MODE_STRICT);
} catch (MeasuredQuantityException $exception) {
    $check($exception->translated() === 'The minimum quantity is 1 m.', 'Refusals are translated with the label');
}

// 8. Rules resolution: product columns, site defaults, frozen lines, rounding per unit.
$check($rules(['measure' => 2])->step === 10 && $rules(['measure' => 2])->min === 10 && $rules(['measure' => 2])->priceUnit === 'm'
    && $rules(['measure' => 2])->rounding === 'up' && $rules(['measure' => 1])->rounding === 'nearest', 'Site defaults apply');
$check($cable->step === 10 && $cable->min === 1000 && $cable->tolerance === 500 && $rules(['measure' => 2, 'quantity_tolerance' => 0])->tolerance === 0, 'Product columns override the defaults');
$check($rules(['measure' => 2, 'quantity_step' => 100])->min === 100, 'The default minimum is at least one step');
$shop->config['product.measures'] = ['length' => ['price_unit' => 'cm', 'step' => 5, 'min' => 50]];
$check($rules(['measure' => 2])->priceUnit === 'cm' && $rules(['measure' => 2])->step === 5 && $rules(['measure' => 2])->factor === 10, 'Configured site defaults are used');
$shop->config['product.measures'] = ['length' => ['step' => 0]];
$check($rules(['measure' => 2])->step === 10, 'Invalid configured defaults fall back to the built-in ones');
$shop->config['product.quantity_rounding'] = ['m' => 'down', 'kg' => 'up'];
$check($rules(['measure' => 2])->rounding === 'down' && $rules(['measure' => 1])->rounding === 'up' && $rules(['measure' => 1, 'price_unit' => 'g'])->rounding === 'nearest'
    && $rules(['measure' => 2, 'quantity_rounding' => 'nearest'])->rounding === 'nearest' && MeasuredQuantity::rounding('xx') === 'up', 'Rounding per unit, product override and unknown units');
$line = ['quantity' => 2530] + $cable->lineKeys();
$shop->config['product.quantity_rounding'] = ['m' => 'nearest'];
$check(MeasuredQuantity::fromLine($line)->rounding === 'up' && MeasuredQuantity::fromLine($line)->factor === 1000
    && MeasuredQuantity::fromLine($line)->min === 1000 && MeasuredQuantity::fromLine(['quantity' => 3])->measure === 'piece', 'Stored lines keep their rules');
unset($shop->config['product.measures'], $shop->config['product.quantity_rounding']);
$errors = [];
$saved = MeasuredQuantity::sanitizeSettings(['measures' => ['weight' => ['price_unit' => 'm'] + MeasuredQuantity::DEFAULTS['weight'], 'length' => ['step' => 20, 'min' => 100] + MeasuredQuantity::DEFAULTS['length']],
    'quantity_rounding' => ['kg' => 'sideways', 'm' => 'down', 'zz' => 'up'], 'vat_rate' => 20], ['quantity_rounding' => ['kg' => 'nearest']], $errors);
$check($errors === ['measured_settings_price_unit', 'measured_settings_rounding'] && $saved['measures']['weight'] === MeasuredQuantity::DEFAULTS['weight']
    && $saved['measures']['length']['step'] === 20 && $saved['quantity_rounding'] === ['kg' => 'nearest', 'm' => 'down'] && $saved['vat_rate'] === 20, 'Settings save refuses invalid values and keeps the previous ones');

// 9. Cart: amounts, integer quantities, stock caps and piece output.
$shop->products = [
    10 => tap(new FakeProduct(10, 'CABLE', 1.2, title: 'Cable'), static function ($p) { $p->measure = 2; $p->price_unit = 'm'; $p->quantity_step = 10; $p->quantity_min = 1000; $p->quantity_tolerance = 500; $p->inventory = 100000; }),
    11 => new FakeProduct(11, 'PIECE', 12.5),
    12 => new FakeProduct(12, 'PIECE-2', 4.0),
    13 => tap(new FakeProduct(13, 'PAINT', 9.9, title: 'Paint'), static function ($p) { $p->measure = 3; $p->price_unit = 'l'; $p->quantity_step = 1000; $p->quantity_min = 1000; $p->weight = 0; }),
    14 => tap(new FakeProduct(14, 'ADS', 2.5, sProduct::MODE_SERVICE, title: 'Ad impressions'), static function ($p) { $p->measure = 4; $p->price_per = 1000; }),
];
$add = static function (array $input): array {
    app()->instance('request', Request::create('/', 'POST', $input));
    return (new sCart())->addProduct();
};
$_SESSION['sCart'] = [];
$added = $add(['productId' => 10, 'amount' => '2.533']);
$item = $added['miniCart']['items'][0];
$check($added['success'] && $added['quantityAdjusted'] === true && $item['quantity'] === 2540 && str_contains($added['message'], 'Quantity changed: 2.54 m'), 'Amounts are parsed and rounded up to the step');
$check($item['measure'] === 'length' && $item['base_unit'] === 'mm' && $item['price_unit'] === 'm' && $item['factor'] === 1000 && $item['price_per'] === 1
    && $item['quantity_step'] === 10 && $item['quantity_min'] === 1000 && $item['quantity_tolerance'] === 500 && $item['quantity_rounding'] === 'up'
    && $item['quantity_label'] === '2.54 m' && $item['unit_price_label'] === '1.20 / m' && $item['line_weight'] === 318 && $item['sumAsFloat'] === 3.05, 'Measured cart line keys and exact sum');
$added = $add(['productId' => 10, 'quantity' => 2530, 'trigger' => 'quantity']);
$check($added['success'] && !isset($added['quantityAdjusted']) && $added['miniCart']['items'][0]['quantity'] === 2530 && $added['miniCart']['totalSum'] === 3.04, 'Integer quantities are base units');
$check($add(['productId' => 10, 'amount' => '2,5'])['success'] === false, 'Locale separators are refused in amount');
$_SESSION['sCart'] = [];
$check($add(['productId' => 10])['miniCart']['items'][0]['quantity'] === 1000, 'The default quantity is the minimum');
$shop->config['product.inventory_on'] = 1;
$shop->products[10]->inventory = 2535;
$capped = $add(['productId' => 10, 'amount' => '3']);
$check($capped['success'] && $capped['quantityAdjusted'] && $capped['miniCart']['items'][0]['quantity'] === 2530, 'Stock caps round down to a step');
$shop->products[10]->inventory = 995;
$check($add(['productId' => 10, 'amount' => '1'])['success'] === false, 'Stock below the minimum is out of stock');
$shop->products[10]->inventory = 100000;
$shop->config['product.inventory_on'] = 0;
$_SESSION['sCart'] = [10 => [0 => 2530], 11 => [0 => 2], 13 => [0 => 2400]];
$mini = (new sCart())->getMiniCart();
$byId = array_column($mini['items'], null, 'id');
$check(!array_intersect_key($byId[11], array_flip(array_merge(MeasuredQuantity::LINE_KEYS, MeasuredQuantity::DISPLAY_KEYS))), 'Piece lines have no measured keys');
$check($byId[13]['quantity'] === 2400 && $byId[13]['sumAsFloat'] === 23.76 && !isset($byId[13]['line_weight']) && $mini['totalSum'] === 3.04 + 25.0 + 23.76, 'Volume line without weight; totals add up');
$capture = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/piece-cart-capture.php') . ' ' . escapeshellarg($package));
$check($capture === str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/fixtures/piece-cart-master.json')), 'Piece cart and order JSON are byte-identical to master (normalized line endings)');

// 10. Quick order and checkout keep the snapshot keys and the exact total.
$quick = sCheckout::quickOrder(['productId' => 10, 'amount' => '2.53', 'email' => 'quick@shop.test']);
$quickLine = $quick['order']->products[0];
$check($quick['success'] && (float)$quick['order']->cost === 3.04 && $quickLine['quantity'] === 2530 && $quickLine['factor'] === 1000
    && $quickLine['quantity_label'] === '2.53 m' && $quickLine['sumAsFloat'] === 3.04 && $quickLine['line_weight'] === 316, 'Quick order line and cost');
$quick = sCheckout::quickOrder(['productId' => 10, 'quantity' => 2533, 'email' => 'quick@shop.test']);
$check($quick['order']->products[0]['quantity'] === 2540, 'Quick order fits the step');
$check((float)$quick['order']->cost === 3.05 && $quick['order']->products[0]['sumAsFloat'] === 3.05, 'Quick order cost follows the measured total, not price times base units');
$ordersBefore = sOrder::count();
foreach (['abc', [], '1.5', 0, -5] as $rawQuantity) {
    $quick = sCheckout::quickOrder(['productId' => 10, 'quantity' => $rawQuantity, 'email' => 'quick@shop.test']);
    $check($quick['success'] === false && ($quick['error_code'] ?? '') === 'checkout_validation', 'Invalid raw measured quantity is refused by validation');
}
$check(sOrder::count() === $ordersBefore, 'Refused measured quick orders are not persisted');
$events = [];
$_SESSION['sCart'] = [10 => [0 => 2530], 11 => [0 => 1]];
$mini = (new sCart())->getMiniCart();
$result = $checkout($mini['items'], ['cost' => $mini['totalSum']])->processOrder();
$order = sOrder::query()->find($result['order']->id);
$stored = array_column($order->products, null, 'product_id');
$created = array_values(array_filter($events, static fn ($event) => $event[0] === 'sCommerce.OrderCreated'))[0][1] ?? [];
$check((float)$order->cost === 15.54 && $stored[10]['quantity'] === 2530 && $stored[10]['measure'] === 'length' && $stored[10]['quantity_label'] === '2.53 m'
    && !isset($stored[11]['measure']), 'Checkout stores the cart snapshot');
$check(($created['order']->products[0]['factor'] ?? null) === 1000 && $created['cost'] === 15.54, 'OrderCreated carries the measured keys');

// 11. Count service: 5,000,000,000 impressions end to end.
$shop->config['product.measures'] = ['count' => ['max' => 10000000000] + MeasuredQuantity::DEFAULTS['count']];
$_SESSION['sCart'] = [];
$service = $add(['productId' => 14, 'quantity' => 5000000000]);
$serviceLine = $service['miniCart']['items'][0];
$check($service['success'] && $serviceLine['quantity'] === 5000000000 && $serviceLine['sumAsFloat'] === 12500000.0 && $serviceLine['quantity_label'] === '5,000,000,000'
    && $serviceLine['unit_price_label'] === '2.50 / 1,000' && !isset($serviceLine['line_weight']), 'A 5e9 service line in the cart');
$result = $checkout($service['miniCart']['items'], ['cost' => $service['miniCart']['totalSum']])->processOrder();
$order = sOrder::query()->find($result['order']->id);
$check((float)$order->cost === 12500000.0 && $order->products[0]['quantity'] === 5000000000
    && OrderCostRecalculator::productsCost($order->products, 'EUR') === 12500000.0, 'A 5e9 service line in the order and its cost');
$tracked = $shop->products[14];
$tracked->mode = sProduct::MODE_SIMPLE;
$check(MeasuredQuantity::rules($tracked)->max === MeasuredQuantity::INVENTORY_MAX, 'Count products with stock are capped at 2e9');
$tracked->mode = sProduct::MODE_SERVICE;
unset($shop->config['product.measures']);

// 12. Line weight: grams for the whole line, half up; absent without a product weight.
$check(MeasuredQuantity::lineWeight(['quantity' => 2533, 'weight' => 125] + $cable->lineKeys()) === 317
    && MeasuredQuantity::lineWeight(['quantity' => 2532, 'weight' => 125] + $cable->lineKeys()) === 317
    && MeasuredQuantity::lineWeight(['quantity' => 2531, 'weight' => 125] + $cable->lineKeys()) === 316, 'Line weight rounds half up');
$check(MeasuredQuantity::lineWeight(['quantity' => 2530, 'weight' => 0] + $cable->lineKeys()) === null && MeasuredQuantity::lineWeight(['quantity' => 3, 'weight' => 125]) === null
    && MeasuredQuantity::lineWeight(['quantity' => 1037, 'weight' => 0] + $per100g->lineKeys()) === 1037
    && MeasuredQuantity::lineWeight(['quantity' => 2530, 'actual_quantity' => 2553, 'weight' => 100] + $cable->lineKeys()) === 255, 'Weights of volume, piece, weight and actual lines');

// 13. Taxes, excise and costs of measured lines.
$excised = OrderTaxes::applyRetailExcise([['priceAsFloat' => 1.2, 'quantity' => 2530, 'retail_excise_rate' => 5] + $cable->lineKeys()]);
$check($excised[0]['retail_excise_amount'] === VatDisplay::excise(3.04, 5), 'Retail excise uses the exact line total');
$check(OrderCostRecalculator::productsCost([['priceAsFloat' => 1.2, 'price' => '1.20', 'quantity' => 2530] + $cable->lineKeys(), ['priceNumber' => 10, 'quantity' => 2]], 'EUR') === 23.04
    && OrderCostRecalculator::productsCost([['price' => '1.20', 'quantity' => 2530, 'actual_quantity' => 2553] + $cable->lineKeys()], 'EUR') === 3.06, 'Costs use the billable quantity');
$module = file_get_contents($package . '/module/sCommerceModule.php');
$check(str_contains($module, '$quantity = $measured ? 1 : max(1, (int) ($product[\'quantity\'] ?? 1));')
    && str_contains($module, 'MeasuredQuantity::lineSum($product, $price) : $price * $quantity;'), 'The dashboard counts a measured line as one unit with its exact revenue');

// 14. Manager edits: rules stay server-side, quantities are strict, stock follows the edit.
$migrationCommit = require $package . '/database/migrations/2026_10_20_000003_add_inventory_committed_at_to_orders.php';
$migrationCommit->up();
foreach ([10 => 10000, 11 => 50, 12 => 20] as $id => $inventory) {
    $db->table('s_products')->insert(['id' => $id, 'sku' => "P$id", 'inventory' => $inventory]);
}
$stock = static fn (int $id): int => (int)DB::table('s_products')->where('id', $id)->value('inventory');
$commitMode = static function (string $mode) use ($app): void {
    $app['config']->set('seiger.settings.sCommerce.product', ['inventory_on' => 2, 'inventory_commit' => $mode, 'inventory_release_on_cancel' => 1]);
};
$lineOf = static function (int $productId, int $quantity) use ($shop): array {
    $product = $shop->products[$productId];
    $line = ['id' => $productId, 'title' => $product->title, 'price' => number_format($product->price, 2, '.', ''), 'priceAsFloat' => $product->price, 'quantity' => $quantity]
        + app(Seiger\sCommerce\Services\OrderLineIdentity::class)->snapshot($product);
    return $line + MeasuredQuantity::lineFields(MeasuredQuantity::rules($product), $quantity, $line['price'], 125);
};
$newOrder = static function (array $lines, array $attributes = []): sOrder {
    $order = new sOrder();
    $order->forceFill($attributes + ['identifier' => bin2hex(random_bytes(8)), 'currency' => 'EUR', 'status' => 1, 'payment_status' => 0, 'lang' => 'en',
        'products' => $lines, 'cost' => OrderCostRecalculator::productsCost($lines, 'EUR') + 5]);
    $order->save();
    return $order;
};
$commitMode(StockCommitter::MODE_ORDER_CREATED);
$order = $newOrder([$lineOf(10, 2530), $lineOf(11, 2)]);
$check($stock(10) === 7470 && $stock(11) === 48, 'Order creation commits base units and pieces');
$edited = $order->products;
$edited[0] = array_replace($edited[0], ['quantity' => 3000, 'factor' => 1, 'measure' => 'weight', 'price_per' => 1000, 'quantity_step' => 1, 'actual_quantity' => 1]);
unset($edited[1]);
$edited[] = ['id' => 12, 'title' => 'Added', 'price' => '4.00', 'priceNumber' => 4, 'quantity' => 3];
$edited[] = ['id' => 10, 'title' => 'Second cut', 'price' => '1.20', 'priceNumber' => 1.2, 'quantity' => 1500, 'measure' => 'weight', 'factor' => 1];
$editor = new OrderLineEditor();
$check($editor->apply($order, array_values($edited)) === true, 'Edits are applied');
[$cut, $added, $second] = $order->products;
$check($cut['measure'] === 'length' && $cut['factor'] === 1000 && $cut['price_per'] === 1 && $cut['quantity_step'] === 10 && !isset($cut['actual_quantity'])
    && $cut['quantity_label'] === '3 m' && $cut['sumAsFloat'] === 3.6, 'Client-tampered rule and actual keys are ignored for stored lines');
$check(!isset($added['measure']) && $added['line_key'] === '12:0' && $second['measure'] === 'length' && $second['factor'] === 1000 && $second['line_key'] === '10:0'
    && $second['quantity_label'] === '1.5 m', 'New lines get server-side rules from the product');
$check((float)$order->cost === 3.6 + 12.0 + 1.8 + 5.0, 'Cost is recalculated with the delivery kept');
DB::transaction(static function () use ($order): void {
    $order->save();
    (new StockCommitter())->adjust($order);
});
$check($stock(10) === 10000 - 4500 && $stock(11) === 50 && $stock(12) === 17, 'Stock follows changed, removed and added lines');
$check(sOrder::query()->find($order->id)->meta[StockCommitter::META_KEY]['lines'] === [10 => 4500, 12 => 3], 'Committed quantities follow the edit');
$check((new StockCommitter())->adjust($order) === false, 'Adjusting twice changes nothing');
$order->status = sOrder::ORDER_STATUS_CANCELED;
$order->save();
$check($stock(10) === 10000 && $stock(11) === 50 && $stock(12) === 20, 'Release restores exactly the adjusted quantities');
$strict = $newOrder([$lineOf(10, 2530)]);
foreach ([2535 => 'measured_quantity_step', 900 => 'measured_quantity_below_min', 'abc' => 'measured_quantity_invalid'] as $quantity => $key) {
    $before = $strict->products;
    $check($refused(fn () => $editor->apply($strict, [array_replace($strict->products[0], ['quantity' => $quantity])]), $key) && $strict->products === $before, "Strict manager quantity $quantity");
}
$pieceOrder = $newOrder([$lineOf(11, 2)]);
$editor->apply($pieceOrder, [array_replace($pieceOrder->products[0], ['quantity' => 3])]);
$check((float)$pieceOrder->cost === 42.5 && !isset($pieceOrder->products[0]['measure']), 'Piece line recalculation is unchanged');
$pieceOrder->save();
$check((new StockCommitter())->adjust($pieceOrder) === true && $stock(11) === 47, 'Piece lines follow manager edits too');
$big = $newOrder([$lineOf(10, 2530)]);
$editor->apply($big, [array_replace($big->products[0], ['quantity' => 1000000])]);
try {
    DB::transaction(static function () use ($big): void {
        $big->save();
        (new StockCommitter())->adjust($big);
    });
    $check(false, 'Shortage is refused');
} catch (StockShortageException) {
    $check(sOrder::query()->find($big->id)->products[0]['quantity'] === 2530, 'A shortage rolls back the edit');
}
final class ProviderCommits implements StockProviderInterface
{
    public function available(sProduct $product, int $modificationId = 0, array $context = []): ?int { return null; }
    public function commit(sOrder $order): bool { return true; }
    public function release(sOrder $order): bool { return true; }
}
$app->instance(StockProviderInterface::class, new ProviderCommits());
$provided = $newOrder([$lineOf(10, 2530)]);
$before = $stock(10);
$editor->apply($provided, [array_replace($provided->products[0], ['quantity' => 3000])]);
$provided->save();
$check((new StockCommitter())->adjust($provided) === false && $stock(10) === $before, 'Provider-committed orders make no local write');
$app->offsetUnset(StockProviderInterface::class);
$commitMode(StockCommitter::MODE_NONE);
$uncommitted = $newOrder([$lineOf(10, 2530)]);
$before = $stock(10);
$editor->apply($uncommitted, [array_replace($uncommitted->products[0], ['quantity' => 3000])]);
$uncommitted->save();
$check((new StockCommitter())->adjust($uncommitted) === false && $stock(10) === $before, 'inventory_commit = none makes no write');
$ukOrder = $newOrder([$lineOf(10, 2530)], ['lang' => 'uk']);
$editor->apply($ukOrder, $ukOrder->products);
$check($ukOrder->products[0]['quantity_label'] === '2,53 м' && MeasuredQuantity::label(2530, MeasuredQuantity::fromLine($ukOrder->products[0])) === '2.53 m',
    'Stored labels use the order language, manager rendering the manager language');

// 15. Product settings: validation, measure changes and deposits.
$product = $load($pieceId);
$check($refused(fn () => MeasuredQuantity::productSettings(['measure' => 'length'], $product), 'measured_change_refused'), 'Measure change refused with stock');
$db->table('s_products')->where('id', $pieceId)->update(['inventory' => 0]);
$product = $load($pieceId);
$check(MeasuredQuantity::productSettings(['measure' => 'length', 'price_unit' => 'cm', 'price_per' => '10', 'quantity_step' => '10', 'quantity_min' => '', 'quantity_tolerance' => '0', 'quantity_rounding' => 'down'], $product)
    === ['measure' => 2, 'price_unit' => 'cm', 'price_per' => 10, 'quantity_step' => 10, 'quantity_min' => null, 'quantity_tolerance' => 0, 'quantity_rounding' => 'down'], 'Valid settings become columns; empty means the default');
$check(MeasuredQuantity::productSettings(['measure' => '0', 'price_unit' => 'kg'], $product) === ['measure' => 0] + array_fill_keys(array_slice($columns, 1), null), 'Piece products have no settings');
$check($refused(fn () => MeasuredQuantity::productSettings(['measure' => 2, 'price_unit' => 'kg'], $product), 'measured_settings_price_unit')
    && $refused(fn () => MeasuredQuantity::productSettings(['measure' => 2, 'price_unit' => 'm', 'price_per' => '10'], $product), 'measured_settings_price_per')
    && $refused(fn () => MeasuredQuantity::productSettings(['measure' => 2, 'quantity_step' => '100', 'quantity_min' => '50'], $product), 'measured_settings_min')
    && $refused(fn () => MeasuredQuantity::productSettings(['measure' => 2, 'quantity_step' => '0'], $product), 'measured_settings_step')
    && $refused(fn () => MeasuredQuantity::productSettings(['measure' => 2, 'quantity_rounding' => 'sideways'], $product), 'measured_settings_rounding')
    && $refused(fn () => MeasuredQuantity::productSettings(['measure' => 9], $product), 'measured_settings_measure'), 'Invalid settings are refused');
$product->deposit = 0.5;
$check($refused(fn () => MeasuredQuantity::productSettings(['measure' => 'volume'], $product), 'measured_deposit_refused'), 'Measured products cannot have a deposit');
$newOrder([['id' => $pieceId, 'product_id' => $pieceId, 'quantity' => 1, 'price' => '1']]);
$product = $load($pieceId);
$check($refused(fn () => MeasuredQuantity::productSettings(['measure' => 'length'], $product), 'measured_change_refused')
    && MeasuredQuantity::productSettings(['measure' => 0], $product)['measure'] === 0, 'Measure change refused once the product is ordered');
$check(str_contains($module, 'MeasuredQuantity::productSettings(request()->only(') && str_contains($module, "quantity_notice"), 'The product form validates the settings');

// 16. CSV columns round-trip through export values and import (sTask stubs when it is not installed).
if (!class_exists(Seiger\sTask\Workers\BaseWorker::class)) {
    eval('namespace Seiger\sTask\Workers; abstract class BaseWorker {}');
}
$csv = (new ReflectionClass(Seiger\sCommerce\Integration\ImportExportCSV::class))->newInstanceWithoutConstructor();
$apply = (new ReflectionMethod($csv, 'applyMeasuredColumns'))->getClosure($csv);
$source = new sProduct();
$source->forceFill(['measure' => 1, 'price_unit' => 'g', 'price_per' => 100, 'quantity_step' => 5, 'quantity_min' => 50, 'quantity_tolerance' => 300, 'quantity_rounding' => 'nearest']);
// Export: `measure` as its alias, the other columns as stored (empty for NULL).
$exported = ['measure' => sProduct::MEASURE_ALIASES[$source->measure]];
foreach (array_slice($columns, 1) as $column) {
    $exported[$column] = (string)($source->{$column} ?? '');
}
$target = new sProduct();
$apply($target, $exported);
$check(array_intersect_key($target->getAttributes(), array_flip($columns)) === array_intersect_key($source->getAttributes(), array_flip($columns)), 'CSV columns round-trip');
$partial = new sProduct();
$partial->forceFill(['measure' => 2, 'quantity_step' => 10]);
$apply($partial, ['quantity_min' => '1000']);
$check($partial->measure === 2 && $partial->quantity_step === 10 && $partial->quantity_min === 1000, 'Missing CSV columns keep the product values');
$failed = false;
try {
    $apply(new sProduct(), ['measure' => 'length', 'price_unit' => 'kg']);
} catch (RuntimeException) {
    $failed = true;
}
$csvSource = file_get_contents($package . '/src/Integration/ImportExportCSV.php');
$check($failed && str_contains($csvSource, "case 'measure':") && str_contains($csvSource, '$this->applyMeasuredColumns($product, $data);'), 'Invalid CSV settings fail the row');
$check(Seiger\sCommerce\Integration\GoogleMerchantFeed::unitPricingMeasure($shop->products[10]) === '1m'
    && Seiger\sCommerce\Integration\GoogleMerchantFeed::unitPricingMeasure((object)['measure' => 1, 'price_unit' => 'g', 'price_per' => 100]) === '100g'
    && Seiger\sCommerce\Integration\GoogleMerchantFeed::unitPricingMeasure((object)['measure' => 1, 'price_unit' => 'g', 'price_per' => 1000]) === '1kg'
    && Seiger\sCommerce\Integration\GoogleMerchantFeed::unitPricingMeasure($shop->products[14]) === null
    && Seiger\sCommerce\Integration\GoogleMerchantFeed::unitPricingMeasure($shop->products[11]) === null, 'Merchant feed unit pricing');

// 17. Storefront helpers and capability flags.
$input = Shop::quantityInput($shop->products[10], ['data-sc-quantity' => 10]);
$check(str_contains($input, 'name="amount"') && str_contains($input, 'step="0.01"') && str_contains($input, 'min="1"') && str_contains($input, 'value="1"') && str_contains($input, 'data-sc-measure="length"')
    && str_contains($input, 'data-sc-factor="1000"') && str_contains($input, 'data-sc-quantity="10"') && str_contains($input, 'data-sc-unit="m"'), 'quantityInput renders a measured input');
$check(Shop::quantityInput($shop->products[11]) === '<input type="number" name="quantity" min="1" step="1" value="1">', 'quantityInput keeps piece inputs');
$check(Shop::supportsIntegration('measured-quantities') && Shop::supportsIntegration('actual-quantities') && Shop::INTEGRATION_EVENTS_VERSION === 1, 'Capability flags');

echo "$checks measured quantity checks passed (isolated SQLite; no site database)\n";
