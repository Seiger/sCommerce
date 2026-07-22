@php
    $notificationGroups = [
        'administrator' => [
            'icon' => 'fa-user-shield',
            'title' => __('sCommerce::global.notifications_email_administrator'),
            'templates' => [
                'admin_order' => __('sCommerce::global.notifications_email_template_admin_order'),
                'admin_fast_order' => __('sCommerce::global.notifications_email_template_admin_fast_order'),
            ],
        ],
        'customer' => [
            'icon' => 'fa-user',
            'title' => __('sCommerce::global.notifications_email_customer'),
            'templates' => [
                'customer_order' => __('sCommerce::global.notifications_email_template_customer_order'),
                'customer_fast_order' => __('sCommerce::global.notifications_email_template_customer_fast_order'),
            ],
        ],
    ];
@endphp

<style>
    .scommerce-email-settings-intro { margin:0 0 18px; color:#475467; line-height:1.45; }
    .scommerce-email-settings-layout { display:grid; grid-template-columns:1fr; gap:18px; margin-bottom:18px; }
    .scommerce-email-settings-section { padding:18px; border:1px solid #dde3ea; background:#fff; box-shadow:0 4px 14px rgba(38,50,56,.05); }
    .scommerce-email-settings-title { display:flex; align-items:center; gap:8px; margin:0 0 14px; color:#1f2937; font-size:16px; font-weight:700; }
    .scommerce-email-settings-title i { color:#036efe; }
    .scommerce-email-settings-field { margin:0 0 14px; min-width:0; }
    .scommerce-email-settings-field:last-child { margin-bottom:0; }
    .scommerce-email-settings-field > label { display:flex; align-items:center; gap:6px; margin:0 0 6px; font-weight:700; color:#344054; }
    .scommerce-email-settings-field .form-control { min-height:38px; }
    .scommerce-email-settings-help { display:block; margin-top:6px; color:#667085; line-height:1.35; }
    .scommerce-email-settings-control { display:flex; flex-wrap:nowrap; align-items:stretch; width:100%; min-width:0; }
    .scommerce-email-settings-control .input-group-prepend { display:flex; flex:0 0 38px; width:38px; align-items:center; justify-content:center; margin:0; border:1px solid #ced4da; border-right:0; background:#f8f9fa; }
    .scommerce-email-settings-control .form-checkbox { width:16px; height:16px; min-height:0 !important; margin:0; padding:0; box-shadow:none; }
    .scommerce-email-settings-control > select.form-control { flex:1 1 auto; width:calc(100% - 38px); min-width:0; border-top-left-radius:0; border-bottom-left-radius:0; }
    @media (min-width:1200px) {
        .scommerce-email-settings-layout { grid-template-columns:repeat(2,minmax(0,1fr)); align-items:start; }
    }
</style>

<h3>@lang('sCommerce::global.notifications_email')</h3>
<p class="scommerce-email-settings-intro">
    @lang('sCommerce::global.notifications_email_intro')
    {!! __('sCommerce::global.notifications_email_layout', ['file' => '/views/notifications/email/layout.blade.php', 'directory' => '/views/notifications/email/']) !!}
</p>

<div class="scommerce-email-settings-layout">
    @foreach($notificationGroups as $groupKey => $group)
        <section class="scommerce-email-settings-section">
            <h4 class="scommerce-email-settings-title">
                <i class="fa {{ $group['icon'] }}"></i>
                {{ $group['title'] }}
            </h4>

            @if($groupKey === 'administrator')
                <div class="scommerce-email-settings-field">
                    <label for="notifications__email_addresses">
                        @lang('sCommerce::global.notifications_email_addresses_default')
                    </label>
                    <input
                        type="text"
                        class="form-control"
                        id="notifications__email_addresses"
                        name="notifications__email_addresses"
                        value="{{ sCommerce::config('notifications.email_addresses', '') }}"
                        onchange="documentDirty=true;"
                    >
                    <small class="scommerce-email-settings-help">@lang('sCommerce::global.notifications_email_addresses_default_help')</small>
                </div>

                @if(evo()->getConfig('scom_pro', false) && evo()->getConfig('check_sMultisite', false))
                    @foreach(Seiger\sMultisite\Models\sMultisite::all() as $domain)
                        <div class="scommerce-email-settings-field">
                            <label for="notifications__email_addresses{{ $domain->key }}">
                                <span class="badge" style="background-color:{{ $domain->site_color ?? '#60a5fa' }}; color:#fff; font-size:90%;">{{ $domain->site_name }}</span>
                                @lang('sCommerce::global.notifications_email_addresses')
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="notifications__email_addresses{{ $domain->key }}"
                                name="notifications__email_addresses{{ $domain->key }}"
                                value="{{ sCommerce::config('notifications.email_addresses'.$domain->key, '') }}"
                                onchange="documentDirty=true;"
                            >
                            <small class="scommerce-email-settings-help">@lang('sCommerce::global.notifications_email_addresses_site_help')</small>
                        </div>
                    @endforeach
                @endif
            @endif

            @foreach($group['templates'] as $templateKey => $templateLabel)
                @php
                    $templateId = 'notifications__email_template_'.$templateKey;
                    $templateConfig = 'notifications.email_template_'.$templateKey;
                    $templateEnabledId = $templateId.'_on';
                    $templateEnabledConfig = $templateConfig.'_on';
                    $selectedTemplate = sCommerce::config($templateConfig, '');
                @endphp
                <div class="scommerce-email-settings-field">
                    <label for="{{ $templateId }}">
                        {{ $templateLabel }}
                    </label>
                    <div class="input-group scommerce-email-settings-control">
                        <div class="input-group-prepend">
                            <input
                                type="checkbox"
                                class="form-checkbox form-control"
                                onchange="documentDirty=true;"
                                onclick="changestate(document.getElementById('{{ $templateEnabledId }}'));"
                                @checked((int)sCommerce::config($templateEnabledConfig, 1) === 1)
                            >
                            <input
                                type="hidden"
                                id="{{ $templateEnabledId }}"
                                name="{{ $templateEnabledId }}"
                                value="{{ sCommerce::config($templateEnabledConfig, 1) }}"
                            >
                        </div>
                        <select id="{{ $templateId }}" class="form-control" name="{{ $templateId }}" onchange="documentDirty=true;">
                            <option value="" @selected($selectedTemplate === '')></option>
                            @foreach($emailNotifications as $view)
                                <option value="{{ $view }}" @selected($selectedTemplate === $view)>/view/{{ $view }}</option>
                            @endforeach
                        </select>
                    </div>
                    <small class="scommerce-email-settings-help">@lang('sCommerce::global.notifications_email_template_on_help')</small>
                </div>
            @endforeach
        </section>
    @endforeach
</div>
