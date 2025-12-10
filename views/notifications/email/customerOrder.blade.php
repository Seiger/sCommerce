@php($currencies = sCommerce::config('currencies', []))
@extends('notifications.email.layout')
@section('subject')
    {{evo()->getConfig('site_name')}} @lang('You Order #:orderId created successful', ['orderId' => $order->id ?? ''])
@endsection
@section('content')
    <h3>
        @lang('You Order #:orderId created successful', ['orderId' => $order->id ?? ''])
    </h3>
    <p>@lang('Created at'): {{$order->created_at}}</p>
    <p>@lang('Sum'): {{sCommerce::convertPrice($order->cost, $order->currency)}}@if(($currencies[$order->currency]['show'] ?? 0) == 0) {{$order->currency}}@endif</p>
    <p>@lang('Thank you for your order. Our manager will contact you shortly.')</p>

    <h3>@lang('Products in the Order')</h3>
    <table>
        <thead>
        <tr>
            <th>@lang('Product Name')</th>
            <th>@lang('Price')</th>
            <th>@lang('Quantity')</th>
            <th>@lang('Sum')</th>
        </tr>
        </thead>
        <tbody>
        @foreach($order->products as $product)
            @php($info = '')
            @foreach($product as $p)
                @if(is_array($p) && isset($p['title']))
                    @php($info .= '<b>' . htmlentities($p['title']) . ':</b> ' . htmlentities($p['label'] ?? '') . '<br>')
                @endif
            @endforeach
            <tr style="height: 42px;">
                <td>
                    <img src="{{$product['coverSrc']}}" style="width: 75px;">
                    <a href="{{$product['link']}}" target="_blank"><b>{{$product['title']}}</b></a>
                    @if(trim($info))<i class="fa fa-question-circle" data-tooltip="{!!$info!!}"></i>@endif
                </td>
                <td>{{$product['price']}}</td>
                <td>{{$product['quantity']}}</td>
                <td>{{sCommerce::convertPrice($product['quantity'] * sCommerce::convertPriceNumber($product['price'], $order->currency, $order->currency), $order->currency)}}@if(($currencies[$order->currency]['show'] ?? 0) == 0) {{$order->currency}}@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection