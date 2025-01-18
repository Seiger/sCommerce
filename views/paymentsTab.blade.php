<div class="table-responsive seiger__module-table">
    <table class="table table-condensed table-hover sectionTrans scom-table">
        <thead>
        <tr>
            <th>
                <span class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.method_type')</span>
            </th>
            <th class="sorting @if($order == 'name') sorted @endif" data-order="name">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.payment_name') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'position') sorted @endif" data-order="position">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.position') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'active') sorted @endif" data-order="active">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.availability') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th id="action-btns">@lang('global.onlineusers_action')</th>
        </tr>
        </thead>
        <tbody>
        @foreach($items as $item)
            @if($item)
                <tr style="height: 42px;" id="payment-{{$item->id}}">
                    <td>{!!$item->type!!}</td>
                    <td>{{$item->title}} @if(trim($item->description))<i class="fa fa-question-circle" data-tooltip="{!!$item->description!!}"></i>@endif</td>
                    <td>{{$item->position}}</td>
                    <td>
                        @if($item->active)
                            <span class="badge badge-success">@lang('global.page_data_published')</span>
                        @else
                            <span class="badge badge-dark">@lang('global.page_data_unpublished')</span>
                        @endif
                    </td>
                    <td style="text-align:center;">
                        <div class="btn-group">
                            <a href="{!!sCommerce::moduleUrl()!!}&get=payment&i={{$item->id}}{{request()->has('page') ? '&page=' . request()->page : ''}}" class="btn btn-outline-success">
                                <i class="fa fa-pencil"></i> <span>@lang('global.edit')</span>
                            </a>
                        </div>
                    </td>
                </tr>
            @endif
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
                    <a class="dropdown__menu-link" data-items="50" href="{!!sCommerce::moduleUrl()!!}&get=payments">50</a>
                </li>
                <li class="dropdown__menu-item">
                    <a class="dropdown__menu-link" data-items="100" href="{!!sCommerce::moduleUrl()!!}&get=payments">100</a>
                </li>
                <li class="dropdown__menu-item">
                    <a class="dropdown__menu-link" data-items="150" href="{!!sCommerce::moduleUrl()!!}&get=payments">150</a>
                </li>
                <li class="dropdown__menu-item">
                    <a class="dropdown__menu-link" data-items="200" href="{!!sCommerce::moduleUrl()!!}&get=payments">200</a>
                </li>
            </ul>
        </div>
    </div>
</div>
@push('scripts.top')
    <script>
        const cookieName = "scom_payments_page_items";
    </script>
@endpush
