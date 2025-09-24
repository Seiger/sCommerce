<style>
    .btn.disabled {opacity:0.6;cursor:not-allowed;pointer-events:none;}
    .btn.disabled:hover {opacity:0.6;}
    .widget-log {
        height:150px;overflow-y:auto;background:#f1f1f1;border:1px solid #e1e1e1;border-radius:.5rem;
        padding:.6rem .9rem .6rem .9rem;white-space:normal;line-height:1.15;
        font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial;font-size:.8rem;
    }
    .widget-log .line-info {color:inherit;}
    .widget-log .line-success {color:#198754;}
    .widget-log .line-error {color:#dc3545;}
    .widget-progress {
        position:relative;display:none;/*grid*/grid-template-columns:1fr auto;align-items:center;
        gap:.5rem;height:14px;margin:.1rem .9rem .1rem .9rem;color:#111827;background:#e9eef3;
        border-radius:999px;overflow:clip;
    }
    .widget-progress .widget-progress__bar {
        grid-column:1/2;height:100%;width:0%;display:block;
        background-image:
                linear-gradient(90deg, #2563eb 0%, #60a5fa 100%),
                repeating-linear-gradient(45deg, rgba(255,255,255,.12) 0 8px, rgba(255,255,255,.06) 8px 16px);
        background-size: 100% 100%, 24px 100%;
        border-radius:999px;transition:width .6s linear;will-change:width;
        animation: wgShine 1.2s linear infinite;
    }
    .widget-progress .widget-progress__cap {
        position:absolute;left:0;top:0;height:100%;width:6px;border-radius:999px;
        background:radial-gradient(120% 100% at 100% 50%, rgba(17,24,39,.05) 0 60%, transparent 70%);
        pointer-events:none;transform:translateX(0);transition:transform .16s linear;
    }
    .widget-progress .widget-progress__meta {
        grid-column:2/3;display:inline-flex;align-items:baseline;gap:.4rem;font-size:.75rem;line-height:1;
        user-select:none;margin-right:.5rem;
    }
    .widget-progress .widget-progress__pct {font-variant-numeric:tabular-nums;}
    .widget-progress .widget-progress__eta {opacity:.75;font-style:normal;}
    .widget-progress.is-indeterminate .widget-progress__bar {width:35%;animation:wgIndet 1.1s ease-in-out infinite;}
    body.darkness .widget-progress {background:#1f2937;color:#e5e7eb;}
    body.darkness .widget-progress .widget-progress__bar {
        background-image:
                linear-gradient(90deg, #3b82f6 0%, #93c5fd 100%),
                repeating-linear-gradient(45deg, rgba(255,255,255,.16) 0 8px, rgba(255,255,255,.08) 8px 16px);
    }
    body.darkness .widget-progress .widget-progress__cap {
        background: radial-gradient(120% 100% at 100% 50%, rgba(255,255,255,.10) 0 60%, transparent 70%);
    }
    @keyframes wgShine {to {background-position: 0 0, 24px 0;} }
    @keyframes wgIndet {0% {transform:translateX(-40%);} 50% {transform: translateX(30%);} 100% {transform: translateX(110%);} }
    @keyframes wgPulse {0%, 100%{filter: saturate(1);} 50% {filter: saturate(1.25);} }
    @media (prefers-reduced-motion: reduce){
        .widget-progress .widget-progress__bar {animation: none !important; transition: none;}
        .widget-progress.is-error .widget-progress__bar {animation: none !important;}
    }
</style>
<div class="row form-row widgets">
    @foreach($items as $item)
        @if($item)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center" @if($item->active)style="background-color:oklch(95.1% .026 236.824)"@endif>
                        <div>{!!$item->icon!!} {!!$item->title!!}</div>
                        <a href="{!!sCommerce::moduleUrl()!!}&get=integration&i={{$item->id}}{{request()->has('page') ? '&page=' . request()->page : ''}}" class="text-muted" data-tooltip="@lang('global.edit')"><i class="fas fa-cogs"></i></a>
                    </div>
                    <div class="card-block">{!!$item->widget!!}</div>
                </div>
            </div>
        @endif
    @endforeach
</div>
<div class="seiger__bottom">
    <div class="seiger__bottom-item"></div>
    <div class="paginator">{{$items?->render()}}</div>
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
@include('sCommerce::scripts.integrations')
@include('sCommerce::scripts.global')
@push('scripts.top')
    <script>
        const cookieName = "scom_per_page";
    </script>
@endpush
