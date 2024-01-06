@if(!is_writable(EVO_CORE_PATH . 'custom/config/seiger/settings/sCommerce.php'))<div class="alert alert-danger" role="alert">@lang('sCommerce::global.not_writable')</div>@endif
<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=settingsSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=settings" />
    {{--<h3>@lang('sCommerce::global.management_additional_fields')</h3>
    <div class="row form-row widgets sortable">
        @php($settings = require EVO_CORE_PATH . 'custom/config/seiger/settings/sCommerce.php')
        @foreach($settings as $setting)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <i style="cursor:pointer;font-size:x-large;" class="fas fa-sort"></i>&emsp; {{$setting['key']}}
                        <span class="close-icon"><i class="fa fa-times"></i></span>
                    </div>
                    <div class="card-block">
                        <div class="userstable">
                            <div class="card-body">
                                <div class="row form-row">
                                    <div class="col-auto col-title-6">
                                        <label class="warning">@lang('global.name')</label>
                                    </div>
                                    <div class="col">
                                        <input type="text" class="form-control" name="settings[name][]" maxlength="255" value="{{$setting['name']}}" onchange="documentDirty=true;" spellcheck="true">
                                    </div>
                                </div>
                                <div class="row form-row">
                                    <div class="col-auto col-title-6">
                                        <label class="warning">@lang('sArticles::global.field_type')</label>
                                    </div>
                                    <div class="col">
                                        <select id="rating" class="form-control" name="settings[type][]" onchange="documentDirty=true;">
                                            @foreach(['Text', 'Textarea', 'RichText', 'File', 'Image'] as $value)
                                                <option value="{{$value}}" @if($setting['type'] == $value) selected @endif>{{$value}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <input type="hidden" name="settings[key][]" value="{{$setting['key']}}" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="split my-3"></div>
    <h3>@lang('sArticles::global.management_fields_on')</h3>
    <div class="row form-row">
        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row">
                <div class="col-title-8">
                    <label for="tag_texts_on" class="warning">@lang('sArticles::global.tag_texts')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sArticles::global.tag_texts_on_off_help')"></i>
                </div>
                <div class="col">
                    <input type="checkbox" id="tag_texts_on_check" class="form-checkbox form-control" name="tag_texts_on_check" value="" onchange="documentDirty=true;" onclick="changestate(document.form.tag_texts_on);" @if(evo()->getConfig('sart_tag_texts_on', 1) == 1) checked @endif>
                    <input type="hidden" id="tag_texts_on" name="tag_texts_on" value="{{evo()->getConfig('sart_tag_texts_on', 1)}}" onchange="documentDirty=true;">
                </div>
            </div>
        </div>
    </div>
    <div class="row form-row">
        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row">
                <div class="col-title-8">
                    <label for="cover_title_on" class="warning">@lang('sArticles::global.cover_title')</label>
                </div>
                <div class="col">
                    <input type="checkbox" id="cover_title_on_check" class="form-checkbox form-control" name="cover_title_on_check" value="" onchange="documentDirty=true;" onclick="changestate(document.form.cover_title_on);" @if(evo()->getConfig('sart_cover_title_on', 1) == 1) checked @endif>
                    <input type="hidden" id="cover_title_on" name="cover_title_on" value="{{evo()->getConfig('sart_cover_title_on', 1)}}" onchange="documentDirty=true;">
                </div>
            </div>
        </div>
    </div>
    <div class="row form-row">
        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row">
                <div class="col-title-8">
                    <label for="long_title_on" class="warning">@lang('global.long_title')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sArticles::global.long_title_on_off_help')"></i>
                </div>
                <div class="col">
                    <input type="checkbox" id="long_title_on_check" class="form-checkbox form-control" name="long_title_on_check" value="" onchange="documentDirty=true;" onclick="changestate(document.form.long_title_on);" @if(evo()->getConfig('sart_long_title_on', 1) == 1) checked @endif>
                    <input type="hidden" id="long_title_on" name="long_title_on" value="{{evo()->getConfig('sart_long_title_on', 1)}}" onchange="documentDirty=true;">
                </div>
            </div>
        </div>
    </div>--}}
    <div class="split my-3"></div>
    <h3>@lang('sCommerce::global.management_base_functionality')</h3>
    <div class="row form-row">
        <div class="col-auto">
            <label for="basic__catalog_root" class="warning">@lang('sCommerce::global.catalog_root')</label>
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
                <i id="plockcat" class="fa fa-folder" onclick="enableCatalogRootSelection(!allowParentSelection);"></i>
                <b id="catalogRootName">{{sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1))}} ({{entities($parentname)}})</b>
                <input type="hidden" name="basic__catalog_root" value="{{sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1))}}" onchange="documentDirty=true;" />
            </div>
        </div>
    </div>
    <div class="row form-row">
        <div class="col-auto">
            <label for="basic__in_main_menu" class="warning">@lang('sCommerce::global.in_main_menu')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.in_main_menu_help')"></i>
        </div>
        <div class="col">
            <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.basic__in_main_menu);" @if(sCommerce::config('basic.in_main_menu', 0) == 1) checked @endif>
            <input type="hidden" id="basic__in_main_menu" name="basic__in_main_menu" value="{{sCommerce::config('basic.in_main_menu', 0)}}" onchange="documentDirty=true;">
        </div>
    </div>
    <div class="row form-row">
        <div class="col-auto">
            <label for="basic__main_menu_order" class="warning">@lang('sCommerce::global.main_menu_order')</label>
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
            <label for="basic__orders_on" class="warning">@lang('sCommerce::global.orders_on')</label>
            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.orders_on_help')"></i>
        </div>
        <div class="col">
            <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.basic__orders_on);" @if(sCommerce::config('basic.orders_on', 1) == 1) checked @endif>
            <input type="hidden" id="basic__orders_on" name="basic__orders_on" value="{{sCommerce::config('basic.orders_on', 1)}}" onchange="documentDirty=true;">
        </div>
    </div>
    {{--
    @if(evo()->getConfig('which_editor', 'TinyMCE5') == 'TinyMCE5')
        @php(evo()->setConfig('tinymce5_theme', evo()->getConfig('sart_tinymce5_theme', 'custom')))
        @php($files = array_diff(scandir(MODX_BASE_PATH.'assets/plugins/tinymce5/configs'), array('.', '..', 'custom.js')))
        @include('tinymce5settings::tinymce5settings', ['themes'=>$files])
    @endif--}}
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
            <a id="Button2" class="btn btn-primary" href="javascript:void(0);" onclick="addItem();">
                <i class="fa fa-plus"></i><span>@lang('global.add')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fas fa-save"></i><span>@lang('global.save')</span>
            </a>
        </div>
    </div>
    <div class="draft-value hidden">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    <i style="cursor:pointer;font-size:x-large;" class="fas fa-sort"></i>&emsp; @lang('sArticles::global.new_field')
                    <span class="close-icon"><i class="fa fa-times"></i></span>
                </div>
                <div class="card-block">
                    <div class="userstable">
                        <div class="card-body">
                            <div class="row form-row">
                                <div class="col-auto col-title-6">
                                    <label class="warning">@lang('sArticles::global.key')</label>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control" name="settings[key][]" maxlength="255" value="" onchange="documentDirty=true;" spellcheck="true">
                                </div>
                            </div>
                            <div class="row form-row">
                                <div class="col-auto col-title-6">
                                    <label class="warning">@lang('global.name')</label>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control" name="settings[name][]" maxlength="255" value="" onchange="documentDirty=true;" spellcheck="true">
                                </div>
                            </div>
                            <div class="row form-row">
                                <div class="col-auto col-title-6">
                                    <label class="warning">@lang('sArticles::global.field_type')</label>
                                </div>
                                <div class="col">
                                    <select id="rating" class="form-control" name="settings[type][]" onchange="documentDirty=true;">
                                        @foreach(['Text', 'Textarea', 'RichText', 'File', 'Image'] as $value)
                                            <option value="{{$value}}">{{$value}}</option>
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
    <script>
        let nIntervId;
        let parentAction = null;
        function enableCatalogRootSelection(b) {
            let plock = document.getElementById('plockcat');
            if (b) {
                parent.tree.ca = "parent";
                plock.className = "fa fa-folder-open";
                allowParentSelection = true;
                parentAction = 'catalogRoot';
                if (!nIntervId) {
                    nIntervId = setInterval(changeParent, 500);
                }
            } else {
                parent.tree.ca = "open";
                plock.className = "fa fa-folder";
                allowParentSelection = false;
                document.form.parent.value = 0;
                parentAction = null;
                clearInterval(nIntervId);
                nIntervId = null;
            }
        }
        function changeParent() {
            if (document.form.parent.value > 0) {
                if (parentAction == 'catalogRoot') {
                    document.form.basic__catalog_root.value = document.form.parent.value;
                    document.getElementById('catalogRootName').innerHTML = document.getElementById('parentName').innerHTML;
                }
            }
        }
    </script>
@endpush
