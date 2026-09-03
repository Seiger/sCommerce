@use(Seiger\sCommerce\Models\sOrder)
@php
    $order = request()->input('order', 'id');
    $currencies = sCommerce::config('currencies', []);

    $formatPhone = static function ($phone): string {
        $phone = trim((string) $phone);
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 12 && str_starts_with($digits, '380')) {
            return sprintf('+38 (%s) %s-%s-%s', substr($digits, 2, 3), substr($digits, 5, 3), substr($digits, 8, 2), substr($digits, 10, 2));
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return sprintf('+38 (%s) %s-%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 2), substr($digits, 8, 2));
        }
        return $phone;
    };

    $paymentClass = static fn (int $status): string => match ($status) {
        sOrder::PAYMENT_STATUS_AUTHORIZED,
        sOrder::PAYMENT_STATUS_PARTIALLY_PAID => 'scom-orders-payment--authorized',
        sOrder::PAYMENT_STATUS_PAID => 'scom-orders-payment--paid',
        sOrder::PAYMENT_STATUS_REFUND_REQUESTED,
        sOrder::PAYMENT_STATUS_REFUNDED,
        sOrder::PAYMENT_STATUS_PARTIALLY_REFUNDED => 'scom-orders-payment--refunded',
        sOrder::PAYMENT_STATUS_FAILED,
        sOrder::PAYMENT_STATUS_REJECTED,
        sOrder::PAYMENT_STATUS_EXPIRED,
        sOrder::PAYMENT_STATUS_DISPUTED => 'scom-orders-payment--failed',
        sOrder::PAYMENT_STATUS_CANCELED => 'scom-orders-payment--canceled',
        default => 'scom-orders-payment--pending',
    };

    $deliveryMethods = $deliveryMethods ?? collect();
    $deliveryDisplay = static function ($item) use ($deliveryMethods): array {
        $info = is_array($item->delivery_info) ? $item->delivery_info : [];
        $method = trim((string) ($info['method'] ?? ''));
        $methodInfo = $method !== '' && is_array($info[$method] ?? null) ? $info[$method] : [];
        $name = '—';
        foreach ([$deliveryMethods->get($method)['title'] ?? null, $info['title'] ?? null, $info['name'] ?? null, $methodInfo['title'] ?? null, $methodInfo['name'] ?? null, $method] as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                $name = trim((string) $candidate);
                break;
            }
        }
        $tracking = '';
        foreach (['ttn', 'tracking_number', 'trackingNumber', 'waybill', 'declaration'] as $key) {
            $candidate = $methodInfo[$key] ?? $info[$key] ?? null;
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                $tracking = trim((string) $candidate);
                break;
            }
        }
        return ['name' => $name, 'tracking' => $tracking];
    };

    $ordersUrl = static function (array $changes = []): string {
        $query = request()->only(['search', 'status', 'payment_status', 'payment_method', 'delivery_method', 'domain', 'date_from', 'date_to', 'order', 'direc']);
        foreach ($changes as $key => $value) {
            if ($value === null || $value === '') {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }
        return sCommerce::moduleUrl() . '&' . http_build_query(['get' => 'orders'] + $query);
    };

    $paymentMethods = $paymentMethods ?? collect();
    $selectedStatuses = $selectedStatuses ?? [];
    $selectedPaymentStatuses = $selectedPaymentStatuses ?? [];
    $selectedPaymentMethods = $selectedPaymentMethods ?? [];
    $selectedDeliveryMethods = $selectedDeliveryMethods ?? [];
    $selectedDomains = $selectedDomains ?? [];
    $statusFilterCount = count($selectedStatuses);
    $paymentFilterCount = count($selectedPaymentStatuses) + count($selectedPaymentMethods);
    $deliveryFilterCount = count($selectedDeliveryMethods);
    $domainFilterCount = count($selectedDomains);
    $dateFilterCount = (int) (request()->filled('date_from') || request()->filled('date_to'));
    $today = today()->toDateString();
    $last7Days = today()->subDays(6)->toDateString();
    $last30Days = today()->subDays(29)->toDateString();
    $sortableColumns = [
        'id' => __('sCommerce::global.number'),
        'client' => __('sCommerce::global.client'),
        'created_at' => __('sCommerce::global.created'),
        'cost' => __('sCommerce::global.sum'),
        'status' => __('sCommerce::global.status'),
        'payment_status' => __('sCommerce::global.payment'),
    ];
    $sortDirection = request()->input('direc', 'desc') === 'asc' ? 'asc' : 'desc';
@endphp

<div class="scom-orders-page">
    <section class="scom-orders-toolbar" aria-label="@lang('sCommerce::global.orders')">
        <div class="scom-orders-summary">
            <span class="scom-orders-summary__icon" aria-hidden="true">@svg('tabler-shopping-cart')</span>
            <div class="scom-orders-summary__copy">
                <div class="scom-orders-summary__title">
                    <strong>@lang('sCommerce::global.orders')</strong>
                    <span>@lang('sCommerce::global.total'): {{$total ?? 0}}</span>
                </div>
                <div class="scom-orders-summary__detail">
                    @lang('sCommerce::global.unprocessed_orders'): {{$unprocessed ?? 0}}<span aria-hidden="true">|</span>@lang('sCommerce::global.working_orders'): {{$working ?? 0}}<span aria-hidden="true">|</span>@lang('sCommerce::global.completed_orders'): {{$completed ?? 0}}
                </div>
            </div>
        </div>

        <div class="scom-orders-controls">
            <div class="scom-orders-filter-row">
                <a class="scom-orders-add" href="{!!sCommerce::moduleUrl()!!}&get=order&i=0" title="@lang('sCommerce::global.add')" aria-label="@lang('sCommerce::global.add')">@svg('tabler-plus')</a>

                <div class="scom-orders-filter-triggers" aria-label="@lang('sCommerce::global.status') / @lang('sCommerce::global.payments') / @lang('sCommerce::global.deliveries') / @lang('sCommerce::global.domains') / @lang('sCommerce::global.created')">
                <details class="scom-orders-filter">
                    <summary class="scom-orders-icon-button" title="@lang('sCommerce::global.status')" aria-label="@lang('sCommerce::global.status')">
                        @svg('tabler-list-check')
                        @if($statusFilterCount)<span class="scom-orders-filter-count">{{$statusFilterCount}}</span>@endif
                    </summary>
                    <div class="scom-orders-filter-menu scom-orders-choice-menu" data-filter-params="status">
                        <strong class="scom-orders-choice-menu__title">@lang('sCommerce::global.status')</strong>
                        <div class="scom-orders-choice-menu__options">
                            @foreach($statuses as $id => $name)
                                <label class="scom-orders-choice">
                                    <input type="checkbox" data-filter-param="status" value="{{$id}}" @checked(in_array((int) $id, $selectedStatuses, true))>
                                    <span class="scom-orders-choice__box" aria-hidden="true">@svg('tabler-check')</span>
                                    <span class="scom-orders-choice__label">{{$name}}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="scom-orders-choice-menu__actions">
                            <button class="scom-orders-filter-action scom-orders-filter-action--reset" type="button" data-filter-reset title="@lang('sCommerce::global.reset_filters')" aria-label="@lang('sCommerce::global.reset_filters')">@svg('tabler-x')</button>
                            <button class="scom-orders-filter-action scom-orders-filter-action--apply" type="button" data-filter-apply title="@lang('sCommerce::global.apply_filters')" aria-label="@lang('sCommerce::global.apply_filters')">@svg('tabler-check')</button>
                        </div>
                    </div>
                </details>

                <details class="scom-orders-filter">
                    <summary class="scom-orders-icon-button" title="@lang('sCommerce::global.payments')" aria-label="@lang('sCommerce::global.payments')">
                        @svg('tabler-credit-card')
                        @if($paymentFilterCount)<span class="scom-orders-filter-count">{{$paymentFilterCount}}</span>@endif
                    </summary>
                    <div class="scom-orders-filter-menu scom-orders-choice-menu" data-filter-params="payment_status,payment_method">
                        <div class="scom-orders-choice-menu__options">
                            <strong class="scom-orders-choice-menu__title">@lang('sCommerce::global.payment_status')</strong>
                            @foreach($paymentStatuses as $id => $name)
                                <label class="scom-orders-choice">
                                    <input type="checkbox" data-filter-param="payment_status" value="{{$id}}" @checked(in_array((int) $id, $selectedPaymentStatuses, true))>
                                    <span class="scom-orders-choice__box" aria-hidden="true">@svg('tabler-check')</span>
                                    <span class="scom-orders-choice__label">{{$name}}</span>
                                </label>
                            @endforeach
                            @if($paymentMethods->isNotEmpty())
                                <strong class="scom-orders-choice-menu__title scom-orders-choice-menu__title--section">@lang('sCommerce::global.payment_methods')</strong>
                                @foreach($paymentMethods as $method)
                                    <label class="scom-orders-choice">
                                        <input type="checkbox" data-filter-param="payment_method" value="{{$method['name'] ?? ''}}" @checked(in_array((string) ($method['name'] ?? ''), $selectedPaymentMethods, true))>
                                        <span class="scom-orders-choice__box" aria-hidden="true">@svg('tabler-check')</span>
                                        <span class="scom-orders-choice__label">{{$method['title'] ?? $method['name'] ?? ''}}</span>
                                    </label>
                                @endforeach
                            @endif
                        </div>
                        <div class="scom-orders-choice-menu__actions">
                            <button class="scom-orders-filter-action scom-orders-filter-action--reset" type="button" data-filter-reset title="@lang('sCommerce::global.reset_filters')" aria-label="@lang('sCommerce::global.reset_filters')">@svg('tabler-x')</button>
                            <button class="scom-orders-filter-action scom-orders-filter-action--apply" type="button" data-filter-apply title="@lang('sCommerce::global.apply_filters')" aria-label="@lang('sCommerce::global.apply_filters')">@svg('tabler-check')</button>
                        </div>
                    </div>
                </details>

                <details class="scom-orders-filter">
                    <summary class="scom-orders-icon-button" title="@lang('sCommerce::global.deliveries')" aria-label="@lang('sCommerce::global.deliveries')">
                        @svg('tabler-truck-delivery')
                        @if($deliveryFilterCount)<span class="scom-orders-filter-count">{{$deliveryFilterCount}}</span>@endif
                    </summary>
                    <div class="scom-orders-filter-menu scom-orders-choice-menu" data-filter-params="delivery_method">
                        <strong class="scom-orders-choice-menu__title">@lang('sCommerce::global.delivery')</strong>
                        <div class="scom-orders-choice-menu__options">
                            @foreach($deliveryMethods as $method)
                                <label class="scom-orders-choice">
                                    <input type="checkbox" data-filter-param="delivery_method" value="{{$method['name'] ?? ''}}" @checked(in_array((string) ($method['name'] ?? ''), $selectedDeliveryMethods, true))>
                                    <span class="scom-orders-choice__box" aria-hidden="true">@svg('tabler-check')</span>
                                    <span class="scom-orders-choice__label">{{$method['title'] ?? $method['name'] ?? ''}}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="scom-orders-choice-menu__actions">
                            <button class="scom-orders-filter-action scom-orders-filter-action--reset" type="button" data-filter-reset title="@lang('sCommerce::global.reset_filters')" aria-label="@lang('sCommerce::global.reset_filters')">@svg('tabler-x')</button>
                            <button class="scom-orders-filter-action scom-orders-filter-action--apply" type="button" data-filter-apply title="@lang('sCommerce::global.apply_filters')" aria-label="@lang('sCommerce::global.apply_filters')">@svg('tabler-check')</button>
                        </div>
                    </div>
                </details>

                @if($domains && $domains->count() > 1)
                    <details class="scom-orders-filter">
                        <summary class="scom-orders-icon-button" title="@lang('sCommerce::global.domains')" aria-label="@lang('sCommerce::global.domains')">
                            @svg('tabler-world')
                            @if($domainFilterCount)<span class="scom-orders-filter-count">{{$domainFilterCount}}</span>@endif
                        </summary>
                        <div class="scom-orders-filter-menu scom-orders-choice-menu scom-orders-filter-menu--end" data-filter-params="domain">
                            <strong class="scom-orders-choice-menu__title">@lang('sCommerce::global.domains')</strong>
                            <div class="scom-orders-choice-menu__options">
                            @foreach($domains as $domain)
                                    <label class="scom-orders-choice">
                                        <input type="checkbox" data-filter-param="domain" value="{{$domain->key}}" @checked(in_array((string) $domain->key, $selectedDomains, true))>
                                        <span class="scom-orders-choice__box" aria-hidden="true">@svg('tabler-check')</span>
                                        <span class="scom-orders-choice__label"><span class="scom-orders-domain-dot" style="--domain-color: {{$domain->site_color ?: '#60a5fa'}}"></span>{{$domain->domain}}</span>
                                    </label>
                            @endforeach
                            </div>
                            <div class="scom-orders-choice-menu__actions">
                                <button class="scom-orders-filter-action scom-orders-filter-action--reset" type="button" data-filter-reset title="@lang('sCommerce::global.reset_filters')" aria-label="@lang('sCommerce::global.reset_filters')">@svg('tabler-x')</button>
                                <button class="scom-orders-filter-action scom-orders-filter-action--apply" type="button" data-filter-apply title="@lang('sCommerce::global.apply_filters')" aria-label="@lang('sCommerce::global.apply_filters')">@svg('tabler-check')</button>
                            </div>
                        </div>
                    </details>
                @endif

                <details class="scom-orders-filter">
                    <summary class="scom-orders-icon-button" title="@lang('sCommerce::global.created')" aria-label="@lang('sCommerce::global.created')">
                        @svg('tabler-calendar')
                        @if($dateFilterCount)<span class="scom-orders-filter-count">{{$dateFilterCount}}</span>@endif
                    </summary>
                    <div class="scom-orders-filter-menu scom-orders-date-menu scom-orders-filter-menu--end" data-date-filter>
                        <label class="scom-orders-date-menu__field">
                            <span>@lang('sCommerce::global.date_from')</span>
                            <input type="date" data-date-param="date_from" value="{{request()->input('date_from', '')}}">
                        </label>
                        <label class="scom-orders-date-menu__field">
                            <span>@lang('sCommerce::global.date_to')</span>
                            <input type="date" data-date-param="date_to" value="{{request()->input('date_to', '')}}">
                        </label>
                        <div class="scom-orders-date-presets" aria-label="@lang('sCommerce::global.quick_filters')">
                            <button @class(['scom-orders-date-preset', 'is-active' => request()->input('date_from') === $today && request()->input('date_to') === $today]) type="button" data-date-preset data-date-from="{{$today}}" data-date-to="{{$today}}">@lang('sCommerce::global.today')</button>
                            <button @class(['scom-orders-date-preset', 'is-active' => request()->input('date_from') === $last7Days && request()->input('date_to') === $today]) type="button" data-date-preset data-date-from="{{$last7Days}}" data-date-to="{{$today}}">@lang('sCommerce::global.quick_7_days')</button>
                            <button @class(['scom-orders-date-preset', 'is-active' => request()->input('date_from') === $last30Days && request()->input('date_to') === $today]) type="button" data-date-preset data-date-from="{{$last30Days}}" data-date-to="{{$today}}">@lang('sCommerce::global.quick_month')</button>
                        </div>
                        <div class="scom-orders-choice-menu__actions">
                            <button class="scom-orders-filter-action scom-orders-filter-action--reset" type="button" data-date-reset title="@lang('sCommerce::global.reset_filters')" aria-label="@lang('sCommerce::global.reset_filters')">@svg('tabler-x')</button>
                            <button class="scom-orders-filter-action scom-orders-filter-action--apply" type="button" data-date-apply title="@lang('sCommerce::global.apply_filters')" aria-label="@lang('sCommerce::global.apply_filters')">@svg('tabler-check')</button>
                        </div>
                    </div>
                </details>

                    <details class="scom-orders-filter scom-orders-filter--mobile-sort">
                        <summary class="scom-orders-icon-button" title="@lang('sCommerce::global.sorting')" aria-label="@lang('sCommerce::global.sorting')">
                            @svg('tabler-repeat')
                        </summary>
                        <div class="scom-orders-filter-menu scom-orders-filter-menu--end scom-orders-sort-menu">
                            <span class="scom-orders-sort-menu__title">@lang('sCommerce::global.sorting')</span>
                            @foreach($sortableColumns as $field => $label)
                                <a @class(['is-active' => $order === $field]) href="{{$ordersUrl(['order' => $field, 'direc' => $order === $field && $sortDirection === 'asc' ? 'desc' : 'asc'])}}">{{$label}}</a>
                            @endforeach
                        </div>
                    </details>
                </div>
            </div>

            <div class="input-group scom-orders-search">
                <input name="search" value="{{request()->search ?? ''}}" type="search" class="form-control scom-input seiger__search" placeholder="@lang('sCommerce::global.search_orders')" aria-label="@lang('sCommerce::global.search_orders')" />
                <button class="scom-submit-search" type="button" aria-label="@lang('sCommerce::global.search_orders')">@svg('tabler-search')</button>
                <button class="scom-clear-search" type="button" aria-label="@lang('sCommerce::global.search_orders')">@svg('tabler-x')</button>
            </div>
        </div>
    </section>

    <div class="scom-orders-bulk-bar" data-orders-bulk-bar role="toolbar" aria-label="@lang('sCommerce::global.bulk_actions')" hidden>
        <div class="scom-orders-bulk-bar__selection" role="status" aria-live="polite">
            <span class="scom-orders-bulk-bar__check" aria-hidden="true">@svg('tabler-check')</span>
            <span>@lang('sCommerce::global.selected'):</span>
            <strong data-orders-selected-count>0</strong>
        </div>
        <div class="scom-orders-bulk-bar__actions">
            <details class="scom-orders-filter scom-orders-bulk-status" data-orders-bulk-status>
                <summary class="scom-orders-bulk-bar__status">@lang('sCommerce::global.change_status') @svg('tabler-chevron-down')</summary>
                <form class="scom-orders-filter-menu scom-orders-choice-menu" action="{{sCommerce::moduleUrl()}}&amp;get=ordersBulkStatus" method="post" data-orders-bulk-form
                      data-confirm="@lang('sCommerce::global.bulk_status_confirm')" data-error="@lang('sCommerce::global.bulk_status_request_error')"
                      data-confirm-title="@lang('sCommerce::global.change_status')" data-confirm-ok="@lang('sCommerce::global.change_status')" data-confirm-cancel="@lang('global.cancel')"
                      data-session-error="@lang('sCommerce::global.bulk_status_session_expired')" data-pending="@lang('sCommerce::global.bulk_status_pending')">
                    @csrf
                    <strong class="scom-orders-choice-menu__title">@lang('sCommerce::global.status')</strong>
                    <div class="scom-orders-choice-menu__options">
                        @foreach(sOrder::listOrderStatuses() as $statusId => $statusLabel)
                            @if((int) $statusId !== sOrder::ORDER_STATUS_DELETED)
                                <label class="scom-orders-choice">
                                    <input type="radio" name="status" value="{{$statusId}}" data-status-label="{{$statusLabel}}" required>
                                    <span class="scom-orders-choice__box" aria-hidden="true">@svg('tabler-check')</span>
                                    <span class="scom-orders-choice__label">@include('sCommerce::partials.orderStatus', ['status' => $statusId, 'orderStatusLabel' => $statusLabel])</span>
                                </label>
                            @endif
                        @endforeach
                    </div>
                    <div class="scom-orders-choice-menu__actions">
                        <button class="scom-orders-filter-action scom-orders-filter-action--reset" type="button" data-bulk-status-cancel title="@lang('sCommerce::global.cancel_selection')" aria-label="@lang('sCommerce::global.cancel_selection')">@svg('tabler-x')</button>
                        <button class="scom-orders-filter-action scom-orders-filter-action--apply" type="submit" disabled title="@lang('sCommerce::global.change_status')" aria-label="@lang('sCommerce::global.change_status')">@svg('tabler-check')</button>
                    </div>
                </form>
            </details>
            <button class="scom-orders-bulk-bar__button" type="button" data-orders-bulk-export
                    data-url="{{sCommerce::moduleUrl()}}&amp;get=ordersBulkExport"
                    data-pending="@lang('sCommerce::global.bulk_export_pending')" data-success="@lang('sCommerce::global.bulk_export_success')"
                    data-error="@lang('sCommerce::global.bulk_export_error')" data-session-error="@lang('sCommerce::global.bulk_status_session_expired')">@lang('sCommerce::global.export')</button>
            <details class="scom-orders-filter scom-orders-bulk-menu" data-orders-bulk-menu
                     data-url="{{sCommerce::moduleUrl()}}&amp;get=ordersBulkMenu"
                     data-error="@lang('sCommerce::global.bulk_status_request_error')" data-session-error="@lang('sCommerce::global.bulk_status_session_expired')"
                     data-pending="@lang('sCommerce::global.bulk_menu_pending')" data-popup-error="@lang('sCommerce::global.bulk_print_blocked')"
                     data-cancel="@lang('global.cancel')">
                <summary class="scom-orders-bulk-bar__button scom-orders-bulk-bar__more" aria-label="@lang('sCommerce::global.additional_actions')">@svg('tabler-dots-vertical')</summary>
                <div class="scom-orders-filter-menu scom-orders-bulk-menu__items">
                    <button type="button" data-bulk-action="paid" data-confirm="@lang('sCommerce::global.bulk_paid_confirm')">@svg('tabler-credit-card') @lang('sCommerce::global.bulk_paid')</button>
                    <button type="button" data-bulk-action="print">@svg('tabler-printer') @lang('sCommerce::global.bulk_print')</button>
                    <button type="button" class="scom-orders-bulk-menu__delete" data-bulk-action="delete" data-confirm="@lang('sCommerce::global.bulk_delete_confirm')">@svg('tabler-trash') @lang('sCommerce::global.bulk_delete')</button>
                </div>
            </details>
        </div>
        <button class="scom-orders-bulk-bar__cancel" type="button" data-orders-selection-clear>@lang('sCommerce::global.cancel_selection')</button>
        <div class="scom-orders-bulk-message" data-orders-bulk-message role="status" aria-live="polite" hidden></div>
    </div>

    <section class="scom-orders-table-panel">
        <div class="table-responsive seiger__module-table scom-orders-table-wrap">
            <table class="table table-condensed table-hover sectionTrans scom-table scom-orders-table">
                <thead>
                    <tr>
                        <th class="scom-orders-cell--select"><label class="scom-orders-checkbox"><input type="checkbox" data-orders-select-all aria-label="@lang('sCommerce::global.select_all_orders')"><span>@svg('tabler-check')</span></label></th>
                        @foreach($sortableColumns as $field => $label)
                            <th @class(['sorting', 'sorted' => $order === $field, 'scom-orders-cell--sum' => $field === 'cost']) data-order="{{$field}}" aria-sort="{{$order === $field ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'}}"><button type="button" class="seiger-sort-btn">{{$label}} @svg('tabler-selector')</button></th>
                        @endforeach
                        <th>@lang('sCommerce::global.delivery')</th>
                        <th id="action-btns">@lang('global.onlineusers_action')</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    @php
                        $domain = $domains?->get($item->domain);
                        $phone = $formatPhone($item->user_info['phone'] ?? '');
                        $client = preg_replace('/\s+/u', ' ', trim(html_entity_decode(implode(' ', [$item->user_info['first_name'] ?? '', $item->user_info['middle_name'] ?? '', $item->user_info['last_name'] ?? '']), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                        $createdDate = $item->created_at?->format('d.m.Y') ?? '';
                        $createdTime = $item->created_at?->format('H:i') ?? '';
                        $formattedCost = sCommerce::convertPrice($item->cost, $item->currency) . (($currencies[$item->currency]['show'] ?? 0) == 0 ? ' ' . $item->currency : '');
                        $delivery = $deliveryDisplay($item);
                    @endphp
                    <tr id="order-{{$item->id}}">
                        <td class="scom-orders-cell--select"><label class="scom-orders-checkbox"><input type="checkbox" value="{{$item->id}}" data-order-select aria-label="@lang('sCommerce::global.select_order', ['number' => $item->order_number ?? $item->id])"><span>@svg('tabler-check')</span></label></td>
                        <td class="scom-orders-cell--reference">@if($domain)<span class="scom-orders-domain-dot" aria-label="{{$domain->domain}}" role="img" title="{{$domain->domain}}" style="--domain-color: {{$domain->site_color ?: '#60a5fa'}}"></span>@endif<span class="scom-orders-order-reference"><a class="scom-orders-order-number" href="{!!sCommerce::moduleUrl()!!}&get=order&i={{$item->id}}">#{{$item->order_number ?? $item->id}}</a>@if($item->is_quick)<span class="scom-orders-quick" title="@lang('sCommerce::global.one_click')">@svg('tabler-bolt-filled')</span>@endif</span></td>
                        <td class="scom-orders-cell--client"><span class="scom-orders-client">{{$client}}</span>@if($phone !== '')<span class="scom-orders-phone">{{$phone}}</span>@endif</td>
                        <td class="scom-orders-cell--created"><span class="scom-orders-mobile-icon">@svg('tabler-calendar')</span><span class="scom-orders-date"><span>{{$createdDate}}</span> <span>{{$createdTime}}</span></span></td>
                        <td class="scom-orders-cell--sum"><span class="scom-orders-mobile-icon">@svg('tabler-shopping-cart')</span><span>{{$formattedCost}}</span></td>
                        <td class="scom-orders-cell--status">
                            @include('sCommerce::partials.orderStatus', ['status' => (int) $item->status])
                            <span @class(['scom-orders-payment', 'scom-orders-payment--stacked', $paymentClass((int) $item->payment_status)])>{{sOrder::getPaymentStatusName($item->payment_status)}}</span>
                        </td>
                        <td class="scom-orders-cell--payment"><span @class(['scom-orders-payment', $paymentClass((int) $item->payment_status)])>{{sOrder::getPaymentStatusName($item->payment_status)}}</span></td>
                        <td class="scom-orders-cell--delivery"><span class="scom-orders-delivery-name">{{$delivery['name']}}</span>@if($delivery['tracking'] !== '')<span class="scom-orders-delivery-tracking">{{$delivery['tracking']}}</span>@endif</td>
                        <td class="scom-orders-cell--actions"><span class="scom-orders-actions"><a class="scom-orders-edit" href="{!!sCommerce::moduleUrl()!!}&get=order&i={{$item->id}}{{request()->has('page') ? '&page=' . request()->page : ''}}" title="@lang('global.edit')" aria-label="@lang('global.edit')">@svg('tabler-pencil')</a><details class="scom-order-actions-menu"><summary title="@lang('sCommerce::global.additional_actions')" aria-label="@lang('sCommerce::global.additional_actions')">@svg('tabler-dots-vertical')</summary><div class="scom-order-actions-menu__popup"><button type="button" disabled title="@lang('sCommerce::global.action_not_available')">@lang('sCommerce::global.repeat_order')</button><button type="button" disabled title="@lang('sCommerce::global.action_not_available')">@lang('sCommerce::global.payment_link')</button>@if (evo()->hasPermission('settings'))<button class="scom-orders-delete" type="button" data-href="{!!sCommerce::moduleUrl()!!}&get=orderDelete&i={{$item->id}}" data-delete="{{$item->id}}" data-name="#{{$item->id}}">@lang('global.remove')</button>@endif</div></details></span></td>
                    </tr>
                @empty
                    <tr><td class="scom-orders-empty" colspan="8">@lang('sCommerce::global.orders_not_found')</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="seiger__bottom scom-orders-bottom">
            <div class="paginator">{{$items->render()}}</div>
            <div class="seiger__list">
                <span class="seiger__label">@lang('sCommerce::global.items_on_page')</span>
                <div class="dropdown">
                    <button class="dropdown__title" type="button"><span data-actual="{{(int) request()->cookie('scom_orders_page_items', 50)}}"></span>@svg('tabler-chevron-down')</button>
                    <ul class="dropdown__menu">
                        @foreach([50, 100, 150, 200] as $size)<li class="dropdown__menu-item"><a class="dropdown__menu-link" data-items="{{$size}}" href="{{$ordersUrl()}}">{{$size}}</a></li>@endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>
</div>
<script>
    window.cookieName = 'scom_orders_page_items';

    document.querySelectorAll('.scom-orders-filter').forEach(function (filter) {
        filter.addEventListener('toggle', function () {
            if (!filter.open) return;

            document.querySelectorAll('.scom-orders-filter[open]').forEach(function (openedFilter) {
                if (openedFilter !== filter) openedFilter.open = false;
            });
        });
    });

    const selectAllOrders = document.querySelector('[data-orders-select-all]');
    const orderSelections = Array.from(document.querySelectorAll('[data-order-select]'));
    const bulkBar = document.querySelector('[data-orders-bulk-bar]');
    const selectedCount = document.querySelector('[data-orders-selected-count]');
    const clearSelection = document.querySelector('[data-orders-selection-clear]');
    const updateOrderSelection = function () {
        const selected = orderSelections.filter(function (input) { return input.checked; });
        if (selectedCount) selectedCount.textContent = String(selected.length);
        if (bulkBar) bulkBar.hidden = selected.length === 0;
        if (selectAllOrders) {
            selectAllOrders.checked = orderSelections.length > 0 && selected.length === orderSelections.length;
            selectAllOrders.indeterminate = selected.length > 0 && selected.length < orderSelections.length;
        }
        document.dispatchEvent(new CustomEvent('scommerce:order-selection-changed'));
    };
    if (selectAllOrders) selectAllOrders.addEventListener('change', function () {
        orderSelections.forEach(function (input) { input.checked = selectAllOrders.checked; });
        updateOrderSelection();
    });
    orderSelections.forEach(function (input) { input.addEventListener('change', updateOrderSelection); });
    if (clearSelection) clearSelection.addEventListener('click', function () {
        orderSelections.forEach(function (input) { input.checked = false; });
        updateOrderSelection();
    });
    updateOrderSelection();

    document.querySelectorAll('.scom-order-actions-menu').forEach(function (menu) {
        menu.addEventListener('toggle', function () {
            if (!menu.open) return;
            document.querySelectorAll('.scom-order-actions-menu[open]').forEach(function (openedMenu) {
                if (openedMenu !== menu) openedMenu.open = false;
            });
        });
    });
    document.addEventListener('click', function (event) {
        document.querySelectorAll('.scom-order-actions-menu[open]').forEach(function (menu) {
            if (!menu.contains(event.target)) menu.open = false;
        });
    });

    document.querySelectorAll('.scom-orders-choice-menu').forEach(function (menu) {
        const params = (menu.dataset.filterParams || '').split(',').filter(Boolean);

        const navigate = function (reset) {
            const url = new URL(window.location.href);
            params.forEach(function (param) {
                url.searchParams.delete(param);
            });

            if (!reset) {
                params.forEach(function (param) {
                    const values = Array.from(menu.querySelectorAll('input[data-filter-param="' + param + '"]:checked'))
                        .map(function (input) { return input.value; })
                        .filter(Boolean);
                    if (values.length) {
                        url.searchParams.set(param, values.join(','));
                    }
                });
            }

            url.searchParams.delete('page');
            window.location.assign(url.toString());
        };

        const applyButton = menu.querySelector('[data-filter-apply]');
        const resetButton = menu.querySelector('[data-filter-reset]');
        if (applyButton) applyButton.addEventListener('click', function () { navigate(false); });
        if (resetButton) resetButton.addEventListener('click', function () { navigate(true); });
    });

    document.querySelectorAll('[data-date-filter]').forEach(function (menu) {
        const navigate = function (reset) {
            const url = new URL(window.location.href);
            ['date_from', 'date_to'].forEach(function (param) {
                url.searchParams.delete(param);
                const input = menu.querySelector('[data-date-param="' + param + '"]');
                if (!reset && input && input.value) {
                    url.searchParams.set(param, input.value);
                }
            });
            url.searchParams.delete('page');
            window.location.assign(url.toString());
        };

        const applyButton = menu.querySelector('[data-date-apply]');
        const resetButton = menu.querySelector('[data-date-reset]');
        menu.querySelectorAll('[data-date-preset]').forEach(function (preset) {
            preset.addEventListener('click', function () {
                const dateFrom = menu.querySelector('[data-date-param="date_from"]');
                const dateTo = menu.querySelector('[data-date-param="date_to"]');
                if (dateFrom) dateFrom.value = preset.dataset.dateFrom || '';
                if (dateTo) dateTo.value = preset.dataset.dateTo || '';
                navigate(false);
            });
        });
        if (applyButton) applyButton.addEventListener('click', function () { navigate(false); });
        if (resetButton) resetButton.addEventListener('click', function () { navigate(true); });
    });
</script>
@include('sCommerce::scripts.ordersBulkStatus')
@include('sCommerce::scripts.ordersBulkExport')
@include('sCommerce::scripts.ordersBulkMenu')
