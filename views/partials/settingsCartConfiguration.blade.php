@php use Seiger\sCommerce\Models\sAttribute; @endphp
<div class="scommerce-checkout-settings-field scommerce-checkout-settings-field--wide">
    <label for="cart__product_attributes_display">
        @lang('sCommerce::global.product_attributes_to_display')
    </label>
    <select id="cart__product_attributes_display" class="form-control select2" name="cart__product_attributes_display[]" multiple onchange="documentDirty=true;">
        @foreach(sAttribute::all() as $item)
            <option value="{{$item->alias}}" @if(in_array($item->alias, sCommerce::config('cart.product_attributes_display', []))) selected @endif>{{$item->text->pagetitle}}</option>
        @endforeach
    </select>
    <small class="scommerce-checkout-settings-help">@lang('sCommerce::global.product_attributes_to_display_help')</small>
</div>
