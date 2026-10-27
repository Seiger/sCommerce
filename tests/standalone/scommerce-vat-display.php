<?php

// php tests/standalone/scommerce-vat-display.php
// Isolated checks for storefront prices with or without VAT: display values only, the
// charged price never changes. In-memory SQLite, fake products, no CMS/site bootstrap.
require __DIR__ . '/fixtures/checkout-harness.php';

use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Cart\sCart;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Services\VatDisplay;
use Seiger\sCommerce\sCommerce;

$shop->products = [1 => new FakeProduct(1, 'MUG', 24.6), 2 => new FakeProduct(2, 'BOOK', 11.0)];
$shop->products[1]->oldPrice = 30.75;
$shop->config['basic.deliveries_on'] = 0;

// Switched off (the default): prices are always shown with VAT, whatever the rate or session say.
$shop->config['product.vat_rate'] = 23;
VatDisplay::setMode('net');
$line = $cartLines([1 => [0 => 1]])[0];
$check(VatDisplay::mode() === 'gross' && $line['vatRate'] === 0.0 && $line['priceNetAsFloat'] === 24.6 && $line['displayPrice'] === '24.60 EUR', 'Off: no VAT display');
VatDisplay::clearMode();
unset($shop->config['product.vat_rate']);
$shop->config['product.vat_display_on'] = 1;

// Without a rate the net price equals the stored price.
$line = $cartLines([1 => [0 => 1]])[0];
$check($line['vatRate'] === 0.0 && $line['priceNetAsFloat'] === 24.6 && $line['displayPrice'] === '24.60 EUR' && $line['vatDisplay'] === 'gross', 'No rate: net equals gross');

// With the shop rate, net is derived from the stored gross price; the default display stays gross.
$shop->config['product.vat_rate'] = 23;
$cart = (function () { $_SESSION['sCart'] = [1 => [0 => 2], 2 => [0 => 1]]; return (new sCart())->getMiniCart(); })();
$mug = $cart['items'][0];
$check($mug['priceGrossAsFloat'] === 24.6 && $mug['priceNetAsFloat'] === 20.0 && $mug['priceNet'] === '20.00 EUR'
    && $mug['oldPriceNetAsFloat'] === 25.0 && $mug['oldPriceNet'] === '25.00 EUR', 'Net prices from the shop rate');
$check($mug['displayPrice'] === '24.60 EUR' && $mug['displayOldPrice'] === '30.75 EUR' && $mug['sumNetAsFloat'] === 40.0 && $mug['displaySumAsFloat'] === 49.2, 'Gross display by default');
$check($cart['totalSum'] === 60.2 && $cart['totalSumNet'] === 48.94 && $cart['totalSumDisplay'] === 60.2 && $cart['vatDisplay'] === 'gross', 'Cart totals with and without VAT');

// A visitor switches to net: only display values change.
(new sCart())->setVatDisplay('NET');
$cart = (new sCart())->getMiniCart();
$mug = $cart['items'][0];
$check($mug['displayPrice'] === '20.00 EUR' && $mug['displayPriceAsFloat'] === 20.0 && $mug['displayOldPrice'] === '25.00 EUR'
    && $mug['displaySum'] === '40.00 EUR' && $cart['totalSumDisplay'] === 48.94 && $cart['totalSumDisplayFormatted'] === '48.94 EUR', 'Net display for this visitor');
$check((float)$mug['priceAsFloat'] === 24.6 && $mug['price'] === '24.60' && $mug['sumAsFloat'] === 49.2 && $cart['totalSum'] === 60.2, 'Charged prices and sums unchanged');
$check((new sCart())->getVatDisplay() === 'net' && $_SESSION['sCommerce']['vatDisplay'] === 'net', 'Mode kept in the session');
(new sCart())->setVatDisplay('anything');
$check(VatDisplay::mode() === 'gross', 'Unknown modes fall back to gross');
(new sCart())->setVatDisplay('net');
(new sCart())->clearVatDisplay();
$shop->config['product.vat_display'] = 'net';
$check(VatDisplay::mode() === 'net', 'Shop default applies without a session choice');
$shop->config['product.vat_display'] = 'gross';

// Listeners: a reduced rate per product and net prices for business customers.
Event::listen(VatDisplay::RATE_EVENT, static fn (array $payload) => $payload['product']->sku === 'BOOK' ? 10 : ($payload['product']->sku === 'MUG' ? 150 : null));
$business = false;
Event::listen(VatDisplay::MODE_EVENT, static function (array $payload) use (&$business): ?string { return $business ? 'net' : null; });
$cart = (new sCart())->getMiniCart();
$check($cart['items'][1]['vatRate'] === 10.0 && $cart['items'][1]['priceNetAsFloat'] === 10.0, 'Per-product rate override');
$check($cart['items'][0]['vatRate'] === 23.0, 'Out-of-range rates are ignored');
$business = true;
$check((new sCart())->getMiniCart()['items'][1]['displayPrice'] === '10.00 EUR', 'Mode override for business customers');
$business = false;

// Product pages read the same values from sProduct.
$product = (new sProduct())->forceFill(['id' => 9, 'sku' => 'LAMP', 'price_regular' => 123, 'price_special' => 0, 'currency' => 'EUR']);
$check($product->priceNet === '100.00 EUR' && $product->priceNetAsFloat === 100.0 && $product->priceGrossAsFloat === 123.0
    && $product->displayPrice === '123.00 EUR' && $product->vatRate === 23.0 && $product->oldPriceNet === '', 'Product accessors');
VatDisplay::setMode('net');
$check($product->displayPrice === '100.00 EUR' && $product->displayPriceAsFloat === 100.0 && $product->priceAsFloat === 123.0, 'Product display follows the visitor mode');

// Retail excise: included in the price, charged on the price with VAT, kept on order lines for the receipt.
VatDisplay::clearMode();
$shop->products[3] = new FakeProduct(3, 'BEER', 129.15);
Event::listen(VatDisplay::EXCISE_EVENT, static fn (array $payload) => $payload['product']->sku === 'BEER' ? 5 : null);
$check(VatDisplay::exciseRate($shop->products[3]) === 0.0 && !isset($cartLines([3 => [0 => 1]])[0]['retail_excise_rate']), 'Off: no retail excise');
$shop->config['product.retail_excise_on'] = 1;
$check(VatDisplay::excise(129.15, 5) === 6.15 && VatDisplay::net(129.15, 23, 5) === 100.0 && VatDisplay::excise(10, 0) === 0.0, 'Excise is taken from the price with VAT');
$cart = (function () { $_SESSION['sCart'] = [3 => [0 => 2], 2 => [0 => 1]]; return (new sCart())->getMiniCart(); })();
$items = array_column($cart['items'], null, 'id');
$beer = $items[3];
$check($beer['retailExciseRate'] === 5.0 && $beer['retailExciseAmountAsFloat'] === 6.15 && $beer['retailExciseAmount'] === '6.15 EUR'
    && $beer['priceNetAsFloat'] === 100.0 && $beer['sumRetailExciseAsFloat'] === 12.3 && $beer['sumNetAsFloat'] === 200.0, 'Cart line shows the included excise');
$check($items[2]['retailExciseAmount'] === '' && !isset($items[2]['retail_excise_rate']) && $cart['totalRetailExcise'] === 12.3
    && $cart['totalRetailExciseFormatted'] === '12.30 EUR' && $cart['totalSum'] === 269.3, 'Excise totals; the charged total is unchanged');
$result = $checkout($cart['items'])->processOrder();
$order = sOrder::query()->find($result['order']->id);
$lines = array_column($order->products, null, 'product_id');
$check((float)$order->cost === 269.3 && $lines[3]['retail_excise_rate'] === 5 && $lines[3]['retail_excise_amount'] === 12.3
    && !isset($lines[2]['retail_excise_amount']), 'Order lines keep the excise for the receipt');
$edited = Seiger\sCommerce\Services\OrderTaxes::applyRetailExcise([array_replace($lines[3], ['quantity' => 3]), $lines[2]]);
$check($edited[0]['retail_excise_amount'] === 18.45 && !isset($edited[1]['retail_excise_amount']), 'Manager quantity edits recalculate the excise');
$module = file_get_contents($package . '/module/sCommerceModule.php');
$check(str_contains($module, 'OrderLineEditor())->apply(') && str_contains(file_get_contents($package . '/src/Services/OrderLineEditor.php'), 'OrderTaxes::applyRetailExcise(')
    && str_contains(file_get_contents($package . '/src/Controllers/OrderRepeatController.php'), 'OrderTaxes::applyRetailExcise('),
    'Manager and repeat saves recalculate the excise');
$check(str_contains(file_get_contents($package . '/views/ordersPrint.blade.php'), "retail_excise_included"), 'Print document shows the excise');
$check(sCommerce::supportsIntegration('retail-excise'), 'Feature key retail-excise');
VatDisplay::setMode('net');

// Orders keep the charged prices and no display values.
$result = $checkout($cartLines([1 => [0 => 1]]))->processOrder();
$order = sOrder::query()->find($result['order']->id);
$check($result['success'] && (float)$order->cost === 24.6 && (float)$order->products[0]['priceAsFloat'] === 24.6, 'Orders are charged the stored price');
$check(array_intersect_key($order->products[0], array_flip(VatDisplay::keys())) === [], 'Display values are not stored in order lines');
VatDisplay::clearMode();

$routes = file_get_contents($package . '/src/Http/routes.php');
$check(str_contains($routes, "Route::post('vat-display', \$vatDisplayHandler)") && str_contains($routes, 'VatDisplay::enabled()'), 'Storefront route for the visitor switch, only while enabled');
$settings = file_get_contents($package . '/views/partials/settingsProductConfiguration.blade.php');
$check(str_contains($settings, 'data-feature-toggle="vat_display_on"') && str_contains($settings, 'data-feature-toggle="retail_excise_on"')
    && str_contains($settings, 'data-feature="vat_display_on"'), 'Settings show the fields of enabled features only');
$check(str_contains(file_get_contents($package . '/src/Services/ProductListingService.php'), 'VatDisplay::pricing('), 'Listing cards carry the display values');
$check(sCommerce::supportsIntegration('vat-display'), 'Feature key vat-display');

echo "$checks VAT display checks passed (isolated SQLite; no site database)\n";
