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
