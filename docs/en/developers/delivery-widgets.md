# Delivery Widgets

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

2. **Custom package views**
   ```
   core/custom/packages/seiger/scommerce/views/delivery/{delivery-name}.blade.php
   ```

3. **Vendor default** (lowest priority)
   ```
   core/vendor/seiger/scommerce/views/delivery/{delivery-name}.blade.php
   ```

This allows you to override any delivery template by simply copying it to a higher-priority location.

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

### Nova Poshta Integration Example

Example location: `core/custom/packages/seiger/scommerce/views/delivery/nova-poshta.blade.php`

Features:
- City autocomplete via Nova Poshta API
- Warehouse selection based on city
- Recipient information fields
- JavaScript integration example

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

## See Also

- [Payment Methods](payments/methods.md)
- [Events](events.md)
- [Settings](settings.md)

