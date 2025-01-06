@php use Seiger\sCommerce\Models\sProduct; @endphp
<h3>@lang('sCommerce::global.management_product_functionality')</h3>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__link_rule" class="warning">@lang('sCommerce::global.product_link')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.product_link_rule_help')"></i>
    </div>
    <div class="col col-4 col-md-3 col-lg-2">
        <select id="product__link_rule" class="form-control" name="product__link_rule" onchange="documentDirty=true;">
            <option value="root" @if(sCommerce::config('product.link_rule', 'root') == 'root') selected @endif>@lang('sCommerce::global.product_link_rule_root')</option>
            <option value="catalog" @if(sCommerce::config('product.link_rule', 'root') == 'catalog') selected @endif>@lang('sCommerce::global.product_link_rule_catalog')</option>
            <option value="category" @if(sCommerce::config('product.link_rule', 'root') == 'category') selected @endif>@lang('sCommerce::global.product_link_rule_category')</option>
        </select>
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__views_on" class="warning">@lang('sCommerce::global.views')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.views_on_help')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__views_on);" @if(sCommerce::config('product.views_on', 1) == 1) checked @endif>
        <input type="hidden" id="product__views_on" name="product__views_on" value="{{sCommerce::config('product.views_on', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__rating_on" class="warning">@lang('sCommerce::global.rating')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.rating_on_help')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__rating_on);" @if(sCommerce::config('product.rating_on', 1) == 1) checked @endif>
        <input type="hidden" id="product__rating_on" name="product__rating_on" value="{{sCommerce::config('product.rating_on', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__show_field_availability" class="warning">@lang('sCommerce::global.availability')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.availability')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__show_field_availability);" @if(sCommerce::config('product.show_field_availability', 1) == 1) checked @endif>
        <input type="hidden" id="product__show_field_availability" name="product__show_field_availability" value="{{sCommerce::config('product.show_field_availability', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__show_field_price" class="warning">@lang('sCommerce::global.price')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.price')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__show_field_price);" @if(sCommerce::config('product.show_field_price', 1) == 1) checked @endif>
        <input type="hidden" id="product__show_field_price" name="product__show_field_price" value="{{sCommerce::config('product.show_field_price', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__show_field_price_special" class="warning">@lang('sCommerce::global.price_special')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.price_special')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__show_field_price_special);" @if(sCommerce::config('product.show_field_price_special', 1) == 1) checked @endif>
        <input type="hidden" id="product__show_field_price_special" name="product__show_field_price_special" value="{{sCommerce::config('product.show_field_price_special', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__show_field_price_opt" class="warning">@lang('sCommerce::global.price_opt')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.price_opt')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__show_field_price_opt);" @if(sCommerce::config('product.show_field_price_opt', 1) == 1) checked @endif>
        <input type="hidden" id="product__show_field_price_opt" name="product__show_field_price_opt" value="{{sCommerce::config('product.show_field_price_opt', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__show_field_price_opt_special" class="warning">@lang('sCommerce::global.price_opt_special')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.price_opt_special')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__show_field_price_opt_special);" @if(sCommerce::config('product.show_field_price_opt_special', 1) == 1) checked @endif>
        <input type="hidden" id="product__show_field_price_opt_special" name="product__show_field_price_opt_special" value="{{sCommerce::config('product.show_field_price_opt_special', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__inventory_on" class="warning">@lang('sCommerce::global.inventory')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.inventory_on_help')"></i>
    </div>
    <div class="col col-4 col-md-3 col-lg-2">
        <select id="product__inventory_on" class="form-control" name="product__inventory_on" onchange="documentDirty=true;">
            <option value="0" @if(sCommerce::config('product.inventory_on', '1') == '0') selected @endif>@lang('sCommerce::global.turned_off')</option>
            <option value="1" @if(sCommerce::config('product.inventory_on', '1') == '1') selected @endif>@lang('sCommerce::global.only_display') (@lang('sCommerce::global.manager_cannot_change'))</option>
            <option value="2" @if(sCommerce::config('product.inventory_on', '1') == '2') selected @endif>@lang('sCommerce::global.display_field')</option>
        </select>
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__show_field_sku" class="warning">@lang('sCommerce::global.sku')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.sku')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__show_field_sku);" @if(sCommerce::config('product.show_field_sku', 1) == 1) checked @endif>
        <input type="hidden" id="product__show_field_sku" name="product__show_field_sku" value="{{sCommerce::config('product.show_field_sku', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__available_types">@lang('sCommerce::global.available_types')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.available_types_help')"></i>
    </div>
    <div class="col">
        <select id="product__available_types" class="form-control select2" name="product__available_types[]" multiple onchange="documentDirty=true;">
            @foreach(sProduct::listType() as $id => $item)
                <option value="{{$id}}" @if(in_array($id, sCommerce::config('product.available_types', []))) selected @endif>{{$item}}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__show_field_categories" class="warning">@lang('sCommerce::global.categories')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.categories')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__show_field_categories);" @if(sCommerce::config('product.show_field_categories', 1) == 1) checked @endif>
        <input type="hidden" id="product__show_field_categories" name="product__show_field_categories" value="{{sCommerce::config('product.show_field_categories', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__show_field_relevant" class="warning">@lang('sCommerce::global.relevant')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.relevant')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__show_field_relevant);" @if(sCommerce::config('product.show_field_relevant', 1) == 1) checked @endif>
        <input type="hidden" id="product__show_field_relevant" name="product__show_field_relevant" value="{{sCommerce::config('product.show_field_relevant', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__show_field_weight" class="warning">@lang('sCommerce::global.weight')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.weight')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__show_field_weight);" @if(sCommerce::config('product.show_field_weight', 1) == 1) checked @endif>
        <input type="hidden" id="product__show_field_weight" name="product__show_field_weight" value="{{sCommerce::config('product.show_field_weight', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__show_field_width" class="warning">@lang('sCommerce::global.width')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.width')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__show_field_width);" @if(sCommerce::config('product.show_field_width', 1) == 1) checked @endif>
        <input type="hidden" id="product__show_field_width" name="product__show_field_width" value="{{sCommerce::config('product.show_field_width', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__show_field_height" class="warning">@lang('sCommerce::global.height')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.height')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__show_field_height);" @if(sCommerce::config('product.show_field_height', 1) == 1) checked @endif>
        <input type="hidden" id="product__show_field_height" name="product__show_field_height" value="{{sCommerce::config('product.show_field_height', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__show_field_length" class="warning">@lang('sCommerce::global.length')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.length')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__show_field_length);" @if(sCommerce::config('product.show_field_length', 1) == 1) checked @endif>
        <input type="hidden" id="product__show_field_length" name="product__show_field_length" value="{{sCommerce::config('product.show_field_length', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__show_field_volume" class="warning">@lang('sCommerce::global.volume')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.show_field') @lang('sCommerce::global.volume')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__show_field_volume);" @if(sCommerce::config('product.show_field_volume', 1) == 1) checked @endif>
        <input type="hidden" id="product__show_field_volume" name="product__show_field_volume" value="{{sCommerce::config('product.show_field_volume', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__visual_editor_introtext" class="warning">@lang('sCommerce::global.visual_editor_for') @lang('global.resource_summary')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.visual_editor_for') @lang('sCommerce::global.resource_summary')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__visual_editor_introtext);" @if(sCommerce::config('product.visual_editor_introtext', 1) == 1) checked @endif>
        <input type="hidden" id="product__visual_editor_introtext" name="product__visual_editor_introtext" value="{{sCommerce::config('product.visual_editor_introtext', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="split my-3"></div>