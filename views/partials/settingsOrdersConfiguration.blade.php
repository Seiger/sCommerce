<h3>@lang('sCommerce::global.orders_settings')</h3>
<div class="row form-row">
    <div class="col-auto">
        <label for="orders__reference_prefix_default">@lang('sCommerce::global.orders_reference_prefix')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.orders_reference_prefix_help')"></i>
    </div>
    <div class="col">
        <input type="text" class="form-control" id="orders__reference_prefix_default" name="orders__reference[prefix_default]" value="{{sCommerce::config('orders.reference.prefix_default', '#')}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="orders__reference_start_default">@lang('sCommerce::global.orders_reference_start')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.orders_reference_start_help')"></i>
    </div>
    <div class="col col-4 col-md-3 col-lg-2">
        <input type="number" class="form-control" id="orders__reference_start_default" name="orders__reference[start_default]" value="{{sCommerce::config('orders.reference.start_default', 1)}}" min="0" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="orders__reference_pad_left">@lang('sCommerce::global.orders_reference_pad_left')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.orders_reference_pad_left_help')"></i>
    </div>
    <div class="col col-4 col-md-3 col-lg-2">
        <input type="number" class="form-control" id="orders__reference_pad_left" name="orders__reference[pad_left]" value="{{sCommerce::config('orders.reference.pad_left', 0)}}" min="0" onchange="documentDirty=true;">
    </div>
</div>
<div class="split my-3"></div>