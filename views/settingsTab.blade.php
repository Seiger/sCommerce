@php use Seiger\sCommerce\Models\sAttribute; @endphp
@if(!is_writable(EVO_CORE_PATH . 'custom/config/seiger/settings/sCommerce.php'))<div class="alert alert-danger" role="alert">@lang('sCommerce::global.not_writable')</div>@endif
<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=settingsSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=settings" />
    @if(count(sCommerce::config('basic.available_currencies', [])) && trim(sCommerce::config('basic.main_currency', '')))
        <h3>@lang('sCommerce::global.currency_conversion')</h3>
        @php($currs = sCommerce::getCurrencies(sCommerce::config('basic.available_currencies', [])))
        @foreach($currs as $curr)
            @foreach($currs as $cur)
                @if($curr['alpha'] != $cur['alpha'])
                    <div class="row form-row">
                        <div class="col-auto">
                            <label for="currencies__{{$curr['alpha']}}_{{$cur['alpha']}}">{{$curr['alpha']}} --> {{$cur['alpha']}}</label>
                        </div>
                        <div class="col col-4 col-md-3 col-lg-2">
                            <input type="number" id="currencies__{{$curr['alpha']}}_{{$cur['alpha']}}" name="currencies__{{$curr['alpha']}}_{{$cur['alpha']}}" class="form-control" value="{{sCommerce::config('currencies.'.$curr['alpha'].'_'.$cur['alpha'], 1)}}" onchange="documentDirty=true;">
                        </div>
                    </div>
                @endif
            @endforeach
        @endforeach
        <div class="split my-3"></div>
    @endif
    <div class="col col-12 col-sm-12 col-md-6">
        <h3 class="sectionTrans">
            @lang('sCommerce::global.additional_fields_main_product_tab')
            <div class="btn-group">
                <span class="btn btn-primary" onclick="addItem('main_product_constructors')">
                    <i class="fa fa-plus"></i> <span>@lang('global.add')</span>
                </span>
            </div>
        </h3>
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
    <h3>@lang('sCommerce::global.price_configuration')</h3>
    <div class="row form-row">
        <div class="col-auto">
            <label for="basic__available_currencies">@lang('sCommerce::global.available_currencies')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.available_currencies_help')"></i>
        </div>
        <div class="col">
            <select id="basic__available_currencies" class="form-control select2" name="basic__available_currencies[]" multiple onchange="documentDirty=true;">
                @foreach(sCommerce::getCurrencies() as $currency)
                    <option value="{{$currency['alpha']}}" @if(in_array($currency['alpha'], sCommerce::config('basic.available_currencies', []))) selected @endif title="{{$currency['name']}}">{{$currency['alpha']}} ({{$currency['symbol']}})</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="row form-row">
        <div class="col-auto">
            <label for="basic__main_currency">@lang('sCommerce::global.main_currency')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.main_currency_help')"></i>
        </div>
        <div class="col col-4 col-md-3 col-lg-2">
            <select id="basic__main_currency" class="form-control" name="basic__main_currency" onchange="documentDirty=true;">
                @foreach(sCommerce::getCurrencies(sCommerce::config('basic.available_currencies', [])) as $cur)
                    <option value="{{$cur['alpha']}}" @if(sCommerce::config('basic.main_currency', '') == $cur['alpha']) selected @endif title="{{$cur['name']}}">{{$cur['alpha']}} ({{$cur['symbol']}})</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="row form-row">
        <div class="col-auto">
            <label for="basic__price_symbol">@lang('sCommerce::global.price_symbol')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_symbol_help')"></i>
        </div>
        <div class="col col-4 col-md-3 col-lg-2">
            <select id="basic__price_symbol" class="form-control" name="basic__price_symbol" onchange="documentDirty=true;">
                <option value="0" @if(sCommerce::config('basic.price_symbol', 1) == 0) selected @endif>@lang('global.no')</option>
                <option value="1" @if(sCommerce::config('basic.price_symbol', 1) == 1) selected @endif>@lang('global.yes')</option>
            </select>
        </div>
    </div>
    <div class="row form-row">
        <div class="col-auto">
            <label for="basic__price_decimal_characters">@lang('sCommerce::global.price_decimals')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_decimals_help')"></i>
        </div>
        <div class="col col-4 col-md-3 col-lg-2">
            <select id="basic__price_decimals" class="form-control" name="basic__price_decimals" onchange="documentDirty=true;">
                <option value="0" @if(sCommerce::config('basic.price_decimals', 2) == 0) selected @endif>0</option>
                <option value="1" @if(sCommerce::config('basic.price_decimals', 2) == 1) selected @endif>1</option>
                <option value="2" @if(sCommerce::config('basic.price_decimals', 2) == 2) selected @endif>2</option>
            </select>
        </div>
    </div>
    <div class="row form-row">
        <div class="col-auto">
            <label for="basic__price_decimal_separator">@lang('sCommerce::global.price_decimal_separator')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_decimal_separator_help')"></i>
        </div>
        <div class="col col-4 col-md-3 col-lg-2">
            <select id="basic__price_decimal_separator" class="form-control" name="basic__price_decimal_separator" onchange="documentDirty=true;">
                <option value="." @if(sCommerce::config('basic.price_decimal_separator', '.') == '.') selected @endif>.</option>
                <option value="," @if(sCommerce::config('basic.price_decimal_separator', '.') == ',') selected @endif>,</option>
            </select>
        </div>
    </div>
    <div class="row form-row">
        <div class="col-auto">
            <label for="basic__price_thousands_separator">@lang('sCommerce::global.price_thousands_separator')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_thousands_separator_help')"></i>
        </div>
        <div class="col col-4 col-md-3 col-lg-2">
            <input type="text" id="basic__price_thousands_separator" name="basic__price_thousands_separator" class="form-control" value="{{sCommerce::config('basic.price_thousands_separator', "&nbsp;")}}" onchange="documentDirty=true;">
        </div>
    </div>
    <div class="split my-3"></div>
    <h3>@lang('sCommerce::global.management_product_functionality')</h3>
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
            <label for="product__quantity_on" class="warning">@lang('sCommerce::global.quantity')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.rating_on_help')"></i>
        </div>
        <div class="col">
            <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__quantity_on);" @if(sCommerce::config('product.quantity_on', 1) == 1) checked @endif>
            <input type="hidden" id="product__quantity_on" name="product__quantity_on" value="{{sCommerce::config('product.quantity_on', 1)}}" onchange="documentDirty=true;">
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
            <label for="product__visual_editor_introtext" class="warning">@lang('sCommerce::global.visual_editor_for') @lang('global.resource_summary')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.visual_editor_for') @lang('sCommerce::global.resource_summary')"></i>
        </div>
        <div class="col">
            <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.product__visual_editor_introtext);" @if(sCommerce::config('product.visual_editor_introtext', 1) == 1) checked @endif>
            <input type="hidden" id="product__visual_editor_introtext" name="product__visual_editor_introtext" value="{{sCommerce::config('product.visual_editor_introtext', 1)}}" onchange="documentDirty=true;">
        </div>
    </div>
    <div class="split my-3"></div>
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
