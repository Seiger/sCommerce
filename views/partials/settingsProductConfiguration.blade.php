@php use Seiger\sCommerce\Models\sProduct; @endphp
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
        <label for="product__inventory_commit" class="warning">@lang('sCommerce::global.inventory_commit')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.inventory_commit_help')"></i>
    </div>
    <div class="col col-4 col-md-3 col-lg-2">
        <select id="product__inventory_commit" class="form-control" name="product__inventory_commit" onchange="documentDirty=true;">
            @foreach(\Seiger\sCommerce\Services\StockCommitter::MODES as $mode)
                <option value="{{$mode}}" @selected(\Seiger\sCommerce\Services\StockCommitter::mode() === $mode)>@lang('sCommerce::global.inventory_commit_' . $mode)</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__inventory_release_on_cancel" class="warning">@lang('sCommerce::global.inventory_release_on_cancel')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.inventory_release_on_cancel_help')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__inventory_release_on_cancel);" @if(sCommerce::config('product.inventory_release_on_cancel', 1) == 1) checked @endif>
        <input type="hidden" id="product__inventory_release_on_cancel" name="product__inventory_release_on_cancel" value="{{sCommerce::config('product.inventory_release_on_cancel', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
@foreach(\Seiger\sCommerce\Services\StockCommitter::configurationWarnings() as $warning)
    <div class="row form-row"><div class="col"><span class="text-danger">{{$warning}}</span></div></div>
@endforeach
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
{{-- Optional features: their fields are shown only while the feature is on. --}}
<div class="row form-row">
    <div class="col-auto">
        <label for="product__vat_display_on" class="warning">@lang('sCommerce::global.vat_display_on')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.vat_display_on_help')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" data-feature-toggle="vat_display_on" onchange="documentDirty=true;" onclick="changestate(document.form.product__vat_display_on);" @if(sCommerce::config('product.vat_display_on', 0) == 1) checked @endif>
        <input type="hidden" id="product__vat_display_on" name="product__vat_display_on" value="{{sCommerce::config('product.vat_display_on', 0)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row" data-feature="vat_display_on" @style(['display:none' => sCommerce::config('product.vat_display_on', 0) != 1])>
    <div class="col-auto">
        <label for="product__vat_rate">@lang('sCommerce::global.vat_rate')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.vat_rate_help')"></i>
    </div>
    <div class="col col-4 col-md-3 col-lg-2">
        <input type="number" id="product__vat_rate" name="product__vat_rate" class="form-control" min="0" max="100" step="0.01" value="{{sCommerce::config('product.vat_rate', 0)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row" data-feature="vat_display_on" @style(['display:none' => sCommerce::config('product.vat_display_on', 0) != 1])>
    <div class="col-auto">
        <label for="product__vat_display">@lang('sCommerce::global.vat_display')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.vat_display_help')"></i>
    </div>
    <div class="col col-4 col-md-3 col-lg-2">
        <select id="product__vat_display" class="form-control" name="product__vat_display" onchange="documentDirty=true;">
            <option value="gross" @if(sCommerce::config('product.vat_display', 'gross') == 'gross') selected @endif>@lang('sCommerce::global.vat_display_gross')</option>
            <option value="net" @if(sCommerce::config('product.vat_display', 'gross') == 'net') selected @endif>@lang('sCommerce::global.vat_display_net')</option>
        </select>
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__retail_excise_on" class="warning">@lang('sCommerce::global.retail_excise_on')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.retail_excise_on_help')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" data-feature-toggle="retail_excise_on" onchange="documentDirty=true;" onclick="changestate(document.form.product__retail_excise_on);" @if(sCommerce::config('product.retail_excise_on', 0) == 1) checked @endif>
        <input type="hidden" id="product__retail_excise_on" name="product__retail_excise_on" value="{{sCommerce::config('product.retail_excise_on', 0)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row" data-feature="retail_excise_on" @style(['display:none' => sCommerce::config('product.retail_excise_on', 0) != 1])>
    <div class="col-auto">
        <label for="product__retail_excise_rate">@lang('sCommerce::global.retail_excise_rate')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.retail_excise_rate_help')"></i>
    </div>
    <div class="col col-4 col-md-3 col-lg-2">
        <input type="number" id="product__retail_excise_rate" name="product__retail_excise_rate" class="form-control" min="0" max="100" step="0.01" value="{{sCommerce::config('product.retail_excise_rate', 0)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__container_deposit_on" class="warning">@lang('sCommerce::global.container_deposit_on')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.container_deposit_on_help')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" data-feature-toggle="container_deposit_on" onchange="documentDirty=true;" onclick="changestate(document.form.product__container_deposit_on);" @if(sCommerce::config('product.container_deposit_on', 0) == 1) checked @endif>
        <input type="hidden" id="product__container_deposit_on" name="product__container_deposit_on" value="{{sCommerce::config('product.container_deposit_on', 0)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row" data-feature="container_deposit_on" @style(['display:none' => sCommerce::config('product.container_deposit_on', 0) != 1])>
    <div class="col-auto">
        <label for="product__container_terms">@lang('sCommerce::global.container_terms')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.container_terms_help')"></i>
    </div>
    <div class="col">
        <input type="text" id="product__container_terms" name="product__container_terms" class="form-control" maxlength="255" value="{{sCommerce::config('product.container_terms', '')}}" onchange="documentDirty=true;">
    </div>
</div>
<script>
    document.querySelectorAll('[data-feature-toggle]').forEach(function (box) {
        box.addEventListener('click', function () {
            document.querySelectorAll('[data-feature="' + box.dataset.featureToggle + '"]').forEach(function (row) {
                row.style.display = box.checked ? '' : 'none';
            });
        });
    });
</script>
<div class="row form-row">
    <div class="col-auto">
        <label for="product__available_modes">@lang('sCommerce::global.available_types')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.available_types_help')"></i>
    </div>
    <div class="col">
        <select id="product__available_modes" class="form-control select2" name="product__available_modes[]" multiple onchange="documentDirty=true;">
            @foreach(sProduct::listMode() as $id => $item)
                <option value="{{$id}}" @if(in_array($id, sCommerce::config('product.available_modes', []))) selected @endif>{{$item}}</option>
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
        <label for="product__visual_editor_introtext" class="warning">@lang('sCommerce::global.visual_editor_for') @lang('global.resource_summary')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.visual_editor_for') @lang('sCommerce::global.resource_summary')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__visual_editor_introtext);" @if(sCommerce::config('product.visual_editor_introtext', 1) == 1) checked @endif>
        <input type="hidden" id="product__visual_editor_introtext" name="product__visual_editor_introtext" value="{{sCommerce::config('product.visual_editor_introtext', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
