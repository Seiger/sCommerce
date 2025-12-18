@php use Seiger\sCommerce\Models\sOrder; @endphp
<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!sCommerce::moduleUrl()!!}&get=orderSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=order&i={{(int)request()->input('i', 0)}}"/>
    <input type="hidden" name="i" value="{{(int)request()->input('i', 0)}}"/>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md">
                <h3>
                    <b>#{{$item->id}}</b>
                    @if($item->is_quick) <span class="badge bg-super bg-seigerit"><i class="fas fa-clock"></i> @lang('sCommerce::global.one_click')</span>@endif
                    <b>{{implode(' ', array_diff([$item->user_info['first_name'] ?? '', $item->user_info['middle_name'] ?? '', $item->user_info['last_name'] ?? ''], ['']))}} {{$item->user_info['phone']}}</b>
                </h3>
                @if($domains)
                    <p>
                        <span @class(['badge', 'domain-badge']) @style(['font-size:100%;', 'line-height:inherit;', 'background-color:'.($domains[$item->domain]->site_color ?? '#60a5fa')])>
                            {{$domains[$item->domain]->domain}}
                        </span>
                    </p>
                @endif
                <p>
                    <strong>@lang('sCommerce::global.created'):</strong> {{$item->created_at}}
                    <span @class(['badge', 'bg-active' => in_array($item->status, $unprocessedes), 'bg-progress' => in_array($item->status, $workings), 'bg-disactive' => in_array($item->status, $completeds)])>
                        {{sOrder::getOrderStatusName($item->status)}}
                    </span>
                </p>
                <p>
                    <strong>@lang('sCommerce::global.sum'):</strong> {{sCommerce::convertPrice($item->cost, $item->currency)}}
                    <span @class(['badge', 'bg-paid' => $item->payment_status == sOrder::PAYMENT_STATUS_PAID, 'bg-pending' => $item->payment_status != sOrder::PAYMENT_STATUS_PAID])>
                        {{sOrder::getPaymentStatusName($item->payment_status)}}
                    </span>
                </p>
                @if(trim($item->comment))
                    <p><strong>@lang('sCommerce::global.comment_to_order'):</strong> {{$item->comment}}</p>
                @endif
                <p>
                    <strong>@lang('sCommerce::global.order_status'):</strong>
                    <select name="status" @style(['display:inline-block', 'width:auto', 'margin-left:10px', 'padding:5px'])>
                        @foreach(sOrder::listOrderStatuses() as $sId => $sName)
                            <option value="{{$sId}}" @if($sId == $item->status) selected @endif>{{$sName}}</option>
                        @endforeach
                    </select>
                </p>
                <div class="split my-3"></div>
            </div>
            <div class="col-md">
                <h3>@lang('sCommerce::global.payment_information')</h3>
                <p></p>
                <p><strong>@lang('sCommerce::global.order_currency'):</strong> {{$item->currency}}</p>
                <p><strong>@lang('sCommerce::global.order_cost'):</strong> {{$item->cost}}</p>
                <p><strong>@lang('sCommerce::global.payment_name'):</strong>@if(!$payment) @lang('sCommerce::global.not_selected_or_unknown') @else {{$payment['title']}}@endif</p>
                <p>
                    <strong>@lang('sCommerce::global.payment_status'):</strong>
                    <select name="payment_status" @style(['display:inline-block', 'width:auto', 'margin-left:10px', 'padding:5px'])>
                        @foreach(sOrder::listPaymentStatuses() as $psId => $psName)
                            <option value="{{$psId}}" @if($psId == $item->payment_status) selected @endif>{{$psName}}</option>
                        @endforeach
                    </select>
                </p>
                <div class="split my-3"></div>
            </div>
            <div class="col-md">
                <h3>@lang('sCommerce::global.shipping_information')</h3>
                <p><strong>@lang('sCommerce::global.delivery_name'):</strong>@if(!$delivery) @lang('sCommerce::global.not_selected_or_unknown') @else {{$delivery['title']}}@endif</p>
                @if($delivery)
                    <p><strong>@lang('sCommerce::global.shipping_cost'):</strong> {{sCommerce::convertPrice(floatval($item->delivery_info['cost'] ?? 0), $item->currency)}}</p>
                    @if(isset($item->delivery_info[$item->delivery_info['method']]) && is_array($item->delivery_info[$item->delivery_info['method']]))
                        @foreach($item->delivery_info[$item->delivery_info['method']] as $key => $value)
                            <p><strong>{{$key}}:</strong> {{$value}}</p>
                        @endforeach
                    @endif
                @endif
                <div class="split my-3"></div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <h3>@lang('sCommerce::global.customer_information')</h3>
                <p>
                    <strong>@lang('global.user_full_name'):</strong>
                    @if((int)$item->user_info['id'] > 0)
                        <a href="/manager/index.php?a=88&id={{(int)$item->user_info['id']}}" target="_blank">
                            {{implode(' ', array_diff([$item->user_info['first_name'] ?? '', $item->user_info['middle_name'] ?? '', $item->user_info['last_name'] ?? ''], ['']))}}
                        </a>
                    @else
                        {{implode(' ', array_diff([$item->user_info['first_name'] ?? '', $item->user_info['middle_name'] ?? '', $item->user_info['last_name'] ?? ''], ['']))}}
                    @endif
                </p>
                <p><strong>@lang('global.user_phone'):</strong> {{$item->user_info['phone'] ?? ''}}</p>
                <p><strong>@lang('global.user_email'):</strong> {{$item->user_info['email'] ?? ''}}</p>
                <div class="split my-3"></div>
            </div>
            <div class="col-md-8">
                <h3>@lang('sCommerce::global.comments_and_notes')</h3>
                <textarea name="note" class="form-control" rows="4" placeholder="@lang('sCommerce::global.add_comment')"></textarea>
                @foreach(array_reverse($item->manager_notes) as $note)
                    <p>
                        <strong>{{$note['timestamp'] ?? ''}}:</strong>
                        @if((int)$note['user_id'] > 0)
                            <a href="/manager/index.php?a=88&id={{(int)$note['user_id']}}" target="_blank">{{evo()->getUserInfo((int)$note['user_id'])['username']}}</a>
                        @else
                            @lang('sCommerce::global.system')
                        @endif
                    </p>
                    <p>{!!$note['comment']!!}</p>
                @endforeach
                <div class="split my-3"></div>
            </div>
        </div>
    </div>

    <h3>@lang('sCommerce::global.products_in_order')</h3>
    <table class="table table-condensed table-hover sectionTrans scom-table">
        <thead>
        <tr>
            <th>@lang('sCommerce::global.product_name')</th>
            <th>@lang('sCommerce::global.price')</th>
            <th>@lang('sCommerce::global.quantity')</th>
            <th>@lang('sCommerce::global.sum')</th>
        </tr>
        </thead>
        <tbody>
        @foreach($item->products as $product)
            @php($info = '')
            @foreach($product as $p)
                @if(is_array($p) && isset($p['title']))
                    @php($info .= '<b>' . htmlentities($p['title']) . ':</b> ' . htmlentities($p['label'] ?? '') . '<br>')
                @endif
            @endforeach
            <tr style="height: 42px;">
                <td>
                    <img src="{{$product['coverSrc']}}" class="product-thumbnail">
                    <a href="{{$product['link']}}" target="_blank"><b>{{$product['title']}}</b></a>
                    @if(trim($info))<i class="fa fa-question-circle" data-tooltip="{!!$info!!}"></i>@endif
                </td>
                <td>{{$product['price']}}</td>
                <td>{{$product['quantity']}}</td>
                <td>{{sCommerce::convertPrice($product['quantity'] * sCommerce::convertPriceNumber($product['price'], $item->currency, $item->currency), $item->currency)}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="split my-3"></div>
</form>

<div class="order-history">
    <h3>@lang('sCommerce::global.history_changes')</h3>
    <table class="history-table">
        <thead>
        <tr>
            <th>@lang('global.mgrlog_time')</th>
            <th>@lang('sCommerce::global.status')</th>
            <th>@lang('global.mgrlog_user')</th>
        </tr>
        </thead>
        <tbody>
        @foreach(array_reverse($item->history) as $history)
            <tr>
                <td>{{$history['timestamp'] ?? ''}}</td>
                <td>
                    @if(isset($history['status']))
                        <span @class(['badge', 'bg-active' => in_array((int)$history['status'], $unprocessedes), 'bg-progress' => in_array((int)$history['status'], $workings), 'bg-disactive' => in_array((int)$history['status'], $completeds)])>
                            {{sOrder::getOrderStatusName((int)$history['status'])}}
                        </span>
                    @endif
                    @if(isset($history['payment_status']))
                        <span @class(['badge', 'bg-paid' => (int)$history['payment_status'] == sOrder::PAYMENT_STATUS_PAID, 'bg-pending' => (int)$history['payment_status'] != sOrder::PAYMENT_STATUS_PAID])>
                            {{sOrder::getPaymentStatusName((int)$history['payment_status'])}}
                        </span>
                    @endif
                </td>
                <td>
                    @if((int)$history['user_id'] > 0)
                        <a href="/manager/index.php?a=88&id={{(int)$history['user_id']}}" target="_blank">{{evo()->getUserInfo((int)$history['user_id'])['username']}}</a>
                    @else
                        @lang('sCommerce::global.system')
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<style>
    .history-table {
        width: 100%;
        border-collapse: collapse;
    }
    .history-table th, .history-table td {
        padding: 8px;
        border: 1px solid #ddd;
    }
</style>

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!sCommerce::moduleUrl()!!}&get=orders{{request()->has('page') ? '&page=' . request()->page : ''}}">
                <i class="fa fa-times-circle"></i><span>@lang('sCommerce::global.to_list_orders')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fa fa-floppy-o"></i>
                <span>@lang('global.save')</span>
            </a>
        </div>
    </div>
    <script>
        // Automatic contrast text color for domain buttons
        $(document).ready(function() {
            function hexToRgb(hex) {
                hex = hex.replace('#', '');
                if (hex.length === 3) {
                    hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
                }
                return {
                    r: parseInt(hex.substr(0, 2), 16),
                    g: parseInt(hex.substr(2, 2), 16),
                    b: parseInt(hex.substr(4, 2), 16)
                };
            }

            function getContrastTextColor(r, g, b) {
                const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                return luminance > 0.5 ? '#000000' : '#ffffff';
            }

            function setButtonTextColor(btn) {
                // Try to get color from inline style first
                const style = btn.getAttribute('style') || '';
                const match = style.match(/background-color:\s*([^;]+)/);
                if (match) {
                    const colorStr = match[1].trim();
                    if (colorStr.startsWith('#')) {
                        const rgb = hexToRgb(colorStr);
                        btn.style.color = getContrastTextColor(rgb.r, rgb.g, rgb.b);
                        return;
                    }
                }

                // Fallback: try computed style
                const bgColor = window.getComputedStyle(btn).backgroundColor;
                const rgbMatch = bgColor.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
                if (rgbMatch) {
                    const r = parseInt(rgbMatch[1]);
                    const g = parseInt(rgbMatch[2]);
                    const b = parseInt(rgbMatch[3]);
                    btn.style.color = getContrastTextColor(r, g, b);
                }
            }

            $('.domain-badge').each(function() {
                setButtonTextColor(this);
            });
        });
    </script>
@endpush