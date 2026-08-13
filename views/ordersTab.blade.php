@use(Seiger\sCommerce\Models\sOrder)
@php $order = request()->has('order') ? request()->input('order') : 'id'; @endphp
@php $currencies = sCommerce::config('currencies', []); @endphp
<style>
    .scom-orders-page { color:#344054; padding:4px 0 22px; }
    .scom-orders-toolbar, .scom-orders-filters, .scom-orders-table-panel { background:#fff; border:1px solid #dde3ea; border-radius:0; box-shadow:0 4px 14px rgba(38,50,56,.05); }
    .scom-orders-toolbar { align-items:center; display:flex; gap:0; height:52px; margin-bottom:16px; padding:0 43px; }
    .scom-orders-summary { align-items:center; display:flex; flex:1 1 auto; flex-wrap:nowrap; min-width:0; }
    .scom-orders-summary__item { align-items:center; color:#344054; display:inline-flex; font-size:14px; font-weight:700; gap:10px; height:30px; line-height:1.2; padding:0 20px; position:relative; white-space:nowrap; }
    .scom-orders-summary__item:first-child { padding-left:0; }
    .scom-orders-summary__item + .scom-orders-summary__item:before { background:#d8e0ec; content:''; left:0; position:absolute; top:0; bottom:0; width:1px; }
    .scom-orders-summary__label { color:#344054; }
    .scom-orders-summary__value { color:#036efe; font-weight:700; }
    .scom-orders-summary__item--new:after, .scom-orders-summary__item--working:after, .scom-orders-summary__item--completed:after { border-radius:50%; content:''; height:7px; left:20px; position:absolute; top:calc(50% - 4px); width:7px; }
    .scom-orders-summary__item--new { padding-left:34px; }
    .scom-orders-summary__item--working { padding-left:34px; }
    .scom-orders-summary__item--completed { padding-left:34px; }
    .scom-orders-summary__item--new:after { background:var(--brand-pink, #EF4B67); }
    .scom-orders-summary__item--working:after { background:var(--brand-orange, #fd7e14); }
    .scom-orders-summary__item--completed:after { background:var(--brand-green, #009891); }
    .scom-orders-search { flex:0 0 590px; margin-left:auto; position:relative; width:590px; }
    .scom-orders-search .form-control { border-color:#cbd7e6; border-radius:4px !important; box-shadow:none; height:38px; padding-left:40px; padding-right:64px; }
    .scom-orders-search .input-group-append { left:0; position:absolute; top:0; z-index:3; }
    .scom-orders-search .scom-submit-search { background:transparent; border:0; border-radius:4px !important; color:#8b9bb5; height:38px; padding:0 12px; }
    .scom-orders-search .scom-submit-search svg { height:18px; width:18px; }
    .scom-orders-search .scom-clear-search { align-items:center; display:flex; height:38px; position:absolute; right:8px; top:0; z-index:3; }
    .scom-orders-search .scom-clear-search svg { height:20px; width:20px; }
    .scom-orders-filters { align-items:center; display:flex; flex-wrap:wrap; gap:10px; height:52px; margin-bottom:16px; padding:0 43px; }
    .scom-orders-filters .btn { align-items:center; background:#fff; border:1px solid #cbd7e6; border-radius:4px; color:#344054; display:inline-flex; font-size:14px; font-weight:600; height:32px; margin:0; padding:0 14px; }
    .scom-orders-filters .btn.btn-info { background:#036efe; border-color:#036efe; color:#fff; }
    .scom-orders-status-filter { --status-color:#64748b; }
    .scom-orders-status-filter:before { background:var(--status-color); border-radius:50%; content:''; height:7px; margin-right:8px; width:7px; }
    .scom-orders-status-filter.is-active { background:var(--status-color); border-color:var(--status-color); color:#fff; }
    .scom-orders-status-filter.is-active:before { background:#fff; }
    .scom-orders-status-filter--new { --status-color:var(--brand-pink, #EF4B67); }
    .scom-orders-status-filter--working { --status-color:var(--brand-orange, #fd7e14); }
    .scom-orders-status-filter--completed { --status-color:var(--brand-green, #009891); }
    .scom-orders-status-filter--cancelled { --status-color:var(--brand-cancelled, #64748b); }
    .scom-orders-filters__label { border-left:1px solid #cbd7e6; color:#344054; font-size:15px; font-weight:600; margin-left:10px; padding-left:28px; }
    .scom-orders-domain-filter { align-items:center; background:#fff; border:1px solid #cbd7e6; border-radius:4px; color:#344054; display:inline-flex; font-size:14px; font-weight:600; gap:8px; height:32px; padding:0 14px; }
    .scom-orders-domain-filter:hover { background:#f8fafc; color:#1d4ed8; }
    .scom-orders-domain-dot { background:var(--domain-color, #60a5fa); border-radius:50%; display:inline-block; flex:0 0 auto; height:9px; width:9px; }
    .scom-orders-domain-filter.is-active { background:var(--domain-color, #036efe); border-color:var(--domain-color, #036efe); color:#fff; }
    .scom-orders-domain-filter.is-active:hover { color:#fff; }
    .scom-orders-domain-filter.is-active .scom-orders-domain-dot { background:#fff; }
    .scom-orders-table-panel { margin:0; overflow:hidden; width:100%; }
    .scom-orders-table { border:0; margin-bottom:0; }
    .scom-orders-table th, .scom-orders-table td { border:0 !important; vertical-align:middle; }
    .scom-orders-table { font-size:13px; }
    .scom-orders-table thead th { border-bottom:2px solid #8aa0c3 !important; color:#475467; font-size:12px; font-weight:700; padding:8px 12px; }
    .scom-orders-table thead th:last-child { text-align:center; }
    .scom-orders-table tbody td { color:#475467; font-size:13px; padding:6px 12px; }
    .scom-orders-table tbody tr { border-bottom:1px solid #e7edf5; }
    .scom-orders-table tbody tr:last-child { border-bottom:0; }
    .scom-orders-bottom { align-items:center; background:#fff; border:1px solid #dde3ea; border-radius:0; box-shadow:0 4px 14px rgba(38,50,56,.05); display:flex; justify-content:space-between; margin:0; min-height:52px; padding:0 43px; }
    .scom-orders-bottom > * { flex:0 1 auto; }
    .scom-orders-bottom .paginator { display:flex; justify-content:center; }
    .scom-orders-bottom .seiger__list { display:flex; align-items:center; margin-left:auto; }
    .scom-orders-bottom .seiger__label { color:#718096; font-size:12px; font-weight:400; line-height:1.2; margin-right:8px; }
    .scom-orders-bottom .dropdown .dropdown__title { border-color:#cbd7e6; border-radius:4px; padding:6px 10px; }
    .scom-orders-bottom .dropdown .dropdown__title span { color:#344054; font-size:12px; font-weight:600; }
    .scom-orders-order-reference { align-items:center; display:inline-flex; }
    .scom-orders-order-number { color:#036efe; font-weight:700; }
    .scom-orders-quick { color:#1476dd; display:inline-flex; margin-left:5px; }
    .scom-orders-quick svg { height:14px; width:14px; }
    .scom-orders-client { color:#475467; display:block; line-height:1.2; }
    .scom-orders-phone { color:#344054; display:block; font-size:12px; font-weight:700; line-height:1.2; }
    .scom-orders-payment { color:#718096; font-size:11px; white-space:nowrap; }
    .scom-orders-payment:before { background:#ffb30f; border-radius:50%; content:''; display:inline-block; height:7px; margin:0 6px 1px 0; width:7px; }
    .scom-orders-payment--paid:before { background:#28ad63; }
    .scom-orders-payment--failed:before { background:#ec4a5e; }
    .scom-orders-actions { align-items:center; display:inline-flex; gap:4px; position:relative; }
    .scom-orders-edit { align-items:center; color:#1476dd; display:inline-flex; padding:3px 7px; }
    .scom-orders-edit svg { height:16px; width:16px; }
    .scom-orders-delete { align-items:center; color:#ef4444; cursor:pointer; display:inline-flex; padding:3px 7px; }
    .scom-orders-delete svg { height:16px; width:16px; }
    @media (max-width:1200px) { .scom-orders-toolbar { height:auto; min-height:52px; padding:8px 43px; } .scom-orders-summary { flex-wrap:wrap; } .scom-orders-search { flex:1 1 100%; margin-top:8px; width:auto; } }
    @media (max-width:992px) { .scom-orders-page { padding-left:0; padding-right:0; } .scom-orders-toolbar { padding:12px; } .scom-orders-summary__item { font-size:15px; padding:0 12px; } .scom-orders-summary__item + .scom-orders-summary__item:before { top:6px; bottom:6px; } .scom-orders-filters { height:auto; min-height:52px; padding:10px 12px; } .scom-orders-filters__label { margin-left:0; padding-left:16px; } .scom-orders-table thead th, .scom-orders-table tbody td { padding-left:8px; padding-right:8px; } .scom-orders-bottom { padding:0 12px; } }
</style>
@php
    $formatPhone = static function ($phone): string {
        $phone = trim((string) $phone);
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 12 && substr($digits, 0, 3) === '380') return sprintf('+38 (%s) %s-%s-%s', substr($digits, 2, 3), substr($digits, 5, 3), substr($digits, 8, 2), substr($digits, 10, 2));
        if (strlen($digits) === 10 && substr($digits, 0, 1) === '0') return sprintf('+38 (%s) %s-%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 2), substr($digits, 8, 2));
        return $phone;
    };
    $paymentClass = static function (int $status): string {
        if (in_array($status, [sOrder::PAYMENT_STATUS_PAID, sOrder::PAYMENT_STATUS_PARTIALLY_PAID], true)) return 'scom-orders-payment--paid';
        if (in_array($status, [sOrder::PAYMENT_STATUS_FAILED, sOrder::PAYMENT_STATUS_CANCELED, sOrder::PAYMENT_STATUS_REJECTED, sOrder::PAYMENT_STATUS_EXPIRED, sOrder::PAYMENT_STATUS_DISPUTED], true)) return 'scom-orders-payment--failed';
        return '';
    };
@endphp
<div class="scom-orders-page">
<div class="scom-orders-toolbar">
    <div class="scom-orders-summary">
        <span class="scom-orders-summary__item"><span class="scom-orders-summary__label">@lang('sCommerce::global.orders')</span><span class="scom-orders-summary__value">{{$total ?? 0}}</span></span>
        <span class="scom-orders-summary__item scom-orders-summary__item--new"><span class="scom-orders-summary__label">@lang('sCommerce::global.unprocessed_orders')</span><span class="scom-orders-summary__value">{{$unprocessed ?? 0}}</span></span>
        <span class="scom-orders-summary__item scom-orders-summary__item--working"><span class="scom-orders-summary__label">@lang('sCommerce::global.working_orders')</span><span class="scom-orders-summary__value">{{$working ?? 0}}</span></span>
        <span class="scom-orders-summary__item scom-orders-summary__item--completed"><span class="scom-orders-summary__label">@lang('sCommerce::global.completed_orders')</span><span class="scom-orders-summary__value">{{$completed ?? 0}}</span></span>
    </div>
    <div class="input-group scom-orders-search">
        <input name="search"
               value="{{request()->search ?? ''}}"
               type="search"
               class="form-control rounded-left scom-input seiger__search"
               placeholder="@lang('sCommerce::global.search_orders')"
               aria-label="@lang('sCommerce::global.search_orders')"
               aria-describedby="basic-addon2" />
        <span class="scom-clear-search">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
                <path d="M22 11.2086L20.7914 10L16 14.7914L11.2086 10L10 11.2086L14.7914 16L10 20.7914L11.2086 22L16 17.2086L20.7914 22L22 20.7914L17.2086 16L22 11.2086Z" fill="#63666B"/>
            </svg>
        </span>
        <div class="input-group-append">
            <button class="btn btn-outline-secondary rounded-right scom-submit-search" type="button">@svg('tabler-search')</button>
        </div>
    </div>
</div>
<div class="scom-orders-filters">
    <a @class(['btn', 'btn-info' => $status == 0, 'btn-light' => $status != 0]) href="{!!sCommerce::moduleUrl()!!}&get=orders">@lang('sCommerce::global.all_statuses')</a>
    @foreach($statuses as $id => $name)
        <a @class([
            'btn',
            'scom-orders-status-filter',
            'scom-orders-status-filter--new' => in_array($id, $unprocessedes),
            'scom-orders-status-filter--working' => in_array($id, $workings),
            'scom-orders-status-filter--completed' => in_array($id, $completeds),
            'scom-orders-status-filter--cancelled' => in_array($id, $canceleds),
            'is-active' => $status == $id,
        ]) href="{!!sCommerce::moduleUrl()!!}&get=orders&status={{$id}}">{{$name}}</a>
    @endforeach
    @if($domains)
        <span class="scom-orders-filters__label">@lang('sCommerce::global.domains')</span>
        <a @class(['btn', 'scom-orders-domain-filter', 'btn-info' => !request()->filled('domain')]) href="{!!sCommerce::moduleUrl()!!}&get=orders">@lang('sCommerce::global.all_domains')</a>
        @foreach($domains as $domain)
            <a @class(['btn', 'scom-orders-domain-filter', 'is-active' => request()->input('domain') === $domain->key]) href="{!!sCommerce::moduleUrl()!!}&get=orders&domain={{$domain->key}}" style="--domain-color: {{$domain->site_color ?: '#60a5fa'}}"><span class="scom-orders-domain-dot"></span>{{$domain->domain}}</a>
        @endforeach
    @endif
</div>
<div class="table-responsive seiger__module-table scom-orders-table-panel">
    <table class="table table-condensed table-hover sectionTrans scom-table scom-orders-table">
        <thead>
            <tr>
                <th class="sorting @if($order == 'id') sorted @endif" data-order="id">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.number') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
                <th class="sorting @if($order == 'client') sorted @endif" data-order="client">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.client') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
                <th class="sorting @if($order == 'created_at') sorted @endif" data-order="created_at">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.created') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
                <th class="sorting @if($order == 'cost') sorted @endif" data-order="cost">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.sum') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
                <th class="sorting @if($order == 'status') sorted @endif" data-order="status">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.status') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
                <th class="sorting @if($order == 'payment_status') sorted @endif" data-order="payment_status">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.payment') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
                <th id="action-btns">@lang('global.onlineusers_action')</th>
            </tr>
        </thead>
        <tbody>
        @foreach($items as $item)
            @php
                $domain = $domains?->get($item->domain);
                $phone = $formatPhone($item->user_info['phone'] ?? '');
            @endphp
            <tr id="order-{{$item->id}}">
                <td>@if($domain)<span class="scom-orders-domain-dot" aria-label="{{$domain->domain}}" role="img" title="{{$domain->domain}}" style="--domain-color: {{$domain->site_color ?: '#60a5fa'}}"></span>@endif <span class="scom-orders-order-reference"><a class="scom-orders-order-number" href="{!!sCommerce::moduleUrl()!!}&get=order&i={{$item->id}}">#{{$item->order_number ?? $item->id}}</a>@if($item->is_quick)<span class="scom-orders-quick" title="@lang('sCommerce::global.one_click')">@svg('tabler-bolt-filled')</span>@endif</span></td>
                <td>
                    <span class="scom-orders-client">{{preg_replace('/\s+/u', ' ', trim(html_entity_decode(implode(' ', [$item->user_info['first_name'] ?? '', $item->user_info['middle_name'] ?? '', $item->user_info['last_name'] ?? '']), ENT_QUOTES | ENT_HTML5, 'UTF-8')))}} </span>
                    @if($phone !== '')<span class="scom-orders-phone">{{$phone}}</span>@endif
                </td>
                <td>{{$item->created_at}}</td>
                <td>{{sCommerce::convertPrice($item->cost, $item->currency)}}@if(($currencies[$item->currency]['show'] ?? 0) == 0) {{$item->currency}}@endif</td>
                <td>
                    <span @class(['badge', 'bg-disactive' => in_array($item->status, $unprocessedes), 'bg-progress' => in_array($item->status, $workings), 'bg-active' => in_array($item->status, $completeds), 'bg-cancelled' => in_array($item->status, $canceleds)])>
                        {{sOrder::getOrderStatusName($item->status)}}
                    </span>
                </td>
                <td><span @class(['scom-orders-payment', $paymentClass((int) $item->payment_status)])>{{sOrder::getPaymentStatusName($item->payment_status)}}</span></td>
                <td style="text-align:center;"><span class="scom-orders-actions"><a class="scom-orders-edit" href="{!!sCommerce::moduleUrl()!!}&get=order&i={{$item->id}}{{request()->has('page') ? '&page=' . request()->page : ''}}" title="@lang('global.edit')" aria-label="@lang('global.edit')">@svg('tabler-pencil')</a>@if (evo()->hasPermission('settings'))<span class="scom-orders-delete" data-href="{!!sCommerce::moduleUrl()!!}&get=orderDelete&i={{$item->id}}" data-delete="{{$item->id}}" data-name="#{{$item->id}}" title="@lang('global.remove')" role="button" aria-label="@lang('global.remove')">@svg('tabler-trash')</span>@endif</span>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="seiger__bottom scom-orders-bottom">
    <div class="seiger__bottom-item"></div>
    <div class="paginator">{{$items->render()}}</div>
    <div class="seiger__list">
        <span class="seiger__label">@lang('sCommerce::global.items_on_page')</span>
        <div class="dropdown">
            <button class="dropdown__title">
                <span data-actual="50"></span>
                <i>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M7.77723 11.7772L2 6H13.5545L7.77723 11.7772Z" fill="#036EFE" />
                    </svg>
                </i>
            </button>
            <ul class="dropdown__menu">
                <li class="dropdown__menu-item">
                    <a class="dropdown__menu-link" data-items="50" href="{!!sCommerce::moduleUrl()!!}&get=orders">50</a>
                </li>
                <li class="dropdown__menu-item">
                    <a class="dropdown__menu-link" data-items="100" href="{!!sCommerce::moduleUrl()!!}&get=orders">100</a>
                </li>
                <li class="dropdown__menu-item">
                    <a class="dropdown__menu-link" data-items="150" href="{!!sCommerce::moduleUrl()!!}&get=orders">150</a>
                </li>
                <li class="dropdown__menu-item">
                    <a class="dropdown__menu-link" data-items="200" href="{!!sCommerce::moduleUrl()!!}&get=orders">200</a>
                </li>
            </ul>
        </div>
    </div>
</div>
</div>
