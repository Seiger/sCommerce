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
            <th class="sorting @if($order == 'id') sorted @endif" data-order="id">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">ID <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'sku') sorted @endif" data-order="sku">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.sku') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'pagetitle') sorted @endif" data-order="pagetitle">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.product_name') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'price_regular') sorted @endif" data-order="price_regular">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.price') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'price_special') sorted @endif" data-order="price_special">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.price_special') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'price_opt_regular') sorted @endif" data-order="price_opt_regular">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.price_opt') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'price_opt_special') sorted @endif" data-order="price_opt_special">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.price_opt_special') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'quantity') sorted @endif" data-order="quantity">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.quantity') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'availability') sorted @endif" data-order="availability">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.availability') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'category') sorted @endif" data-order="category">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.category') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'websites') sorted @endif" data-order="websites">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.websites') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th class="sorting @if($order == 'published') sorted @endif" data-order="published">
                <button class="seiger-sort-btn" style="padding:0;displai: inline;border: none;background: transparent;">@lang('sCommerce::global.visibility') <i class="fas fa-sort" style="color: #036efe;"></i></button>
            </th>
            <th id="action-btns">@lang('global.onlineusers_action')</th>
        </tr>
        </thead>
        <tbody>
        @foreach($subscribers as $subscriber)
            <tr style="height: 42px;" id="subscriber-{{$subscriber->id}}">
                <td>{{$subscriber->email}}</td>
                <td>
                    <span class="seiger-sub-date">{{Carbon\Carbon::parse($subscriber->created_at)->format('d.m.Y H:i')}}</span>
                </td>
                <td>
                    @if($subscriber->blocked == 0 && $subscriber->subscribe == 1)
                        <span class="seiger-subs-active seiger-subs-status-title">Активний</span>
                    @else
                        @if($subscriber->subscribe == 0)
                            <span class="seiger-subs-unsubs seiger-subs-status-title">Відписався</span>
                        @else
                            <span class="seiger-subs-block seiger-subs-status-title">Заблокований</span>
                        @endif
                    @endif
                </td>
                <td style="text-align:center;">
                    <button class="seiger-sub-lock-status border-0 js_lock p-0">
                        @if($subscriber->blocked == 0 && $subscriber->subscribe == 1)
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
                            <path d="M13.3331 9.73333C13.3331 9.02609 13.614 8.34781 14.1141 7.84772C14.6142 7.34762 15.2925 7.06667 15.9997 7.06667C16.707 7.06667 17.3853 7.34762 17.8854 7.84772C18.3855 8.34781 18.6664 9.02609 18.6664 9.73333V10.2667H19.7331V9.73333C19.7331 8.74319 19.3397 7.7936 18.6396 7.09347C17.9395 6.39333 16.9899 6 15.9997 6C15.0096 6 14.06 6.39333 13.3599 7.09347C12.6597 7.7936 12.2664 8.74319 12.2664 9.73333V12.4H10.6664C10.2421 12.4 9.83509 12.5686 9.53504 12.8686C9.23498 13.1687 9.06641 13.5757 9.06641 14V20.4C9.06641 20.8243 9.23498 21.2313 9.53504 21.5314C9.83509 21.8314 10.2421 22 10.6664 22H21.3331C21.7574 22 22.1644 21.8314 22.4644 21.5314C22.7645 21.2313 22.9331 20.8243 22.9331 20.4V14C22.9331 13.5757 22.7645 13.1687 22.4644 12.8686C22.1644 12.5686 21.7574 12.4 21.3331 12.4H13.3331V9.73333Z" fill="#009891"/>
                        </svg>
                        @else
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M19.7331 9.73333V12.4H21.3331C21.7574 12.4 22.1644 12.5686 22.4644 12.8686C22.7645 13.1687 22.9331 13.5757 22.9331 14V20.4C22.9331 20.8243 22.7645 21.2313 22.4644 21.5314C22.1644 21.8314 21.7574 22 21.3331 22H10.6664C10.2421 22 9.83509 21.8314 9.53504 21.5314C9.23498 21.2313 9.06641 20.8243 9.06641 20.4V14C9.06641 13.5757 9.23498 13.1687 9.53504 12.8686C9.83509 12.5686 10.2421 12.4 10.6664 12.4H12.2664V9.73333C12.2664 8.74319 12.6597 7.7936 13.3599 7.09347C14.06 6.39333 15.0096 6 15.9997 6C16.9899 6 17.9395 6.39333 18.6396 7.09347C19.3397 7.7936 19.7331 8.74319 19.7331 9.73333ZM13.3331 9.73333C13.3331 9.02609 13.614 8.34781 14.1141 7.84772C14.6142 7.34762 15.2925 7.06667 15.9997 7.06667C16.707 7.06667 17.3853 7.34762 17.8854 7.84772C18.3855 8.34781 18.6664 9.02609 18.6664 9.73333V12.4H13.3331V9.73333Z" fill="#5F2D8C"/>
                        </svg>
                        @endif
                    </button>
                </td>
                <td style="text-align:center;">
                    <button class="seiger-sub-remove border-0 js_delete p-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 15 15" fill="none">
                            <rect x="13.6328" y="0.558838" width="1.80855" height="17.9902" transform="rotate(45 13.6328 0.558838)" fill="#EF4B67"/>
                            <rect x="0.912109" y="1.83777" width="1.80855" height="17.9902" transform="rotate(-45 0.912109 1.83777)" fill="#EF4B67"/>
                        </svg>
                    </button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="seiger__bottom">
    <div class="seiger__bottom-item"></div>
    {{--<div class="paginator">{{$subscribers->render()}}</div>--}}
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
    <script>
        $('.sorting').on('click', function () {
            let order = $(this).attr('data-order');
            let direc = $(this).attr('data-direc');
            window.location.href = '{!!$url!!}&get=subscribers&order='+order+'&direc='+direc;
        });
        $('.js_lock').on('click', function () {
            let subscriber = $(this).closest('tr').attr('id').split('-')[1];
            jQuery.ajax({
                url: '{!!$url!!}&get=user-lock',
                type: 'POST',
                dataType: 'JSON',
                data: 'subscriber=' + subscriber,
                success: function (ajax) {
                    if (ajax.status == 1) {
                        window.location.reload();
                    }
                }
            });
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
