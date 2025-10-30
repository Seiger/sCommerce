{{--
    Courier Delivery Widget

    This widget renders the checkout form fields for courier delivery.

    To customize, copy to: views/delivery/courier.blade.php

    Available variables:
    - $delivery : Delivery method info (name, title, description)
    - $checkout : Checkout data (user, cart, etc.)
    - $settings : Delivery settings from admin (cities, info, etc.)
--}}

<label class="fluid-placeholder __mb-1">
    <input
        type="text"
        name="delivery[{{$delivery['name']}}][city]"
        value="{{$checkout['user']['address']['city'] ?? ''}}"
        placeholder=" "
        required
    />
    <span>{{__('sCommerce::global.city')}}</span>
</label>

@if(!empty($settings['cities']))
    <ul class="list-quick __mb-1">
        @foreach($settings['cities'] as $city)
            <li><span class="__more">{{$city['city']}}</span></li>
        @endforeach
    </ul>
@endif

<label class="fluid-placeholder __mb-1">
    <input
        type="text"
        name="delivery[{{$delivery['name']}}][street]"
        value="{{$checkout['user']['address']['street'] ?? ''}}"
        placeholder=" "
        required
    />
    <span>{{__('sCommerce::global.street')}}</span>
</label>

<div class="ch-x2 __mb-1">
    <label class="fluid-placeholder">
        <input
            type="text"
            name="delivery[{{$delivery['name']}}][build]"
            value="{{$checkout['user']['address']['build'] ?? ''}}"
            placeholder=" "
        />
        <span>{{__('sCommerce::global.building')}}</span>
    </label>

    <label class="fluid-placeholder">
        <input
            type="text"
            name="delivery[{{$delivery['name']}}][room]"
            value="{{$checkout['user']['address']['room'] ?? ''}}"
            placeholder=" "
        />
        <span>{{__('sCommerce::global.apartment')}}</span>
    </label>
</div>

<div class="receiver __mb-2" x-data="{ otherReceiver: false }">
    <label>
        <input
            type="radio"
            name="delivery[{{$delivery['name']}}][receiver]"
            value="self"
            x-model="otherReceiver"
            checked
        >
        {{__('sCommerce::global.i_am_receiver')}}
    </label>

    <div class="__mb-s1">
        <input
            type="radio"
            name="delivery[{{$delivery['name']}}][receiver]"
            id="receiver-other-{{$delivery['name']}}"
            value="other"
            x-model="otherReceiver"
        >
        <label for="receiver-other-{{$delivery['name']}}">
            {{__('sCommerce::global.other_receiver')}}
        </label>

        <div class="receiver-other" x-show="otherReceiver" x-transition>
            <label class="fluid-placeholder">
                <input
                    type="text"
                    name="delivery[{{$delivery['name']}}][first_name]"
                    placeholder=" "
                    :required="otherReceiver"
                />
                <span>{{__('sCommerce::global.first_name')}}</span>
            </label>

            <label class="fluid-placeholder">
                <input
                    type="text"
                    name="delivery[{{$delivery['name']}}][last_name]"
                    placeholder=" "
                    :required="otherReceiver"
                />
                <span>{{__('sCommerce::global.last_name')}}</span>
            </label>

            <label class="fluid-placeholder">
                <input
                    type="tel"
                    name="delivery[{{$delivery['name']}}][phone]"
                    placeholder=" "
                    data-mask
                    :required="otherReceiver"
                />
                <span>{{__('sCommerce::global.phone')}}</span>
            </label>
        </div>
    </div>
</div>

