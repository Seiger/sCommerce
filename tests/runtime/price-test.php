<?php

/**
 * Support isolated package assertions without a site bootstrap.
 *
 * @since 1.4.0
 */
function evo()
{
    return $GLOBALS['app'];
}
/**
 * Support isolated package assertions without a site bootstrap.
 *
 * @since 1.4.0
 */
function config($key, $default = null)
{
    return Illuminate\Support\Arr::get($GLOBALS['rates'] ?? [], $key, $default);
}
require __DIR__ . '/bootstrap.php';
use Seiger\sCommerce\sCommerce;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Services\sPriceResolver;
use Illuminate\Support\Facades\Facade;
$GLOBALS['app'] = new Illuminate\Container\Container();
$app = $GLOBALS['app'];
Facade::setFacadeApplication($app);
$events = new Illuminate\Events\Dispatcher($app);
$app->instance('events', $events);
/**
 * Provide a controlled fixture for package behavior comparison.
 *
 * @since 1.4.0
 */
class PriceCommerceProbe extends sCommerce
{
    public int $formatted = 0;
    /**
     * Count formatting work while delegating to the real currency conversion.
     *
     * @since 1.4.0
     */
    public function convertPrice($price, $from = null, $to = null): string
    {
        ++$this->formatted;
        return parent::convertPrice($price, $from, $to);
    }
}
$commerce = (new ReflectionClass(PriceCommerceProbe::class))->newInstanceWithoutConstructor();
$app->instance('sCommerce', $commerce);
$app->instance(sPriceResolver::class, new sPriceResolver($commerce));
$currencies = new ReflectionProperty(sCommerce::class, 'currencies');
$current = new ReflectionProperty(sCommerce::class, 'currentCurrency');
$method = file_get_contents(__DIR__ . '/price-baseline.txt');
$method = str_replace('sCommerce::', '\Seiger\sCommerce\Facades\sCommerce::', $method);
eval('namespace Seiger\sCommerce\Models; class LegacyPriceProduct extends sProduct {' . $method . '}');
$currencyRows = [['alpha' => 'USD', 'exp' => 2, 'decimals' => '.', 'thousands' => ',', 'show' => 1, 'symbol' => '$', 'position' => 'before'], ['alpha' => 'EUR', 'exp' => 2, 'decimals' => ',', 'thousands' => '&nbsp;', 'show' => 1, 'symbol' => ' EUR', 'position' => 'after'], ['alpha' => 'JPY', 'exp' => 0, 'decimals' => '.', 'thousands' => ',', 'show' => 0], ['alpha' => 'GBP', 'exp' => 3, 'decimals' => '.', 'thousands' => '', 'show' => 1, 'symbol' => '&nbsp;£&nbsp;', 'position' => 'before']];
$currencies->setValue($commerce, collect($currencyRows));
$GLOBALS['rates'] = ['seiger' => ['settings' => ['sCommerceCurrencies' => ['USD_EUR' => 0.8, 'USD_JPY' => 150, 'USD_GBP' => 0.7]]]];
/**
 * Support isolated package assertions without a site bootstrap.
 *
 * @since 1.4.0
 */
function verify($ok, $message)
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message . PHP_EOL;
}
foreach (['USD', 'EUR', 'JPY', 'GBP', 'UNKNOWN'] as $currency) {
    foreach ([[2250, 0], [2250, 2000], [2250, 3000], [0, 0], [-10, 0], [12.345, 1.234]] as [$regular, $special]) {
        $current->setValue(null, $currency);
        $attrs = ['id' => 7, 'currency' => 'USD', 'price_regular' => $regular, 'price_special' => $special];
        $old = new Seiger\sCommerce\Models\LegacyPriceProduct($attrs);
        $old->setRawAttributes($attrs);
        $new = new sProduct();
        $new->setRawAttributes($attrs);
        $commerce->formatted = 0;
        $expected = $old->price;
        $oldFormats = $commerce->formatted;
        $commerce->formatted = 0;
        $actual = $new->price;
        verify($expected === $actual, 'same output ' . $currency . ' ' . $regular . '/' . $special . ' ' . json_encode($actual));
    }
}
foreach ([123.5, ['priceAsFloat' => 99.5, 'oldPriceAsFloat' => 120], ['priceAsFloat' => 9, 'price' => 'custom', 'oldPriceAsFloat' => 0, 'oldPrice' => 'custom old']] as $override) {
    $events->forget('sCommerce.ResolveProductPrice');
    $events->listen('sCommerce.ResolveProductPrice', fn($payload) => $override);
    $current->setValue(null, 'USD');
    $attrs = ['id' => 7, 'currency' => 'USD', 'price_regular' => 200, 'price_special' => 100];
    $old = new Seiger\sCommerce\Models\LegacyPriceProduct();
    $old->setRawAttributes($attrs);
    $new = new sProduct();
    $new->setRawAttributes($attrs);
    verify($old->price === $new->price, 'pricing listener numeric/string override preserved');
}
$events->forget('sCommerce.ResolveProductPrice');
$product = new sProduct();
$product->setRawAttributes(['currency' => 'USD', 'price_regular' => 200, 'price_special' => 100]);
$full = $commerce->resolveProductPricing($product, 'USD');
verify($full['price'] === '$100.00' && $full['oldPrice'] === '$200.00', 'default resolver display strings unchanged');
$GLOBALS['rates']['seiger']['settings']['sCommerceCurrencies']['USD_EUR'] = 2;
verify($commerce->convertPriceNumber(10, 'USD', 'EUR') === 20.0, 'changed exchange rate read immediately');
echo 'PHP ' . PHP_VERSION . PHP_EOL;
// Mutating the exposed collection must not leave normalized format state stale.
$events->forget('sCommerce.ResolveProductPrice');
$current->setValue(null, 'USD');
$collection = $currencies->getValue($commerce);
$definition = $collection->get(0);
$definition['symbol'] = 'US ';
$collection->put(0, $definition);
verify($commerce->getCurrencyFormat('USD')['symbol'] === 'US', 'in-place currency definition mutation is immediate');
$currencies->setValue($commerce, collect([['alpha' => 'USD', 'symbol' => 'NEW', 'show' => 1]]));
verify($commerce->getCurrencyFormat('USD')['symbol'] === 'NEW', 'replacement currency collection is immediate');
