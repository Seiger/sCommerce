@php
    $order = request()->has('order') ? request()->input('order') : 'id';
@endphp
<div class="row-col pl-0 scom-conters">
    <div class="d-flex flex-row align-items-center">
        <div class="scom-conters-item scom-all pl-0">@lang('sCommerce::global.total_reviews'): <span>{{$total ?? 0}}</span></div>
        <div class="scom-conters-item scom-status-title scom-active">@lang('sCommerce::global.publisheds'): <span>{{$active ?? 0}}</span></div>
        <div class="scom-conters-item scom-status-title scom-disactive">@lang('sCommerce::global.unpublisheds'): <span>{{$disactive ?? 0}}</span></div>
    </div>
</div>
<div class="input-group">
    <div class="input-group mb-3">
        <input name="search"
               value="{{request()->search ?? ''}}"
               type="search"
               class="form-control rounded-left scom-input seiger__search"
               placeholder="@lang('sCommerce::global.search_among_products') (@lang('sCommerce::global.product_name'))"
               aria-label="@lang('sCommerce::global.search_among_products') (@lang('sCommerce::global.product_name'))"
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
{{--@php($resources = \EvolutionCMS\Models\SiteContent::select('id', 'pagetitle')->whereIn('id', $items->pluck('category')->unique()->toArray())->get()->pluck('pagetitle', 'id')->toArray())--}}
<div class="table-responsive seiger__module-table">
    <table class="table table-condensed table-hover sectionTrans scom-table">
        <thead>
        <tr>
            @if (sCommerce::config('products.show_field_id', 1) == 1)
                <th class="sorting @if($order == 'id') sorted @endif" data-order="id">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">ID <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            <th class="sorting @if($order == 'pagetitle') sorted @endif" data-order="pagetitle">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.product_name') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'rating') sorted @endif" data-order="rating">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.rating') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'name') sorted @endif" data-order="name">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('global.user') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'created_at') sorted @endif" data-order="created_at">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('global.publish_date') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'published') sorted @endif" data-order="published">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.visibility') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th id="action-btns">@lang('global.onlineusers_action')</th>
        </tr>
        </thead>
        <tbody>
        @foreach($items as $item)
            <tr style="height: 42px;" id="review-{{$item->id}}">
                @if (sCommerce::config('products.show_field_id', 1) == 1)
                    <td>{{$item->id}}</td>
                @endif
                <td>
                    @if($item->toProduct)
                        <img src="{{$item->toProduct->coverSrc}}" alt="{{$item->toProduct->coverSrc}}" class="product-thumbnail">
                        <a href="{{$item->toProduct->link}}" target="_blank"><b>{{$item->toProduct->title}}</b></a>
                    @else
                        <a><b>@lang('sCommerce::global.review_global')</b></a>
                    @endif
                </td>
                <td>{{$item->rating}}</td>
                <td>{{$item->name}}</td>
                <td>{{$item->created_at ?? ''}}</td>
                <td>
                    @if($item->published)
                        <span class="badge badge-success">@lang('global.page_data_published')</span>
                    @else
                        <span class="badge badge-dark">@lang('global.page_data_unpublished')</span>
                    @endif
                </td>
                <td style="text-align:center;">
                    <div class="btn-group">
                        <a href="{!!$moduleUrl!!}&get=review&i={{$item->id}}{{request()->has('page') ? '&page=' . request()->page : ''}}" class="btn btn-outline-success">
                            <i class="fa fa-pencil"></i> <span>@lang('global.edit')</span>
                        </a>
                        <a href="#" data-href="{!!$moduleUrl!!}&get=reviewDelete&i={{$item->id}}" data-delete="{{$item->id}}" data-name="{{$item->name ?? __('sCommerce::global.no_text')}}" class="btn btn-outline-danger">
                            <i class="fa fa-trash"></i> <span>@lang('global.remove')</span>
                        </a>
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
                    <a class="dropdown__menu-link" data-items="50" href="{!!$moduleUrl!!}&get=reviews">50</a>
                </li>
                <li class="dropdown__menu-item">
                    <a class="dropdown__menu-link" data-items="100" href="{!!$moduleUrl!!}&get=reviews">100</a>
                </li>
                <li class="dropdown__menu-item">
                    <a class="dropdown__menu-link" data-items="150" href="{!!$moduleUrl!!}&get=reviews">150</a>
                </li>
                <li class="dropdown__menu-item">
                    <a class="dropdown__menu-link" data-items="200" href="{!!$moduleUrl!!}&get=reviews">200</a>
                </li>
            </ul>
        </div>
    </div>
</div>
@push('scripts.top')
    <script>
        const cookieName = "scom_reviews_page_items";
    </script>
@endpush
@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button2" href="{!!$moduleUrl!!}&get=review&i=0" class="btn btn-primary" title="@lang('sCommerce::global.add_product_help')">
                <i class="fa fa-plus"></i> <span>@lang('global.add')</span>
            </a>
        </div>
    </div>
@endpush
