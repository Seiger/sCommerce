@php use Seiger\sCommerce\Models\sOrder; @endphp

@if($ordersAvailable ?? true)
    @php
        $periodRevenue = array_sum(array_column($salesChartData, 'revenue'));
        $periodOrders = array_sum(array_column($salesChartData, 'orders'));
        $averageOrder = $periodOrders > 0 ? $periodRevenue / $periodOrders : 0;
        $paidRate = $periodOrders > 0 ? $periodPaidOrders / $periodOrders * 100 : 0;
        $previousAverageOrder = $previousPeriodOrders > 0 ? $previousPeriodRevenue / $previousPeriodOrders : 0;
        $previousPaidRate = $previousPeriodOrders > 0 ? $previousPeriodPaidOrders / $previousPeriodOrders * 100 : 0;
        $change = static fn (float $value, float $previous): ?float => $previous > 0 ? round(($value - $previous) / $previous * 100, 1) : null;
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
            if (in_array($status, [sOrder::PAYMENT_STATUS_PAID, sOrder::PAYMENT_STATUS_PARTIALLY_PAID], true)) return 'scom-dashboard__payment--paid';
            if (in_array($status, [sOrder::PAYMENT_STATUS_FAILED, sOrder::PAYMENT_STATUS_CANCELED, sOrder::PAYMENT_STATUS_REJECTED, sOrder::PAYMENT_STATUS_EXPIRED, sOrder::PAYMENT_STATUS_DISPUTED], true)) return 'scom-dashboard__payment--failed';
            if (in_array($status, [sOrder::PAYMENT_STATUS_REFUND_REQUESTED, sOrder::PAYMENT_STATUS_REFUNDED, sOrder::PAYMENT_STATUS_PARTIALLY_REFUNDED], true)) return 'scom-dashboard__payment--refunded';
            return '';
        };
    @endphp

    <div class="scom-dashboard">
        <section class="scom-dashboard__summary">
            <div class="scom-dashboard__metric scom-dashboard__metric--orders">@svg('tabler-shopping-cart', 'scom-dashboard__metric-icon')<div><span class="scom-dashboard__metric-label">@lang('sCommerce::global.orders')<span class="scom-dashboard__metric-value">{{$totalOrders}}</span></span><span class="scom-dashboard__metric-detail">@lang('sCommerce::global.unprocessed_orders'): {{$newOrders}} | @lang('sCommerce::global.working_orders'): {{$workingOrders}} | @lang('sCommerce::global.completed_orders'): {{$completedOrders}}</span></div></div>
            <div class="scom-dashboard__metric scom-dashboard__metric--revenue">@svg('tabler-cash-banknote', 'scom-dashboard__metric-icon')<div><span class="scom-dashboard__metric-label">@lang('sCommerce::global.total_revenue')<span class="scom-dashboard__metric-value">{{sCommerce::convertPrice($totalRevenue, sCommerce::currentCurrency())}}</span></span><span class="scom-dashboard__metric-detail">@lang('sCommerce::global.today'): {{sCommerce::convertPrice($todayRevenue, sCommerce::currentCurrency())}} | @lang('sCommerce::global.this_month'): {{sCommerce::convertPrice($monthRevenue, sCommerce::currentCurrency())}}</span></div></div>
            <div class="scom-dashboard__metric scom-dashboard__metric--products">@svg('tabler-cube', 'scom-dashboard__metric-icon')<div><span class="scom-dashboard__metric-label">@lang('sCommerce::global.products')<span class="scom-dashboard__metric-value">{{$totalProducts}}</span></span><span class="scom-dashboard__metric-detail">@lang('sCommerce::global.publisheds'): {{$publishedProducts}} | @lang('sCommerce::global.unpublisheds'): {{$unpublishedProducts}}</span></div></div>
            <div class="scom-dashboard__metric scom-dashboard__metric--payments">@svg('tabler-credit-card', 'scom-dashboard__metric-icon')<div><span class="scom-dashboard__metric-label">@lang('sCommerce::global.payments')<span class="scom-dashboard__metric-value">{{$paidOrders}}</span></span><span class="scom-dashboard__metric-detail">@lang('sCommerce::global.payment_status_paid'): {{$paidOrders}} | @lang('sCommerce::global.payment_status_pending'): {{$pendingOrders}}</span></div></div>
        </section>

        <div class="scom-dashboard__grid">
            <section class="scom-dashboard__card scom-dashboard__card--orders">
                <div class="scom-dashboard__card-head"><h3 class="scom-dashboard__card-title">@lang('sCommerce::global.recent_orders')</h3><a class="scom-dashboard__link" href="{!!sCommerce::moduleUrl()!!}&get=orders">@lang('sCommerce::global.to_list_orders') @svg('tabler-arrow-right', 'scom-dashboard__link-icon')</a></div>
                <table class="scom-dashboard__table">
                    <colgroup>
                        <col class="scom-dashboard__order-column scom-dashboard__order-column--reference">
                        <col class="scom-dashboard__order-column scom-dashboard__order-column--client">
                        <col class="scom-dashboard__order-column scom-dashboard__order-column--date">
                        <col class="scom-dashboard__order-column scom-dashboard__order-column--sum">
                        <col class="scom-dashboard__order-column scom-dashboard__order-column--status">
                        <col class="scom-dashboard__order-column scom-dashboard__order-column--payment">
                    </colgroup>
                    <thead><tr><th>#</th><th>@lang('sCommerce::global.client')</th><th>@lang('sCommerce::global.created')</th><th>@lang('sCommerce::global.sum')</th><th>@lang('sCommerce::global.status')</th><th>@lang('sCommerce::global.payment')</th></tr></thead>
                    <tbody>
                    @forelse($recentOrders as $order)
                        @php($paymentStatus = (int) $order->payment_status)
                        @php($phone = $formatPhone($order->user_info['phone'] ?? ''))
                        @php($domain = $domains?->get($order->domain))
                        <tr>
                            <td class="scom-dashboard__order-cell scom-dashboard__order-cell--reference">@if($domain)<span class="scom-dashboard__order-domain" aria-label="{{$domain->domain}}" role="img" title="{{$domain->domain}}" style="--domain-color: {{$domain->site_color ?: '#60a5fa'}}"></span>@endif<span class="scom-dashboard__order-reference"><a class="scom-dashboard__order-number" href="{!!sCommerce::moduleUrl()!!}&get=order&i={{$order->id}}">#{{$order->order_number ?? $order->id}}</a>@if($order->is_quick)<span class="scom-dashboard__quick-order" title="@lang('sCommerce::global.one_click')">@svg('tabler-bolt-filled')</span>@endif</span></td>
                            <td class="scom-dashboard__order-cell scom-dashboard__order-cell--client"><span class="scom-dashboard__client">{{implode(' ', array_filter([$order->user_info['first_name'] ?? '', $order->user_info['middle_name'] ?? '', $order->user_info['last_name'] ?? ''])) ?: '—'}}</span>@if($phone !== '')<span class="scom-dashboard__client-phone">{{$phone}}</span>@endif</td>
                            <td class="scom-dashboard__order-cell scom-dashboard__order-cell--date" data-label="@lang('sCommerce::global.created')">@svg('tabler-calendar', 'scom-dashboard__order-meta-icon')<span class="scom-dashboard__order-date"><span>{{$order->created_at->format('d.m.Y')}}</span><span>{{$order->created_at->format('H:i')}}</span></span></td>
                            <td class="scom-dashboard__order-cell scom-dashboard__order-cell--sum" data-label="@lang('sCommerce::global.sum')">@svg('tabler-shopping-cart', 'scom-dashboard__order-meta-icon')<span>{{sCommerce::convertPrice($order->cost, $order->currency)}}</span></td>
                            <td class="scom-dashboard__order-cell scom-dashboard__order-cell--status" data-label="@lang('sCommerce::global.status')">@include('sCommerce::partials.orderStatus', ['status' => (int) $order->status])</td>
                            <td class="scom-dashboard__order-cell scom-dashboard__order-cell--payment" data-label="@lang('sCommerce::global.payment')"><span @class(['scom-dashboard__payment', $paymentStatusClass($paymentStatus)])>{{sOrder::getPaymentStatusName($paymentStatus)}}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="scom-dashboard__empty">@lang('sCommerce::global.no_data_found')</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>

            <section class="scom-dashboard__card scom-dashboard__card--products">
                <div class="scom-dashboard__card-head"><h3 class="scom-dashboard__card-title">@lang('sCommerce::global.top_products')</h3><a class="scom-dashboard__link" href="{!!sCommerce::moduleUrl()!!}&get=products">@lang('sCommerce::global.to_list_products') @svg('tabler-arrow-right', 'scom-dashboard__link-icon')</a></div>
                <ul class="scom-dashboard__products">
                @forelse($topProducts as $product)
                    <li class="scom-dashboard__product"><div class="scom-dashboard__product-image"><img src="{{sGallery::file($product['cover'])->fit(sGallery::defaultFit(), 128, 112)}}" alt="{{$product['cover']}}"></div><div class="scom-dashboard__product-content"><span class="scom-dashboard__product-name">{{$product['title']}}</span><span class="scom-dashboard__product-meta"><span class="scom-dashboard__product-meta-item scom-dashboard__product-meta-item--orders" title="@lang('sCommerce::global.orders')">@svg('tabler-shopping-cart', 'scom-dashboard__product-meta-icon') {{$product['count']}}</span><span class="scom-dashboard__product-meta-item scom-dashboard__product-meta-item--views" title="@lang('sCommerce::global.views')">@svg('tabler-eye', 'scom-dashboard__product-meta-icon') {{$product['views']}}</span>@if($product['sku'] !== '')<span class="scom-dashboard__product-meta-item scom-dashboard__product-meta-item--sku" title="@lang('sCommerce::global.sku')">@svg('tabler-barcode', 'scom-dashboard__product-meta-icon') {{$product['sku']}}</span>@endif</span><span class="scom-dashboard__product-revenue">{{sCommerce::convertPrice($product['revenue'], sCommerce::currentCurrency())}}</span></div></li>
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
                    <div class="scom-dashboard__chart-actions"><div class="scom-dashboard__periods">@foreach([7, 30, 90, 365] as $period)<a @class(['scom-dashboard__period', 'scom-dashboard__period--active' => $dashboardPeriod === $period]) href="{!!sCommerce::moduleUrl()!!}&get=dashboard&dashboard_period={{$period}}">{{$period === 365 ? __('sCommerce::global.year') : $period . ' ' . __('sCommerce::global.days')}}</a>@endforeach</div><div class="scom-dashboard__legend"><span class="scom-dashboard__legend-item"><i class="scom-dashboard__legend-line"></i>@lang('sCommerce::global.revenue')</span><span class="scom-dashboard__legend-item"><i class="scom-dashboard__legend-line scom-dashboard__legend-line--orders"></i>@lang('sCommerce::global.orders')</span></div></div>
                </div>
            </div>
            <div class="scom-dashboard__chart"><canvas id="salesChart"></canvas></div>
        </section>
    </div>
@else
    <div class="scom-dashboard scom-dashboard--no-cart">
        <section class="scom-dashboard__summary">
            <div class="scom-dashboard__metric scom-dashboard__metric--products">@svg('tabler-cube', 'scom-dashboard__metric-icon')<div><span class="scom-dashboard__metric-label">@lang('sCommerce::global.products')<span class="scom-dashboard__metric-value">{{$totalProducts}}</span></span><span class="scom-dashboard__metric-detail">@lang('sCommerce::global.dashboard_catalog_total')</span></div></div>
            <div class="scom-dashboard__metric scom-dashboard__metric--published">@svg('tabler-circle-check', 'scom-dashboard__metric-icon')<div><span class="scom-dashboard__metric-label">@lang('sCommerce::global.publisheds')<span class="scom-dashboard__metric-value">{{$publishedProducts}}</span></span><span class="scom-dashboard__metric-detail">@lang('sCommerce::global.unpublisheds'): {{$unpublishedProducts}}</span></div></div>
            <div class="scom-dashboard__metric scom-dashboard__metric--views">@svg('tabler-eye', 'scom-dashboard__metric-icon')<div><span class="scom-dashboard__metric-label">@lang('sCommerce::global.views')<span class="scom-dashboard__metric-value">{{$totalViews}}</span></span><span class="scom-dashboard__metric-detail">@lang('sCommerce::global.dashboard_cumulative_counter')</span></div></div>
            <div class="scom-dashboard__metric scom-dashboard__metric--reviews">@svg('tabler-message-star', 'scom-dashboard__metric-icon')<div><span class="scom-dashboard__metric-label">@lang('sCommerce::global.reviews')<span class="scom-dashboard__metric-value">{{$totalReviews}}</span></span><span class="scom-dashboard__metric-detail">@lang('sCommerce::global.dashboard_new_reviews'): {{$newReviews}} | @lang('sCommerce::global.dashboard_average_rating'): {{$averageRating}}</span></div></div>
        </section>

        <div class="scom-dashboard__grid">
            <section class="scom-dashboard__card scom-dashboard__card--products-views">
                <div class="scom-dashboard__card-head"><h3 class="scom-dashboard__card-title">@lang('sCommerce::global.dashboard_top_products_by_views')</h3><a class="scom-dashboard__link" href="{!!sCommerce::moduleUrl()!!}&get=products">@lang('sCommerce::global.to_list_products') @svg('tabler-arrow-right', 'scom-dashboard__link-icon')</a></div>
                <ul class="scom-dashboard__products">@forelse($topProducts as $product)<li class="scom-dashboard__product"><div class="scom-dashboard__product-image"><img src="{{sGallery::file($product['cover'])->fit(sGallery::defaultFit(), 128, 112)}}" alt="{{$product['cover']}}"></div><div class="scom-dashboard__product-content"><span class="scom-dashboard__product-name">{{$product['title']}}</span><span class="scom-dashboard__product-meta"><span class="scom-dashboard__product-meta-item scom-dashboard__product-meta-item--views" title="@lang('sCommerce::global.views')">@svg('tabler-eye', 'scom-dashboard__product-meta-icon') {{$product['views']}}</span>@if($product['sku'] !== '')<span class="scom-dashboard__product-meta-item scom-dashboard__product-meta-item--sku" title="@lang('sCommerce::global.sku')">@svg('tabler-barcode', 'scom-dashboard__product-meta-icon') {{$product['sku']}}</span>@endif</span><span class="scom-dashboard__product-revenue">{{sCommerce::convertPrice($product['price'], sCommerce::currentCurrency())}}</span></div></li>@empty<li class="scom-dashboard__empty">@lang('sCommerce::global.no_data_found')</li>@endforelse</ul>
            </section>
            <section class="scom-dashboard__card scom-dashboard__card--reviews">
                <div class="scom-dashboard__card-head"><h3 class="scom-dashboard__card-title">@lang('sCommerce::global.dashboard_recent_reviews')</h3><a class="scom-dashboard__link" href="{!!sCommerce::moduleUrl()!!}&get=reviews">@lang('sCommerce::global.dashboard_to_reviews') @svg('tabler-arrow-right', 'scom-dashboard__link-icon')</a></div>
                <ul class="scom-dashboard__reviews">@forelse($recentReviews as $review)@php($rating = max(0, min(5, (int) round((float) $review->rating))))<li class="scom-dashboard__review"><div class="scom-dashboard__review-line"><span class="scom-dashboard__review-name">{{$review->name ?: '—'}}</span><span class="scom-dashboard__stars" aria-label="{{$review->rating}} / 5">@for($star = 1; $star <= 5; $star++)@svg('tabler-star-filled', $star <= $rating ? 'scom-dashboard__star' : 'scom-dashboard__star scom-dashboard__star--muted', ['fill' => 'currentColor', 'stroke' => 'none'])@endfor</span><time class="scom-dashboard__review-date" datetime="{{$review->created_at?->format('Y-m-d')}}">{{$review->created_at?->format('d.m.Y')}}</time></div><span class="scom-dashboard__review-message">{{$review->message}}</span><span class="scom-dashboard__review-product">{{$review->toProduct?->pagetitle ?? '—'}}</span></li>@empty<li class="scom-dashboard__empty">@lang('sCommerce::global.no_data_found')</li>@endforelse</ul>
            </section>
        </div>

        <section class="scom-dashboard__card scom-dashboard__chart-card">
            <div class="scom-dashboard__card-head scom-dashboard__chart-head"><div><h3 class="scom-dashboard__chart-title">@lang('sCommerce::global.dashboard_views_dynamics')</h3>@if(!($viewsHistoryAvailable ?? false))<div class="scom-dashboard__chart-subtitle">@lang('sCommerce::global.dashboard_history_unavailable')</div>@endif</div><div class="scom-dashboard__chart-content"><div class="scom-dashboard__chart-metrics"><div class="scom-dashboard__chart-metric"><span>@lang('sCommerce::global.views')</span><strong>{{niceCount($totalViews)}}</strong></div><div class="scom-dashboard__chart-metric"><span>@lang('sCommerce::global.products')</span><strong>{{niceCount($totalProducts)}}</strong></div><div class="scom-dashboard__chart-metric"><span>@lang('sCommerce::global.reviews')</span><strong>{{niceCount($totalReviews)}}</strong></div><div class="scom-dashboard__chart-metric"><span>@lang('sCommerce::global.dashboard_average_rating')</span><strong>{{$averageRating}}</strong></div></div><div class="scom-dashboard__chart-actions"><div class="scom-dashboard__periods">@foreach([7, 30, 90, 365] as $period)<a @class(['scom-dashboard__period', 'scom-dashboard__period--active' => $dashboardPeriod === $period]) href="{!!sCommerce::moduleUrl()!!}&get=dashboard&dashboard_period={{$period}}">{{$period === 365 ? __('sCommerce::global.year') : $period . ' ' . __('sCommerce::global.days')}}</a>@endforeach</div></div></div></div>
            <div class="scom-dashboard__chart"><canvas id="viewsChart"></canvas>@if(!($viewsHistoryAvailable ?? false))<div class="scom-dashboard__history-note">@lang('sCommerce::global.dashboard_history_unavailable')</div>@endif</div>
        </section>
    </div>
@endif

@push('scripts.bot')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    $(function () {
        const ordersAvailable = @json($ordersAvailable ?? true);
        const canvas = document.getElementById(ordersAvailable ? 'salesChart' : 'viewsChart');
        if (!canvas || typeof Chart === 'undefined') return;
        const source = ordersAvailable ? @json($salesChartData ?? []) : @json($viewsChartData ?? []);
        const dashboardStyles = getComputedStyle(document.documentElement);
        const chartTickSize = Number.parseFloat(dashboardStyles.getPropertyValue('--sc-chart-tick-size')) || 10;
        const chartPrimary = dashboardStyles.getPropertyValue('--sc-color-chart-primary').trim() || '#1683f6';
        const chartPrimaryFill = dashboardStyles.getPropertyValue('--sc-color-chart-primary-fill').trim() || 'rgba(22,131,246,.08)';
        const chartSecondary = dashboardStyles.getPropertyValue('--sc-color-chart-secondary').trim() || '#29af63';
        const datasets = ordersAvailable ? [
            {label:'@lang("sCommerce::global.revenue")',data:source.map(item=>item.revenue),borderColor:chartPrimary,backgroundColor:chartPrimaryFill,borderWidth:2.5,fill:true,pointRadius:context=>context.raw>0?3:0,tension:.35,yAxisID:'primary'},
            {label:'@lang("sCommerce::global.orders")',data:source.map(item=>item.orders),borderColor:chartSecondary,backgroundColor:'transparent',borderWidth:2.5,pointRadius:context=>context.raw>0?3:0,tension:.35,yAxisID:'secondary'}
        ] : [{label:'@lang("sCommerce::global.views")',data:source.map(item=>item.views),borderColor:chartPrimary,backgroundColor:chartPrimaryFill,borderWidth:2.5,fill:true,pointRadius:4,tension:.35,yAxisID:'primary'}];
        new Chart(canvas.getContext('2d'), {type:'line',data:{labels:source.map(item=>item.label),datasets},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(113,128,150,.10)'},ticks:{color:'#98a2b3',font:{size:chartTickSize}}},primary:{type:'linear',position:'left',beginAtZero:true,grid:{color:'rgba(113,128,150,.13)'},ticks:{color:chartPrimary,font:{size:chartTickSize}}},secondary:{display:ordersAvailable,type:'linear',position:'right',beginAtZero:true,grid:{drawOnChartArea:false},ticks:{color:chartSecondary,precision:0,font:{size:chartTickSize}}}}}});
    });
</script>
@endpush
