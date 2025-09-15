<div class="row form-row widgets">
    <div class="col-sm-12">
        {{----}}
    </div>
</div>
<div class="seiger__bottom">
    <div class="seiger__bottom-item"></div>
    <div class="paginator">{{$items->render()}}</div>
    @if($items?->count() > 10)
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
                        <a class="dropdown__menu-link" data-items="50" href="{!!sCommerce::moduleUrl()!!}&get=integrations">50</a>
                    </li>
                    <li class="dropdown__menu-item">
                        <a class="dropdown__menu-link" data-items="100" href="{!!sCommerce::moduleUrl()!!}&get=integrations">100</a>
                    </li>
                    <li class="dropdown__menu-item">
                        <a class="dropdown__menu-link" data-items="150" href="{!!sCommerce::moduleUrl()!!}&get=integrations">150</a>
                    </li>
                    <li class="dropdown__menu-item">
                        <a class="dropdown__menu-link" data-items="200" href="{!!sCommerce::moduleUrl()!!}&get=integrations">200</a>
                    </li>
                </ul>
            </div>
        </div>
    @endif
</div>
@push('scripts.top')
    <script>
        const cookieName = "scom_integrations_page_items";
    </script>
@endpush
