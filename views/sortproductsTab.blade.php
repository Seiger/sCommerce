<h3>@lang('sCommerce::global.sortproducts_help') <b>{{$catName}}</b></h3>
<div class="split my-3"></div>
<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=sortproductssave" onsubmit="documentDirty=false;">
    <input type="hidden" name="cat" value="{{$cat}}">
    <div class="table-responsive seiger__module-table">
        <table class="table table-condensed table-hover sectionTrans scom-table sortable">
            <thead>
            <tr>
                <th></th>
                @if (sCommerce::config('products.show_field_id', 1) == 1)
                    <th>
                        <button class="seiger-sort-btn" style="padding:0;border:none;background:transparent;">ID</button>
                    </th>
                @endif
                @if (sCommerce::config('products.show_field_sku', 1) == 1)
                    <th>
                        <button class="seiger-sort-btn" style="padding:0;border:none;background:transparent;">@lang('sCommerce::global.sku')</button>
                    </th>
                @endif
                <th>
                    <button class="seiger-sort-btn" style="padding:0;border:none;background:transparent;">@lang('sCommerce::global.product_name')</button>
                </th>
                @if (sCommerce::config('products.show_field_price', 1) == 1)
                    <th>
                        <button class="seiger-sort-btn" style="padding:0;border:none;background:transparent;">@lang('sCommerce::global.price')</button>
                    </th>
                @endif
                @if (sCommerce::config('products.show_field_price_special', 1) == 1)
                    <th>
                        <button class="seiger-sort-btn" style="padding:0;border:none;background:transparent;">@lang('sCommerce::global.price_special')</button>
                    </th>
                @endif
                @if (sCommerce::config('products.show_field_price_opt', 1) == 1)
                    <th>
                        <button class="seiger-sort-btn" style="padding:0;border:none;background:transparent;">@lang('sCommerce::global.price_opt')</button>
                    </th>
                @endif
                @if (sCommerce::config('products.show_field_price_opt_special', 1) == 1)
                    <th>
                        <button class="seiger-sort-btn" style="padding:0;border:none;background:transparent;">@lang('sCommerce::global.price_opt_special')</button>
                    </th>
                @endif
                @if (sCommerce::config('products.show_field_quantity', 1) == 1)
                    <th>
                        <button class="seiger-sort-btn" style="padding:0;border:none;background:transparent;">@lang('sCommerce::global.quantity')</button>
                    </th>
                @endif
                @if (sCommerce::config('products.show_field_availability', 1) == 1)
                    <th>
                        <button class="seiger-sort-btn" style="padding:0;border:none;background:transparent;">@lang('sCommerce::global.availability')</button>
                    </th>
                @endif
                @if (sCommerce::config('products.show_field_visibility', 1) == 1)
                    <th>
                        <button class="seiger-sort-btn" style="padding:0;border:none;background:transparent;">@lang('sCommerce::global.visibility')</button>
                    </th>
                @endif
                @if (sCommerce::config('product.views_on', 1) == 1 && sCommerce::config('products.show_field_views', 1) == 1)
                    <th>
                        <button class="seiger-sort-btn" style="padding:0;border:none;background:transparent;">@lang('sCommerce::global.views')</button>
                    </th>
                @endif
                @if (sCommerce::config('product.rating_on', 1) == 1 && sCommerce::config('products.show_field_rating', 1) == 1)
                    <th>
                        <button class="seiger-sort-btn" style="padding:0;border:none;background:transparent;">@lang('sCommerce::global.rating')</button>
                    </th>
                @endif
                @if(count(sCommerce::config('products.additional_fields', [])))
                    @foreach(sCommerce::config('products.additional_fields', []) as $field)
                        <th>
                            <button class="seiger-sort-btn" style="padding:0;border:none;background:transparent;">{{sCommerce::config('constructor.main_product.'.$field.'.pagetitle', $field)}}</button>
                        </th>
                    @endforeach
                @endif
            </tr>
            </thead>
            <tbody>
            @foreach($items as $item)
                <tr style="height: 42px;" id="product-{{$item->id}}">
                    <td>
                        <i class="fa fa-sort" title="@lang('sCommerce::global.sort_order')"></i>
                        <input type="hidden" name="products[]" value="{{$item->id}}">
                    </td>
                    @if (sCommerce::config('products.show_field_id', 1) == 1)
                        <td>{{$item->id}}</td>
                    @endif
                    @if (sCommerce::config('products.show_field_sku', 1) == 1)
                        <td>{{$item->sku}}</td>
                    @endif
                    <td>
                        <img src="{{$item->coverSrc}}" alt="{{$item->coverSrc}}" class="product-thumbnail">
                        <a href="{{$item->link}}" target="_blank"><b>{{$item->pagetitle ?? __('sCommerce::global.no_text')}}</b></a>
                    </td>
                    @if (sCommerce::config('products.show_field_price', 1) == 1)
                        <td>
                            @if(sCommerce::config('basic.price_symbol', 1))
                                {{$item->priceTo($item->currency)}}
                            @else
                                {{sCommerce::getCurrencies([$item->currency])->first()['symbol']}}{{$item->priceTo($item->currency)}}
                            @endif
                        </td>
                    @endif
                    @if (sCommerce::config('products.show_field_price_special', 1) == 1)
                        <td>{{$item->price_special}}</td>
                    @endif
                    @if (sCommerce::config('products.show_field_price_opt', 1) == 1)
                        <td>{{$item->price_opt_regular}}</td>
                    @endif
                    @if (sCommerce::config('products.show_field_price_opt_special', 1) == 1)
                        <td>{{$item->price_opt_special}}</td>
                    @endif
                    @if (sCommerce::config('products.show_field_quantity', 1) == 1)
                        <td>{{$item->quantity}}</td>
                    @endif
                    @if (sCommerce::config('products.show_field_availability', 1) == 1)
                        <td>{{$item->availability}}</td>
                    @endif
                    @if (sCommerce::config('products.show_field_visibility', 1) == 1)
                        <td>
                            @if($item->published)
                                <span class="badge badge-success">@lang('global.page_data_published')</span>
                            @else
                                <span class="badge badge-dark">@lang('global.page_data_unpublished')</span>
                            @endif
                        </td>
                    @endif
                    @if (sCommerce::config('product.views_on', 1) == 1 && sCommerce::config('products.show_field_views', 1) == 1)
                        <td>{{$item->views}}</td>
                    @endif
                    @if (sCommerce::config('product.rating_on', 1) == 1 && sCommerce::config('products.show_field_rating', 1) == 1)
                        <td>{{$item->rating}}</td>
                    @endif
                    @if(count(sCommerce::config('products.additional_fields', [])))
                        @foreach(sCommerce::config('products.additional_fields', []) as $field)
                            <td>{{ $item->{$field} }}</td>
                        @endforeach
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</form>
@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!$moduleUrl!!}">
                <i class="fa fa-times-circle"></i><span>@lang('sCommerce::global.to_list_products')</span>
            </a>
            @if($cat > 0)
                <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                    <i class="fa fa-floppy-o"></i>
                    <span>@lang('global.save')</span>
                </a>
            @endif
        </div>
    </div>
@endpush
