<style>
    .scommerce-general-settings-intro { margin:0 0 18px; color:#475467; line-height:1.45; }
    .scommerce-general-settings-layout { display:grid; grid-template-columns:1fr; gap:18px; margin-bottom:18px; }
    .scommerce-general-settings-section { min-width:0; padding:18px; border:1px solid #dde3ea; background:#fff; box-shadow:0 4px 14px rgba(38,50,56,.05); }
    .scommerce-general-settings-title { display:flex; align-items:center; gap:8px; margin:0 0 14px; color:#1f2937; font-size:16px; font-weight:700; }
    .scommerce-general-settings-title > i { color:#036efe; }
    .scommerce-general-settings-title .btn-group { margin-left:auto; }
    .scommerce-general-settings-section .row.form-row { min-width:0; }
    .scommerce-general-settings-section .row.form-row > .col { min-width:0; }
    #form .scommerce-general-settings-section .select2-container { width:100% !important; max-width:100%; min-width:0; }
    @media (min-width:1200px) {
        .scommerce-general-settings-layout { grid-template-columns:repeat(2,minmax(0,1fr)); align-items:start; }
    }
</style>

<h3>@lang('sCommerce::global.general_settings')</h3>
<p class="scommerce-general-settings-intro">@lang('sCommerce::global.general_settings_intro')</p>

<div class="scommerce-general-settings-layout">
    <section class="scommerce-general-settings-section scommerce-general-settings-section--base">
        <h4 class="scommerce-general-settings-title">
            <i class="fa fa-sliders"></i>
            @lang('sCommerce::global.management_base_functionality')
        </h4>
        @include('sCommerce::partials.settingsBaseConfiguration')
    </section>

    <section class="scommerce-general-settings-section scommerce-general-settings-section--currency">
        <h4 class="scommerce-general-settings-title">
            <i class="fa fa-money"></i>
            @lang('sCommerce::global.currency_price_configuration')
            <span class="btn-group">
                <button type="button" class="btn btn-primary" onclick="addCurrencyItem('available_currencies')">
                    <i class="fa fa-plus"></i> <span>@lang('sCommerce::global.add')</span>
                </button>
            </span>
        </h4>
        @include('sCommerce::partials.settingsCurrencyPriceConfiguration')
    </section>
</div>
<div class="split my-3"></div>
