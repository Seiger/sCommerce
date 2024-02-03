<h3>{{(int)request()->input('i', 0) == 0 ? __('sCommerce::global.new_product') : ($item->pagetitle ?? __('sCommerce::global.no_text'))}}</h3>
<div class="split my-3"></div>

<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=productSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=product&i={{(int)request()->input('i', 0)}}" />
    <input type="hidden" name="i" value="{{(int)request()->input('i', 0)}}" />
    <div class="row form-row">
        <div class="row-col col-lg-3 col-md-6 col-12">
            <div class="row form-row form-row-checkbox">
                <div class="col-auto col-title">
                    <label for="publishedcheck" class="warning">@lang('sCommerce::global.visibility')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.published_help')"></i>
                </div>
                <div class="col">
                    <input type="checkbox" id="publishedcheck" class="form-checkbox form-control" name="publishedcheck" value="" onchange="documentDirty=true;" onclick="changestate(document.form.published);" @if(isset($item->published) && $item->published) checked @endif>
                    <input type="hidden" id="published" name="published" value="{{$item->published ?? 0}}" onchange="documentDirty=true;">
                    @if(sCommerce::config('product.views_on', 1) == 1)&emsp;<i class="fa fa-eye" data-tooltip="@lang('sCommerce::global.views')"> <b>{{$item->views ?? 0}}</b></i>@endif
                    @if(sCommerce::config('product.rating_on', 1) == 1)&emsp;<i class="fa fa-star" data-tooltip="@lang('sCommerce::global.rating')"> <b>{{$item->rating ?? 5}}</b></i>@endif
                    @if(sCommerce::config('product.quantity_on', 1) == 1)&emsp;<i class="fas fa-warehouse" data-tooltip="@lang('sCommerce::global.quantity')"> <b>{{$item->quantity ?? 0}}</b></i>@endif
                </div>
            </div>
        </div>
        @if(sCommerce::config('product.show_field_availability', 1) == 1)
            <div class="row-col col-lg-3 col-md-6 col-12">
                <div class="row form-row">
                    <div class="col-auto col-title">
                        <label for="availability" class="warning">@lang('sCommerce::global.availability')</label>
                        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.availability_help')"></i>
                    </div>
                    <div class="col">
                        <select id="availability" class="form-control" name="availability" onchange="documentDirty=true;">
                            @foreach(\Seiger\sCommerce\Models\sProduct::listAvailability() as $key => $title)
                                <option value="{{$key}}" @if($key == ($item->availability ?? 0)) selected @endif>{{$title}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        @endif
        @if(sCommerce::config('product.show_field_sku', 1) == 1)
            <div class="row-col col-lg-3 col-md-6 col-12">
                <div class="row form-row">
                    <div class="col-auto col-title">
                        <label for="sku" class="warning">@lang('sCommerce::global.sku')</label>
                        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.sku_help')"></i>
                    </div>
                    <div class="col">
                        <input id="sku" class="form-control" name="sku" value="{{$item->sku ?? ''}}" onblur="documentDirty=true;">
                    </div>
                </div>
            </div>
        @endif
        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row">
                <div class="col-auto col-title-7">
                    <label for="alias" class="warning">@lang('global.resource_alias')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('global.resource_alias_help')"></i>
                </div>
                <div class="input-group col">
                    <input type="text" id="alias" class="form-control" name="alias" maxlength="512" value="{{$item->alias ?? 'new-product'}}" onchange="documentDirty=true;" spellcheck="true">
                    <a id="preview" href="{{$item->link ?? '/'}}" class="btn btn-outline-secondary form-control" type="button" target="_blank">@lang('global.preview')</a>
                </div>
            </div>
        </div>
        @if(sCommerce::config('product.show_field_price', 1) == 1)
            <div class="row-col col-lg-3 col-md-3 col-12">
                <div class="row form-row">
                    <div class="col-auto col-title">
                        <label for="price" class="warning">@lang('sCommerce::global.price')</label>
                        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_help')"></i>
                    </div>
                    <div class="col">
                        <input id="price" class="form-control" name="price_regular" value="{{$item->price_regular ?? ''}}" onblur="documentDirty=true;">
                    </div>
                </div>
            </div>
        @endif
        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row">
                <div class="col-auto col-title">
                    <label for="parent" class="warning">@lang('sCommerce::global.category')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.categories_product_help')"></i>
                </div>
                <div class="col">
                    <div>
                        @php($parentlookup = false)
                        @if(($item->category ?? sCommerce::config('basic.catalog_root', 0)) == 0)
                            @php($parentname = evo()->getConfig('site_name'))
                        @else
                            @php($parentlookup = ($item->category ?? sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1))))
                        @endif
                        @if($parentlookup !== false && is_numeric($parentlookup))
                            @php($parentname = \EvolutionCMS\Models\SiteContent::withTrashed()->select('pagetitle')->find($parentlookup)->pagetitle)
                            @if(!$parentname)
                                @php(evo()->webAlertAndQuit($_lang["error_no_parent"]))
                            @endif
                        @endif
                        <i id="plock" class="fa fa-folder" onclick="enableParentSelection(!allowParentSelection);"></i>
                        <b id="parentName">{{$parentlookup}} ({{entities($parentname)}})</b>
                        <input type="hidden" name="parent" value="{{($item->category ?? sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1)))}}" onchange="documentDirty=true;" />
                    </div>
                </div>
            </div>
        </div>
        @if(sCommerce::config('product.show_field_categories', 1) == 1)
            <div class="row-col col-lg-6 col-md-6 col-12">
                <div class="row form-row">
                    <div class="col-auto col-title">
                        <label for="categories" class="warning">@lang('sCommerce::global.categories')</label>
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
    </div>
</form>
<div class="split my-3"></div>
@if($item->id)
    <div class="row-col col-12">
        <div class="row form-row">
            <div class="col-auto col-title">
                <label class="warning">@lang('sCommerce::global.gallery')</label>
                <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.gallery_help')"></i>
            </div>
            <div class="col">
                {!! sGallery::initialise('section', 'product', 'i') !!}
            </div>
        </div>
    </div>
    <div class="split my-3"></div>
@endif

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!$moduleUrl!!}">
                <i class="fa fa-times-circle"></i><span>@lang('sCommerce::global.to_list_products')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fa fa-floppy-o"></i>
                <span>@lang('global.save')</span>
            </a>
            <a id="Button3" class="btn btn-danger" data-href="{!!$moduleUrl!!}&get=productDelete&i={{$item->id}}" data-delete="{{$item->id}}" data-name="{{$item->pagetitle}}">
                <i class="fa fa-trash"></i> <span>@lang('global.remove')</span>
            </a>
        </div>
    </div>
@endpush
