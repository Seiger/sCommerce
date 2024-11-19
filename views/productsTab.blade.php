@php
    $order = request()->has('order') ? request()->input('order') : 'id';
    if (evo()->getConfig('check_sMultisite', false)) {
        $domains = \Seiger\sMultisite\Models\sMultisite::all();
    }
@endphp
<div class="row form-row">
    <div class="row-col col-lg-4 col-md-12 pl-0 scom-conters">
        <div class="d-flex flex-row align-items-center">
            <div class="scom-conters-item scom-all pl-0">@lang('sCommerce::global.total_products'): <span>{{$total ?? 0}}</span></div>
            <div class="scom-conters-item scom-status-title scom-active">@lang('sCommerce::global.publisheds'): <span>{{$active ?? 0}}</span></div>
            <div class="scom-conters-item scom-status-title scom-disactive">@lang('sCommerce::global.unpublisheds'): <span>{{$disactive ?? 0}}</span></div>
        </div>
    </div>
    <div class="row-col col-lg-8 col-md-12 input-group mb-2">
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
    <a @class(['btn', 'btn-info' => $cat == 0, 'btn-light' => $cat != 0]) href="{!!$moduleUrl!!}&get=products" class="btn btn-info">@lang('sCommerce::global.all_products')</a>
    @foreach($resources as $id => $resource)
        <a @class(['btn', 'btn-info' => $cat == $id, 'btn-light' => $cat != $id]) href="{!!$moduleUrl!!}&get=products&cat={{$id}}">{{$resource}}</a>
    @endforeach
</div>
<div class="table-responsive seiger__module-table">
    <table class="table table-condensed table-hover sectionTrans scom-table">
        <thead>
        <tr>
            @if (sCommerce::config('products.show_field_id', 1) == 1)
                <th class="sorting @if($order == 'id') sorted @endif" data-order="id">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">ID <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
                @if (sCommerce::config('products.show_field_sku', 1) && sCommerce::config('product.show_field_sku', 1))
                <th class="sorting @if($order == 'sku') sorted @endif" data-order="sku">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.sku') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            <th class="sorting @if($order == 'pagetitle') sorted @endif" data-order="pagetitle">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.product_name') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            @if (sCommerce::config('products.show_field_price', 1) && sCommerce::config('product.show_field_price', 1))
                <th class="sorting @if($order == 'price_regular') sorted @endif" data-order="price_regular">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.price') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (sCommerce::config('products.show_field_price_special', 1) && sCommerce::config('product.show_field_price_special', 1))
                <th class="sorting @if($order == 'price_special') sorted @endif" data-order="price_special">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.price_special') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (sCommerce::config('products.show_field_price_opt', 1) && sCommerce::config('product.show_field_price_opt', 1))
                <th class="sorting @if($order == 'price_opt_regular') sorted @endif" data-order="price_opt_regular">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.price_opt') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (sCommerce::config('products.show_field_price_opt_special', 1) && sCommerce::config('product.show_field_price_opt_special', 1))
                <th class="sorting @if($order == 'price_opt_special') sorted @endif" data-order="price_opt_special">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.price_opt_special') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (sCommerce::config('products.show_field_quantity', 1) && sCommerce::config('product.quantity_on', 1))
                <th class="sorting @if($order == 'quantity') sorted @endif" data-order="quantity">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.quantity') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (sCommerce::config('products.show_field_availability', 1) == 1)
                <th class="sorting @if($order == 'availability') sorted @endif" data-order="availability">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.availability') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (sCommerce::config('products.show_field_category', 1) == 1)
                <th class="sorting @if($order == 'category') sorted @endif" data-order="category">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.category') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (evo()->getConfig('check_sMultisite', false) && sCommerce::config('products.show_field_websites', 1) == 1)
                <th {{--class="sorting @if($order == 'websites') sorted @endif" data-order="websites"--}}>
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.websites') {{--<i class="fas fa-sort" style="color: #036efe;"></i>--}}</button>
                </th>
            @endif
            @if (sCommerce::config('products.show_field_visibility', 1) == 1)
                <th class="sorting @if($order == 'published') sorted @endif" data-order="published">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.visibility') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (sCommerce::config('product.views_on', 1) == 1 && sCommerce::config('products.show_field_views', 1) == 1)
                <th class="sorting @if($order == 'views') sorted @endif" data-order="views">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.views') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (sCommerce::config('product.rating_on', 1) == 1 && sCommerce::config('products.show_field_rating', 1) == 1)
                <th class="sorting @if($order == 'rating') sorted @endif" data-order="rating">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.rating') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if(count(sCommerce::config('products.additional_fields', [])))
                @foreach(sCommerce::config('products.additional_fields', []) as $field)
                    <th class="sorting @if($order == $field) sorted @endif" data-order="{{$field}}">
                        <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">{{sCommerce::config('constructor.main_product.'.$field.'.pagetitle', $field)}} <i class="fas fa-sort" style="color: #036efe;"></i></button>
                    </th>
                @endforeach
            @endif
            <th id="action-btns">@lang('global.onlineusers_action')</th>
        </tr>
        </thead>
        <tbody>
        @foreach($items as $item)
            <tr style="height: 42px;" id="product-{{$item->id}}">
                @if (sCommerce::config('products.show_field_id', 1) == 1)
                    <td>{{$item->id}}</td>
                @endif
                @if (sCommerce::config('products.show_field_sku', 1) && sCommerce::config('product.show_field_sku', 1))
                    <td>{{$item->sku}}</td>
                @endif
                <td>
                    <img src="{{$item->coverSrc}}" alt="{{$item->coverSrc}}" class="product-thumbnail">
                    <a href="{{$item->link}}" target="_blank"><b>{{$item->pagetitle ?? __('sCommerce::global.no_text')}}</b></a>
                </td>
                    @if (sCommerce::config('products.show_field_price', 1) && sCommerce::config('product.show_field_price', 1))
                    <td>
                        @if(sCommerce::config('basic.price_symbol', 1))
                            {{$item->priceTo($item->currency)}}
                        @else
                            {{sCommerce::getCurrencies([$item->currency])->first()['symbol']}}{{$item->priceTo($item->currency)}}
                        @endif
                    </td>
                @endif
                @if (sCommerce::config('products.show_field_price_special', 1) && sCommerce::config('product.show_field_price_special', 1))
                    <td>{{$item->price_special}}</td>
                @endif
                @if (sCommerce::config('products.show_field_price_opt', 1) && sCommerce::config('product.show_field_price_opt', 1))
                    <td>{{$item->price_opt_regular}}</td>
                @endif
                @if (sCommerce::config('products.show_field_price_opt_special', 1) && sCommerce::config('product.show_field_price_opt_special', 1))
                    <td>{{$item->price_opt_special}}</td>
                @endif
                @if (sCommerce::config('products.show_field_quantity', 1) && sCommerce::config('product.quantity_on', 1))
                    <td>{{$item->quantity}}</td>
                @endif
                @if (sCommerce::config('products.show_field_availability', 1) == 1)
                    <td>{{$item->availability}}</td>
                @endif
                @if (sCommerce::config('products.show_field_category', 1) == 1)
                    <td>
                        @if($item->category > 1)
                            <a href="@makeUrl($item->category)" target="_blank">{{$resources[$item->category]}}</a>
                        @elseif(evo()->getConfig('check_sMultisite', false))
                            @php($categories = $item->categories()->where('scope', 'LIKE', 'primary%')->get())
                            @foreach($categories as $category)
                                <a href="@makeUrl($category->id){{$category->alias_visible == 1 ? '' : $category->alias . evo()->getConfig('friendly_url_suffix', '')}}" target="_blank">{{$category->pagetitle}}</a>
                            @endforeach
                        @else
                            <a href="@makeUrl(1)" target="_blank">{{evo()->getConfig('site_name')}}</a>
                        @endif
                    </td>
                @endif
                @if (evo()->getConfig('check_sMultisite', false) && sCommerce::config('products.show_field_websites', 1) == 1)
                    <td>
                        @php($categories = $item->categories()->withPivot(['scope'])->wherePivot('scope', 'LIKE', 'primary%')->get())
                        @foreach($categories as $category)
                            @php($scope = str_replace('primary_', '', $category->pivot->scope))
                            @php($website = $domains->where('key', $scope)->first())
                            <a href="https://{{$website->domain}}/" target="_blank">{{$website->site_name}}</a>
                        @endforeach
                    </td>
                @endif
                @if (sCommerce::config('products.show_field_visibility', 1) == 1)
                    <td>
                        @if($item->published)
                            <span class="badge badge-success">@lang('global.page_data_published')</span>
                        @else
                            <span class="badge badge-dark">@lang('global.page_data_unpublished')</span>
                        @endif
                    </td>
                @endif
                @if (sCommerce::config('product.views_on', 1) == 1 && sCommerce::config('products.show_field_views', 1) == 1)
                    <td>{{$item->views}}</td>
                @endif
                @if (sCommerce::config('product.rating_on', 1) == 1 && sCommerce::config('products.show_field_rating', 1) == 1)
                    <td>{{$item->rating}}</td>
                @endif
                @if(count(sCommerce::config('products.additional_fields', [])))
                    @foreach(sCommerce::config('products.additional_fields', []) as $field)
                        <td>{{ $item->{$field} }}</td>
                    @endforeach
                @endif
                <td style="text-align:center;">
                    <div class="btn-group">
                        <a href="{!!$moduleUrl!!}&get=product&i={{$item->id}}{{request()->has('page') ? '&page=' . request()->page : ''}}" class="btn btn-outline-success">
                            <i class="fa fa-pencil"></i> <span>@lang('global.edit')</span>
                        </a>
                        <a href="#" data-href="{!!$moduleUrl!!}&get=productDelete&i={{$item->id}}" data-delete="{{$item->id}}" data-name="{{$item->pagetitle ?? __('sCommerce::global.no_text')}}" class="btn btn-outline-danger">
                            <i class="fa fa-trash"></i> <span>@lang('global.delete')</span>
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
                    <a class="dropdown__menu-link" data-items="50" href="{!!$moduleUrl!!}&get=products">50</a>
                </li>
                <li class="dropdown__menu-item">
                    <a class="dropdown__menu-link" data-items="100" href="{!!$moduleUrl!!}&get=products">100</a>
                </li>
                <li class="dropdown__menu-item">
                    <a class="dropdown__menu-link" data-items="150" href="{!!$moduleUrl!!}&get=products">150</a>
                </li>
                <li class="dropdown__menu-item">
                    <a class="dropdown__menu-link" data-items="200" href="{!!$moduleUrl!!}&get=products">200</a>
                </li>
            </ul>
        </div>
    </div>
</div>
@push('scripts.top')
    <script>
        const cookieName = "scom_products_page_items";
    </script>
@endpush
@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button2" href="{!!$moduleUrl!!}&get=product&i=0" class="btn btn-primary" title="@lang('sCommerce::global.add_product_help')">
                <i class="fa fa-plus"></i> <span>@lang('global.add')</span>
            </a>
            @if($cat > 0)
                <a id="Button7" href="{!!$moduleUrl!!}&get=sortproducts&cat={{$cat}}" class="btn btn-info" title="@lang('sCommerce::global.change_sorting')">
                    <i class="fa fa-sort"></i> <span>@lang('sCommerce::global.change_sorting')</span>
                </a>
            @endif
        </div>
    </div>
@endpush
