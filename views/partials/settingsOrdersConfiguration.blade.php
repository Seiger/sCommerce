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
<div class="scommerce-checkout-settings-field scommerce-checkout-settings-field--wide">
    <label for="orders__marketing_fields">
        @lang('sCommerce::global.orders_marketing_fields')
    </label>
    <input type="text" class="form-control" id="orders__marketing_fields" name="orders__marketing_fields" value="{{sCommerce::config('orders.marketing_fields', '')}}" placeholder="Google Click ID==gclid||UTM Source==utm_source" onchange="documentDirty=true;">
    <small class="scommerce-checkout-settings-help">@lang('sCommerce::global.orders_marketing_fields_help')</small>
</div>
<div class="scommerce-checkout-settings-field">
    <label for="shipping__sync_order_status">
        @lang('sCommerce::global.shipping_sync_order_status')
    </label>
    <select class="form-control" id="shipping__sync_order_status" name="shipping__sync_order_status" onchange="documentDirty=true;">
        <option value="0" @selected((int)sCommerce::config('shipping.sync_order_status', 0) !== 1)>@lang('global.no')</option>
        <option value="1" @selected((int)sCommerce::config('shipping.sync_order_status', 0) === 1)>@lang('global.yes')</option>
    </select>
    <small class="scommerce-checkout-settings-help">@lang('sCommerce::global.shipping_sync_order_status_help')</small>
</div>
<div class="scommerce-checkout-settings-field">
    <label for="shipping__partial_policy">
        @lang('sCommerce::global.shipping_partial_policy')
    </label>
    <select class="form-control" id="shipping__partial_policy" name="shipping__partial_policy" onchange="documentDirty=true;">
        @foreach(['on_complete', 'on_first'] as $policy)
            <option value="{{$policy}}" @selected(sCommerce::config('shipping.partial_policy', 'on_complete') === $policy)>@lang('sCommerce::global.shipping_partial_' . $policy)</option>
        @endforeach
    </select>
    <small class="scommerce-checkout-settings-help">@lang('sCommerce::global.shipping_partial_policy_help')</small>
</div>
