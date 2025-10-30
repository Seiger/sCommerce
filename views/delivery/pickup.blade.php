{{--
    Pickup Delivery Widget

    This widget renders the checkout form for self-pickup delivery.

    To customize, copy to: views/delivery/pickup.blade.php

    Available variables:
    - $delivery : Delivery method info (name, title, description)
    - $checkout : Checkout data (user, cart, etc.)
    - $settings : Delivery settings from admin (locations, info, etc.)
--}}

@if(!empty($settings['locations']))
    <div class="pickup-locations __mb-2">
        <label class="__mb-1">
            <strong>{{__('sCommerce::global.pickup_locations')}}</strong>
        </label>

        @foreach($settings['locations'] as $index => $location)
            <label class="pickup-location-option">
                <input
                        type="radio"
                        name="delivery[{{$delivery['name']}}][location]"
                        value="{{$index}}"
                        @if($loop->first) checked @endif
                        required
                />
                <span>{{$location['address']}}</span>
            </label>
        @endforeach
    </div>
@else
    <p class="text-muted">{{__('sCommerce::global.pickup_location_info')}}</p>
@endif
