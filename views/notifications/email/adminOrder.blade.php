@php($currencies = sCommerce::config('currencies', []))
@extends('notifications.email.layout')
@section('subject')
    {{evo()->getConfig('site_name')}} @lang('Order created by user') {{implode(' ', [trim($order->user_info['name'] ?? ''), trim($order->user_info['phone'] ?? ''), trim($order->user_info['email'] ?? '')])}}
@endsection
@section('content')
    <h3>
        @lang('Created new Order')
        <a href="{{EVO_MANAGER_URL}}{{sCommerce::moduleUrl()}}&get=order&i={{$order->id ?? 0}}" target="_blank">
            <strong>#{{$order->id ?? ''}}</strong>
        </a>
    </h3>
    <p>@lang('Created at'): {{$order->created_at}}</p>
    <p>@lang('Sum'): {{sCommerce::convertPrice($order->cost, $order->currency)}}@if(($currencies[$order->currency]['show'] ?? 0) == 0) {{$order->currency}}@endif</p>
    <p>@lang('Customer Name'): {{$order->user_info['name'] ?? ''}}</p>
    <p>@lang('E-Mail'): {{$order->user_info['email'] ?? ''}}</p>
    <p>@lang('Phone number'): {{$order->user_info['phone'] ?? ''}}</p>
    <p>@lang('Message'):</p><p>{{$order->comment ?? ''}}</p>

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