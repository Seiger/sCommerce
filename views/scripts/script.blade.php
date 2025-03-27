@include('sCommerce::scripts.filter')
@if(sCommerce::config('basic.orders_on', 0) == 1)@include('sCommerce::scripts.cart')@endif
@if(sCommerce::config('basic.wishlist_on', 0) == 1)@include('sCommerce::scripts.wishlist')@endif
@include('sCommerce::scripts.global')
