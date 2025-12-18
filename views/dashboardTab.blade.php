@php use Seiger\sCommerce\Models\sOrder; @endphp
<style>
    .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-left: 4px solid #036efe;
    }
    .stat-card.success { border-left-color: #28a745; }
    .stat-card.warning { border-left-color: #ffc107; }
    .stat-card.info { border-left-color: #17a2b8; }
    .stat-card.danger { border-left-color: #dc3545; }
    .stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #666;
        font-weight: normal;
    }
    .stat-card .value {
        font-size: 32px;
        font-weight: bold;
        color: #333;
        margin: 0;
    }
    .stat-card .sub-value {
        font-size: 14px;
        color: #999;
        margin-top: 5px;
    }
    .dashboard-section {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .dashboard-section h3 {
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }
    .chart-container {
        height: 300px;
        position: relative;
    }
    .recent-orders-table {
        width: 100%;
    }
    .recent-orders-table th {
        text-align: left;
        padding: 10px;
        border-bottom: 2px solid #f0f0f0;
        font-weight: 600;
    }
    .recent-orders-table td {
        padding: 10px;
        border-bottom: 1px solid #f0f0f0;
    }
    .recent-orders-table tr:hover {
        background: #f9f9f9;
    }
</style>

<div class="dashboard-container">
    <div class="dashboard-stats">
        <div class="stat-card danger">
            <h3>@lang('sCommerce::global.total_orders')</h3>
            <p class="value">{{$totalOrders}}</p>
            <p class="sub-value">
                @lang('sCommerce::global.unprocessed_orders'): {{$newOrders}} | 
                @lang('sCommerce::global.working_orders'): {{$workingOrders}} | 
                @lang('sCommerce::global.completed_orders'): {{$completedOrders}}
            </p>
        </div>
        
        <div class="stat-card success">
            <h3>@lang('sCommerce::global.total_revenue')</h3>
            <p class="value">{{sCommerce::convertPrice($totalRevenue, sCommerce::currentCurrency())}}</p>
            <p class="sub-value">
                @lang('sCommerce::global.today'): {{sCommerce::convertPrice($todayRevenue, sCommerce::currentCurrency())}} | 
                @lang('sCommerce::global.this_month'): {{sCommerce::convertPrice($monthRevenue, sCommerce::currentCurrency())}}
            </p>
        </div>
        
        <div class="stat-card info">
            <h3>@lang('sCommerce::global.total_products')</h3>
            <p class="value">{{$totalProducts}}</p>
            <p class="sub-value">
                @lang('sCommerce::global.publisheds'): {{$publishedProducts}} | 
                @lang('sCommerce::global.unpublisheds'): {{$unpublishedProducts}}
            </p>
        </div>
        
        <div class="stat-card warning">
            <h3>@lang('sCommerce::global.payment_status')</h3>
            <p class="value">{{$paidOrders}}</p>
            <p class="sub-value">
                @lang('sCommerce::global.payment_status_paid'): {{$paidOrders}} | 
                @lang('sCommerce::global.payment_status_pending'): {{$pendingOrders}}
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="dashboard-section">
                <h3>@lang('sCommerce::global.sales_chart') (30 @lang('sCommerce::global.days'))</h3>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="dashboard-section">
                <h3>@lang('sCommerce::global.today_statistics')</h3>
                <div style="margin-bottom: 15px;">
                    <strong>@lang('sCommerce::global.orders'):</strong> {{$todayOrders}}
                </div>
                <div>
                    <strong>@lang('sCommerce::global.revenue'):</strong> {{sCommerce::convertPrice($todayRevenue, sCommerce::currentCurrency())}}
                </div>
                
                <h3 style="margin-top: 30px;">@lang('sCommerce::global.this_month')</h3>
                <div style="margin-bottom: 15px;">
                    <strong>@lang('sCommerce::global.orders'):</strong> {{$monthOrders}}
                </div>
                <div>
                    <strong>@lang('sCommerce::global.revenue'):</strong> {{sCommerce::convertPrice($monthRevenue, sCommerce::currentCurrency())}}
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="dashboard-section">
                <h3>@lang('sCommerce::global.recent_orders')</h3>
                <table class="recent-orders-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>@lang('sCommerce::global.client')</th>
                            <th>@lang('sCommerce::global.created')</th>
                            <th>@lang('sCommerce::global.sum')</th>
                            <th>@lang('sCommerce::global.status')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            <tr>
                                <td>
                                    <a href="{!!sCommerce::moduleUrl()!!}&get=order&i={{$order->id}}">
                                        <b>#{{$order->id}}</b>
                                    </a>
                                </td>
                                <td>
                                    {{implode(' ', array_diff([
                                        $order->user_info['first_name'] ?? '',
                                        $order->user_info['middle_name'] ?? '',
                                        $order->user_info['last_name'] ?? ''
                                    ], ['']))}}
                                    ({{$order->user_info['phone'] ?? ''}})
                                </td>
                                <td>{{$order->created_at->format('d.m.Y H:i')}}</td>
                                <td>{{sCommerce::convertPrice($order->cost, $order->currency)}}</td>
                                <td>
                                    <span @class(['badge', 'bg-disactive' => in_array($order->status, $unprocessedes), 'bg-progress' => in_array($order->status, $workings), 'bg-active' => in_array($order->status, $completeds)])>
                                        {{sOrder::getOrderStatusName($order->status)}}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">@lang('sCommerce::global.no_data_found')</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="col-md-4">
            @if(count($topProducts ?? []) > 0)
            <div class="dashboard-section">
                <h3>@lang('sCommerce::global.top_products')</h3>
                <ol style="padding-left: 20px;">
                    @foreach($topProducts ?? [] as $product)
                        <li style="margin-bottom: 10px;">
                            <strong>{{$product['title'] ?? 'N/A'}}</strong><br>
                            <small>@lang('sCommerce::global.orders'): {{$product['count'] ?? 0}}</small>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
        </div>
    </div>
</div>

@push('scripts.bot')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    $(document).ready(function() {
        const salesData = @json($salesChartData);
        
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesData.map(d => d.label),
                datasets: [
                    {
                        label: '@lang("sCommerce::global.revenue")',
                        data: salesData.map(d => d.revenue),
                        borderColor: 'rgb(3, 110, 254)',
                        backgroundColor: 'rgba(3, 110, 254, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y',
                    },
                    {
                        label: '@lang("sCommerce::global.orders")',
                        data: salesData.map(d => d.orders),
                        borderColor: 'rgb(40, 167, 69)',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: '@lang("sCommerce::global.revenue")'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: '@lang("sCommerce::global.orders")'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    });
</script>
@endpush

