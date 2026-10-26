@php
    use Seiger\sCommerce\Models\sOrderShipment;
    use Seiger\sCommerce\Services\OrderShipments;

    $shipmentRows = $panel['rows'] ?? collect();
    $shipmentStatuses = sOrderShipment::listStatuses();
    $coverage = OrderShipments::coverage($item, $shipmentRows);
    $orderLines = is_array($item->products) ? $item->products : [];
    $isHttpUrl = static fn ($url) => is_string($url) && preg_match('~\Ahttps?://~i', $url);
@endphp
<div class="row" data-order-shipments data-url="{{sCommerce::moduleUrl()}}&amp;get=orderShipmentSave" data-order="{{(int) $item->id}}"
     data-error="@lang('sCommerce::global.bulk_status_request_error')" data-session-error="@lang('sCommerce::global.bulk_status_session_expired')"
     data-pending="@lang('sCommerce::global.payment_action_sending')">
    <div class="col-12">
        <h3>@lang('sCommerce::global.shipments')</h3>
        <p data-order-shipments-message role="status" hidden></p>
        @if($coverage['fullyDelivered'])
            <p><span class="badge bg-paid" data-shipments-coverage="delivered">@lang('sCommerce::global.shipment_fully_delivered')</span></p>
        @elseif($coverage['fullyShipped'])
            <p><span class="badge bg-pending" data-shipments-coverage="shipped">@lang('sCommerce::global.shipment_fully_shipped')</span></p>
        @endif
        @if(count($shipmentRows))
            <div class="table-responsive">
                <table class="table table-sm" data-shipments-table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>@lang('sCommerce::global.status')</th>
                        <th>@lang('sCommerce::global.carrier')</th>
                        <th>@lang('sCommerce::global.tracking_number')</th>
                        <th>@lang('sCommerce::global.shipment_items')</th>
                        <th>@lang('sCommerce::global.shipment_shipped_at')</th>
                        <th>@lang('sCommerce::global.shipment_delivered_at')</th>
                        <th>@lang('sCommerce::global.shipment_source')</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($shipmentRows as $shipment)
                        <tr data-shipment="{{(int) $shipment->id}}">
                            <td>{{(int) $shipment->sequence}}</td>
                            <td>{{$shipmentStatuses[$shipment->status] ?? $shipment->status}}</td>
                            <td>{{$shipment->carrier}}@if($shipment->service) <small>({{$shipment->service}})</small>@endif</td>
                            <td>
                                @if($isHttpUrl($shipment->tracking_url))
                                    <a href="{{$shipment->tracking_url}}" target="_blank" rel="noopener noreferrer">{{$shipment->tracking_number ?: $shipment->tracking_url}}</a>
                                @else
                                    {{$shipment->tracking_number}}
                                @endif
                            </td>
                            <td>
                                @if($shipment->items === null)
                                    @lang('sCommerce::global.shipment_whole_order')
                                @else
                                    @foreach($shipment->items as $shipmentItem)
                                        <div>{{$orderLines[$shipmentItem['line'] ?? -1]['title'] ?? ($shipmentItem['sku'] ?? '')}} &times; {{(int) ($shipmentItem['quantity'] ?? 0)}}</div>
                                    @endforeach
                                @endif
                            </td>
                            <td>{{$shipment->shipped_at}}</td>
                            <td>{{$shipment->delivered_at}}</td>
                            <td>{{$shipment->source}}@if($shipment->external_ref)<br><small>{{$shipment->external_ref}}</small>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($panel['nonce'] ?? null)
            <div class="d-flex flex-wrap align-items-center" style="gap:.5rem" data-shipment-form data-nonce="{{$panel['nonce']}}">
                <strong>@lang('sCommerce::global.add_tracking'):</strong>
                <select data-shipment-field="shipment" aria-label="@lang('sCommerce::global.shipments')">
                    <option value="0">@lang('sCommerce::global.shipment_new')</option>
                    @foreach($shipmentRows as $shipment)
                        <option value="{{(int) $shipment->id}}">#{{(int) $shipment->sequence}} {{$shipment->tracking_number}}</option>
                    @endforeach
                </select>
                <select data-shipment-field="status" aria-label="@lang('sCommerce::global.status')">
                    @foreach($shipmentStatuses as $statusKey => $statusLabel)
                        <option value="{{$statusKey}}" @selected($statusKey === sOrderShipment::STATUS_SHIPPED)>{{$statusLabel}}</option>
                    @endforeach
                </select>
                <input type="text" size="10" maxlength="64" data-shipment-field="carrier" placeholder="@lang('sCommerce::global.carrier')" aria-label="@lang('sCommerce::global.carrier')">
                <input type="text" size="16" maxlength="128" data-shipment-field="tracking_number" placeholder="@lang('sCommerce::global.tracking_number')" aria-label="@lang('sCommerce::global.tracking_number')">
                <input type="url" size="28" maxlength="512" data-shipment-field="tracking_url" placeholder="@lang('sCommerce::global.tracking_url')" aria-label="@lang('sCommerce::global.tracking_url')">
                <button type="button" class="btn btn-sm btn-success" data-shipment-save>@lang('sCommerce::global.shipment_save')</button>
            </div>
        @endif
        <div class="split my-3"></div>
    </div>
</div>
@push('scripts.bot')
<script>
(function () {
    const panel = document.querySelector('[data-order-shipments]');
    const fields = panel && panel.querySelector('[data-shipment-form]');
    if (!fields) return;
    const message = panel.querySelector('[data-order-shipments-message]');
    const show = function (text, isError) {
        message.hidden = false;
        message.classList.toggle('is-error', Boolean(isError));
        message.textContent = text;
    };
    fields.querySelector('[data-shipment-save]').addEventListener('click', async function (event) {
        event.preventDefault();
        const body = new FormData();
        const form = document.getElementById('form');
        const token = document.querySelector('meta[name="csrf-token"]')?.content || (form && form.querySelector('[name="_token"]')?.value);
        body.set('_token', token || '');
        body.set('order', panel.dataset.order);
        body.set('nonce', fields.dataset.nonce);
        fields.querySelectorAll('[data-shipment-field]').forEach(input => { body.set(input.dataset.shipmentField, input.value.trim()); });
        const controls = fields.querySelectorAll('button, input, select');
        controls.forEach(input => { input.disabled = true; });
        show(panel.dataset.pending, false);
        try {
            const response = await fetch(panel.dataset.url, {
                method: 'POST', credentials: 'same-origin', body,
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            let result;
            try { result = await response.json(); } catch (_) { result = null; }
            if (!response.ok || !result || result.success !== true) {
                throw new Error((result && result.message) || (response.status === 403 ? panel.dataset.sessionError : panel.dataset.error));
            }
            show(result.message, false);
            window.setTimeout(function () { window.location.reload(); }, 1000);
        } catch (error) {
            show(error instanceof TypeError ? panel.dataset.error : error.message, true);
            controls.forEach(input => { input.disabled = false; });
        }
    });
})();
</script>
@endpush
