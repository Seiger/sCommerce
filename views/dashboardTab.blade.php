@php use Seiger\sCommerce\Models\sOrder; @endphp
<style>
    .scom-dashboard { color:#475467; padding:4px 0 20px; }
    .scom-dashboard__summary, .scom-dashboard__card { background:#fff; border:1px solid #dde3ea; border-radius:0; box-shadow:0 4px 14px rgba(38,50,56,.05); }
    .scom-dashboard__summary { display:grid; grid-template-columns:repeat(4, 1fr); margin-bottom:20px; overflow:hidden; }
    .scom-dashboard__metric { min-width:0; display:flex; gap:13px; align-items:flex-start; padding:15px 20px; position:relative; }
    .scom-dashboard__metric + .scom-dashboard__metric:before { content:''; width:1px; background:#e7ebf0; position:absolute; left:0; top:14px; bottom:14px; }
    .scom-dashboard__metric-icon { width:22px; height:22px; flex:0 0 22px; stroke-width:2; margin:1px 3px 0; }
    .scom-dashboard__metric--orders .scom-dashboard__metric-icon { color:#ec4a5e; }
    .scom-dashboard__metric--revenue .scom-dashboard__metric-icon { color:#24aa5c; }
    .scom-dashboard__metric--products .scom-dashboard__metric-icon { color:#129fc1; }
    .scom-dashboard__metric--payments .scom-dashboard__metric-icon { color:#f5ad10; }
    .scom-dashboard__metric-label { display:block; color:#344054; font-size:14px; font-weight:700; line-height:1.2; }
    .scom-dashboard__metric-value { display:inline-block; margin-left:4px; color:#344054; font-size:14px; font-weight:700; }
    .scom-dashboard__metric-detail { display:block; color:#667085; font-size:12px; line-height:1.45; margin-top:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .scom-dashboard__grid { display:grid; grid-template-columns:minmax(0, 2fr) minmax(360px, 1fr); gap:20px; margin-bottom:20px; }
    .scom-dashboard__card { padding:18px 20px; min-width:0; }
    .scom-dashboard__card-head { display:flex; align-items:center; justify-content:space-between; gap:15px; border-bottom:1px solid #e7ebf0; padding-bottom:11px; margin-bottom:8px; }
    .scom-dashboard__card-title { color:#1f2937; font-size:16px; font-weight:700; margin:0; }
    .scom-dashboard__link { color:#1476dd; font-size:12px; font-weight:600; white-space:nowrap; }
    .scom-dashboard__link:hover { color:#075db6; text-decoration:none; }
    .scom-dashboard__link-icon { display:inline-block; width:14px; height:14px; margin-left:3px; vertical-align:-2px; }
    .scom-dashboard__table { width:100%; margin:0; border-collapse:collapse; font-size:13px; table-layout:fixed; }
    .scom-dashboard__table th { border:0; color:#475467; font-size:12px; font-weight:700; padding:8px; }
    .scom-dashboard__table td { border:0; color:#475467; padding:6px 8px; vertical-align:middle; }
    .scom-dashboard__table tbody tr { border-bottom:1px solid #edf0f4; }
    .scom-dashboard__table tbody tr:last-child { border-bottom:0; }
    .scom-dashboard__order-number { color:#1476dd; font-weight:700; }
    .scom-dashboard__client { display:block; max-width:245px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .scom-dashboard__client-phone { color:#344054; display:block; font-size:12px; font-weight:700; line-height:1.2; }
    .scom-dashboard__payment { color:#718096; font-size:11px; white-space:nowrap; }
    .scom-dashboard__payment:before { background:#ffb30f; border-radius:50%; content:''; display:inline-block; height:7px; margin-right:6px; width:7px; }
    .scom-dashboard__payment--paid:before { background:#28ad63; }
    .scom-dashboard__payment--failed:before { background:#ec4a5e; }
    .scom-dashboard__payment--refunded:before { background:#98a2b3; }
    .scom-dashboard__products { list-style:none; padding:0; margin:0; }
    .scom-dashboard__product { display:grid; grid-template-columns:46px minmax(0, 1fr) auto; gap:11px; padding:11px 0; border-bottom:1px solid #edf0f4; align-items:center; }
    .scom-dashboard__product:last-child { border-bottom:0; }
    .scom-dashboard__product-image { background:#f7f9fb; border-radius:5px; height:42px; overflow:hidden; width:42px; }
    .scom-dashboard__product-image img { height:100%; object-fit:contain; width:100%; }
    .scom-dashboard__product-name { color:#344054; display:block; font-size:13px; font-weight:700; line-height:1.35; }
    .scom-dashboard__product-meta { color:#667085; display:flex; font-size:12px; gap:9px; margin-top:4px; }
    .scom-dashboard__product-meta-icon { width:13px; height:13px; margin-right:2px; vertical-align:-2px; }
    .scom-dashboard__product-revenue { color:#475467; font-size:13px; font-weight:700; text-align:right; white-space:nowrap; }
    .scom-dashboard__chart-card { padding:0 20px 15px; }
    .scom-dashboard__chart-head { align-items:flex-start; border-bottom:0; gap:48px; justify-content:flex-start; margin:0; padding:18px 0 6px; }
    .scom-dashboard__chart-title { color:#1f2937; font-size:16px; font-weight:700; margin:0; }
    .scom-dashboard__chart-subtitle { color:#98a2b3; font-size:11px; margin-top:29px; }
    .scom-dashboard__chart-content { align-items:flex-start; display:flex; flex:1; gap:26px; justify-content:space-between; min-width:0; }
    .scom-dashboard__chart-metrics { display:grid; flex:1; grid-template-columns:repeat(4, minmax(110px, 1fr)); gap:12px; margin:0; max-width:720px; }
    .scom-dashboard__chart-metric { color:#667085; font-size:11px; min-width:0; }
    .scom-dashboard__chart-metric strong { color:#344054; display:block; font-size:18px; line-height:1.2; margin:4px 0; }
    .scom-dashboard__chart-change { color:#98a2b3; display:block; font-size:11px; }
    .scom-dashboard__chart-change--positive { color:#24aa5c; font-weight:700; }
    .scom-dashboard__chart-change--negative { color:#ec4a5e; font-weight:700; }
    .scom-dashboard__chart-actions { align-items:flex-end; display:flex; flex:0 0 auto; flex-direction:column; gap:16px; }
    .scom-dashboard__periods { display:flex; }
    .scom-dashboard__period { border:1px solid #dde3ea; color:#475467; font-size:12px; margin-left:-1px; padding:6px 12px; }
    .scom-dashboard__period:first-child { border-radius:3px 0 0 3px; margin-left:0; }
    .scom-dashboard__period:last-child { border-radius:0 3px 3px 0; }
    .scom-dashboard__period:hover { color:#036efe; text-decoration:none; }
    .scom-dashboard__period--active { background:#036efe; border-color:#036efe; color:#fff; position:relative; z-index:1; }
    .scom-dashboard__period--active:hover { color:#fff; }
    .scom-dashboard__legend { display:flex; gap:22px; color:#667085; font-size:12px; }
    .scom-dashboard__legend-item { display:flex; align-items:center; gap:7px; }
    .scom-dashboard__legend-line { background:#1683f6; display:inline-block; height:2px; width:24px; }
    .scom-dashboard__legend-line--orders { background:#29af63; position:relative; }
    .scom-dashboard__legend-line--orders:after { background:#29af63; border-radius:50%; content:''; height:7px; left:9px; position:absolute; top:-3px; width:7px; }
    .scom-dashboard__chart { border-top:1px solid #edf0f4; height:270px; padding-top:10px; position:relative; }
    .scom-dashboard__empty { color:#98a2b3; font-size:13px; padding:30px 0; text-align:center; }
    @media (max-width:1100px) { .scom-dashboard__summary { grid-template-columns:repeat(2, 1fr); } .scom-dashboard__metric:nth-child(3):before { display:none; } .scom-dashboard__grid { grid-template-columns:1fr; } }
    @media (max-width:1100px) { .scom-dashboard__chart-head { flex-direction:column; gap:15px; } .scom-dashboard__chart-content { width:100%; justify-content:space-between; } .scom-dashboard__chart-actions { align-items:flex-start; flex-direction:row; } .scom-dashboard__chart-metrics { grid-template-columns:repeat(2, minmax(130px, 1fr)); } }
    @media (max-width:640px) { .scom-dashboard__summary { grid-template-columns:1fr; } .scom-dashboard__metric + .scom-dashboard__metric:before { display:none; } .scom-dashboard__grid { gap:12px; } .scom-dashboard__card { overflow-x:auto; padding:15px; } .scom-dashboard__chart-card { padding:0 15px 15px; } .scom-dashboard__chart-metrics { grid-template-columns:repeat(2, minmax(110px, 1fr)); gap:12px; } .scom-dashboard__chart-actions { align-items:flex-start; flex-direction:column; gap:10px; } .scom-dashboard__period { padding:6px 8px; } .scom-dashboard__chart { height:240px; } }
</style>

@php
    $periodRevenue = array_sum(array_column($salesChartData, 'revenue'));
    $periodOrders = array_sum(array_column($salesChartData, 'orders'));
    $averageOrder = $periodOrders > 0 ? $periodRevenue / $periodOrders : 0;
    $paidRate = $periodOrders > 0 ? $periodPaidOrders / $periodOrders * 100 : 0;
    $previousAverageOrder = $previousPeriodOrders > 0 ? $previousPeriodRevenue / $previousPeriodOrders : 0;
    $previousPaidRate = $previousPeriodOrders > 0 ? $previousPeriodPaidOrders / $previousPeriodOrders * 100 : 0;
    $change = static function (float $value, float $previous): ?float {
        return $previous > 0 ? round(($value - $previous) / $previous * 100, 1) : null;
    };
    $dashboardChanges = [
        'revenue' => $change($periodRevenue, (float) $previousPeriodRevenue),
        'orders' => $change((float) $periodOrders, (float) $previousPeriodOrders),
        'average' => $change($averageOrder, $previousAverageOrder),
        'paid' => $previousPeriodOrders > 0 ? round($paidRate - $previousPaidRate, 1) : null,
    ];
    $formatPhone = static function ($phone): string {
        $phone = trim((string) $phone);
        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 12 && substr($digits, 0, 3) === '380') {
            return sprintf('+38 (%s) %s-%s-%s', substr($digits, 2, 3), substr($digits, 5, 3), substr($digits, 8, 2), substr($digits, 10, 2));
        }

        if (strlen($digits) === 10 && substr($digits, 0, 1) === '0') {
            return sprintf('+38 (%s) %s-%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 2), substr($digits, 8, 2));
        }

        return $phone;
    };
    $paymentStatusClass = static function (int $status): string {
        if (in_array($status, [sOrder::PAYMENT_STATUS_PAID, sOrder::PAYMENT_STATUS_PARTIALLY_PAID], true)) {
            return 'scom-dashboard__payment--paid';
        }
        if (in_array($status, [sOrder::PAYMENT_STATUS_FAILED, sOrder::PAYMENT_STATUS_CANCELED, sOrder::PAYMENT_STATUS_REJECTED, sOrder::PAYMENT_STATUS_EXPIRED, sOrder::PAYMENT_STATUS_DISPUTED], true)) {
            return 'scom-dashboard__payment--failed';
        }
        if (in_array($status, [sOrder::PAYMENT_STATUS_REFUND_REQUESTED, sOrder::PAYMENT_STATUS_REFUNDED, sOrder::PAYMENT_STATUS_PARTIALLY_REFUNDED], true)) {
            return 'scom-dashboard__payment--refunded';
        }

        return '';
    };
@endphp

<div class="scom-dashboard">
    <section class="scom-dashboard__summary">
        <div class="scom-dashboard__metric scom-dashboard__metric--orders">
            @svg('tabler-shopping-cart', 'scom-dashboard__metric-icon')
            <div><span class="scom-dashboard__metric-label">@lang('sCommerce::global.orders')<span class="scom-dashboard__metric-value">{{$totalOrders}}</span></span><span class="scom-dashboard__metric-detail">@lang('sCommerce::global.unprocessed_orders'): {{$newOrders}} | @lang('sCommerce::global.working_orders'): {{$workingOrders}} | @lang('sCommerce::global.completed_orders'): {{$completedOrders}}</span></div>
        </div>
        <div class="scom-dashboard__metric scom-dashboard__metric--revenue">
            @svg('tabler-cash-banknote', 'scom-dashboard__metric-icon')
            <div><span class="scom-dashboard__metric-label">@lang('sCommerce::global.total_revenue')<span class="scom-dashboard__metric-value">{{sCommerce::convertPrice($totalRevenue, sCommerce::currentCurrency())}}</span></span><span class="scom-dashboard__metric-detail">@lang('sCommerce::global.today'): {{sCommerce::convertPrice($todayRevenue, sCommerce::currentCurrency())}} | @lang('sCommerce::global.this_month'): {{sCommerce::convertPrice($monthRevenue, sCommerce::currentCurrency())}}</span></div>
        </div>
        <div class="scom-dashboard__metric scom-dashboard__metric--products">
            @svg('tabler-package', 'scom-dashboard__metric-icon')
            <div><span class="scom-dashboard__metric-label">@lang('sCommerce::global.products')<span class="scom-dashboard__metric-value">{{$totalProducts}}</span></span><span class="scom-dashboard__metric-detail">@lang('sCommerce::global.publisheds'): {{$publishedProducts}} | @lang('sCommerce::global.unpublisheds'): {{$unpublishedProducts}}</span></div>
        </div>
        <div class="scom-dashboard__metric scom-dashboard__metric--payments">
            @svg('tabler-credit-card', 'scom-dashboard__metric-icon')
            <div><span class="scom-dashboard__metric-label">@lang('sCommerce::global.payments')<span class="scom-dashboard__metric-value">{{$paidOrders}}</span></span><span class="scom-dashboard__metric-detail">@lang('sCommerce::global.payment_status_paid'): {{$paidOrders}} | @lang('sCommerce::global.payment_status_pending'): {{$pendingOrders}}</span></div>
        </div>
    </section>

    <div class="scom-dashboard__grid">
        <section class="scom-dashboard__card">
            <div class="scom-dashboard__card-head"><h3 class="scom-dashboard__card-title">@lang('sCommerce::global.recent_orders')</h3><a class="scom-dashboard__link" href="{!!sCommerce::moduleUrl()!!}&get=orders">@lang('sCommerce::global.to_list_orders') @svg('tabler-arrow-right', 'scom-dashboard__link-icon')</a></div>
            <table class="scom-dashboard__table">
                <colgroup><col style="width:7%"><col style="width:31%"><col style="width:20%"><col style="width:13%"><col style="width:12%"><col style="width:17%"></colgroup>
                <thead><tr><th>#</th><th>@lang('sCommerce::global.client')</th><th>@lang('sCommerce::global.created')</th><th>@lang('sCommerce::global.sum')</th><th>@lang('sCommerce::global.status')</th><th>@lang('sCommerce::global.payment_status')</th></tr></thead>
                <tbody>
                @forelse($recentOrders as $order)
                    @php $paymentStatus = (int) $order->payment_status; $phone = $formatPhone($order->user_info['phone'] ?? ''); @endphp
                    <tr><td><a class="scom-dashboard__order-number" href="{!!sCommerce::moduleUrl()!!}&get=order&i={{$order->id}}">#{{$order->order_number ?? $order->id}}</a></td><td><span class="scom-dashboard__client">{{implode(' ', array_filter([$order->user_info['first_name'] ?? '', $order->user_info['middle_name'] ?? '', $order->user_info['last_name'] ?? ''])) ?: '—'}}</span>@if($phone !== '')<span class="scom-dashboard__client-phone">{{$phone}}</span>@endif</td><td>{{$order->created_at->format('d.m.Y H:i')}}</td><td>{{sCommerce::convertPrice($order->cost, $order->currency)}}</td><td><span @class(['badge', 'bg-disactive' => in_array($order->status, $unprocessedes), 'bg-progress' => in_array($order->status, $workings), 'bg-active' => in_array($order->status, $completeds), 'bg-cancelled' => in_array($order->status, $canceleds)])>{{sOrder::getOrderStatusName($order->status)}}</span></td><td><span @class(['scom-dashboard__payment', $paymentStatusClass($paymentStatus)])>{{sOrder::getPaymentStatusName($paymentStatus)}}</span></td></tr>
                @empty
                    <tr><td colspan="6" class="scom-dashboard__empty">@lang('sCommerce::global.no_data_found')</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section class="scom-dashboard__card">
            <div class="scom-dashboard__card-head"><h3 class="scom-dashboard__card-title">@lang('sCommerce::global.top_products')</h3><a class="scom-dashboard__link" href="{!!sCommerce::moduleUrl()!!}&get=products">@lang('sCommerce::global.to_list_products') @svg('tabler-arrow-right', 'scom-dashboard__link-icon')</a></div>
            <ul class="scom-dashboard__products">
            @forelse($topProducts as $product)
                <li class="scom-dashboard__product"><div class="scom-dashboard__product-image"><img src="{{$product['cover']}}" alt=""></div><div><span class="scom-dashboard__product-name">{{$product['title']}}</span><span class="scom-dashboard__product-meta"><span>@svg('tabler-eye', 'scom-dashboard__product-meta-icon') {{$product['views']}}</span>@if($product['sku'] !== '')<span>@lang('sCommerce::global.sku'): {{$product['sku']}}</span>@endif<span>@lang('sCommerce::global.orders'): {{$product['count']}}</span></span></div><span class="scom-dashboard__product-revenue">{{sCommerce::convertPrice($product['revenue'], sCommerce::currentCurrency())}}</span></li>
            @empty
                <li class="scom-dashboard__empty">@lang('sCommerce::global.no_data_found')</li>
            @endforelse
            </ul>
        </section>
    </div>

    <section class="scom-dashboard__card scom-dashboard__chart-card">
        <div class="scom-dashboard__card-head scom-dashboard__chart-head">
            <div><h3 class="scom-dashboard__chart-title">@lang('sCommerce::global.sales_dynamics')</h3><div class="scom-dashboard__chart-subtitle">@lang('sCommerce::global.previous_period')</div></div>
            <div class="scom-dashboard__chart-content">
                <div class="scom-dashboard__chart-metrics">
                    @foreach([
                        ['label' => __('sCommerce::global.revenue'), 'value' => sCommerce::convertPrice($periodRevenue, sCommerce::currentCurrency()), 'change' => $dashboardChanges['revenue'], 'suffix' => '%'],
                        ['label' => __('sCommerce::global.orders'), 'value' => $periodOrders . ' ' . __('sCommerce::global.pcs'), 'change' => $dashboardChanges['orders'], 'suffix' => '%'],
                        ['label' => __('sCommerce::global.average_check'), 'value' => sCommerce::convertPrice($averageOrder, sCommerce::currentCurrency()), 'change' => $dashboardChanges['average'], 'suffix' => '%'],
                        ['label' => __('sCommerce::global.paid_share'), 'value' => round($paidRate, 1) . '%', 'change' => $dashboardChanges['paid'], 'suffix' => ' в.п.'],
                    ] as $metric)
                        <div class="scom-dashboard__chart-metric"><span>{{$metric['label']}}</span><strong>{{$metric['value']}}</strong>@if($metric['change'] !== null)<span @class(['scom-dashboard__chart-change', 'scom-dashboard__chart-change--positive' => $metric['change'] > 0, 'scom-dashboard__chart-change--negative' => $metric['change'] < 0])>{{($metric['change'] > 0 ? '+' : '') . $metric['change'] . $metric['suffix']}}</span>@endif</div>
                    @endforeach
                </div>
                <div class="scom-dashboard__chart-actions">
                    <div class="scom-dashboard__periods">@foreach([7, 30, 90, 365] as $period)<a @class(['scom-dashboard__period', 'scom-dashboard__period--active' => $dashboardPeriod === $period]) href="{!!sCommerce::moduleUrl()!!}&get=dashboard&dashboard_period={{$period}}">{{$period === 365 ? __('sCommerce::global.year') : $period . ' ' . __('sCommerce::global.days')}}</a>@endforeach</div>
                    <div class="scom-dashboard__legend"><span class="scom-dashboard__legend-item"><i class="scom-dashboard__legend-line"></i>@lang('sCommerce::global.revenue')</span><span class="scom-dashboard__legend-item"><i class="scom-dashboard__legend-line scom-dashboard__legend-line--orders"></i>@lang('sCommerce::global.orders')</span></div>
                </div>
            </div>
        </div>
        <div class="scom-dashboard__chart"><canvas id="salesChart"></canvas></div>
    </section>
</div>

@push('scripts.bot')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    $(function () {
        const salesData = @json($salesChartData);
        const canvas = document.getElementById('salesChart');
        if (!canvas) return;
        new Chart(canvas.getContext('2d'), {
            type: 'line', data: { labels: salesData.map(item => item.label), datasets: [
                { label: '@lang("sCommerce::global.revenue")', data: salesData.map(item => item.revenue), borderColor: '#1683f6', backgroundColor: 'rgba(22,131,246,.08)', borderWidth: 2.5, fill: true, pointRadius: context => context.raw > 0 ? 3 : 0, pointHoverRadius: context => context.raw > 0 ? 5 : 0, tension: 0.35, yAxisID: 'revenue' },
                { label: '@lang("sCommerce::global.orders")', data: salesData.map(item => item.orders), borderColor: '#29af63', backgroundColor: 'transparent', borderWidth: 2.5, pointRadius: context => context.raw > 0 ? 3 : 0, pointHoverRadius: context => context.raw > 0 ? 5 : 0, tension: 0.35, yAxisID: 'orders' }
            ]}, options: { responsive:true, maintainAspectRatio:false, interaction:{mode:'index', intersect:false}, plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label: context => context.dataset.yAxisID === 'revenue' ? context.dataset.label + ': ' + context.parsed.y.toLocaleString() : context.dataset.label + ': ' + context.parsed.y } } }, scales:{ x:{ grid:{ color:'rgba(113,128,150,.10)' }, ticks:{ color:'#98a2b3', font:{size:10}, maxTicksLimit:16 } }, revenue:{ type:'linear', position:'left', beginAtZero:true, grid:{ color:'rgba(113,128,150,.13)' }, ticks:{ color:'#1683f6', font:{size:10} }, title:{ display:true, text:'@lang("sCommerce::global.revenue")', color:'#98a2b3', font:{size:11} } }, orders:{ type:'linear', position:'right', beginAtZero:true, grid:{ drawOnChartArea:false }, ticks:{ color:'#29af63', precision:0, font:{size:10} }, title:{ display:true, text:'@lang("sCommerce::global.orders")', color:'#98a2b3', font:{size:11} } } } }
        });
    });
</script>
@endpush
