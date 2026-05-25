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

By default, sCommerce uses the historical retail price resolver:

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

### sCommerceResolveProductPriceMode

Use this event to override the session price mode for a specific product.

```php
use Illuminate\Support\Facades\Event;

Event::listen('evolution.sCommerceResolveProductPriceMode', function(array $payload) {
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

### sCommerceResolveProductPrice

Use this event when a project needs to set a fully custom price for a specific product.

```php
use Illuminate\Support\Facades\Event;

Event::listen('evolution.sCommerceResolveProductPrice', function(array $payload) {
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
