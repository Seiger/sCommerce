@php
    $order = request()->has('order') ? request()->input('order') : 'id';
@endphp
<div class="row-col pl-0 scom-conters">
    <div class="d-flex flex-row align-items-center">
        <div class="scom-conters-item scom-all pl-0">@lang('sCommerce::global.total_products'): <span>{{$total??0}}</span></div>
        <div class="scom-conters-item scom-status-title scom-active">@lang('sCommerce::global.publisheds'): <span>{{$active??0}}</span></div>
        <div class="scom-conters-item scom-status-title scom-disactive">@lang('sCommerce::global.unpublisheds'): <span>{{$disactive??0}}</span></div>
    </div>
</div>
<form id="search" name="search" method="get" action="{!!$moduleUrl!!}&get=products">
    <div class="input-group mb-3">
        <input name="search" value="{{request()->search ?? ''}}" type="search" class="form-control rounded-left scom-input seiger__search" placeholder="@lang('sCommerce::global.search_among_products')" aria-label="@lang('sCommerce::global.search_among_products')" aria-describedby="basic-addon2">
        <span class="scom-clear-search">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
                <path d="M22 11.2086L20.7914 10L16 14.7914L11.2086 10L10 11.2086L14.7914 16L10 20.7914L11.2086 22L16 17.2086L20.7914 22L22 20.7914L17.2086 16L22 11.2086Z" fill="#63666B"/>
            </svg>
        </span>
        <div class="input-group-append">
            <button class="btn btn-outline-secondary rounded-right" type="submit"><i class="fas fa-search"></i></button>
        </div>
    </div>
</form>
<div class="table-responsive seiger__module-table">
    <table class="table table-condensed table-hover sectionTrans scom-table">
        <thead>
        <tr>
            @if (evo()->getConfig('scom_show_field_products_id', 1) == 1)
                <th class="sorting @if($order == 'id') sorted @endif" data-order="id">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">ID <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (evo()->getConfig('scom_show_field_products_sku', 1) == 1)
                <th class="sorting @if($order == 'sku') sorted @endif" data-order="sku">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.sku') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            <th class="sorting @if($order == 'pagetitle') sorted @endif" data-order="pagetitle">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.product_name') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            @if (evo()->getConfig('scom_show_field_products_price', 1) == 1)
                <th class="sorting @if($order == 'price_regular') sorted @endif" data-order="price_regular">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.price') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (evo()->getConfig('scom_show_field_products_price_special', 1) == 1)
                <th class="sorting @if($order == 'price_special') sorted @endif" data-order="price_special">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.price_special') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (evo()->getConfig('scom_show_field_products_price_opt', 1) == 1)
                <th class="sorting @if($order == 'price_opt_regular') sorted @endif" data-order="price_opt_regular">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.price_opt') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (evo()->getConfig('scom_show_field_products_price_opt_special', 1) == 1)
                <th class="sorting @if($order == 'price_opt_special') sorted @endif" data-order="price_opt_special">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.price_opt_special') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (evo()->getConfig('scom_show_field_products_quantity', 1) == 1)
                <th class="sorting @if($order == 'quantity') sorted @endif" data-order="quantity">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.quantity') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (evo()->getConfig('scom_show_field_products_availability', 1) == 1)
                <th class="sorting @if($order == 'availability') sorted @endif" data-order="availability">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.availability') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (evo()->getConfig('scom_show_field_products_category', 1) == 1)
                <th class="sorting @if($order == 'category') sorted @endif" data-order="category">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.category') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (evo()->getConfig('check_sMultisite', false) && evo()->getConfig('scom_show_field_products_websites', 1) == 1)
                <th class="sorting @if($order == 'websites') sorted @endif" data-order="websites">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.websites') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (evo()->getConfig('scom_show_field_products_visibility', 1) == 1)
                <th class="sorting @if($order == 'published') sorted @endif" data-order="published">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.visibility') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            @if (evo()->getConfig('scom_show_field_products_views', 1) == 1)
                <th class="sorting @if($order == 'views') sorted @endif" data-order="views">
                    <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.views') <i class="fas fa-sort" style="color: #036efe;"></i></button>
                </th>
            @endif
            <th id="action-btns">@lang('global.onlineusers_action')</th>
        </tr>
        </thead>
        <tbody>
        @foreach($products as $product)
            <tr style="height: 42px;" id="product-{{$product->id}}">
                @if (evo()->getConfig('scom_show_field_products_id', 1) == 1)
                    <td>{{$product->id}}</td>
                @endif
                @if (evo()->getConfig('scom_show_field_products_sku', 1) == 1)
                    <td>{{$product->sku}}</td>
                @endif
                <td>
                    <img src="{{$product->coverSrc}}" alt="{{$product->coverSrc}}" class="post-thumbnail">
                    <a href="{{$product->link}}" target="_blank"><b>{{$product->pagetitle}}</b></a>
                </td>
                @if (evo()->getConfig('scom_show_field_products_price', 1) == 1)
                    <td>{{$product->price_regular}}</td>
                @endif
                @if (evo()->getConfig('scom_show_field_products_price_special', 1) == 1)
                    <td>{{$product->price_special}}</td>
                @endif
                @if (evo()->getConfig('scom_show_field_products_price_opt', 1) == 1)
                    <td>{{$product->price_opt_regular}}</td>
                @endif
                @if (evo()->getConfig('scom_show_field_products_price_opt_special', 1) == 1)
                    <td>{{$product->price_opt_special}}</td>
                @endif
                @if (evo()->getConfig('scom_show_field_products_quantity', 1) == 1)
                    <td>{{$product->quantity}}</td>
                @endif
                @if (evo()->getConfig('scom_show_field_products_availability', 1) == 1)
                    <td>{{$product->availability}}</td>
                @endif
                @if (evo()->getConfig('scom_show_field_products_category', 1) == 1)
                    <td>{{$product->category}}</td>
                @endif
                @if (evo()->getConfig('check_sMultisite', false) && evo()->getConfig('scom_show_field_products_websites', 1) == 1)
                    <td>{{$product->websites}}</td>
                @endif
                @if (evo()->getConfig('scom_show_field_products_visibility', 1) == 1)
                    <td>{{$product->published}}</td>
                @endif
                @if (evo()->getConfig('scom_show_field_products_views', 1) == 1)
                    <td>{{$product->views}}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="seiger__bottom">
    <div class="seiger__bottom-item"></div>
    {{--<div class="paginator">{{$products->render()}}</div>--}}
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
@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button2" href="{!!$moduleUrl!!}&get=product&i=0" class="btn btn-primary" title="@lang('sCommerce::global.add_product_help')">
                <i class="fa fa-plus"></i> <span>@lang('global.add')</span>
            </a>
        </div>
    </div>
    <script>
        $('.sorting').on('click', function () {
            let order = $(this).attr('data-order');
            let direc = $(this).attr('data-direc');
            window.location.href = '{!!$moduleUrl!!}&get=products&order='+order+'&direc='+direc;
        });
        $('.js_delete').on('click', function () {
            let subscriber = $(this).closest('tr').attr('id').split('-')[1];
            alertify
                .confirm(
                    "@lang('sSettings::global.are_you_sure')",
                    "Якщо Ви натиснете кнопку видалити, користувача буде видалено безповоротно.",
                    function(){
                        alertify.error("@lang('sSettings::global.deleted')");
                        jQuery.ajax({
                            url: '{!!$url!!}&get=user-delete',
                            type: 'POST',
                            dataType: 'JSON',
                            data: 'subscriber=' + subscriber,
                            success: function (ajax) {
                                if (ajax.status == 1) {
                                    window.location.reload();
                                }
                            }
                        });
                    },
                    function(){
                        alertify.success("@lang('sSettings::global.canceled')")
                    })
                .set('labels',{ok:"@lang('global.delete')",cancel:"@lang('global.cancel')"})
                .set({transition:'zoom'});
        });
        //dropdown
        document.addEventListener("click", function (event) {
        const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(function(dropdown) {
                if (!dropdown.contains(event.target)) {
                    dropdown.classList.remove('active')
                } else {
                    dropdown.classList.toggle('active')
                }
            });
        });
        //dropdown
        //form clear
        const clearFrom = document.querySelector('.seiger-clear-search');
        const searchForm = document.querySelector('.seiger__search');
        clearFrom?.addEventListener('click', () => {
            searchForm.value = "";
            document.querySelector('#search').submit();
        })
        // cookies
        const cookieName = "scom_products_page_items";
        const cookieItems = document.querySelectorAll('[data-items]');
        const actualCount = document.querySelector('[data-actual]');
        const cookieValue = document.cookie.split('; ').find(row => row.startsWith(cookieName + '='))?.split('=')[1];
        console.log(cookieValue);
        if (cookieValue !== undefined) {
            actualCount.setAttribute('data-actual', cookieValue);
        } else {
            setCookie(cookieName, 50, 30)
        }
        // Function to set a cookie
        function setCookie(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + value + expires + "; path=/";
        }
        function getCookie(name) {
            document.cookie
        }
        cookieItems?.forEach(cookieItem => {
            cookieItem.addEventListener('click', (e) => {
                let itemValue = cookieItem.getAttribute('data-items')
                console.log(itemValue);
                setCookie(cookieName, itemValue, 30)
            })
        })
    </script>
@endpush
