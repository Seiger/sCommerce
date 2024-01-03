<h3>{{(request()->i ?? 0) == 0 ? __('sCommerce::global.new_product') : ($product->pagetitle ?? __('sCommerce::global.no_text'))}}</h3>
<div class="split my-3"></div>

<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=productSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=product&i={{request()->i ?? 0}}" />
    <input type="hidden" name="product" value="{{request()->i ?? 0}}" />
    <div class="row form-row">
        <div class="row-col col-lg-3 col-md-6 col-12">
            <div class="row form-row form-row-checkbox">
                <div class="col-auto col-title">
                    <label for="publishedcheck" class="warning">@lang('sCommerce::global.visibility')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.published_help')"></i>
                </div>
                <div class="col">
                    <input type="checkbox" id="publishedcheck" class="form-checkbox form-control" name="publishedcheck" value="" onchange="documentDirty=true;" onclick="changestate(document.form.published);" @if(isset($product->published) && $product->published) checked @endif>
                    <input type="hidden" id="published" name="published" value="{{$product->published ?? 0}}" onchange="documentDirty=true;">
                    &emsp;<i class="fa fa-eye" data-tooltip="@lang('sCommerce::global.views')"> <b>{{$product->views ?? 0}}</b></i>
                    @if(evo()->getConfig('scom_rating_on', 1) == 1)&emsp;<i class="fa fa-star" data-tooltip="@lang('sCommerce::global.rating')"> <b>{{$product->rating ?? 0}}</b></i>@endif
                    @if(evo()->getConfig('scom_rating_on', 1) == 1)&emsp;<i class="fas fa-warehouse" data-tooltip="@lang('sCommerce::global.quantity')"> <b>{{$product->quantity ?? 0}}</b></i>@endif
                </div>
            </div>
        </div>
        <div class="row-col col-lg-3 col-md-6 col-12">
            <div class="row form-row">
                <div class="col-auto col-title">
                    <label for="availability" class="warning">@lang('sCommerce::global.availability')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.availability_help')"></i>
                </div>
                <div class="col">
                    <select id="availability" class="form-control" name="availability" onchange="documentDirty=true;">
                        @foreach(\Seiger\sCommerce\Models\sProduct::listAvailability() as $key => $title)
                            <option value="{{$key}}" @if($key == ($product->availability ?? 0)) selected @endif>{{$title}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="row-col col-lg-3 col-md-6 col-12">
            <div class="row form-row">
                <div class="col-auto col-title">
                    <label for="parent" class="warning">@lang('sCommerce::global.category')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.category_help')"></i>
                </div>
                <div class="col">
                    <div>
                        @php($parentlookup = false)
                        @if(($product->parent ?? evo()->getConfig('scom_catalog_root', 0)) == 0)
                            @php($parentname = evo()->getConfig('site_name'))
                        @else
                            @php($parentlookup = ($product->parent ?? evo()->getConfig('scom_catalog_root', 1)))
                        @endif
                        @if($parentlookup !== false && is_numeric($parentlookup))
                            @php($parentname = \EvolutionCMS\Models\SiteContent::withTrashed()->select('pagetitle')->find($parentlookup)->pagetitle)
                            @if(!$parentname)
                                @php(evo()->webAlertAndQuit($_lang["error_no_parent"]))
                            @endif
                        @endif
                        <i id="plock" class="fa fa-folder" onclick="enableParentSelection(!allowParentSelection);"></i>
                        <b id="parentName">{{$parentlookup}} ({{entities($parentname)}})</b>
                        <input type="hidden" name="parent" value="{{($product->parent ?? evo()->getConfig('scom_catalog_root', 1))}}" onchange="documentDirty=true;" />
                    </div>
                </div>
            </div>
        </div>
        <div class="row-col col-lg-3 col-md-6 col-12">
            <div class="row form-row">
                <div class="col-auto col-title">
                    <label for="sku" class="warning">@lang('sCommerce::global.sku')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.sku_help')"></i>
                </div>
                <div class="col">
                    <input id="sku" class="form-control" name="sku" value="{{$product->sku ?? ''}}" onblur="documentDirty=true;">
                </div>
            </div>
        </div>
        <div class="row-col col-lg-3 col-md-6 col-12">
            <div class="row form-row">
                <div class="col-auto col-title">
                    <label for="price" class="warning">@lang('sCommerce::global.price')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.sku_help')"></i>
                </div>
                <div class="col">
                    <input id="price" class="form-control" name="price_regular" value="{{$product->price_regular ?? ''}}" onblur="documentDirty=true;">
                </div>
            </div>
        </div>
        {{--@if(evo()->getConfig('sart_features_on', 1) == 1)
            <div class="row-col col-lg-6 col-md-6 col-12">
                <div class="row form-row">
                    <div class="col-auto col-title">
                        <label for="features" class="warning">@lang('sArticles::global.features')</label>
                        <i class="fa fa-question-circle" data-tooltip="@lang('sArticles::global.features_article_help')"></i>
                    </div>
                    <div class="col">
                        @php($product->feature = $product->features->pluck('fid')->toArray())
                        <select id="features" class="form-control select2" name="features[]" multiple onchange="documentDirty=true;">
                            @foreach($features as $feature)
                                <option value="{{$feature->fid}}" @if(in_array($feature->fid, $product->feature)) selected @endif>{{$feature->base}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        @endif--}}
        {{--<div class="row-col col-lg-6 col-md-6 col-12">
            <div class="row form-row">
                <div class="col-auto col-title">
                    <label for="tags" class="warning">@lang('sArticles::global.main_tag_article')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sArticles::global.tags_article_help')"></i>
                </div>
                <div class="col">
                    @php($product->tag = $product->tags()->pluck('tagid')->toArray())
                    <select id="type" class="form-control select2" name="tags[]" onchange="documentDirty=true;">
                        <option></option>
                        @foreach($tags as $tag)
                            <option value="{{$tag->tagid}}" @if($tag->tagid == $product->tag[0]) selected @endif>{{$tag->base}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>--}}
        {{--<div class="row-col col-lg-6 col-md-6 col-12">
            <div class="row form-row">
                <div class="col-auto col-title">
                    <label for="relevants" class="warning">@lang('sArticles::global.relevant_articles')</label>
                </div>
                <div class="col">
                    @php($productRelevants = data_is_json($product->relevants ?? '', true) ?: [])
                    <select id="relevants" class="form-control select2" name="relevants[]" multiple onchange="documentDirty=true;">
                        @foreach(sArticles::all(\Seiger\sArticles\sArticles::ALL_PAGES) as $item)
                            @if(($product->id ?? 0) != $item->id)
                                <option value="{{$item->id}}" @if(in_array($item->id, $productRelevants)) selected @endif>{{$item->pagetitle}}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
        </div>--}}
        <div class="row-col col-lg-6 col-md-6 col-12">
            <div class="row form-row form-row-image">
                <div class="col-auto col-title">
                    <label for="cover" class="warning">@lang('sArticles::global.image')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sArticles::global.image_help')"></i>
                </div>
                <div class="col">
                    <input type="text" id="cover" class="form-control" name="cover" value="{{$product->cover ?? ''}}" onchange="documentDirty=true;">
                    <input class="form-control" type="button" value="@lang('global.insert')" onclick="BrowseServer('cover')">
                    <div class="col-12">
                        <div id="image_for_cover" class="image_for_field" data-image="{{$product->coverSrc ?? ''}}" onclick="BrowseServer('cover')" style="background-image: url('{{$product->coverSrc ?? ''}}');"></div>
                        <script>document.getElementById('cover').addEventListener('change', evoRenderImageCheck, false);</script>
                    </div>
                </div>
            </div>
        </div>
        <div class="row-col col-lg-6 col-md-6 col-12">
            <div class="row form-row">
                <div class="col-auto col-title-7">
                    <label for="alias" class="warning">@lang('global.resource_alias')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('global.resource_alias_help')"></i>
                </div>
                <div class="input-group col">
                    <input type="text" id="alias" class="form-control" name="alias" maxlength="255" value="{{$product->alias ?? ''}}" onchange="documentDirty=true;" spellcheck="true">
                    <a id="preview" href="{{$product->link ?? '/'}}" class="btn btn-outline-secondary form-control" type="button" target="_blank">@lang('global.preview')</a>
                </div>
            </div>
        </div>
    </div>
</form>
<div class="split my-3"></div>

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!$url!!}">
                <i class="fa fa-times-circle"></i><span>@lang('sArticles::global.to_list_articles')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fa fa-floppy-o"></i>
                <span>@lang('global.save')</span>
            </a>
            <a id="Button3" class="btn btn-danger" data-href="{{$url}}&get=articleDelete&i={{$product->id}}" data-delete="{{$product->id}}" data-name="{{$product->pagetitle}}">
                <i class="fa fa-trash"></i> <span>@lang('global.remove')</span>
            </a>
        </div>
    </div>
@endpush
