---
layout: page
title: Currencies
description: sCommerce Currencies
permalink: /currencies/
---

The list of available currencies of this store, as well as their exchange rate,
is configured in the Settings tab.

## Available currencies

The list of available currencies is presented according to the ISO 4217 standard.

To view the entire list of available currencies in your controller, simply call
the `sCommerce::getCurrencies()` method. This will return the entire list available for ISO 4217.

```php
use Seiger\sCommerce\Facades\sCommerce;
...

$currencies = sCommerce::getCurrencies();
dd($currencies);
...

Illuminate\Support\Collection {#1351 ▼
  #items: array:158 [▼
    0 => array:6 [▼
      "name" => "UAE Dirham"
      "alpha" => "AED"
      "numeric" => "784"
      "symbol" => "د.إ"
      "exp" => 2
      "country" => "AE"
    ]
    1 => array:6 [▶]
    ...
    157 => array:6 [▶]
  ]
  #escapeWhenCastingToString: false
}
```

Currency data includes:
- currency name
- alphanumeric currency code
- digital currency code
- an international symbol
- the number of decimal places
- countries of distribution

## Product price and currency

You have the opportunity to set the price of the product depending on the selected currency.
The currency for all product prices is selected for the Price field.
If you use more than one currency in your store, you must make sure that one of
the currencies available for the site is added to the user session, the list of which is configured
in the **Admin Panel -> Modules -> Commerce -> Settings** tab.

You can use your site's base controller to set the currency for the user by default.

```php
if (!isset($_SESSION['currency'])) {
    $_SESSION['currency'] = 'USD';
}
```

If this is not done, then the default currency of the user will be the currency selected as
**Main currency** in the store configuration.

If there is a need to show the product price regardless of the user's currency selection,
then use the following function:

```php
echo $product->priceTo('EUR');
```

The `priceTo('XXX')` method will display the product price converted to the selected currency
in the form of a string formatted according to the sCommerce configuration.

If you want to get only an unformatted number with the price in the selected currency,
then use the `priceToNumber('XXX')` method.

## Exchange rates

Choose the available currencies and main currency in Settings. The Currencies tab appears when more than one currency is enabled. A → B is a separate conversion multiplier: 50.55 and 0.012345 are valid rates. Configure the reverse pair explicitly; rates are not fetched automatically. Values are stored in core/custom/config/seiger/settings/sCommerceCurrencies.php. A missing pair defaults to 1; an explicitly configured zero is not a reliable 1:1 fallback. Product display precision is configured separately from exchange-rate precision.


## Storefront currency switcher

Enable the currencies in Commerce settings and configure the conversion rates for the pairs you use. An absent rate defaults to `1`. `getCurrencies()` returns the currency directory; the switcher must use the shop’s `basic.available_currencies` list.

Add this POST route to your project’s route file loaded by its service provider. `/shop/currency` is a project route; sCommerce does not register it. The `web` middleware provides the form’s CSRF protection. Store the choice in `$_SESSION`, which sCommerce reads:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Seiger\sCommerce\Facades\sCommerce;

Route::middleware('web')->post('/shop/currency', function (Request $request) {
    $data = $request->validate([
        'currency' => ['required', 'string'],
    ]);

    $currency = strtoupper(trim($data['currency']));
    $allowed = (array) sCommerce::config('basic.available_currencies', []);

    abort_unless(in_array($currency, $allowed, true), 422);

    $_SESSION['currency'] = $currency;

    return back();
})->name('shop.currency');
```

Add the switcher to your Blade template:

```blade
<form method="POST" action="{{ route('shop.currency') }}">
    @csrf

    <select name="currency" onchange="this.form.submit()">
        @foreach((array) sCommerce::config('basic.available_currencies', []) as $code)
            <option value="{{ $code }}"
                @selected(sCommerce::currentCurrency() === $code)>
                {{ $code }}
            </option>
        @endforeach
    </select>

    <button type="submit">Change currency</button>
</form>
```

The route validates the selected code against the shop’s enabled currencies, stores it for the current session and redirects back. sCommerce reads the session first, then the `currency` cookie, and otherwise uses `basic.main_currency`. This example does not create a persistent cookie.

Keep the redirect: `currentCurrency()` caches the currency for the current request. The following request displays prices and recalculates cart totals in the selected currency. For an AJAX implementation, reload the page after a successful write instead of rendering prices in the same request. Place currency switching before checkout; it does not change the currency of an existing order.
