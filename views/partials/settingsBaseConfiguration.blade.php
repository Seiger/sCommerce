<h3>@lang('sCommerce::global.management_base_functionality')</h3>
@if (evo()->getConfig('check_sMultisite', false))
    @foreach(Seiger\sMultisite\Models\sMultisite::all() as $domain)
        <div class="row form-row">
            <div class="col-auto">
                <label for="basic__catalog_root">{{$domain->site_name}} @lang('sCommerce::global.catalog_root')</label>
                <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.catalog_root_help')"></i>
            </div>
            <div class="col">
                <div>
                    @php($parentlookup = false)
                    @if(sCommerce::config('basic.catalog_root'.$domain->key, $domain->site_start) == 0)
                        @php($parentname = $domain->site_name)
                    @else
                        @php($parentlookup = sCommerce::config('basic.catalog_root'.$domain->key, $domain->site_start))
                    @endif
                    @if($parentlookup !== false && is_numeric($parentlookup))
                        @php($parentname = \EvolutionCMS\Models\SiteContent::withTrashed()->select('pagetitle')->find($parentlookup)->pagetitle)
                        @if(!$parentname)
                            @php(evo()->webAlertAndQuit($_lang["error_no_parent"]))
                        @endif
                    @endif
                    <i id="plockcat{{$domain->key}}" class="fa fa-folder" onclick="enableCatalogRootSelection(this, !allowParentSelection, '{{$domain->key}}');"></i>
                    <b id="catalogRootName{{$domain->key}}">{{sCommerce::config('basic.catalog_root'.$domain->key, $domain->site_start)}} ({{entities($parentname)}})</b>
                    <input type="hidden" name="basic__catalog_root{{$domain->key}}" value="{{sCommerce::config('basic.catalog_root'.$domain->key, $domain->site_start)}}" onchange="documentDirty=true;" />
                </div>
            </div>
        </div>
    @endforeach
@else
    <div class="row form-row">
        <div class="col-auto">
            <label for="basic__catalog_root">@lang('sCommerce::global.catalog_root')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.catalog_root_help')"></i>
        </div>
        <div class="col">
            <div>
                @php($parentlookup = false)
                @if(sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1)) == 0)
                    @php($parentname = evo()->getConfig('site_name'))
                @else
                    @php($parentlookup = sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1)))
                @endif
                @if($parentlookup !== false && is_numeric($parentlookup))
                    @php($parentname = \EvolutionCMS\Models\SiteContent::withTrashed()->select('pagetitle')->find($parentlookup)->pagetitle)
                    @if(!$parentname)
                        @php(evo()->webAlertAndQuit($_lang["error_no_parent"]))
                    @endif
                @endif
                <i id="plockcat" class="fa fa-folder" onclick="enableCatalogRootSelection(this, !allowParentSelection, '');"></i>
                <b id="catalogRootName">{{sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1))}} ({{entities($parentname)}})</b>
                <input type="hidden" name="basic__catalog_root" value="{{sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1))}}" onchange="documentDirty=true;" />
            </div>
        </div>
    </div>
@endif
<div class="row form-row">
    <div class="col-auto">
        <label for="basic__in_main_menu">@lang('sCommerce::global.in_main_menu')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.in_main_menu_help')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.basic__in_main_menu);" @if(sCommerce::config('basic.in_main_menu', 0) == 1) checked @endif>
        <input type="hidden" id="basic__in_main_menu" name="basic__in_main_menu" value="{{sCommerce::config('basic.in_main_menu', 0)}}" onchange="documentDirty=true;">
    </div>
</div>
{{--<div class="row form-row">
    <div class="col-auto">
        <label for="basic__in_new_tab">@lang('sCommerce::global.in_new_tab')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.in_new_tab_help')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.basic__in_new_tab);" @if(sCommerce::config('basic.in_new_tab', 1) == 1) checked @endif>
        <input type="hidden" id="basic__in_new_tab" name="basic__in_new_tab" value="{{sCommerce::config('basic.in_new_tab', 1)}}" onchange="documentDirty=true;">
    </div>
</div>--}}
<div class="row form-row">
    <div class="col-auto">
        <label for="basic__main_menu_order">@lang('sCommerce::global.main_menu_order')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.main_menu_order_help')"></i>
    </div>
    <div class="input-group col col-4 col-md-3 col-lg-2">
        <div class="input-group-prepend">
            <span class="btn btn-secondary" onclick="let elm = document.form.basic__main_menu_order;let v=parseInt(elm.value+'')-1;elm.value=v>0? v:0;elm.focus();documentDirty=true;return false;" style="cursor: pointer;"><i class="fa fa-angle-left"></i></span>
            <span class="btn btn-secondary" onclick="let elm = document.form.basic__main_menu_order;let v=parseInt(elm.value+'')+1;elm.value=v>0? v:0;elm.focus();documentDirty=true;return false;" style="cursor: pointer;"><i class="fa fa-angle-right"></i></span>
        </div>
        <input type="text" id="basic__main_menu_order" name="basic__main_menu_order" class="form-control" value="{{sCommerce::config('basic.main_menu_order', 0)}}" maxlength="11" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="basic__search" class="warning">@lang('sCommerce::global.search')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.search_help')"></i>
    </div>
    <div class="col col-4 col-md-3 col-lg-2">
        <select id="basic__search" class="form-control" name="basic__search" onchange="documentDirty=true;">
            <option value="blurred" @if(sCommerce::config('basic.search', 'blurred') == 'blurred') selected @endif>@lang('sCommerce::global.blurred')</option>
            <option value="focused" @if(sCommerce::config('basic.search', 'blurred') == 'focused') selected @endif>@lang('sCommerce::global.focused')</option>
        </select>
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="basic__orders_on">@lang('sCommerce::global.orders_on')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.orders_on_help')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.basic__orders_on);" @if(sCommerce::config('basic.orders_on', 1) == 1) checked @endif>
        <input type="hidden" id="basic__orders_on" name="basic__orders_on" value="{{sCommerce::config('basic.orders_on', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="basic__deliveries_on">@lang('sCommerce::global.deliveries')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.deliveries_on_help')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.basic__deliveries_on);" @if(sCommerce::config('basic.deliveries_on', 1) == 1) checked @endif>
        <input type="hidden" id="basic__deliveries_on" name="basic__deliveries_on" value="{{sCommerce::config('basic.deliveries_on', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="basic__payments_on">@lang('sCommerce::global.payments')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.payments_on_help')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.basic__payments_on);" @if(sCommerce::config('basic.payments_on', 1) == 1) checked @endif>
        <input type="hidden" id="basic__payments_on" name="basic__payments_on" value="{{sCommerce::config('basic.payments_on', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="basic__wishlist_on">@lang('sCommerce::global.wishlist')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.wishlist_on_help')"></i>
    </div>
    <div class="col">
        <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.basic__wishlist_on);" @if(sCommerce::config('basic.wishlist_on', 1) == 1) checked @endif>
        <input type="hidden" id="basic__wishlist_on" name="basic__wishlist_on" value="{{sCommerce::config('basic.wishlist_on', 1)}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="split my-3"></div>
