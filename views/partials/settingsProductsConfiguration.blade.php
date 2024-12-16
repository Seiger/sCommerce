<h3>@lang('sCommerce::global.representation_products_fields')</h3>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_id" class="warning">ID</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') ID"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_id);" @if(sCommerce::config('products.show_field_id', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_id" name="products__show_field_id" value="{{sCommerce::config('products.show_field_id', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_sku" class="warning">@lang('sCommerce::global.sku')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.sku')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_sku);" @if(sCommerce::config('products.show_field_sku', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_sku" name="products__show_field_sku" value="{{sCommerce::config('products.show_field_sku', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_price" class="warning">@lang('sCommerce::global.price')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.price')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_price);" @if(sCommerce::config('products.show_field_price', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_price" name="products__show_field_price" value="{{sCommerce::config('products.show_field_price', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_price_special" class="warning">@lang('sCommerce::global.price_special')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.price_special')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_price_special);" @if(sCommerce::config('products.show_field_price_special', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_price_special" name="products__show_field_price_special" value="{{sCommerce::config('products.show_field_price_special', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_price_opt" class="warning">@lang('sCommerce::global.price_opt')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.price_opt')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_price_opt);" @if(sCommerce::config('products.show_field_price_opt', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_price_opt" name="products__show_field_price_opt" value="{{sCommerce::config('products.show_field_price_opt', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_price_opt_special" class="warning">@lang('sCommerce::global.price_opt_special')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.price_opt_special')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_price_opt_special);" @if(sCommerce::config('products.show_field_price_opt_special', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_price_opt_special" name="products__show_field_price_opt_special" value="{{sCommerce::config('products.show_field_price_opt_special', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_quantity" class="warning">@lang('sCommerce::global.quantity')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.quantity')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_quantity);" @if(sCommerce::config('products.show_field_quantity', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_quantity" name="products__show_field_quantity" value="{{sCommerce::config('products.show_field_quantity', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_availability" class="warning">@lang('sCommerce::global.availability')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.availability')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_availability);" @if(sCommerce::config('products.show_field_availability', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_availability" name="products__show_field_availability" value="{{sCommerce::config('products.show_field_availability', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_category" class="warning">@lang('sCommerce::global.category')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.category')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_category);" @if(sCommerce::config('products.show_field_category', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_category" name="products__show_field_category" value="{{sCommerce::config('products.show_field_category', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
@if(evo()->getConfig('check_sMultisite', false))
    <div class="row form-row">
        <div class="col-auto">
            <label for="products__show_field_websites" class="warning">@lang('sCommerce::global.websites')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.websites')"></i>
        </div>
        <div class="col">
            <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_websites);" @if(sCommerce::config('products.show_field_websites', 1) == 1) checked @endif>
            <input type="hidden" id="products__show_field_websites" name="products__show_field_websites" value="{{sCommerce::config('products.show_field_websites', 1)}}" onchange="documentDirty=true;">
        </div>
    </div>
@endif
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_weight" class="warning">@lang('sCommerce::global.weight')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.weight')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_weight);" @if(sCommerce::config('products.show_field_weight', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_weight" name="products__show_field_weight" value="{{sCommerce::config('products.show_field_weight', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_width" class="warning">@lang('sCommerce::global.width')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.width')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_width);" @if(sCommerce::config('products.show_field_width', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_width" name="products__show_field_width" value="{{sCommerce::config('products.show_field_width', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_height" class="warning">@lang('sCommerce::global.height')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.height')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_height);" @if(sCommerce::config('products.show_field_height', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_height" name="products__show_field_height" value="{{sCommerce::config('products.show_field_height', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_length" class="warning">@lang('sCommerce::global.length')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.length')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_length);" @if(sCommerce::config('products.show_field_length', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_length" name="products__show_field_length" value="{{sCommerce::config('products.show_field_length', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_volume" class="warning">@lang('sCommerce::global.volume')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.volume')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_volume);" @if(sCommerce::config('products.show_field_volume', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_volume" name="products__show_field_volume" value="{{sCommerce::config('products.show_field_volume', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_visibility" class="warning">@lang('sCommerce::global.visibility')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.visibility')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_visibility);" @if(sCommerce::config('products.show_field_visibility', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_visibility" name="products__show_field_visibility" value="{{sCommerce::config('products.show_field_visibility', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_views" class="warning">@lang('sCommerce::global.views')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.views')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_views);" @if(sCommerce::config('products.show_field_views', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_views" name="products__show_field_views" value="{{sCommerce::config('products.show_field_views', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="products__show_field_rating" class="warning">@lang('sCommerce::global.rating')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.rating')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.products__show_field_rating);" @if(sCommerce::config('products.show_field_rating', 1) == 1) checked @endif>
        <input type="hidden" id="products__show_field_rating" name="products__show_field_rating" value="{{sCommerce::config('products.show_field_rating', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="basic__available_currencies">@lang('sCommerce::global.additional_fields_to_products_tab')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.additional_fields_to_products_tab_help')"></i>
    </div>
    <div class="col">
        <select id="products__additional_fields" class="form-control select2" name="products__additional_fields[]" multiple onchange="documentDirty=true;">
            @foreach(sCommerce::config('constructor.main_product', []) as $item)
                <option value="{{$item['key']}}" @if(in_array($item['key'], sCommerce::config('products.additional_fields', []))) selected @endif>{{$item['pagetitle']}}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="split my-3"></div>