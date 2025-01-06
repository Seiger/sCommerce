@php use Seiger\sCommerce\Models\sAttribute; @endphp
@if(!is_writable(EVO_CORE_PATH . 'custom/config/seiger/settings/sCommerce.php'))<div class="alert alert-danger" role="alert">@lang('sCommerce::global.not_writable')</div>@endif
@if(!is_writable(EVO_CORE_PATH . 'custom/config/seiger/settings/sCommerceCurrencies.php'))<div class="alert alert-danger" role="alert">@lang('sCommerce::global.not_writable_currencies')</div>@endif
<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=settingsSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=settings" />
    <h3 class="sectionTrans">
        @lang('sCommerce::global.additional_fields_main_product_tab')
        <div class="btn-group">
            <span class="btn btn-primary" onclick="addItem('main_product_constructors')">
                <i class="fa fa-plus"></i> <span>@lang('global.add')</span>
            </span>
        </div>
    </h3>
    <div class="col col-12 col-sm-12 col-md-6">
        <div id="main_product_constructors" class="row form-row widgets sortable">
            @foreach(sCommerce::config('constructor.main_product', []) as $item)
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header" style="background: #CECECF;">
                            <i style="cursor:pointer;" class="fas fa-sort"></i>&emsp; {{$item['key']}}
                            <span class="close-icon" onclick="deleteItem(this.closest('.card'))"><i class="fa fa-times"></i></span>
                        </div>
                        <div class="card-block">
                            <div class="userstable">
                                <div class="card-body">
                                    <div class="row form-row">
                                        <div class="col-auto col-title-6">
                                            <label class="warning">@lang('sCommerce::global.key')</label>
                                        </div>
                                        <div class="col">
                                            <input type="text" class="form-control" name="main_product_constructors[key][]" value="{{$item['key']}}" onchange="documentDirty=true;">
                                        </div>
                                    </div>
                                    <div class="row form-row">
                                        <div class="col-auto col-title-6">
                                            <label class="warning">@lang('sCommerce::global.caption')</label>
                                        </div>
                                        <div class="col">
                                            <input type="text" class="form-control" name="main_product_constructors[pagetitle][]" value="{{$item['pagetitle']}}" onchange="documentDirty=true;">
                                        </div>
                                    </div>
                                    <div class="row form-row">
                                        <div class="col-auto col-title-6">
                                            <label class="warning">@lang('sCommerce::global.helptext')</label>
                                        </div>
                                        <div class="col">
                                            <input type="text" class="form-control" name="main_product_constructors[helptext][]" value="{{$item['helptext']}}" onchange="documentDirty=true;">
                                        </div>
                                    </div>
                                    <div class="row form-row">
                                        <div class="col-auto col-title-6">
                                            <label class="warning">@lang('sCommerce::global.type_input')</label>
                                        </div>
                                        <div class="col">
                                            <select class="form-control" name="main_product_constructors[type][]" onchange="documentDirty=true;">
                                                @foreach(sAttribute::listType() as $key => $value)
                                                    <option value="{{$key}}" @if($item['type'] == $key) selected @endif>{{$value}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    @if(in_array($item['type'], [
                                        sAttribute::TYPE_ATTR_SELECT,
                                        sAttribute::TYPE_ATTR_MULTISELECT,
                                    ]))
                                        <div class="row form-row js_options">
                                            <div class="col-auto">
                                                <label>
                                                    @lang('sCommerce::global.value')
                                                    <i onclick="addOption(this.closest('.js_options'))" class="fa fa-plus-circle text-primary"></i>
                                                </label>
                                            </div>
                                            <div class="col">
                                                @if(isset($item['options']) && is_array($item['options']) && count($item['options']))
                                                    @foreach($item['options'] as $option)
                                                        <div class="row form-row">
                                                            <div class="col">
                                                                <input type="text" class="form-control" name="main_product_constructors[options][{{$item['key']}}][]" value="{{$option}}" onchange="documentDirty=true;">
                                                            </div>
                                                            <div class="col-auto">
                                                                <i onclick="deleteItem(this.closest('.row'))" class="fa fa-minus-circle text-danger"></i>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <input type="hidden" data-name="oldkey" name="main_product_constructors[oldkey][]" value="{{$item['key']}}">
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="split my-3"></div>
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
    <div class="row form-row">
        <div class="col-auto">
            <label for="basic__in_new_tab">@lang('sCommerce::global.in_new_tab')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.in_new_tab_help')"></i>
        </div>
        <div class="col">
            <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.basic__in_new_tab);" @if(sCommerce::config('basic.in_new_tab', 1) == 1) checked @endif>
            <input type="hidden" id="basic__in_new_tab" name="basic__in_new_tab" value="{{sCommerce::config('basic.in_new_tab', 1)}}" onchange="documentDirty=true;">
        </div>
    </div>
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
            <label for="basic__orders_on">@lang('sCommerce::global.orders_on')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.orders_on_help')"></i>
        </div>
        <div class="col">
            <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.basic__orders_on);" @if(sCommerce::config('basic.orders_on', 1) == 1) checked @endif>
            <input type="hidden" id="basic__orders_on" name="basic__orders_on" value="{{sCommerce::config('basic.orders_on', 1)}}" onchange="documentDirty=true;">
        </div>
    </div>
    <div class="split my-3"></div>
    @include('sCommerce::partials.settingsCurrencyPriceConfiguration')
    @include('sCommerce::partials.settingsCartConfiguration')
    @include('sCommerce::partials.settingsProductConfiguration')
    @include('sCommerce::partials.settingsProductsConfiguration')
    <span id="parentName" class="hidden"></span>
    <input type="hidden" name="parent" value="0"/>
</form>

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!$moduleUrl!!}">
                <i class="fa fa-times-circle"></i><span>@lang('global.cancel')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fas fa-save"></i><span>@lang('global.save')</span>
            </a>
        </div>
    </div>
    <div class="draft-value hidden">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header" style="background: #CECECF;">
                    <i style="cursor:pointer;" class="fas fa-sort"></i>&emsp;
                    <span class="close-icon" onclick="deleteItem(this.closest('.card'))"><i class="fa fa-times"></i></span>
                </div>
                <div class="card-block">
                    <div class="userstable">
                        <div class="card-body">
                            <div class="row form-row">
                                <div class="col-auto col-title-6">
                                    <label class="warning">@lang('sCommerce::global.key')</label>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control" name="constructors[key][]" value="" onchange="documentDirty=true;">
                                </div>
                            </div>
                            <div class="row form-row">
                                <div class="col-auto col-title-6">
                                    <label>@lang('sCommerce::global.caption')</label>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control" name="constructors[pagetitle][]" value="" onchange="documentDirty=true;">
                                </div>
                            </div>
                            <div class="row form-row">
                                <div class="col-auto col-title-6">
                                    <label>@lang('sCommerce::global.helptext')</label>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control" name="constructors[helptext][]" value="" onchange="documentDirty=true;">
                                </div>
                            </div>
                            <div class="row form-row">
                                <div class="col-auto col-title-6">
                                    <label>@lang('sCommerce::global.type_input')</label>
                                </div>
                                <div class="col">
                                    <select class="form-control" name="constructors[type][]" onchange="documentDirty=true;">
                                        @foreach(sAttribute::listType() as $key => $value)
                                            <option value="{{$key}}">{{$value}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="draft-option hidden">
        <div class="row form-row">
            <div class="col">
                <input type="text" class="form-control" name="constructors[options][]" value="" onchange="documentDirty=true;">
            </div>
            <div class="col-auto">
                <i onclick="deleteItem(this.closest('.row'))" class="fa fa-minus-circle text-danger"></i>
            </div>
        </div>
    </div>
    <script>
        let nIntervId;
        let parentAction = null;
        function enableCatalogRootSelection(a, b, c) {
            if (b) {
                parent.tree.ca = "parent";
                a.className = "fa fa-folder-open";
                allowParentSelection = true;
                parentAction = 'catalogRoot';
                if (!nIntervId) {
                    nIntervId = setInterval(changeParent, 500, c);
                }
            } else {
                parent.tree.ca = "open";
                a.className = "fa fa-folder";
                allowParentSelection = false;
                document.form.parent.value = 0;
                parentAction = null;
                clearInterval(nIntervId);
                nIntervId = null;
            }
        }
        function changeParent(key) {
            if (document.form.parent.value > 0) {
                if (parentAction == 'catalogRoot') {
                    document.getElementsByName('basic__catalog_root' + key)[0].value = document.form.parent.value;
                    document.getElementById('catalogRootName' + key).innerHTML = document.getElementById('parentName').innerHTML
                }
            }
        }
        function addItem(selector){document.getElementById(selector).insertAdjacentHTML('beforeend', document.querySelector('.draft-value').innerHTML.replaceAll('constructors', selector));documentDirty=true}
        function deleteItem(element){alertify.confirm("@lang('sCommerce::global.are_you_sure')","@lang('sCommerce::global.deleted_irretrievably')",function(){alertify.error("@lang('sCommerce::global.deleted')");element.remove()},function(){alertify.success("@lang('sCommerce::global.canceled')")}).set('labels',{ok:"@lang('global.delete')",cancel:"@lang('global.cancel')"}).set({transition:'zoom'});documentDirty=true}
        function addOption(element){element.querySelector('.col').insertAdjacentHTML('beforeend', document.querySelector('.draft-option').innerHTML.replaceAll('constructors[options]', element.closest('.widgets').getAttribute('id')+'[options]['+element.closest('.card').querySelector('[data-name="oldkey"]').value+']'));documentDirty=true}
    </script>
@endpush
