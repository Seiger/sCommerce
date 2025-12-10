@php use Seiger\sCommerce\Models\sOrder; @endphp
@php($order = request()->has('order') ? request()->input('order') : 'id')
@php($currencies = sCommerce::config('currencies', []))
<style>
    .badge-1click {
        background-color: #036efe;
        color: white;
        padding: 5px 10px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: bold;
        display: inline-flex;
        align-items: center;
        text-transform: uppercase;
    }
    .badge-1click i {
        margin-right: 5px;
    }
</style>
<div class="row form-row">
    <div class="row-col col-lg-5 col-md-12 pl-0 scom-conters">
        <div class="d-flex flex-row align-items-center">
            <div class="scom-conters-item scom-all pl-0">@lang('sCommerce::global.total_orders'): <span>{{$total ?? 0}}</span></div>
            <div class="scom-conters-item scom-status-title scom-disactive">@lang('sCommerce::global.unprocessed_orders'): <span>{{$unprocessed ?? 0}}</span></div>
            <div class="scom-conters-item scom-status-title scom-progress">@lang('sCommerce::global.working_orders'): <span>{{$working ?? 0}}</span></div>
            <div class="scom-conters-item scom-status-title scom-active">@lang('sCommerce::global.completed_orders'): <span>{{$completed ?? 0}}</span></div>
        </div>
    </div>
    <div class="row-col col-lg-7 col-md-12 input-group mb-2">
        <input name="search"
               value="{{request()->search ?? ''}}"
               type="search"
               class="form-control rounded-left scom-input seiger__search"
               placeholder="@lang('sCommerce::global.search_among_products') (@lang('sCommerce::global.sku'), @lang('sCommerce::global.product_name'), @lang('global.long_title'), @lang('global.resource_summary'), @lang('sCommerce::global.content'))"
               aria-label="@lang('sCommerce::global.search_among_products') (@lang('sCommerce::global.sku'), @lang('sCommerce::global.product_name'), @lang('global.long_title'), @lang('global.resource_summary'), @lang('sCommerce::global.content'))"
               aria-describedby="basic-addon2" />
        <span class="scom-clear-search">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
                <path d="M22 11.2086L20.7914 10L16 14.7914L11.2086 10L10 11.2086L14.7914 16L10 20.7914L11.2086 22L16 17.2086L20.7914 22L22 20.7914L17.2086 16L22 11.2086Z" fill="#63666B"/>
            </svg>
        </span>
        <div class="input-group-append">
            <button class="btn btn-outline-secondary rounded-right scom-submit-search" type="button"><i class="fas fa-search"></i></button>
        </div>
    </div>
</div>
<div class="row form-row mb-2 scom-btn-container">
    <a @class(['btn', 'btn-info' => $status == 0, 'btn-light' => $status != 0]) href="{!!sCommerce::moduleUrl()!!}&get=orders" class="btn btn-info">@lang('sCommerce::global.all_statuses')</a>
    @foreach($statuses as $id => $name)
        <a @class(['btn', 'btn-info' => $status == $id, 'btn-light' => $status != $id]) href="{!!sCommerce::moduleUrl()!!}&get=orders&status={{$id}}">{{$name}}</a>
    @endforeach
</div>
<div class="table-responsive seiger__module-table">
    <table class="table table-condensed table-hover sectionTrans scom-table">
        <thead>
            <tr>
                <th class="sorting @if($order == 'id') sorted @endif" data-order="id">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.order_number') <i class="fas fa-sort" style="color: #036efe;"></i></button>
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
                <th class="sorting @if($order == 'payment_status') sorted @endif" data-order="payment_status">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.payment') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
                <th class="sorting @if($order == 'status') sorted @endif" data-order="status">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.status') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
                <th id="action-btns">@lang('global.onlineusers_action')</th>
            </tr>
        </thead>
        <tbody>
        @foreach($items as $item)
            <tr style="height: 42px;" id="order-{{$item->id}}">
                <td><b>#{{$item->id}}</b>@if($item->is_quick) <span class="badge bg-super bg-seigerit"><i class="fas fa-clock"></i> @lang('sCommerce::global.one_click')</span>@endif</td>
                <td>
                    {{implode(' ', array_diff([
                        $item->user_info['first_name'] ?? '',
                        $item->user_info['middle_name'] ?? '',
                        $item->user_info['last_name'] ?? ''],
                    ['']))}}
                    ({{$item->user_info['phone'] ?? ''}})
                </td>
                <td>{{$item->created_at}}</td>
                <td>{{sCommerce::convertPrice($item->cost, $item->currency)}}@if(($currencies[$item->currency]['show'] ?? 0) == 0) {{$item->currency}}@endif</td>
                <td>
                    <span @class(['badge', 'bg-paid' => $item->payment_status == sOrder::PAYMENT_STATUS_PAID, 'bg-pending' => $item->payment_status != sOrder::PAYMENT_STATUS_PAID])>
                        {{sOrder::getPaymentStatusName($item->payment_status)}}
                    </span>
                </td>
                <td>
                    <span @class(['badge', 'bg-active' => in_array($item->status, $unprocessedes), 'bg-progress' => in_array($item->status, $workings), 'bg-disactive' => in_array($item->status, $completeds)])>
                        {{sOrder::getOrderStatusName($item->status)}}
                    </span>
                </td>
                <td style="text-align:center;">
                    <div class="btn-group">
                        <a href="{!!sCommerce::moduleUrl()!!}&get=order&i={{$item->id}}{{request()->has('page') ? '&page=' . request()->page : ''}}" class="btn btn-outline-success">
                            <i class="fa fa-pencil"></i> <span>@lang('global.edit')</span>
                        </a>
                        @if (evo()->hasPermission('settings'))
                            <span data-href="{!!sCommerce::moduleUrl()!!}&get=orderDelete&i={{$item->id}}" data-delete="{{$item->id}}" data-name="#{{$item->id}}" class="btn btn-outline-danger">
                                <i class="fa fa-trash"></i> <span>@lang('global.remove')</span>
                            </span>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="seiger__bottom">
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