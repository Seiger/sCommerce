@php use Seiger\sCommerce\Models\sProduct; @endphp
<tr style="height: 42px;" id="product-{{$item->id}}">
    @if (sCommerce::config('products.show_field_id', 1) == 1)
        <td>{{$item->id}}</td>
    @endif
    @if (sCommerce::config('products.show_field_sku', 1) && sCommerce::config('product.show_field_sku', 1))
        <td>{{$item->sku}}</td>
    @endif
    <td>
        <img src="{{$item->coverSrc}}" alt="{{$item->coverSrc}}" class="product-thumbnail">
        <a href="{{$item->link}}" target="_blank"><b>{{$item->pagetitle ?? __('sCommerce::global.no_text')}}</b></a>
    </td>
    @if(count($parameters))
        @foreach($parameters as $parameter)
            <th>{{$item->attribute($parameter)?->label ?? ''}}</th>
        @endforeach
    @endif
    @if (sCommerce::config('products.show_field_price', 1) && sCommerce::config('product.show_field_price', 1))
        <td>
            @if(sCommerce::config('basic.price_symbol', 1))
                {{$item->priceTo($item->currency)}}
            @else
                {{sCommerce::getCurrencies([$item->currency])->first()['symbol']}}{{$item->priceTo($item->currency)}}
            @endif
        </td>
    @endif
    @if (sCommerce::config('products.show_field_price_special', 1) && sCommerce::config('product.show_field_price_special', 1))
        <td>
            @if(sCommerce::config('basic.price_symbol', 1))
                {{$item->specialPriceTo($item->currency)}}
            @else
                {{sCommerce::getCurrencies([$item->currency])->first()['symbol']}}{{$item->specialPriceTo($item->currency)}}
            @endif
        </td>
    @endif
    @if (sCommerce::config('products.show_field_availability', 1) && sCommerce::config('product.show_field_availability', 1))
        <td>{{sProduct::listAvailability()[$item->availability]}}</td>
    @endif
    <td style="text-align:center;">
        <div class="btn-group">
            @if($add ?? false)
                <span class="btn btn-outline-primary addProduct">
                    <i class="fa fa-plus"></i> <span class="addProduct">@lang('sCommerce::global.add')</span>
                </span>
            @else
                <input type="hidden" name="modifications[]" value="{{$item->id}}">
                @if($item->id != ($disableId ?? 0))
                    <a href="{{sCommerce::moduleUrl()}}&get=product&i={{$item->id}}{{request()->has('page') ? '&page=' . request()->page : ''}}" class="btn btn-outline-success">
                        <i class="fa fa-pencil"></i> <span>@lang('global.edit')</span>
                    </a>
                    <span class="btn btn-outline-danger" onclick="this.closest('tr').remove(); return false;">
                        <i class="fa fa-trash"></i> <span>@lang('global.delete')</span>
                    </span>
                @endif
            @endif
        </div>
    </td>
</tr>
