@php
    use Carbon\Carbon;use Illuminate\Support\Str;use Seiger\sCommerce\Facades\sCommerce;
    use Seiger\sCommerce\Models\sAttribute;
    use Seiger\sCommerce\Models\sProduct;
@endphp
<h3>{{(int)request()->input('i', 0) == 0 ? __('sCommerce::global.new_product') : ($item->pagetitle ?? __('sCommerce::global.no_text'))}}</h3>
<div class="split my-3"></div>

<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=productSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=product&i={{(int)request()->input('i', 0)}}"/>
    <input type="hidden" name="i" value="{{(int)request()->input('i', 0)}}"/>
    <div class="row-col col-12">
        <div class="row form-row">
            <div class="row-col col-lg-3 col-md-6 col-12">
                <div class="row form-row form-row-checkbox">
                    <div class="col-auto col-title">
                        <label for="publishedcheck">@lang('sCommerce::global.visibility')</label>
                        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.published_help')"></i>
                    </div>
                    <div class="col">
                        <input type="checkbox" id="publishedcheck" class="form-checkbox form-control" name="publishedcheck" value="" onchange="documentDirty=true;" onclick="changestate(document.form.published);" @if(isset($item->published) && $item->published) checked @endif>
                        <input type="hidden" id="published" name="published" value="{{$item->published ?? 0}}" onchange="documentDirty=true;">
                        @if(sCommerce::config('product.views_on', 1) == 1)&emsp;
                            <i class="fa fa-eye" data-tooltip="@lang('sCommerce::global.views')">
                                <b>{{$item->views ?? 0}}</b>
                            </i>
                        @endif
                        @if(sCommerce::config('product.rating_on', 1) == 1)&emsp;
                            <i class="fa fa-star" data-tooltip="@lang('sCommerce::global.rating')">
                                <b>{{$item->rating ?? 5}}</b>
                            </i>
                        @endif
                        @if(sCommerce::config('product.inventory_on', 1))&emsp;
                            <i class="fas fa-warehouse" data-tooltip="@lang('sCommerce::global.inventory')">
                                <b>{{$item->inventory ?? 0}}</b>
                            </i>
                        @endif
                    </div>
                </div>
            </div>
            @if(sCommerce::config('product.show_field_availability', 1))
                <div class="row-col col-lg-3 col-md-6 col-12">
                    <div class="row form-row">
                        <div class="col-auto col-title">
                            <label for="availability">@lang('sCommerce::global.availability')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.availability_help')"></i>
                        </div>
                        <div class="col">
                            <select id="availability" class="form-control" name="availability" onchange="documentDirty=true;">
                                @foreach(sProduct::listAvailability() as $key => $title)
                                    <option value="{{$key}}" @if($key == ($item->availability ?? 0)) selected @endif>{{$title}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            @endif
            @if(sCommerce::config('product.show_field_price', 1) == 1)
                <div class="row-col col-lg-3 col-md-6 col-12">
                    <div class="row form-row">
                        <div class="col-auto col-title">
                            <label for="price">@lang('sCommerce::global.price')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_help')"></i>
                        </div>
                        <div class="input-group col">
                            <div class="input-group-prepend">
                                <select name="currency" class="form-control" onchange="documentDirty=true;" style="background-color: #e9ecef;">
                                    @foreach(sCommerce::getCurrencies(sCommerce::config('basic.available_currencies', [])) as $cur)
                                        <option value="{{$cur['alpha']}}" @if(($item->currency ?? sCommerce::config('basic.main_currency', 'USD')) == $cur['alpha']) selected @endif data-tooltip="{{$cur['name']}}">{{str_replace('&nbsp;', ' ', trim($cur['symbol'] ?? '&nbsp;'))}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <input id="price" class="form-control" name="price_regular" value="{{$item->price_regular ?? ''}}" onblur="documentDirty=true;">
                        </div>
                    </div>
                </div>
            @endif
            @if(sCommerce::config('product.show_field_price_special', 1) == 1)
                <div class="row-col col-lg-3 col-md-6 col-12">
                    <div class="row form-row">
                        <div class="col-auto col-title">
                            <label for="price_special">@lang('sCommerce::global.price_special')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_special_help')"></i>
                        </div>
                        <div class="input-group col">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><small>{{str_replace('&nbsp;', ' ', trim(sCommerce::getCurrencies([$item->currency ?? sCommerce::config('basic.main_currency', 'USD')])->first()['symbol']))}}</small></span>
                            </div>
                            <input id="price_special" class="form-control" name="price_special" value="{{$item->price_special ?? ''}}" onblur="documentDirty=true;">
                        </div>
                    </div>
                </div>
            @endif
            @if(sCommerce::config('product.show_field_price_opt', 1) == 1)
                <div class="row-col col-lg-3 col-md-6 col-12">
                    <div class="row form-row">
                        <div class="col-auto col-title">
                            <label for="price_opt_regular">@lang('sCommerce::global.price_opt')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_opt_help')"></i>
                        </div>
                        <div class="input-group col">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><small>{{sCommerce::getCurrencies([$item->currency ?? sCommerce::config('basic.main_currency', 'USD')])->first()['symbol']}}</small></span>
                            </div>
                            <input id="price_opt_regular" class="form-control" name="price_opt_regular" value="{{$item->price_opt_regular ?? ''}}" onblur="documentDirty=true;">
                        </div>
                    </div>
                </div>
            @endif
            @if(sCommerce::config('product.show_field_price_opt_special', 1) == 1)
                <div class="row-col col-lg-3 col-md-6 col-12">
                    <div class="row form-row">
                        <div class="col-auto col-title">
                            <label for="price_opt_special">@lang('sCommerce::global.price_opt_special')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_opt_special_help')"></i>
                        </div>
                        <div class="input-group col">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><small>{{sCommerce::getCurrencies([$item->currency ?? sCommerce::config('basic.main_currency', 'USD')])->first()['symbol']}}</small></span>
                            </div>
                            <input id="price_opt_special" class="form-control" name="price_opt_special" value="{{$item->price_opt_special ?? ''}}" onblur="documentDirty=true;">
                        </div>
                    </div>
                </div>
            @endif
            @if(sCommerce::config('product.inventory_on', 1) == 2)
                <div class="row-col col-lg-3 col-md-6 col-12">
                    <div class="row form-row">
                        <div class="col-auto col-title">
                            <label for="inventory">@lang('sCommerce::global.inventory')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.inventory_help')"></i>
                        </div>
                        <div class="col">
                            <input id="inventory" class="form-control" name="inventory" value="{{$item->inventory ?? ''}}" onblur="documentDirty=true;">
                        </div>
                    </div>
                </div>
            @endif
            @if(sCommerce::config('product.show_field_sku', 1) == 1)
                <div class="row-col col-lg-3 col-md-6 col-12">
                    <div class="row form-row">
                        <div class="col-auto col-title">
                            <label for="sku">@lang('sCommerce::global.sku')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.sku_help')"></i>
                        </div>
                        <div class="col">
                            <input id="sku" class="form-control" name="sku" value="{{$item->sku ?? ''}}" onblur="documentDirty=true;">
                        </div>
                    </div>
                </div>
            @endif
            @if(count(sCommerce::config('product.available_types', [])) > 1)
                <div class="row-col col-lg-3 col-md-6 col-12">
                    <div class="row form-row">
                        <div class="col-auto col-title">
                            <label for="type">@lang('sCommerce::global.product_type')</label>
                            {{--<i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.product_type_help')"></i>--}}
                        </div>
                        <div class="col">
                            <select id="type" name="type" class="form-control" onchange="documentDirty=true;">
                                @foreach(sCommerce::config('product.available_types', []) as $type)
                                    <option value="{{$type}}" @if(($item->type ?? 0) == $type) selected @endif>{{sProduct::listType()[$type]}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        <div class="split my-3"></div>
        <div class="row form-row">
            <div class="row-col col-lg-3 col-md-6 col-12">
                <div class="row form-row">
                    <div class="col-auto col-title">
                        <label for="alias">@lang('global.resource_alias')</label>
                        <i class="fa fa-question-circle" data-tooltip="@lang('global.resource_alias_help')"></i>
                    </div>
                    <div class="input-group col">
                        <input type="text" id="alias" class="form-control" name="alias" maxlength="512" value="{{$item->alias ?? 'new-product'}}" onchange="documentDirty=true;" spellcheck="true">
                        <a id="preview" href="{{$item->link ?? '/'}}" class="btn btn-outline-secondary form-control" type="button" target="_blank">@lang('global.preview')</a>
                    </div>
                </div>
            </div>
            @if (evo()->getConfig('check_sMultisite', false))
                <span id="parentName" class="hidden"></span>
                <input type="hidden" name="parent" value="0"/>
                @foreach(Seiger\sMultisite\Models\sMultisite::all() as $domain)
                    <div class="row-col col-lg-3 col-md-3 col-12">
                        <div class="row form-row">
                            <div class="col-auto col-title">
                                <label for="parent">{{$domain->site_name}} @lang('sCommerce::global.category')</label>
                                <i class="fa fa-question-circle"
                                   data-tooltip="@lang('sCommerce::global.categories_product_help')"></i>
                            </div>
                            <div class="col">
                                <div>
                                    @php($parentlookup = false)
                                    @if(($item->categories()->whereScope('primary_' . $domain->key)->first()->id ?? 0) == 0)
                                        @php($parentname = __('global.disabled'))
                                    @else
                                        @php($parentlookup = ($item->getCategoryAttribute($domain->key) ?? sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1))))
                                    @endif
                                    @if($parentlookup !== false && is_numeric($parentlookup))
                                        @php($parentname = \EvolutionCMS\Models\SiteContent::withTrashed()->select('pagetitle')->find($parentlookup)->pagetitle)
                                        @if(!$parentname)
                                            @php(evo()->webAlertAndQuit(__('global.error_no_parent')))
                                        @endif
                                    @endif
                                    <i id="plockcat{{$domain->key}}" class="fa fa-folder" onclick="enableCatalogRootSelection(this, !allowParentSelection, '{{$domain->key}}');"></i>
                                    <b id="parentRootName{{$domain->key}}">{{$parentlookup}} ({{entities($parentname)}})</b>
                                    <i onclick="cleareCat(this)" class="fa fa-minus-circle text-danger b-btn-del"></i>
                                    <input type="hidden" name="parent_{{$domain->key}}" value="{{$item->categories()->where('scope', 'primary_' . $domain->key)->first()->id ?? 0}}" onchange="documentDirty=true;"/>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="row-col col-lg-3 col-md-3 col-12">
                    <div class="row form-row">
                        <div class="col-auto col-title">
                            <label for="parent">@lang('sCommerce::global.category')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.categories_product_help')"></i>
                        </div>
                        <div class="col">
                            <select id="parent" class="form-control select2" name="parent" onchange="documentDirty=true;">
                                @foreach($sCommerceController->listCategories() as $key => $value)
                                    <option value="{{$key}}" @if($key == ($item->category ?? sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1)))) selected @endif>{{$value}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            @endif
            @if(sCommerce::config('product.show_field_categories', 1) == 1)
                <div class="row-col col-lg-6 col-md-6 col-12">
                    <div class="row form-row">
                        <div class="col-auto col-title">
                            <label for="categories">@lang('sCommerce::global.categories')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.categories_help')"></i>
                        </div>
                        <div class="col">
                            <select id="categories" class="form-control select2" name="categories[]" multiple onchange="documentDirty=true;">
                                @foreach($sCommerceController->listCategories() as $key => $value)
                                    <option value="{{$key}}" @if(in_array($key, $categories)) selected @endif>{{$value}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            @endif
            @if(sCommerce::config('product.show_field_relevant', 1))
                <div class="row-col col-lg-6 col-md-6 col-12">
                    <div class="row form-row">
                        <div class="col-auto col-title">
                            <label for="relevants">@lang('sCommerce::global.relevant')</label>
                        </div>
                        <div class="col">
                            @php($productRelevants = data_is_json($item->relevants ?? '', true) ?: [])
                            <select id="relevants" class="form-control select2" name="relevants[]" multiple onchange="documentDirty=true;">
                                @foreach(sProduct::all() as $itm)
                                    @if(($item->id ?? 0) != $itm->id)
                                        <option value="{{$itm->id}}" @if(in_array($itm->id, $productRelevants)) selected @endif>{{$itm->title}}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        @if(sCommerce::config('product.show_field_weight', 1) || sCommerce::config('product.show_field_width', 1) || sCommerce::config('product.show_field_height', 1) || sCommerce::config('product.show_field_length', 1) || sCommerce::config('product.show_field_volume', 1))
            <div class="split my-3"></div>
            <div class="row form-row">
                @if(sCommerce::config('product.show_field_weight', 1))
                    <div class="row-col col-lg-3 col-md-6 col-12">
                        <div class="row form-row">
                            <div class="col-auto col-title">
                                <label for="weight">@lang('sCommerce::global.weight')</label>
                                <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.weight') - @lang('sCommerce::global.technical_parameter_for_delivery')"></i>
                            </div>
                            <div class="col">
                                <input id="weight" class="form-control" name="weight" value="{{round($item->weight ?? 0, 4)}}" onblur="documentDirty=true;">
                            </div>
                        </div>
                    </div>
                @endif
                @if(sCommerce::config('product.show_field_width', 1))
                    <div class="row-col col-lg-3 col-md-6 col-12">
                        <div class="row form-row">
                            <div class="col-auto col-title">
                                <label for="width">@lang('sCommerce::global.width')</label>
                                <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.width') - @lang('sCommerce::global.technical_parameter_for_delivery')"></i>
                            </div>
                            <div class="col">
                                <input id="width" class="form-control" name="width" value="{{round($item->width ?? 0, 4)}}" onblur="documentDirty=true;">
                            </div>
                        </div>
                    </div>
                @endif
                @if(sCommerce::config('product.show_field_height', 1))
                    <div class="row-col col-lg-3 col-md-6 col-12">
                        <div class="row form-row">
                            <div class="col-auto col-title">
                                <label for="height">@lang('sCommerce::global.height')</label>
                                <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.height') - @lang('sCommerce::global.technical_parameter_for_delivery')"></i>
                            </div>
                            <div class="col">
                                <input id="height" class="form-control" name="height" value="{{round($item->height ?? 0, 4)}}" onblur="documentDirty=true;">
                            </div>
                        </div>
                    </div>
                @endif
                @if(sCommerce::config('product.show_field_length', 1))
                    <div class="row-col col-lg-3 col-md-6 col-12">
                        <div class="row form-row">
                            <div class="col-auto col-title">
                                <label for="length">@lang('sCommerce::global.length')</label>
                                <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.length') - @lang('sCommerce::global.technical_parameter_for_delivery')"></i>
                            </div>
                            <div class="col">
                                <input id="length" class="form-control" name="length" value="{{round($item->length ?? 0, 4)}}" onblur="documentDirty=true;">
                            </div>
                        </div>
                    </div>
                @endif
                @if(sCommerce::config('product.show_field_volume', 1))
                    <div class="row-col col-lg-3 col-md-6 col-12">
                        <div class="row form-row">
                            <div class="col-auto col-title">
                                <label for="volume">@lang('sCommerce::global.volume')</label>
                                <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.volume') - @lang('sCommerce::global.technical_parameter_for_delivery')"></i>
                            </div>
                            <div class="col">
                                <input id="volume" class="form-control" name="volume" value="{{round($item->volume ?? 0, 4)}}" onblur="documentDirty=true;">
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
    @php($mainAttributes = sCommerce::config('constructor.main_product', []))
    @if(count($mainAttributes))
        <div class="split my-3"></div>
        <div class="row-col col-12">
            <div class="row form-row">
                @php($prefix = 'constructor__')
                @foreach($mainAttributes as $attribute)
                    @php($value = data_is_json(($item->{'constructor_'.$attribute['key']}??''),true)?:($item->{'constructor_'.$attribute['key']}??''))
                    @php($attribute = (object)array_merge($attribute, ['id' => $attribute['key'], 'value' => $value]))
                    @switch($attribute->type)
                        @case(sAttribute::TYPE_ATTR_NUMBER)
                            @include('sCommerce::partials.attributeNumber')
                            @break
                        @case(sAttribute::TYPE_ATTR_CHECKBOX)
                            @include('sCommerce::partials.attributeCheckbox')
                            @break
                        @case(sAttribute::TYPE_ATTR_SELECT)
                            @php($options = $attribute->options)
                            @php($options = array_combine(array_values($options), $options))
                            @include('sCommerce::partials.attributeSelect')
                            @break
                        @case(sAttribute::TYPE_ATTR_MULTISELECT)
                            @php($options = $attribute->options)
                            @php($options = array_combine(array_values($options), $options))
                            @php($value = is_array($attribute->value) ? $attribute->value : [])
                            @include('sCommerce::partials.attributeMultiselect')
                            @break
                        @case(sAttribute::TYPE_ATTR_TEXT)
                            @php($field = (array)$attribute)
                            @php($field['prefix'] = $prefix)
                            @php($field['label'] = $field['pagetitle'])
                            @php($field['name'] = $field['key'])
                            <div class="row-col col-12">
                                <div class="row form-row">
                                    @include('sCommerce::partials.textField')
                                </div>
                            </div>
                            @break
                        @case(sAttribute::TYPE_ATTR_CUSTOM)
                            @php(View::getFinder()->setPaths([MODX_BASE_PATH . 'assets/modules/scommerce/attribute']))
                            @include($attribute->alias)
                            @break
                    @endswitch
                @endforeach
            </div>
        </div>
    @endif
</form>
<div class="split my-3"></div>

@if($item->id)
    <h3>@lang('sCommerce::global.gallery') <small><i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.gallery_help')"></i></small></h3>
    {!!sGallery::initialiseView()->itemType('product')!!}
    <div class="split my-3"></div>
    @if($item->reviews->count())
        <h3>Reviews</h3>
        <table class="table table-condensed table-hover sectionTrans scom-table">
            @foreach($item->reviews as $review)
                <tr style="height:40px;" id="review-{{$review->id}}">
                    <td style="padding-left:15px;">
                        @if($review->published)
                            <span class="badge badge-success">@lang('global.page_data_published')</span>
                        @else
                            <span class="badge badge-dark">@lang('global.page_data_unpublished')</span>
                        @endif
                    </td>
                    <td style="width:95px;">{{\Carbon\Carbon::parse($review->created_at)->toFormattedDateString()}}</td>
                    <td style="width:30px;">{{$review->rating}}</td>
                    <td>{{$review->name}}</td>
                    <td>{{Str::of($review->message)->limit(360)}}</td>
                    <td style="text-align:center;padding-right:15px;">
                        <div class="btn-group">
                            <a href="{!!$moduleUrl!!}&get=review&i={{$review->id}}" class="btn btn-outline-success">
                                <i class="fa fa-pencil"></i> <span>@lang('global.edit')</span>
                            </a>
                            <a href="#" data-href="{!!$moduleUrl!!}&get=reviewDelete&i={{$review->id}}"
                               data-delete="{{$review->id}}"
                               data-name="{{$review->name ?? __('sCommerce::global.no_text')}}"
                               class="btn btn-outline-danger">
                                <i class="fa fa-trash"></i> <span>@lang('global.remove')</span>
                            </a>
                        </div>
                    </td>
                </tr>
            @endforeach
        </table>
        <div class="split my-3"></div>
    @endif
@endif

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!$moduleUrl!!}&get=products{{request()->has('page') ? '&page=' . request()->page : ''}}">
                <i class="fa fa-times-circle"></i><span>@lang('sCommerce::global.to_list_products')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fa fa-floppy-o"></i>
                <span>@lang('global.save')</span>
            </a>
            <a id="Button6" class="btn btn-info" data-href="{!!$moduleUrl!!}&get=productDuplicate&i={{$item->id}}" data-duplicate="{{$item->id}}" data-name="{{$item->pagetitle}}">
                <i class="fa fa-clone"></i> <span>@lang('global.duplicate')</span>
            </a>
            <a id="Button3" class="btn btn-danger" data-href="{!!$moduleUrl!!}&get=productDelete&i={{$item->id}}" data-delete="{{$item->id}}" data-name="{{$item->pagetitle}}">
                <i class="fa fa-trash"></i> <span>@lang('global.delete')</span>
            </a>
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
                parentAction = 'parentRoot';
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
                if (parentAction == 'parentRoot') {
                    document.getElementsByName('parent_' + key)[0].value = document.form.parent.value;
                    document.getElementById('parentRootName' + key).innerHTML = document.getElementById('parentName').innerHTML
                }
            }
        }

        function cleareCat(elm) {
            elm.parentNode.getElementsByTagName('input')[0].value = 0;
            elm.parentNode.getElementsByTagName('b')[0].innerHTML = "(@lang('global.disabled'))";
            documentDirty = true;
        }
    </script>
@endpush
