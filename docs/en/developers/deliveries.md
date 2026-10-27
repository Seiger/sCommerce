# Delivery Methods

Delivery widgets provide a flexible way to customize the checkout form fields for different delivery methods in sCommerce. Each delivery method can have its own Blade template that renders the necessary form fields.

## Overview

Starting from version 1.x, sCommerce supports customizable delivery widgets through Blade templates. This allows you to:

- Customize checkout forms for each delivery method
- Create project-specific delivery implementations
- Integrate third-party delivery service widgets (Nova Poshta, UPS, etc.)
- Maintain consistent styling across delivery methods

## Template Resolution

Templates are resolved in the following priority order:

1. **Project views** (highest priority)
   ```
   views/delivery/{delivery-name}.blade.php
   ```

2. **Vendor default** (lowest priority)
   ```
   core/vendor/seiger/scommerce/views/delivery/{delivery-name}.blade.php
   ```

This allows you to override any delivery template by simply copying it to your project's views directory.

## Available Variables

All delivery widget templates receive the following variables:

### `$delivery`
Array containing delivery method information:
- `$delivery['name']` - Unique delivery identifier (e.g., 'courier', 'pickup')
- `$delivery['title']` - Localized delivery method title
- `$delivery['description']` - Localized delivery method description

### `$checkout`
Array with current checkout data:
- `$checkout['user']` - User information
- `$checkout['user']['address']` - Address data (city, street, building, room, etc.)
- `$checkout['cart']` - Shopping cart data
- Other checkout-related information

### `$settings`
Array with delivery method settings configured in admin panel:
- Custom settings vary by delivery method
- Example for courier: `$settings['cities']`, `$settings['info']`
- Example for pickup: `$settings['locations']`

## Creating Custom Delivery Widget

### Step 1: Create Delivery Class

Create a new delivery method class that extends `BaseDeliveryMethod`:

```php
<?php namespace App\Delivery;

use Seiger\sCommerce\Delivery\BaseDeliveryMethod;

class CustomDelivery extends BaseDeliveryMethod
{
    public function getName(): string
    {
        return 'custom';
    }

    public function getType(): string
    {
        return "<b>Custom Delivery</b> (custom)";
    }

    public function getValidationRules(): array
    {
        return [
            'delivery.custom.address' => 'required|string|max:255',
            'delivery.custom.phone' => 'required|string',
        ];
    }

    public function calculateCost(array $order): float
    {
        return 50.00; // Fixed cost or custom calculation
    }

    public function defineFields(): array
    {
        return [
            // Admin panel settings fields
        ];
    }

    public function prepareSettings(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
```

### Step 2: Create Widget Template

Create a Blade template for your delivery widget:

**File:** `views/delivery/custom.blade.php`

```blade
{{--
    Custom Delivery Widget
    
    Available variables: $delivery, $checkout, $settings
--}}

<label class="form-label">
    <input 
        type="text" 
        name="delivery[{{$delivery['name']}}][address]" 
        value="{{old('delivery.'.$delivery['name'].'.address', '')}}"
        placeholder="Enter delivery address"
        required
    />
    <span>Delivery Address</span>
</label>

<label class="form-label">
    <input 
        type="tel" 
        name="delivery[{{$delivery['name']}}][phone]" 
        placeholder="Phone number"
        required
    />
    <span>Contact Phone</span>
</label>
```

### Step 3: Register Delivery Method

The delivery method will be automatically registered when added to the database with the correct class name.

## Examples

### Courier Delivery

Default template location: `core/vendor/seiger/scommerce/views/delivery/courier.blade.php`

Features:
- City input with quick city selection
- Street, building, apartment fields
- Receiver selection (self or other person)

To customize, copy to: `views/delivery/courier.blade.php`

### Pickup Delivery

Default template location: `core/vendor/seiger/scommerce/views/delivery/pickup.blade.php`

Features:
- Radio buttons for pickup location selection
- Displays all configured pickup addresses

To customize, copy to: `views/delivery/pickup.blade.php`

## Field Naming Convention

Always use the following naming pattern for input fields:

```html
name="delivery[{{$delivery['name']}}][field_name]"
```

This ensures proper data structure for:
- Validation processing
- Order data storage
- Multi-delivery support

## Using Widgets in Checkout

In your checkout view, simply output the widget:

```blade
@foreach($deliveries as $delivery)
    <div data-delivery="{{$delivery['name']}}">
        
        {{-- Info message from settings --}}
        @if(isset($delivery['info']) && trim($delivery['info']))
            <div class="message-info">
                {!! nl2br(e($delivery['info'])) !!}
            </div>
        @endif

        {{-- Delivery widget --}}
        {!! $delivery['widget'] !!}
        
    </div>
@endforeach
```

The `$delivery['widget']` contains the pre-rendered HTML from the delivery method's template.

## Advanced Usage

### Adding JavaScript

Use `@push('scripts')` directive to add JavaScript for your widget:

```blade
<div id="delivery-{{$delivery['name']}}">
    {{-- Your form fields --}}
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Your JavaScript code
    console.log('Delivery widget initialized');
});
</script>
@endpush
```

### Conditional Fields with Alpine.js

```blade
<div x-data="{ showAdvanced: false }">
    <label>
        <input type="checkbox" x-model="showAdvanced">
        Show advanced options
    </label>
    
    <div x-show="showAdvanced" x-transition>
        {{-- Additional fields --}}
    </div>
</div>
```

### API Integration

For third-party delivery services:

```blade
<div id="delivery-api-widget-{{$delivery['name']}}"></div>

@push('scripts')
<script src="https://api.delivery-service.com/widget.js"></script>
<script>
DeliveryServiceWidget.init({
    container: '#delivery-api-widget-{{$delivery['name']}}',
    apiKey: '{{$settings['api_key'] ?? ''}}',
    onSelect: function(data) {
        // Handle delivery selection
    }
});
</script>
@endpush
```

## Best Practices

1. **Always validate input** - Define validation rules in `getValidationRules()`
2. **Use translation keys** - Make widgets multilingual with `__('key')`
3. **Preserve user data** - Use `old()` helper for form repopulation
4. **Handle errors gracefully** - Check if settings exist before using them
5. **Keep widgets focused** - One widget = one delivery method
6. **Document your code** - Add comments explaining complex logic
7. **Test across browsers** - Ensure compatibility with target browsers

## Troubleshooting

### Widget not displaying

1. Check if template file exists in one of the search paths
2. Verify delivery method class implements `DeliveryMethodInterface`
3. Check Laravel logs for rendering errors: `storage/logs/scommerce.log`

### Validation errors

1. Ensure field names match validation rules
2. Check `getValidationRules()` method in delivery class
3. Verify all required fields are present in the widget

### Styling issues

1. Check if your project's CSS classes match the widget's HTML
2. Consider creating a custom template in `views/delivery/`
3. Use browser developer tools to inspect applied styles

## Migration from Hardcoded Forms

If you have existing hardcoded delivery forms in `checkout.blade.php`:

1. Create widget template: `views/delivery/{name}.blade.php`
2. Copy form HTML from checkout to the widget template
3. Replace hardcoded values with template variables
4. Update input names to use `delivery[{{$delivery['name']}}][field]` format
5. Test checkout process thoroughly
6. Remove old hardcoded form from checkout.blade.php

## Shipments and tracking (API + events)

Since 1.5.0, an order can have shipments: parcels or deliveries with a carrier, a tracking number and the shipped quantities. Managers add them on the order screen (**Shipments** block, **Add tracking**). ERPs and carrier integrations write them through `Seiger\sCommerce\Services\OrderShipments` and never write `s_orders` directly. Run the sCommerce migrations to create the `s_order_shipments` table.

### Writing shipments

```php
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Services\OrderShipments;
use Seiger\sCommerce\Shipping\ShipmentData;

$shipments = new OrderShipments();

EventScope::run('erp', 0, fn () => $shipments->upsert($order, new ShipmentData(
    status: 'shipped',
    carrier: 'packeta',
    service: 'pickup_point',
    trackingNumber: 'Z123456789',
    trackingUrl: 'https://tracking.packeta.com/Z123456789',
    items: [['line' => 0, 'quantity' => 2], ['line_key' => '7:0', 'quantity' => 1]],
    source: 'erp',
    externalRef: 'SH-2026-0042',
)), integrationActor: 'erp-sync');

// Later updates from the same system:
$shipment = $shipments->findByExternalRef('erp', 'SH-2026-0042');
$shipments->setStatus($shipment, 'delivered', new DateTimeImmutable('2026-10-21 10:15', new DateTimeZone('UTC')));
```

| Method | Purpose |
|---|---|
| `upsert(sOrder $order, ShipmentData $data)` | Creates a shipment, or updates the one with the same `(source, externalRef)`. Idempotent: repeating the same data changes nothing. Without `externalRef` every call creates a shipment |
| `update(sOrderShipment $shipment, ShipmentData $data)` | Applies data to a known shipment with the same rules |
| `setStatus(sOrderShipment $shipment, string $status, ?DateTimeImmutable $at = null, array $metadata = [])` | Moves a shipment to another status |
| `findByExternalRef(string $source, string $externalRef)` | Finds the shipment written by a source system |
| `OrderShipments::coverage(sOrder $order)` | `fullyShipped`, `fullyDelivered` and `anySent` of the order |
| `project(int $orderId)` | Rebuilds `delivery_info.tracking` and the order status from the stored shipments, for example in a reconciliation job |

`ShipmentData` fields: `status`, `carrier`, `service`, `trackingNumber`, `trackingUrl`, `items`, `source`, `externalRef`, `metadata`, `shippedAt`, `deliveredAt`. When a shipment already exists, `null` keeps the stored carrier, service, tracking, items and dates, and `metadata` is merged.

Rules:

- **Statuses** move forward along `pending → ready → shipped → in_transit → delivered`. `failed`, `returned` and `canceled` end any unfinished shipment. A move back or a change of a finished shipment is ignored and logged as a warning to the `scommerce` channel.
- Reaching `shipped`, `in_transit` or `delivered` without a date sets `shipped_at` to now; `delivered` also sets `delivered_at`.
- **Items** refer to order lines by `line` (the index in `s_orders.products`) and/or `line_key`. `product_id` and `sku` are optional and checked against the line. Quantities are positive integers; the same line listed twice is summed. Lines with `requires_shipping = false` (services) cannot be shipped. `items = null` ships the whole order and cannot be combined with other active shipments of the order.
- The shipped quantities of active shipments (all except `failed`, `returned` and `canceled`) cannot exceed the ordered quantities.
- Lines of products sold by measure are shipped in base units (grams, millimetres, millilitres). When an actual quantity is set, it replaces the ordered quantity for coverage. Use the line's `line_weight` (grams for the whole line) instead of `weight × quantity`; see [Measured quantities](measured-quantities.md#order-lines).
- Canceling a sent parcel is a correction: its quantities are released, `metadata.voided_after` records the previous status, a warning is logged, and the order status is not moved back.
- Errors: `InvalidArgumentException` for invalid data, `Seiger\sCommerce\Shipping\ShipmentConflictException` when the reference belongs to another order or the quantities exceed or overlap the order.
- Writes lock the order row. A concurrent insert of the same reference is detected and applied to the existing shipment.

Open an [event scope](events.md#event-scope) with your own `source` and `integrationActor` so that your listeners can ignore the echo of your own writes.

### Tracking in templates

After every change, the order's `delivery_info['tracking']` holds a list of shipments with a tracking number or URL (canceled shipments are left out):

```php
[
    ['carrier' => 'packeta', 'number' => 'Z123456789', 'url' => 'https://tracking.packeta.com/Z123456789', 'status' => 'shipped', 'updated_at' => '2026-10-20 14:03:11'],
]
```

Order templates and notification e-mails can show it without new code. Other `delivery_info` keys are kept.

```blade
@foreach($order->delivery_info['tracking'] ?? [] as $parcel)
    <a href="{{$parcel['url']}}">{{$parcel['number']}}</a> ({{$parcel['status']}})
@endforeach
```

### Order status from shipments

Off by default. Turn on **Order status from shipments** in *Settings → Orders* (`shipping.sync_order_status = 1`) when sCommerce should set the order status:

- **Delivered** when all shipped products are covered by delivered parcels; otherwise **Shipped** when they are covered by sent parcels (`shipped`, `in_transit` or `delivered`).
- Only lines that require shipping count. An order of services only is never shipped or delivered by shipments.
- **Partial shipments** (`shipping.partial_policy`): `on_complete` (default) waits for all parcels; `on_first` sets **Shipped** with the first sent parcel. **Delivered** always needs all quantities, so one delivered parcel never settles a cash-on-delivery order early.
- The status never moves back and is never changed from **Completed** or a later status (canceled, on hold, returns, failed). The change is recorded in the order history by the system user and dispatches the usual order events, so offline settlement on **Delivered** works as for a manual status change.

Leave it off when an ERP or another integration sets the order status; exactly one component should set it.

### Events

Every change dispatches `sCommerce.ShipmentUpdated` after commit. See [Events](events.md#shipment-events).

## See Also

- [Payment Methods](payments/methods.md)
- [Events](events.md)
- [Settings](settings.md)

