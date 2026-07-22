@php use Seiger\sCommerce\Models\sAttribute; @endphp
<style>
    #form .select2-container {
        width:100% !important;
        max-width:100%;
        min-width:0;
    }
    #form input[type="checkbox"] {
        display:inline-block;
        flex:0 0 16px;
        width:16px !important;
        height:16px !important;
        min-height:16px !important;
        margin:0;
        padding:0;
        border-radius:2px;
        accent-color:#036efe;
        box-shadow:none;
        cursor:pointer;
        vertical-align:middle;
    }
    #form input[type="checkbox"]:focus {
        outline:2px solid rgba(3,110,254,.25);
        outline-offset:2px;
    }
    #form input[type="checkbox"]:disabled {
        cursor:not-allowed;
        opacity:.55;
    }
    #form :is(.scommerce-general-settings-section, .scommerce-products-settings-section) label {
        color:#344054;
        font-weight:700;
    }
    #form :is(.scommerce-general-settings-section, .scommerce-products-settings-section)
    .row.form-row:has(input.form-checkbox[type="checkbox"]) {
        display:flex;
        align-items:center;
        gap:8px;
        min-height:24px;
        margin:0 0 4px;
        padding:2px 0;
    }
    #form :is(.scommerce-general-settings-section, .scommerce-products-settings-section)
    .row.form-row:has(input.form-checkbox[type="checkbox"]) > .col {
        order:-1;
        flex:0 0 16px;
        width:16px;
        min-width:16px;
        margin:0;
        padding:0;
    }
    #form :is(.scommerce-general-settings-section, .scommerce-products-settings-section)
    .row.form-row:has(input.form-checkbox[type="checkbox"]) > .col-auto {
        display:flex;
        flex:0 1 auto;
        align-items:center;
        gap:6px;
        min-width:0;
        padding:0;
    }
    #form :is(.scommerce-general-settings-section, .scommerce-products-settings-section)
    .row.form-row:has(input.form-checkbox[type="checkbox"]) label {
        margin:0;
    }
    #form :is(.scommerce-general-settings-section--base, .scommerce-products-settings-section--functionality)
    .row.form-row:not(:has(input.form-checkbox[type="checkbox"])) {
        display:grid;
        grid-template-columns:minmax(0,1fr);
        gap:6px;
        margin:0 0 14px;
        padding:0;
    }
    #form :is(.scommerce-general-settings-section--base, .scommerce-products-settings-section--functionality)
    .row.form-row:not(:has(input.form-checkbox[type="checkbox"])) > .col-auto {
        display:flex;
        align-items:center;
        gap:6px;
        width:auto;
        min-width:0;
        max-width:none;
        margin:0;
        padding:0;
    }
    #form :is(.scommerce-general-settings-section--base, .scommerce-products-settings-section--functionality)
    .row.form-row:not(:has(input.form-checkbox[type="checkbox"])) > .col {
        width:100%;
        min-width:0;
        max-width:none;
        margin:0;
        padding:0;
    }
    #form :is(.scommerce-general-settings-section--base, .scommerce-products-settings-section--functionality)
    .row.form-row:not(:has(input.form-checkbox[type="checkbox"])) > .col > .form-control {
        width:100%;
        max-width:100%;
        min-height:38px;
    }
    #form .scommerce-menu-order-control {
        display:flex;
        flex-wrap:nowrap;
        align-items:stretch;
    }
    #form .scommerce-menu-order-control .input-group-prepend {
        display:flex;
        flex:0 0 auto;
    }
    #form .scommerce-menu-order-control .btn {
        display:flex;
        align-items:center;
        justify-content:center;
        min-width:42px;
        min-height:38px;
    }
    #form .scommerce-menu-order-control > .form-control {
        flex:1 1 auto;
        width:1% !important;
        min-width:0;
    }
</style>
@if(!is_writable(EVO_CORE_PATH . 'custom/config/seiger/settings/sCommerce.php'))<div class="alert alert-danger" role="alert">@lang('sCommerce::global.not_writable')</div>@endif
@if(!is_writable(EVO_CORE_PATH . 'custom/config/seiger/settings/sCommerceCurrencies.php'))<div class="alert alert-danger" role="alert">@lang('sCommerce::global.not_writable_currencies')</div>@endif
<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=settingsSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=settings" />
    {{--<h3 class="sectionTrans">
        @lang('sCommerce::global.additional_fields_main_product_tab')
        <div class="btn-group">
            <span class="btn btn-primary" onclick="addItem('main_product_constructors')">
                <i class="fa fa-plus"></i> <span>@lang('global.add')</span>
            </span>
        </div>
    </h3>--}}
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
    @include('sCommerce::partials.settingsGeneralConfiguration')
    @include('sCommerce::partials.settingsProductsSectionConfiguration')
    @include('sCommerce::partials.settingsCheckoutConfiguration')
    @include('sCommerce::partials.settingsNotificationsEmailConfiguration')
    @if(is_array($events = evo()->invokeEvent('sCommerceManagerSettingsBlocksEvent', ['dataInput' => $sCommerceController->getData()])))
        @foreach($events as $event)
            @if(is_array($event) && isset($event['view'])){!!$event['view']!!}@endif
        @endforeach
    @endif
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
