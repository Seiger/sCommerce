<div class="scommerce-checkout-settings-field scommerce-checkout-settings-field--wide">
    <label for="orders__reference_prefix_default">
        @lang('sCommerce::global.orders_reference_prefix')
    </label>
    <input type="text" class="form-control" id="orders__reference_prefix_default" name="orders__reference[prefix_default]" value="{{sCommerce::config('orders.reference.prefix_default', '#')}}" onchange="documentDirty=true;">
    <small class="scommerce-checkout-settings-help">@lang('sCommerce::global.orders_reference_prefix_help')</small>
</div>
<div class="scommerce-checkout-settings-field">
    <label for="orders__reference_start_default">
        @lang('sCommerce::global.orders_reference_start')
    </label>
    <input type="number" class="form-control" id="orders__reference_start_default" name="orders__reference[start_default]" value="{{sCommerce::config('orders.reference.start_default', 1)}}" min="0" onchange="documentDirty=true;">
    <small class="scommerce-checkout-settings-help">@lang('sCommerce::global.orders_reference_start_help')</small>
</div>
<div class="scommerce-checkout-settings-field">
    <label for="orders__reference_pad_left">
        @lang('sCommerce::global.orders_reference_pad_left')
    </label>
    <input type="number" class="form-control" id="orders__reference_pad_left" name="orders__reference[pad_left]" value="{{sCommerce::config('orders.reference.pad_left', 0)}}" min="0" onchange="documentDirty=true;">
    <small class="scommerce-checkout-settings-help">@lang('sCommerce::global.orders_reference_pad_left_help')</small>
</div>
