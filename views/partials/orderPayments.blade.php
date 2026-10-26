@php
    use Seiger\sCommerce\Models\sOrder;
    use Seiger\sCommerce\Payment\PaymentFlow;
    use Seiger\sCommerce\Payment\PaymentSchedule;
    use Seiger\sCommerce\Payment\PaymentSummary;

    $schedule = PaymentSchedule::of($item);
    $totals = $panel['totals'] ?? null;
    $orderActions = $panel['orderActions'] ?? [];
    $dueLabel = static function (string $due): string {
        if (str_starts_with($due, 'on_status:')) {
            return __('sCommerce::global.payment_due_on_status', ['status' => sOrder::getOrderStatusName((int) substr($due, 10))]);
        }
        return __('sCommerce::global.payment_due_' . $due);
    };
    // The final installment absorbs cost changes made after checkout.
    $scheduledMinor = array_sum(array_map(static fn ($installment) => PaymentFlow::toMinor((string) $installment['amount']), $schedule));
    $finalMinor = $schedule ? PaymentFlow::toMinor((string) end($schedule)['amount']) + PaymentFlow::toMinor((string) $item->cost) - $scheduledMinor : 0;
@endphp
<div class="row" data-order-payments data-url="{{sCommerce::moduleUrl()}}&amp;get=orderPaymentAction" data-order="{{(int) $item->id}}"
     data-error="@lang('sCommerce::global.bulk_status_request_error')" data-session-error="@lang('sCommerce::global.bulk_status_session_expired')"
     data-pending="@lang('sCommerce::global.payment_action_sending')" data-confirm-ok="@lang('sCommerce::global.payment_action_run')"
     data-confirm-cancel="@lang('global.cancel')">
    <div class="col-12">
        <h3>@lang('sCommerce::global.payments_ledger')</h3>
        <p data-order-payments-message role="status" hidden></p>
        @if($totals)
            <p data-payment-totals>
                @lang('sCommerce::global.payment_order_total'): <strong>{{$totals['total']}}</strong> {{$totals['currency']}}
                &middot; @lang('sCommerce::global.payment_paid_total'): <strong data-payment-paid>{{$totals['paid']}}</strong> {{$totals['currency']}}
                &middot; @lang('sCommerce::global.payment_balance_due'): <strong data-payment-due>{{$totals['due']}}</strong> {{$totals['currency']}}
                @if($totals['overpaid'] !== '0.00')
                    &middot; <span class="text-danger">@lang('sCommerce::global.payment_overpaid'): <strong data-payment-overpaid>{{$totals['overpaid']}}</strong> {{$totals['currency']}}</span>
                @endif
            </p>
        @endif
        @if($schedule)
            <p><strong>@lang('sCommerce::global.payment_schedule'):</strong></p>
            <ul data-payment-schedule>
                @foreach($schedule as $installment)
                    <li>{{trim(($installment['label'] ?? '') ?: $installment['kind'])}} &mdash; {{number_format((float) $installment['amount'], 2, '.', '')}} {{$item->currency}}@if($loop->last && $finalMinor !== PaymentFlow::toMinor((string) $installment['amount'])) &rarr; <span data-payment-final-adjusted>{{PaymentSummary::decimal(max(0, $finalMinor))}}</span> {{$item->currency}}@endif ({{$dueLabel($installment['due'])}})</li>
                @endforeach
            </ul>
        @endif
        @if(count($panel['rows']))
            <div class="table-responsive">
                <table class="table table-sm" data-payment-ledger>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>@lang('sCommerce::global.payment_kind')</th>
                        <th>@lang('sCommerce::global.status')</th>
                        <th>@lang('sCommerce::global.payment_amount')</th>
                        <th>@lang('sCommerce::global.payment_provider')</th>
                        <th>@lang('sCommerce::global.payment_reference')</th>
                        <th>@lang('sCommerce::global.payment_captured_at')</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($panel['rows'] as $row)
                        @php($ledgerRow = $row['payment'])
                        <tr data-payment="{{(int) $ledgerRow->id}}" data-label="#{{(int) $ledgerRow->sequence}}">
                            <td>{{(int) $ledgerRow->sequence}}</td>
                            <td>{{$ledgerRow->kind}}</td>
                            <td>{{$ledgerRow->status}}</td>
                            <td>{{number_format((float) $ledgerRow->amount, 2, '.', '')}} {{$ledgerRow->currency}}</td>
                            <td>{{$ledgerRow->provider}}</td>
                            <td>
                                {{$ledgerRow->provider_ref}}
                                @if($row['parentProviderRef'])<br><small>&#8627; {{$row['parentProviderRef']}}</small>@endif
                            </td>
                            <td>{{$ledgerRow->captured_at}}</td>
                            <td class="text-nowrap">
                                @isset($row['actions']['capture'])
                                    <span data-payment-form="capture">
                                        @if($panel['partialCapture'] ?? false)
                                            <input type="text" inputmode="decimal" size="8" data-payment-amount value="{{$row['capturable']}}" aria-label="@lang('sCommerce::global.payment_capture_amount')" title="@lang('sCommerce::global.payment_capture_amount_help')">
                                        @endif
                                        <button type="button" class="btn btn-sm btn-success" data-payment-action="capture" data-nonce="{{$row['actions']['capture']}}">@lang('sCommerce::global.payment_capture')</button>
                                    </span>
                                @endisset
                                @isset($row['actions']['refund'])
                                    <span data-payment-form="refund">
                                        <input type="text" inputmode="decimal" size="8" data-payment-amount value="{{$row['refundable']}}" aria-label="@lang('sCommerce::global.payment_refund_amount')" title="@lang('sCommerce::global.payment_refundable'): {{$row['refundable']}}">
                                        <input type="text" size="12" maxlength="255" data-payment-reason placeholder="@lang('sCommerce::global.payment_refund_reason')" aria-label="@lang('sCommerce::global.payment_refund_reason')">
                                        <button type="button" class="btn btn-sm btn-warning" data-payment-action="refund" data-nonce="{{$row['actions']['refund']}}">@lang('sCommerce::global.payment_refund')</button>
                                    </span>
                                @endisset
                                @isset($row['actions']['cancel'])
                                    <button type="button" class="btn btn-sm btn-secondary" data-payment-action="cancel" data-nonce="{{$row['actions']['cancel']}}">@lang('sCommerce::global.payment_cancel')</button>
                                @endisset
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($orderActions)
            <div class="d-flex flex-wrap" style="gap:1rem" data-payment="0" data-label="">
                @isset($orderActions['receive'])
                    <span class="text-nowrap" data-payment-form="receive">
                        <input type="text" inputmode="decimal" size="8" data-payment-amount value="{{$totals['due'] ?? ''}}" aria-label="@lang('sCommerce::global.payment_record_amount')" title="@lang('sCommerce::global.payment_balance_due'): {{$totals['due'] ?? ''}}">
                        <input type="text" size="14" maxlength="255" data-payment-reason placeholder="@lang('sCommerce::global.payment_record_note')" aria-label="@lang('sCommerce::global.payment_record_note')">
                        <button type="button" class="btn btn-sm btn-success" data-payment-action="receive" data-nonce="{{$orderActions['receive']}}">@lang('sCommerce::global.payment_record')</button>
                    </span>
                @endisset
                @isset($orderActions['containers'])
                    <span class="text-nowrap" data-payment-form="containers">
                        <select data-payment-field="line" aria-label="@lang('sCommerce::global.container_return_line')">
                            @foreach($panel['containerLines'] ?? [] as $lineIndex => $lineLabel)
                                <option value="{{$lineIndex}}">{{$lineLabel}}</option>
                            @endforeach
                        </select>
                        <input type="number" min="1" step="1" style="width:5em" value="1" data-payment-field="count" aria-label="@lang('sCommerce::global.container_return_count')" title="@lang('sCommerce::global.container_return_count')">
                        <select data-payment-field="settlement" aria-label="@lang('sCommerce::global.container_return_settlement')">
                            <option value="cash">@lang('sCommerce::global.container_return_cash')</option>
                            <option value="credit">@lang('sCommerce::global.container_return_credit')</option>
                        </select>
                        <input type="number" min="1" step="1" style="width:7em" data-payment-field="credit_order" placeholder="@lang('sCommerce::global.container_return_credit_order')" aria-label="@lang('sCommerce::global.container_return_credit_order')">
                        <button type="button" class="btn btn-sm btn-info" data-payment-action="containers" data-nonce="{{$orderActions['containers']}}">@lang('sCommerce::global.container_return')</button>
                    </span>
                @endisset
                @isset($orderActions['return'])
                    <span class="text-nowrap" data-payment-form="return">
                        <input type="text" inputmode="decimal" size="8" data-payment-amount value="{{($totals['overpaid'] ?? '0.00') !== '0.00' ? $totals['overpaid'] : ''}}" aria-label="@lang('sCommerce::global.payment_return_amount')" title="@lang('sCommerce::global.payment_returnable'): {{$totals['returnable'] ?? ''}}">
                        <input type="text" size="14" maxlength="255" data-payment-reason placeholder="@lang('sCommerce::global.payment_record_note')" aria-label="@lang('sCommerce::global.payment_record_note')">
                        <button type="button" class="btn btn-sm btn-warning" data-payment-action="return" data-nonce="{{$orderActions['return']}}">@lang('sCommerce::global.payment_record_return')</button>
                    </span>
                @endisset
            </div>
        @endif
        @if(count($panel['operations']))
            <p><strong>@lang('sCommerce::global.payment_operations_open'):</strong></p>
            <ul data-payment-operations>
                @foreach($panel['operations'] as $operation)
                    <li>{{__('sCommerce::global.payment_' . $operation->action)}}@if($operation->amount !== null) {{number_format((float) $operation->amount, 2, '.', '')}} {{$operation->currency}}@endif &mdash; {{$operation->state}} ({{$operation->updated_at}})</li>
                @endforeach
            </ul>
        @endif
        <div class="split my-3"></div>
    </div>
</div>
@include('sCommerce::scripts.orderPaymentActions')
