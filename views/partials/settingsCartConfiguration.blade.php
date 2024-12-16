@php use Seiger\sCommerce\Models\sAttribute; @endphp
<h3 class="sectionTrans">
    @lang('sCommerce::global.managing_cart_functionality')
</h3>
<div class="row form-row">
    <div class="col-auto">
        <label for="cart__product_attributes_display">@lang('sCommerce::global.product_attributes_to_display')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.product_attributes_to_display_help')"></i>
    </div>
    <div class="col">
        <select id="cart__product_attributes_display" class="form-control select2" name="cart__product_attributes_display[]" multiple onchange="documentDirty=true;">
            @foreach(sAttribute::all() as $item)
                <option value="{{$item->alias}}" @if(in_array($item->alias, sCommerce::config('cart.product_attributes_display', []))) selected @endif>{{$item->text->pagetitle}}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="split my-3"></div>
