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

### Prices with or without VAT

Since 1.5.0, the storefront can show prices with VAT (gross) or without VAT (net). Product prices are stored with VAT; this is display only, and the price charged in the cart and at checkout is always the stored price.

Settings (**Settings → Product**):

- **Prices without VAT** (`product.vat_display_on`, off by default) switches the feature on. While it is off, the settings below are hidden, prices are always shown with VAT, the display keys equal the charged prices and `scommerce/vat-display` answers `404`;
- **VAT rate, %** (`product.vat_rate`, default `0`): the rate included in product prices. With `0`, the net price equals the gross price;
- **Storefront prices by default** (`product.vat_display`, `gross` or `net`, default `gross`): the default for visitors who have not chosen, e.g. `net` for a B2B shop. It affects only the storefront;
- **Without VAT** in **Presentation of the list of products** (`products.show_field_price_net`, on by default): a manager product list column with the price without VAT and the included retail excise (`sProduct::priceNetTo()`), shown while prices without VAT are on.

Defaults of products sold by measure and the rounding to the step per unit have their own **Measured quantities** block on the same settings page.

The net price is `round(gross / (1 + rate / 100), 2)`. Templates read these values:

| Where | Keys |
|---|---|
| `sProduct` (product pages) | `$product->displayPrice`, `displayPriceAsFloat`, `displayOldPrice`, `priceNet`, `priceNetAsFloat`, `priceGross`, `priceGrossAsFloat`, `oldPriceNet`, `vatRate`; `$product->vatPricing()` returns all keys at once |
| `sCommerce::productCards()` (listings) | `displayPrice`, `displayOldPrice`, `priceNet`, `priceGross`, their `AsFloat` variants, `vatRate`, `vatDisplay` |
| `sCart::getMiniCart()` items | the same keys plus `sumNet`, `sumNetAsFloat`, `displaySum`, `displaySumAsFloat` |
| `sCart::getMiniCart()` totals | `totalSumNet`, `totalSumNetFormatted`, `totalSumDisplay`, `totalSumDisplayFormatted`, `vatDisplay` |

The existing `price`, `priceAsFloat`, `sum` and `totalSum` keys keep the charged prices. Display values are not stored in order lines.

Switch the current visitor on the server:

```php
use Seiger\sCommerce\Facades\sCart;

sCart::setVatDisplay('net');   // or 'gross'
sCart::getVatDisplay();        // 'net'
sCart::clearVatDisplay();      // back to the shop default
```

A storefront toggle can post `mode=gross|net` to `scommerce/vat-display` (route `sCommerce.vatDisplay`). It answers with `vatDisplay` and the updated `miniCart`.

#### sCommerce.ResolveProductVatRate

Return another rate for a product, for example a reduced rate. The payload contains `product`, `optionId` and `rate` (the shop rate). The first numeric response from `0` to `100` is used; return `null` to keep the shop rate.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolveProductVatRate', function (array $payload) {
    return str_starts_with((string) $payload['product']->sku, 'BOOK-') ? 10 : null;
});
```

#### sCommerce.ResolveVatDisplay

Override the display mode, for example to show net prices to logged-in business customers. The payload contains `mode` (the session choice or the shop default). The first `gross` or `net` response is used.

```php
Event::listen('sCommerce.ResolveVatDisplay', function (array $payload) {
    $userId = (int) evo()->getLoginUserID('web');
    return $userId && in_array($userId, config('shop.business_customers', []), true) ? 'net' : null;
});
```

`sCommerce::supportsIntegration('vat-display')` reports support.

#### Retail excise

Some goods (for example alcohol, tobacco and fuel) carry a retail excise that is charged on the price with VAT and printed separately on the receipt. The stored price includes it, so the charged price does not change.

- **Retail excise** (`product.retail_excise_on`, off by default) switches the feature on. While it is off, no excise is calculated or stored and its rate setting is hidden.
- **Retail excise rate, %** (`product.retail_excise_rate`, default `0`) sets a shop-wide rate. Usually it stays `0`, and a `sCommerce.ResolveProductRetailExciseRate` listener returns the rate for the affected products. Its payload contains `product`, `optionId` and `rate`; the first numeric response from `0` to `100` is used.
- For a stored price P and an excise rate r, the excise is `round(P × r / (100 + r), 2)`. The price without VAT then also excludes the excise: `round((P − excise) / (1 + VAT / 100), 2)`. For example, with VAT 23 % and excise 5 %, a price of 129.15 contains an excise of 6.15 and a price without VAT of 100.00.
- Display keys: `retailExciseRate`, `retailExciseAmount` and `retailExciseAmountAsFloat` on products, listing cards and cart items; `sumRetailExcise` and `sumRetailExciseAsFloat` on cart items; `totalRetailExcise` and `totalRetailExciseFormatted` in the mini-cart.
- Order lines of such products store `retail_excise_rate` and `retail_excise_amount` (the excise included in the line total) for the receipt. The amount is recalculated when a manager edits the lines or saves a repeat order. The manager order page and the print document show it as **Including retail excise**.

```php
Event::listen('sCommerce.ResolveProductRetailExciseRate', function (array $payload) {
    return in_array($payload['product']->sku, ['BEER-05', 'WINE-07'], true) ? 5 : null;
});
```

`sCommerce::supportsIntegration('retail-excise')` reports support.

### sCommerce.ResolveProductDeposit

Returns the returnable container deposit per unit of a product, for example a bottle deposit. It is dispatched only while **Returnable container deposits** is on. The payload contains `product`, `optionId`, `currency` and `deposit` (the product's **Container deposit** converted to `currency`). The first non-negative numeric response is used; return `null` to keep it. See [Returnable container deposits](payments/index.md#returnable-container-deposits).

```php
Event::listen('sCommerce.ResolveProductDeposit', function (array $payload) {
    return str_starts_with((string) $payload['product']->sku, 'WATER-19') ? 250 : null;
});
```

### sCommerce.CheckoutValidationRules

`getValidationRules(array $data, string $context = 'default')` is the single source
of standard, delivery and plugin rules. Normal checkout uses user, delivery
and payment fields; quick checkout uses quantity, phone and email fields.
Disabled delivery/payment features do not require their corresponding method.
Both sets include product array and integer quantity rules.

The Laravel Event payload contains `data`, `rules` passed by reference, and
`context` (`default` by default, `quick` for quick orders). Modify `rules` in place; return values are
ignored. Later listeners see earlier changes. A switch selects the base rules;
other contexts start with normal checkout rules and remain available to plugins.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.CheckoutValidationRules', function (array $params): void {
    if ($params['context'] === 'default') $params['rules']['user.email'] = 'nullable|email';
    $params['rules']['products'][] = function ($field, $items, $fail) {
        // Project checks; call $fail('Message') to reject.
    };
});
```

`setOrderData()` keeps its partial-update behavior: only rules for submitted
fields are applied. `processOrder()` validates prepared server order data
against the full same rule set before persistence and payment, including when
called directly. Quick checkout invokes the validator and this event once, after preparing
server product lines. Submitted quantities stay uncast in both input and lines;
integer conversion, single-product totals and deposits follow successful validation. Request-supplied `products` are ignored and replaced with
the actual product or cart lines; partial forms without products skip that rule.
The package contains no project-specific minimum attribute or policy class.

Since 1.5.0 the storefront is checked before an order is saved:

- `processOrder()` re-prices the lines it kept since `initCheckout()` with current prices, publication,
  stock and currency. When anything differs it stores the current lines and fails with
  `error_code` `cart_changed` and the new `products` and `cost`; the customer confirms the order again.
  Lines above stock fail with `out_of_stock`, in quick orders too.
- The delivery cost is recalculated on every `setOrderData()` and again in `processOrder()`, and
  converted from the method's cost currency into the order currency. A failed attempt leaves the
  checkout data as it was, so a retry never adds the delivery twice.
- Unpublished products cannot be added to the cart or ordered. Quick orders without a `productId`
  order the cart; any other value than a whole number is an unknown product. Piece quantities stop at
  1,000,000, and a negative price from a listener never lowers a total.
- The `scommerce/process-order` and `scommerce/quick-order` routes return the order as
  `sOrder::toCustomerArray()` (no manager notes, history or `meta`) and allow `orders.rate_limit`
  orders per IP address in 10 minutes (default 10, 0 = no limit, **Settings → Cart and orders**);
  above it they answer `429` with `error_code` `rate_limited`.

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
- `environment`: `live`, or `test` for orders of a [trusted test checkout](#trusted-checkout-context);
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

`Seiger\sCommerce\Events\EventScope` tells listeners who made the change. sCommerce uses these `source` values: `storefront`, `storefront_quick`, `storefront_pay`, `payment_callback`, `manager`, `manager_bulk_status`, `manager_bulk_menu`, `manager_repeat`, `manager_payment_action`, `manager_container_return`, `manager_shipment`, `offline_settlement` and `system` (the default when no scope is open).

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

`sCommerce::INTEGRATION_EVENTS_VERSION` (currently `1`) changes only when payloads change incompatibly. `sCommerce::integrationFeatures()` lists the available feature keys, for example `line-identity`, `fulfilment-modes`, `line-tax-data` and `checkout-context`.

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

## Order lines, fulfilment and taxes

Since 1.5.0, every new order passes these hooks before it is first saved, in this order: `sCommerce.ResolveCheckoutContext`, `sCommerce.ResolveOrderTaxes`, `sCommerce.BeforeOrderCreate`, `sCommerce.ResolvePaymentSchedule`. `sCommerce.OrderCreated` follows after commit.

### sCommerce.ResolveLineIdentity

Dispatched by `OrderLineIdentity::resolve()` for every new order line: cart lines, the single-product quick order, the manager product search, and manager or repeat-order lines that have no identity yet. The payload contains `product`, `optionId` (cart option id, `0` = none) and `identity`, passed by reference.

Listeners may change `sku` (the effective SKU, for example a variant SKU) and `gtin` only. sCommerce restores `line_key`, `product_id`, `option_id` and `product_sku` afterwards, so they cannot be spoofed. See [Order line snapshot](integration.md#order-line-snapshot) for all keys.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolveLineIdentity', function (array $payload) {
    if ($payload['optionId'] === 3) {
        $payload['identity']['sku'] = $payload['product']->sku . '-RED';
    }
});
```

### sCommerce.ResolveProductFulfilment

Dispatched by `sProduct::fulfilment($optionId)`. The payload contains `product`, `optionId` and `fulfilment` (`['shipping' => bool, 'inventory' => bool]`). By default, products of the **Virtual** and **Service** types are neither shipped nor counted against inventory; all other products are. Enable these types in the product settings (**Available types**) to select them on a product.

The first array response replaces the default; a key it omits keeps the default value. Return `null` to keep the default.

```php
Event::listen('sCommerce.ResolveProductFulfilment', function (array $payload) {
    if ($payload['product']->sku === 'GIFT-CARD') {
        return ['shipping' => false];
    }
    return null;
});
```

The result is stored on each cart and order line as `requires_shipping` and `tracks_inventory`:

- the cart limits the quantity to the inventory only for tracked lines, so a service with inventory `0` can be ordered;
- with `basic.deliveries_on`, checkout requires a delivery method only when at least one line requires shipping. Otherwise the `delivery.method` rule is removed before `sCommerce.CheckoutValidationRules`, no delivery cost is added, and the order stores `delivery_info = {"method": "", "required": false, "cost": 0}`;
- `sCheckout::requiresDelivery()` reports the decision, and `initCheckout()` returns it as `requiresDelivery`, so a template can hide the delivery step.

Lines of orders created before 1.5.0 have no flags and count as shipped and tracked.

### sCommerce.ResolveOrderTaxes

Dispatched once for every new order (checkout and quick order) before it is first saved. sCommerce never computes taxes: a listener supplies them. The payload contains `order` (the unsaved `sOrder`), `lines` and `totals` (both passed by reference) and `source` (`storefront` or `storefront_quick`).

Listeners may set these optional keys on each line of `lines`:

| Key | Type |
|---|---|
| `tax_rate` | float, percent |
| `tax_code` | string, up to 32 characters |
| `unit_price_net` | float, unit price without tax |
| `unit_price_gross` | float, unit price with tax |
| `tax_amount` | float, tax of the whole line |
| `prices_include_tax` | bool |
| `retail_excise_amount` | float, retail excise included in the line total |

`totals` is stored in the `s_orders.totals` column. Supported keys: `subtotal_net`, `subtotal_gross`, `shipping_net`, `shipping_gross`, `shipping_tax_rate`, `discount_gross`, `tax_total`, `grand_total`, `retail_excise_total` (floats) and `prices_include_tax` (bool). sCommerce sets `retail_excise_amount` on lines with a retail excise before this event, so listeners see it and may replace it.

Values of the wrong type, other keys, added lines and changes to prices or quantities are ignored. The order `cost` is never changed; when `totals.grand_total` differs from it by more than `0.01`, a warning is logged to the `scommerce` channel. Without listeners, `totals` stays `NULL` and the lines are unchanged.

```php
Event::listen('sCommerce.ResolveOrderTaxes', function (array $payload) {
    foreach ($payload['lines'] as &$line) {
        $line['tax_rate'] = 23;
        $line['unit_price_gross'] = (float) $line['priceAsFloat'];
        $line['unit_price_net'] = round($line['unit_price_gross'] / 1.23, 2);
        $line['prices_include_tax'] = true;
    }
    unset($line);
    $payload['totals'] = ['grand_total' => (float) $payload['order']->cost, 'prices_include_tax' => true];
});
```

### sCommerce.BeforeOrderCreate

Dispatched right before a new checkout or quick order is first saved. The payload contains `order` (the unsaved `sOrder`), `data` (the checkout data, without client environment flags) and `source`. Listeners may set order attributes. Store project fields, such as a booking date or the address of a job, in the JSON column `s_orders.meta`; the manager order page shows `meta` read-only.

```php
Event::listen('sCommerce.BeforeOrderCreate', function (array $payload) {
    $meta = $payload['order']->meta ?? [];
    $meta['booking'] = ['date' => $payload['data']['booking_date'] ?? '', 'slot' => '09:00-11:00'];
    $payload['order']->meta = $meta;
});
```

`meta.integration` is reserved for the checkout context (see below); listeners cannot change it.

## Trusted checkout context

The website always stays live. A trusted user, for example a QA account, can place test orders on the live storefront while all other visitors keep buying live. sCommerce resolves the environment of every new order on the server; customer input never selects it.

### sCommerce.ResolveCheckoutContext

Dispatched by storefront checkout (`processOrder()`) and quick orders before validation of the payment method, before the order is saved and before any payment or email. The payload contains `data` (the checkout data), `source` and `context`, passed by reference. The default context is `['environment' => 'live', 'actorId' => <logged-in web or manager user id, 0 for guests>]`.

A listener that has checked the user's entitlement on the server may set:

- `environment`: `live` or `test`;
- `reference`: an opaque string or array of up to 2 KB, for example the sandbox connection to use;
- `testRecipients`: e-mail addresses that receive the order e-mails of a test order;
- `refusal`: a message shown to the customer. The checkout stops before the order, the payment and any e-mail, for example when a test payment cannot be isolated for the selected collector.

sCommerce then validates the context: any other environment is refused rather than treated as live, guests cannot get `test`, and `actorId` and `source` always come from the request. Keys that a client could use to claim an environment (`environment`, `test`, `is_test`, `test_mode`, `sandbox`, `livemode`, `checkout_context`, `meta`, `integration` and their camelCase forms) are removed from the data before listeners and payment methods see it.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolveCheckoutContext', function (array $payload) {
    $userId = $payload['context']['actorId'];
    if ($userId > 0 && in_array($userId, config('shop.test_users', []), true)) {
        $payload['context']['environment'] = 'test';
        $payload['context']['reference'] = ['connection' => 'stripe-sandbox'];
        $payload['context']['testRecipients'] = ['qa@example.com'];
    }
});
```

The resolved context is stored once in `s_orders.meta.integration` and never changes afterwards, whatever later writes, logouts or role changes happen. Read it with `CheckoutContext::of($order)`; orders without a stored context are live.

- Payment methods receive it as `checkout_context` in `processPayment()`, from checkout and from pay links that send `order`, whoever opens the link. Choose sandbox or live credentials from it, never from the current user.
- A test order sends its order e-mails only to `testRecipients` (none when empty), never to the shop or the customer.
- Test orders are never settled by offline settlement, and they are left out of the dashboard metrics and the order list. The order list shows a **Test orders** link to view them, and a test order shows a **TEST** badge.
- `sOrder::live()` and `sOrder::test()` scope queries, and `$order->isTest()` checks one order. Order events carry `environment`.
- A ledger entry whose `metadata.environment` differs from the order's environment is rejected (see [Recording payments](payments/index.md#metadata)).

Test contexts need the `s_orders.meta` column; run the sCommerce migrations. Without it, test checkouts are refused and live checkouts continue. `sCommerce::supportsIntegration('checkout-context')` reports support.

## Shipment events

### sCommerce.ShipmentUpdated

Since 1.5.0, a shipment that is created, or whose status, tracking number or URL, carrier, service, items or dates change, dispatches `sCommerce.ShipmentUpdated`. It follows the delivery rules of the order events: after commit only, with the event scope context, and never merged. A repeated write with the same data dispatches nothing.

The payload contains:

- `event` and `shipment` (a detached `sOrderShipment` copy for reading only);
- `shipmentId`, `shipmentUuid`, `sequence`, `orderId`, `orderUuid`, `environment` (`live` or `test`, from the order);
- `created` (bool), `oldStatus` (`null` when created) and `newStatus`;
- `carrier`, `service`, `trackingNumber`, `trackingUrl`, `items`, `shippedAt`, `deliveredAt` (UTC, for example `2026-10-21T08:00:00Z`), `metadata`;
- `fullyShipped` and `fullyDelivered`: whether all shipped products of the order are covered by sent or delivered parcels at this moment;
- `shipmentSource` and `externalRef`: the system that wrote the shipment and its id there;
- the context keys `source`, `actorId`, `correlationId`, `causationId`, `integrationActor`, `eventId` and `occurredAt`. `source` is the event scope, as for every other event; it is not the shipment's `shipmentSource`.

The shipment event is dispatched before the order events it causes, for example `sCommerce.OrderStatusChanged` to *Shipped*. See [Shipments and tracking](deliveries.md#shipments-and-tracking-api--events) for the API. `sCommerce::supportsIntegration('shipments')` reports support.

## Stock events

### sCommerce.ResolveProductStock

Since 1.5.0, the cart asks `Seiger\sCommerce\Services\StockResolver::available()` how many units of a product it may add. The first integer returned by a `sCommerce.ResolveProductStock` listener wins; `null` lets the next listener answer. The payload contains `product`, `modificationId` (always `0`: a cart option id is not a modification), `quantity` (the local `s_products.inventory`) and `context` (`optionId` and the requested `quantity`). A bound stock provider is asked before the listeners, and the local inventory is used when nobody answers.

```php
Event::listen('sCommerce.ResolveProductStock', function (array $payload) {
    return $payload['product']->sku === 'GIFT-CARD' ? 1000 : null;
});
```

### Stock provider

A package can make an external system, for example an ERP, the stock source by binding `Seiger\sCommerce\Interfaces\StockProviderInterface` in its service provider: `app()->bind(StockProviderInterface::class, ErpStock::class)`. At most one provider is used.

- `available(sProduct $product, int $modificationId = 0, array $context = []): ?int` returns the quantity, or `null` to use the listeners and then the local inventory.
- `commit(sOrder $order): bool` and `release(sOrder $order): bool` are called when stock reduction on orders is enabled (see [Configuration](../configuration.md#stock)). Return `true` when the provider handled the order; sCommerce then changes no local stock. An order is released by whoever committed it: an order that sCommerce decremented locally is restored locally without asking the provider.
- Both methods may run again after a failure or a crash, so they must be idempotent per order, for example keyed by the order UUID.

### Writing stock

`Seiger\sCommerce\Services\StockWriter::set($productOrId, $quantity, 0, 'erp', $availability)` sets the inventory and, when given, the availability (`0` not available, `1` in stock, `2` on order) of one product. A GROUP variant is its own product row: pass the variant product. A non-zero `modificationId` is rejected, because `s_product_modifications` rows have no identity of their own. A write that changes nothing records no event. Nothing in core caches stock values, so no cache is cleared; listeners of `sCommerce.ProductStockChanged` can clear their own caches.

### sCommerce.ProductStockChanged

Since 1.5.0, a change of `s_products.inventory` or `s_products.availability` dispatches `sCommerce.ProductStockChanged`, whoever wrote it: the product form, the CSV import, `StockWriter` or a stock reduction of an order. It follows the delivery rules of the order events: after commit only, with the event scope context. Changes of one product in one event scope are merged from the first old to the last new values, and a merge that changes nothing is dropped.

The payload contains `event`, `productId`, `modificationId` (always `0`), `sku`, `oldQuantity`, `newQuantity`, `oldAvailability`, `newAvailability`, `stockSource` (the writer: `manager`, `import`, `order`, the source passed to `StockWriter`, or the event scope source for other model saves) and the context keys `source`, `actorId`, `correlationId`, `causationId`, `integrationActor`, `eventId` and `occurredAt`. Stock reductions and returns of orders run in the event scope source `inventory`.

For products sold by weight, length, volume or count, all stock quantities (inventory, events, commits and the provider's `available()`) are base units: grams, millimetres, millilitres or items. See [Measured quantities](measured-quantities.md#stock-and-shipments).

### Stock follows order edits

Since 1.5.0, when a manager changes the products of an order whose stock sCommerce reduced locally, the stock follows in the same request: `StockCommitter::adjust($order)` takes or returns the difference for changed, added and removed lines, for piece and measured products alike. Actual quantities of measured lines adjust stock the same way. When a product has too little stock for an increase, the edit is not saved. Orders committed by a stock provider are not adjusted locally; the provider sees `sCommerce.OrderUpdated`. A later return restores exactly the adjusted quantities. Before 1.5.0 manager edits did not change reduced stock.

`sCommerce::supportsIntegration('stock-provider')` reports support.

## Split cart lines

### sCommerce.SplitCartLine

Since 1.5.0, a listener can sell one cart entry (a product with an option and a quantity) as several order lines with different prices. sCommerce itself has no stock lots; this event lets a lot package or an ERP adapter apply its own rules. Typical cases:

- Excise goods with a price fixed per lot: the units of the older lot are sold at its price before the next lot opens.
- Perishables: units of a lot close to its expiry date and time are sold first at a markdown price, the rest of the same entry at the regular price.
- Goods that must come from one batch, such as tiles of one shade: the listener names the batch on the line.

The cart, the checkout and the single-product quick order dispatch the event for every entry after its price is resolved. The payload contains `product`, `optionId`, `quantity` (base units for products sold by measure), `currency` and `pricing` (the resolved price of the entry, see [sCommerce.ResolveProductPrice](#scommerceresolveproductprice)). The first listener that returns an array decides; return `null` to leave the entry as one line.

The response is a list of parts whose quantities add up to the entry quantity:

| Key | Required | Meaning |
|---|---|---|
| `key` | yes | Unique within the entry: 1 to 40 letters, digits, `.`, `_` or `-`, for example `lot-11` |
| `quantity` | yes | Positive integer |
| `priceAsFloat` | no | Unit price in `currency`; without it the part has the price of the entry |
| `oldPriceAsFloat` | no | Crossed-out price of a markdown; only together with `priceAsFloat`, default none |
| `label` | no | Text up to 255 characters for the customer and the manager, for example `Best before 27.10 18:00` |
| `allocation` | no | JSON-encodable array stored on the order line as it is, for example the lot ids and quantities |

Any other response, such as quantities with a different sum, a duplicate key or a negative price, throws `UnexpectedValueException`, so a listener error is never sold at a wrong price.

```php
use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Cart\CartLineParts;

Event::listen(CartLineParts::EVENT, function (array $payload) {
    $lots = MyLots::sellable($payload['product']->id); // oldest first, each with id, quantity, price, markdown
    if ($lots === []) {
        return null;
    }
    $parts = [];
    $left = $payload['quantity'];
    foreach ($lots as $lot) {
        $units = min($left, $lot->quantity);
        if ($units > 0) {
            $parts[] = array_filter([
                'key' => 'lot-' . $lot->id,
                'quantity' => $units,
                'priceAsFloat' => $lot->markdown ?? $lot->price,
                'oldPriceAsFloat' => $lot->markdown !== null ? $payload['pricing']['priceAsFloat'] : null,
                'label' => $lot->markdown !== null ? 'Best before ' . $lot->expiresAt->format('d.m H:i') : null,
                'allocation' => ['lots' => [['id' => $lot->id, 'quantity' => $units]]],
            ], static fn ($value) => $value !== null);
            $left -= $units;
        }
    }

    return $parts;
});
```

Each part becomes one cart and order line:

- Its `line_key` is `{productId}:{optionId}:{key}`, for example `5:0:lot-11`.
- Its price, sum, VAT and deposit keys are calculated for the part. Declared container exchanges fill the parts in order.
- It carries `part_key`, `part_label` (when given) and `allocation` (when given). Order emails, the manager order screen and the print form show `part_label` under the product title.
- In the cart only, `cart_quantity` is the quantity of the whole entry. A quantity control of a split line must send this entry quantity, because `sCart::addProduct()` sets the quantity of the entry, not of one part. `cart_quantity` is not stored on orders.

The checkout prices its lines again before saving the order. When the split changed meanwhile, for example a lot sold out, the line keys or prices differ, and the customer gets `cart_changed` with the new lines and total.

The listener also has to make the rest of the lot rules consistent:

- **Stock.** Make `sCommerce.ResolveProductStock` or a stock provider return what can be sold.
- **Catalog price.** Make `sCommerce.ResolveProductPrice` return the price of the first unit, for product cards.
- **Committing stock.** Lot stock is committed by a `StockProviderInterface`, which reads `allocation` from the order lines. sCommerce does not interpret allocations. Its local stock reduction counts the parts of a product together, as before.

Part keys stay on the server: when a manager saves an order, lines keep the `part_key`, `part_label` and `allocation` stored under their `line_key`, and lines added in the manager have none. A manager who changes the quantity of a split line changes the line, not its allocation; the stock provider sees `sCommerce.OrderUpdated`. Repeated orders keep the stored prices and labels but drop `allocation`.

`sCommerce::supportsIntegration('line-parts')` reports support.

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
