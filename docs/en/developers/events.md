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

## Order domain events

Since 1.5.0, sCommerce dispatches typed order events from the `sOrder` model. They cover storefront checkout, quick orders, payment pages and callbacks, the manager order form, bulk actions, repeat orders and any other code that saves orders through Eloquent. Use them instead of `evolution.sCommerceAfterOrderSave` in new code.

Like the pricing events, these names have no `evolution.` prefix. Each listener receives one associative array.

| Event | Dispatched when | Additional keys |
|---|---|---|
| `sCommerce.OrderCreated` | An order was created | none |
| `sCommerce.OrderUpdated` | Order columns changed | `changes` |
| `sCommerce.OrderStatusChanged` | `status` changed | `oldStatus`, `newStatus`, `oldStatusKey`, `newStatusKey` |
| `sCommerce.OrderPaymentStatusChanged` | `payment_status` changed | `oldPaymentStatus`, `newPaymentStatus`, `oldPaymentStatusKey`, `newPaymentStatusKey` |
| `sCommerce.OrderCancelled` | `status` became `ORDER_STATUS_CANCELED` | `previousStatus` |
| `sCommerce.OrderDeleted` | An order was soft-deleted or force-deleted | `forced` (`false` for a soft delete) |

Every payload contains:

- `event`: the event object (`Seiger\sCommerce\Events\Order*`);
- `order`: a detached `sOrder` copy of the committed row captured when the change was recorded. Use it only to read data. Do not save it, and re-read the order when you need its current state;
- `orderId`, `orderUuid`, `reference`, `status`, `paymentStatus`, `cost`, `currency`, `isQuick`, `siteKey` (the order `domain`);
- `source`, `actorId`, `correlationId`, `causationId`, `integrationActor`: the context of the change (see below);
- `eventId` (UUID v4) and `occurredAt` (UTC, for example `2026-10-20T08:15:30.123Z`).

`changes` maps every changed column to `['old' => ..., 'new' => ...]`, with JSON columns decoded. `updated_at` is never included. Status keys are untranslated machine names derived from the constants, for example `canceled`, `ready_for_shipment`, `paid` or `authorized`. You can get them with `sOrder::orderStatusKey()` and `sOrder::paymentStatusKey()`. A payment status change is a change of the order projection, not proof that money was captured.

New orders receive a UUID in `s_orders.uuid` when the column exists. Existing orders are not backfilled and may keep `NULL`. The local order `id` remains the stable key for integrations; `uuid` and `reference` are additional identifiers.

### Delivery rules

- Events are dispatched only after the database transaction commits. Rolled-back changes never produce events.
- Inside an event scope, events are buffered until the outermost scope ends. They are then merged for each order: `OrderCreated` absorbs later updates and carries the final snapshot. Several `OrderUpdated`, `OrderStatusChanged` or `OrderPaymentStatusChanged` events become one event from the first old value to the last new value. A value that returns to its original value is dropped. `OrderCancelled` and `OrderDeleted` are never merged.
- For each order, events are dispatched in this order: created, updated, status changed, payment status changed, cancelled, deleted. Orders keep the order in which they were first changed.
- A listener exception is logged to the `scommerce` channel and does not turn a successful checkout or manager action into an error. Later events are still dispatched. Other listeners of the failing event are not called after the exception.
- Do not return `false` from a listener: Laravel stops calling the remaining listeners of that event.
- Direct `DB::table('s_orders')->update()` queries bypass model events.
- Dispatch happens in the same PHP process and is not a durable outbox. If an integration must not lose changes, it needs its own durable storage and periodic reconciliation.

### Event scope

`Seiger\sCommerce\Events\EventScope` tells listeners who made the change. sCommerce uses these `source` values: `storefront`, `storefront_quick`, `storefront_pay`, `payment_callback`, `manager`, `manager_bulk_status`, `manager_bulk_menu`, `manager_repeat`, `manager_payment_action`, `offline_settlement` and `system` (the default when no scope is open).

Code that writes orders on behalf of an integration should open its own scope. Listeners can then ignore changes caused by their own writes:

```php
use Seiger\sCommerce\Events\EventScope;

EventScope::run('erp', 0, function () use ($order) {
    $order->status = \Seiger\sCommerce\Models\sOrder::ORDER_STATUS_SHIPPED;
    $order->save();
}, correlationId: $flowId, causationId: $remoteEventId, integrationActor: 'erp-sync');
```

`correlationId` and `causationId` are opaque strings of up to 64 characters. Nested scopes inherit the actor, correlation, causation and integration actor when they are not specified. `EventScope::run()` always closes the scope; when you use `EventScope::begin()`, call `end()` in a `finally` block.

### Example listener

Place listeners in `core/custom/packages/main/plugins/sCommerceEvents.php` when the main service provider loads that directory:

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.OrderStatusChanged', function (array $payload) {
    if ($payload['integrationActor'] === 'erp-sync') {
        return; // Ignore the echo of our own write.
    }

    if ($payload['newStatusKey'] === 'canceled') {
        // Notify an external system about $payload['orderId'] / $payload['orderUuid'].
    }
});
```

### Detecting support

Detect the feature instead of comparing package versions:

```php
use Seiger\sCommerce\sCommerce;

$supported = class_exists(sCommerce::class)
    && defined(sCommerce::class . '::INTEGRATION_EVENTS_VERSION')
    && sCommerce::supportsIntegration('order-events');
```

`sCommerce::INTEGRATION_EVENTS_VERSION` (currently `1`) changes only when payloads change incompatibly. `sCommerce::integrationFeatures()` lists the available feature keys.

## Payment ledger events

Since 1.5.0, every write to the payments ledger (`s_order_payments`) dispatches an event, whichever code made it. They follow the same delivery rules as the order events: after commit only, never for rolled-back writes, with the same event scope context. Payment events are never merged.

| Event | Dispatched when | Additional keys |
|---|---|---|
| `sCommerce.PaymentRecorded` | A ledger row was created | none |
| `sCommerce.PaymentStatusChanged` | The `status` of a ledger row changed | `oldStatus`, `newStatus` |

Every payload contains:

- `event` and `payment` (a detached `sOrderPayment` copy for reading only);
- `paymentId`, `orderId`, `orderUuid`, `sequence`, `kind`, `status`, `provider`, `providerRef`, `parentProviderRef`, `metadata`;
- `amount` and `amountMinor` (integer minor units, for example cents), and `currency`;
- the context keys `source`, `actorId`, `correlationId`, `causationId`, `integrationActor`, `eventId` and `occurredAt`.

A status change that also changes the order's payment status is followed by `sCommerce.OrderPaymentStatusChanged`. `sCommerce::supportsIntegration('payment-ledger-api')` reports support. See [Recording payments](payments/index.md#recording-payments-ledger-api) for the API that writes the ledger.

## Payment schedule event

### sCommerce.ResolvePaymentSchedule

Dispatched once for every new order (checkout and quick order) before it is first saved. A listener can split the order cost into a deposit and a final payment:

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolvePaymentSchedule', function (array $payload) {
    // $payload['order'] is the unsaved sOrder; $payload['schedule'] is passed by reference.
    $payload['schedule'] = [
        ['kind' => 'deposit', 'amount' => 30, 'due' => 'checkout'],
        ['kind' => 'final', 'amount' => $payload['order']->cost - 30, 'due' => 'manual'],
    ];
});
```

Without listeners the schedule stays empty and the full cost is charged as before. See [Payment schedules](payments/index.md#payment-schedules-deposit-and-final-payment) for the item format and how payment methods receive `payment_request`.

## Additional manager and frontend events

Manager events use the evolution. prefix with Laravel Event listeners. Pricing events use their literal sCommerce.* names without that prefix. Frontend callbacks use the names below; do not infer that every callback has the same response fields.

- `evolution.sCommerceManagerTabsEvent`
- `evolution.sCommerceManagerSettingsSaveEvent`
- `evolution.sCommerceAfterProductContentSave`
- `evolution.sCommerceAfterOrderSave`: legacy and manager-only. It fires after successful saves in the manager order form, bulk status changes, bulk "mark as paid" and repeat orders, with the saved order as `item`. It does not fire for storefront orders; use the order domain events above.

```javascript
sCommerce.onAddedToCart = (data) => console.log(data);
sCommerce.onRemovedFromCart = (data) => console.log(data);
sCommerce.onUpdatedCart = (data) => console.log(data);
sCommerce.onFastOrder = (data) => console.log(data);
sCommerce.onWishlist = (data) => console.log(data);
```
