---
layout: page
title: Events
description: sCommerce Events list
permalink: /events/
---
Evo's events provide a simple observer pattern implementation, allowing you to subscribe and listen
for various events that occur within your application. Using events, it is convenient to manage
additional sCommerce parameters. Below is a list of reserved events.

## Enhancement of interface management capabilities

### sCommerceManagerAddTabEvent

```php
Event::listen('evolution.sCommerceManagerAddTabEvent', function($params) {
    dd($params);
});
```

## Product manipulation

### sCommerceAfterProductSave

```php
Event::listen('evolution.sCommerceAfterProductSave', function($params) {
    dd($params);
});
```

### sCommerceAfterProductDuplicate

```php
Event::listen('evolution.sCommerceAfterProductDuplicate', function($params) {
    dd($params);
});
```

## Cart pricing

`sPriceResolver` is the single price-resolution point for the product model,
storefront, cart, and checkout. When `sPricing` is installed, it receives the
current-user context and may return an applicable personal or cumulative price.
Cached page data remains user-neutral; the contextual price is applied during
the request, so shared cache data cannot expose one customer's price to another.

When `sPricing` is unavailable or has no applicable price, sCommerce uses the
historical retail price resolver:

- `price_special` is used when it is greater than `0` and lower than `price_regular`;
- otherwise `price_regular` is used.

Wholesale pricing is controlled on the server side, not from frontend request data. This keeps cart and
checkout totals protected from client-side manipulation.

### Session price mode

Use the `sCart` facade to switch the current customer session to wholesale pricing:

```php
use Seiger\sCommerce\Facades\sCart;

sCart::setPriceMode('wholesale');
```

Reset the session back to default retail pricing:

```php
sCart::clearPriceMode();
```

Wholesale prices use the same rule as retail prices:

- `price_opt_special` is used when it is greater than `0` and lower than `price_opt_regular`;
- otherwise `price_opt_regular` is used.

### sCommerce.CheckoutValidationRules

Dispatched by `sCheckout::getValidationRules(array $data)` after base rules and selected delivery-method rules have been merged.

- `data`: the input data passed to `getValidationRules()`.
- `rules`: the final rules array, passed by reference. Add or replace keys, or use `unset()` to remove a rule.

Place the listener in `core/custom/packages/main/plugins/sCommerceEvents.php` when the main service provider loads that directory. Modify `rules` directly; returned arrays are not applied. Listeners see changes made by earlier listeners, so a later listener can overwrite them.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.CheckoutValidationRules', function (array $params) {
    $rules = &$params['rules'];
    $data = $params['data'];

    $rules['user.email'] = 'nullable|email|max:255';
    $rules['user.first_name'] = ['required', 'string', 'max:100'];
    unset($rules['user.middle_name']);
});
```

`setOrderData()` subsequently filters the rules to keys present in the submitted data. Adding `required` here does not make an omitted field mandatory in that partial update. Removing a rule also removes that field from this validation path; it does not automatically persist arbitrary fields. Quick-order validation uses its own rules and does not call this hook.

These three events use the exact names documented on this page, without the `evolution.` prefix. The previous price-event names `evolution.sCommerceResolveProductPriceMode` and `evolution.sCommerceResolveProductPrice` are no longer dispatched; update project listeners and sPricing together with sCommerce.

### sCommerce.ResolveProductPriceMode

Dispatched by `sCart` when adding a product or reading the mini-cart, and by `sCheckout` when resolving a product price for a quick order. The first non-empty string response is used: `wholesale` and `opt` normalize to `wholesale`; any other string normalizes to `auto` (case-insensitive, surrounding whitespace ignored). Return `null` to keep the session mode unless another listener overrides it. This event does not change the session.

Use this event to override the session price mode for a specific product.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolveProductPriceMode', function(array $payload) {
    $product = $payload['product'];

    if ((int)$product->id === 123) {
        return 'wholesale';
    }

    return null;
});
```

The payload contains:

- `product`: current product model;
- `optionId`: cart option ID;
- `priceMode`: resolved session mode before product-level overrides.

### sCommerce.ResolveProductPrice

Dispatched by `sPriceResolver::resolve()` after resolving the base/promotional price. The payload contains `product` (product object), `optionId` (option ID, default `0`), `priceMode` (`auto` or `wholesale`), `currency` (display currency code), and `pricing` (the initial array with `priceMode`, `priceAsFloat`, `oldPriceAsFloat`, `price`, `oldPrice`). Amounts must be returned in the display currency.

The first numeric or array response is applied; responses are not merged across listeners. A numeric response sets the current price and resets the old price to `0`. An array replaces only the supplied supported keys. Return `null` to leave the price unchanged and allow another listener to provide it. All listeners are dispatched before their responses are inspected. `sPricing` listens to this event and returns `priceAsFloat` and `oldPriceAsFloat`; consider registration order when adding another price provider.


Use this event when a project needs to set a fully custom price for a specific product.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolveProductPrice', function(array $payload) {
    $product = $payload['product'];

    if ((int)$product->id === 123) {
        return [
            'priceAsFloat' => 77.50,
            'oldPriceAsFloat' => 100.00,
        ];
    }

    return null;
});
```

The listener can return either a numeric price or an array with any of these keys:

- `priceMode`
- `price`
- `priceAsFloat`
- `oldPrice`
- `oldPriceAsFloat`

If formatted `price` or `oldPrice` values are omitted, sCommerce formats them from the numeric values.
