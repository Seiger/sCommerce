@php
    $returns = $returns ?? collect();
    $depositProducts = $depositProducts ?? collect();
    $notice = (string)request()->input('container_notice', '');
    $notices = ['ok' => 'container_return_success', 'container_return_invalid' => 'container_return_invalid',
        'container_credit_exceeds' => 'container_credit_exceeds', 'payment_action_forbidden' => 'payment_action_forbidden',
        'bulk_status_session_expired' => 'bulk_status_session_expired', 'bulk_status_error' => 'bulk_status_error'];
    $currencies = (array)sCommerce::config('basic.available_currencies', []);
    $mainCurrency = sCommerce::config('basic.main_currency', 'USD');
@endphp
<div class="container-fluid" data-container-returns>
    @isset($notices[$notice])
        <div @class(['alert', 'alert-success' => $notice === 'ok', 'alert-danger' => $notice !== 'ok']) role="status">@lang('sCommerce::global.' . $notices[$notice])</div>
    @endisset
    <p class="text-muted">@lang('sCommerce::global.containers_help')</p>
    @if($containerNonce ?? null)
        <form method="post" action="{!!sCommerce::moduleUrl()!!}&get=containerReturnSave" class="row g-2 align-items-end mb-4" data-container-return-form>
            @csrf
            <input type="hidden" name="nonce" value="{{$containerNonce}}">
            <div class="col-md-3">
                <label class="form-label" for="container-product">@lang('sCommerce::global.product')</label>
                <select id="container-product" name="product" class="form-control">
                    <option value="">@lang('sCommerce::global.container_return_other')</option>
                    @foreach($depositProducts as $depositProduct)
                        <option value="{{$depositProduct->id}}">{{$depositProduct->sku}} — {{$depositProduct->pagetitle}} ({{number_format((float)$depositProduct->deposit, 2, '.', '')}} {{$depositProduct->currency}})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="container-title">@lang('sCommerce::global.container_return_title')</label>
                <input id="container-title" name="title" class="form-control" maxlength="255">
            </div>
            <div class="col-md-1">
                <label class="form-label" for="container-unit">@lang('sCommerce::global.container_deposit')</label>
                <input id="container-unit" name="unit" class="form-control" inputmode="decimal" placeholder="0.00">
            </div>
            @if(count($currencies) > 1)
                <div class="col-md-1">
                    <label class="form-label" for="container-currency">@lang('sCommerce::global.order_currency')</label>
                    <select id="container-currency" name="currency" class="form-control">
                        @foreach($currencies as $code)<option value="{{$code}}" @selected($code === $mainCurrency)>{{$code}}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-1">
                <label class="form-label" for="container-quantity">@lang('sCommerce::global.container_return_count')</label>
                <input id="container-quantity" name="quantity" type="number" min="1" step="1" value="1" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="container-settlement">@lang('sCommerce::global.container_return_settlement')</label>
                <select id="container-settlement" name="settlement" class="form-control">
                    <option value="cash">@lang('sCommerce::global.container_return_cash')</option>
                    <option value="credit">@lang('sCommerce::global.container_return_credit')</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="container-credit-order">@lang('sCommerce::global.container_return_credit_order')</label>
                <input id="container-credit-order" name="credit_order" type="number" min="1" step="1" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="container-customer-name">@lang('sCommerce::global.client')</label>
                <input id="container-customer-name" name="customer[name]" class="form-control" maxlength="255">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="container-customer-phone">@lang('sCommerce::global.phone')</label>
                <input id="container-customer-phone" name="customer[phone]" class="form-control" maxlength="50">
            </div>
            <div class="col-md-1">
                <label class="form-label" for="container-user">ID</label>
                <input id="container-user" name="user_id" type="number" min="1" step="1" class="form-control" title="@lang('sCommerce::global.container_return_user')">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="container-note">@lang('sCommerce::global.payment_record_note')</label>
                <input id="container-note" name="note" class="form-control" maxlength="255">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-info">@lang('sCommerce::global.container_return')</button>
            </div>
        </form>
    @endif
    <table class="table table-condensed table-hover sectionTrans scom-table" data-container-returns-table>
        <thead>
        <tr>
            <th>@lang('sCommerce::global.created')</th>
            <th>@lang('sCommerce::global.product')</th>
            <th>@lang('sCommerce::global.container_return_count')</th>
            <th>@lang('sCommerce::global.sum')</th>
            <th>@lang('sCommerce::global.container_return_settlement')</th>
            <th>@lang('sCommerce::global.order')</th>
            <th>@lang('sCommerce::global.client')</th>
            <th>@lang('sCommerce::global.container_terms')</th>
        </tr>
        </thead>
        <tbody>
        @forelse($returns as $return)
            <tr>
                <td>{{$return->created_at}}</td>
                <td>{{$return->title}}</td>
                <td>{{$return->quantity}} × {{number_format((float)$return->unit_amount, 2, '.', '')}}</td>
                <td>{{number_format((float)$return->amount, 2, '.', '')}} {{$return->currency}}</td>
                <td>@lang('sCommerce::global.container_return_' . $return->settlement)@if($return->credit_order_id) → <a href="{!!sCommerce::moduleUrl()!!}&get=order&i={{$return->credit_order_id}}">#{{$return->credit_order_id}}</a>@endif</td>
                <td>@if($return->source_order_id)<a href="{!!sCommerce::moduleUrl()!!}&get=order&i={{$return->source_order_id}}">#{{$return->source_order_id}}</a>@else — @endif</td>
                <td>{{trim(implode(' ', array_filter([(string)($return->customer['name'] ?? trim(($return->customer['first_name'] ?? '') . ' ' . ($return->customer['last_name'] ?? ''))), (string)($return->customer['phone'] ?? '')]))) ?: ($return->user_id ? '#' . $return->user_id : '—')}}</td>
                <td><small>{{$return->terms}}</small></td>
            </tr>
        @empty
            <tr><td colspan="8">@lang('sCommerce::global.container_returns_empty')</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
