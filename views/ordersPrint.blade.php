@use(Seiger\sCommerce\Models\sOrder)
@php
    $currencies = \Seiger\sCommerce\Facades\sCommerce::config('currencies', []);
    $money = static fn($amount, $currency) => \Seiger\sCommerce\Facades\sCommerce::convertPrice($amount, $currency)
        . (($currencies[$currency]['show'] ?? 0) == 0 ? ' ' . $currency : '');
    $text = static fn($value) => is_scalar($value) ? trim(html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    $phone = static function ($value) use ($text): string {
        $value = $text($value);
        $digits = preg_replace('/\D/', '', $value);
        if (preg_match('/\A\+?[\d ()-]+\z/', $value)) {
            if (strlen($digits) === 10 && str_starts_with($digits, '0')) $digits = '38' . $digits;
            if (strlen($digits) === 12 && str_starts_with($digits, '380')) return sprintf('+38 (%s) %s-%s-%s', substr($digits, 2, 3), substr($digits, 5, 3), substr($digits, 8, 2), substr($digits, 10, 2));
        }
        return $value;
    };
    $seller = $text($seller ?? evo()->getConfig('site_name', ''));
    $deliveryMethods = $deliveryMethods ?? [];
@endphp
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@lang('sCommerce::global.bulk_print')</title>
<style>
* { box-sizing:border-box; }
body { margin:0; background:#e9ebee; color:#111; font:10pt/1.35 Arial,Helvetica,sans-serif; }
.print-controls { max-width:210mm; margin:16px auto; display:flex; justify-content:flex-end; }
.print-controls button { background:#fff; border:1px solid #b9bec6; border-radius:4px; color:#222; cursor:pointer; font:inherit; padding:8px 18px; }
article { background:#fff; width:210mm; margin:0 auto 20px; padding:12mm; box-shadow:0 1px 6px #0002; break-after:page; }
article:last-child { break-after:auto; }
h1 { border-bottom:2px solid #111; font-size:14pt; line-height:1.3; margin:0 0 12px; padding:0 0 8px; }
p { margin:0; overflow-wrap:anywhere; }
.parties { display:grid; grid-template-columns:92px minmax(0,1fr); gap:7px 12px; margin:0 0 12px; }
.parties dt,.parties dd { margin:0; overflow-wrap:anywhere; }.parties dd { font-weight:700; }.parties .contact { font-weight:400; }
.status-line { display:flex; flex-wrap:wrap; gap:4px 24px; margin:0 0 10px; font-size:9pt; }
.items { border-collapse:collapse; border:1px solid #111; width:100%; table-layout:fixed; font-size:9pt; }
.items col:nth-child(1) { width:4%; }.items col:nth-child(2) { width:11%; }.items col:nth-child(3) { width:44%; }
.items col:nth-child(4) { width:11%; }.items col:nth-child(5),.items col:nth-child(6) { width:15%; }
.items th,.items td { border:1px solid #333; padding:4px 5px; vertical-align:top; overflow-wrap:anywhere; }
.items th { background:#ededed; text-align:center; font-weight:700; vertical-align:middle; }
.items thead { display:table-header-group; }.items tr { break-inside:avoid; }
.items .index,.items .quantity { text-align:center; }.items .amount { text-align:right; font-variant-numeric:tabular-nums; }
.option { font-size:8pt; margin-top:2px; }.item-title { font-weight:400; }
.order-bottom { break-inside:avoid; }.totals { margin:9px 0 10px auto; width:48%; border-collapse:collapse; }
.totals th { text-align:right; padding:3px 10px 3px 0; }.totals td { text-align:right; font-weight:700; padding:3px 0; }
.totals .grand-total { font-size:11pt; }.positions { margin:8px 0; }
.fulfilment { border-top:1.5px solid #111; border-bottom:1.5px solid #111; padding:7px 0; margin-top:10px; }
.fulfilment p + p { margin-top:3px; }.comment { white-space:pre-wrap; }
.signature { display:flex; gap:24px; align-items:flex-end; margin-top:16px; }.signature-line { border-bottom:1px solid #111; flex:0 1 65mm; height:20px; }
@page { size:A4; margin:10mm; }
@media print {
    body { background:#fff; }.print-controls { display:none; }
    article { width:100%; margin:0; padding:3mm; box-shadow:none; }
    .items th { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
}
@media screen and (max-width:820px) { article { width:100%; padding:20px; }.print-controls { margin:12px; } }
</style></head><body>
<div class="print-controls"><button type="button" onclick="window.print()">@lang('sCommerce::global.bulk_print')</button></div>
@foreach($orders as $order)
    @php
        $user = is_array($order->user_info) ? $order->user_info : [];
        $delivery = is_array($order->delivery_info) ? $order->delivery_info : [];
        $method = $text($delivery['method'] ?? '');
        $details = is_array($delivery[$method] ?? null) ? $delivery[$method] : [];
        $deliveryName = $text($deliveryMethods[$method]['title'] ?? $delivery['title'] ?? $delivery['name'] ?? $details['title'] ?? $details['name'] ?? '');
        if ($deliveryName === '' && $method !== '') {
            $key = $method === 'arrangement' ? 'print_delivery_arrangement' : $method;
            $translated = __('sCommerce::global.' . $key);
            $deliveryName = $translated !== 'sCommerce::global.' . $key ? $translated : '';
        }
        // Only human-facing delivery fields belong on paper, never API identifiers or raw metadata.
        $address = [];
        foreach (['city', 'warehouse', 'address', 'street', 'house', 'building', 'apartment', 'postal_code'] as $key) {
            $value = $text($details[$key] ?? $delivery[$key] ?? '');
            if ($value !== '') $address[] = $value;
        }
        $tracking = '';
        foreach (['ttn', 'tracking_number', 'trackingNumber', 'waybill', 'declaration'] as $key) {
            $tracking = $text($details[$key] ?? $delivery[$key] ?? '');
            if ($tracking !== '') break;
        }
        $products = array_values(array_filter(is_array($order->products) ? $order->products : [], 'is_array'));
        $client = trim(implode(' ', array_filter(array_map(static fn($key) => $text($user[$key] ?? ''), ['first_name', 'middle_name', 'last_name']))));
    @endphp
    <article>
        <h1>{{__('sCommerce::global.print_order_heading', ['number' => $order->order_number ?? $order->id, 'date' => $order->created_at?->format('d.m.Y H:i') ?? '—'])}}</h1>
        <dl class="parties">
            @if($seller !== '')<dt>@lang('sCommerce::global.print_seller'):</dt><dd>{{$seller}}</dd>@endif
            <dt>@lang('sCommerce::global.print_customer'):</dt>
            <dd>{{$client ?: '—'}}@if($phone($user['phone'] ?? '') !== '')<span class="contact">, @lang('sCommerce::global.phone'): {{$phone($user['phone'] ?? '')}}</span>@endif</dd>
            @if($text($user['email'] ?? '') !== '')<dt>@lang('sCommerce::global.email'):</dt><dd class="contact">{{$text($user['email'])}}</dd>@endif
        </dl>
        <div class="status-line"><p>@lang('sCommerce::global.status'): <strong>{{sOrder::getOrderStatusName((int)$order->status)}}</strong></p><p>@lang('sCommerce::global.payment'): <strong>{{sOrder::getPaymentStatusName((int)$order->payment_status)}}</strong></p></div>
        <table class="items">
            <colgroup><col><col><col><col><col><col></colgroup>
            <thead><tr><th>№</th><th>@lang('sCommerce::global.sku')</th><th>@lang('sCommerce::global.products')</th><th>@lang('sCommerce::global.quantity')</th><th>@lang('sCommerce::global.price')</th><th>@lang('sCommerce::global.sum')</th></tr></thead>
            <tbody>
            @foreach($products as $index => $product)
                @php
                    $price = \Seiger\sCommerce\Facades\sCommerce::convertPriceNumber($text($product['price'] ?? 0), $order->currency, $order->currency);
                    $quantity = is_numeric($product['quantity'] ?? null) ? $product['quantity'] : 0;
                @endphp
                <tr><td class="index">{{$index + 1}}</td><td>{{$text($product['sku'] ?? '')}}</td><td class="item-title">{{$text($product['title'] ?? '')}}
                    @foreach($product as $option)
                        @if(is_array($option) && isset($option['title']))<p class="option">{{$text($option['title'])}}: {{$text($option['label'] ?? '')}}</p>@endif
                    @endforeach
                </td><td class="quantity">{{$quantity}}</td><td class="amount">{{$money($price, $order->currency)}}</td><td class="amount">{{$money($price * $quantity, $order->currency)}}</td></tr>
            @endforeach
            </tbody>
        </table>
        <div class="order-bottom">
            <table class="totals"><tbody>
                @if(is_numeric($delivery['cost'] ?? null) && (float)$delivery['cost'] > 0)<tr><th>@lang('sCommerce::global.shipping_cost'):</th><td>{{$money($delivery['cost'], $order->currency)}}</td></tr>@endif
                <tr class="grand-total"><th>@lang('sCommerce::global.total'):</th><td>{{$money($order->cost, $order->currency)}}</td></tr>
            </tbody></table>
            <p class="positions">{{__('sCommerce::global.print_positions', ['count' => count($products)])}}</p>
            @if($deliveryName !== '' || $address || $tracking !== '' || $text($order->comment) !== '')
                <div class="fulfilment">
                    @if($deliveryName !== '')<p><strong>@lang('sCommerce::global.delivery'):</strong> {{$deliveryName}}</p>@endif
                    @if($address)<p>{{implode(', ', array_unique($address))}}</p>@endif
                    @if($tracking !== '')<p><strong>@lang('sCommerce::global.export_tracking'):</strong> {{$tracking}}</p>@endif
                    @if($text($order->comment) !== '')<p class="comment"><strong>@lang('sCommerce::global.comment_to_order'):</strong> {{$text($order->comment)}}</p>@endif
                </div>
            @endif
            <div class="signature"><strong>@lang('sCommerce::global.print_manager')</strong><span class="signature-line"></span></div>
        </div>
    </article>
@endforeach
</body></html>
