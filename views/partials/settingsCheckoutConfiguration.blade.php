<style>
    .scommerce-checkout-settings-intro { margin:0 0 18px; color:#475467; line-height:1.45; }
    .scommerce-checkout-settings-layout { display:grid; grid-template-columns:1fr; gap:18px; margin-bottom:18px; }
    .scommerce-checkout-settings-section { padding:18px; border:1px solid #dde3ea; background:#fff; box-shadow:0 4px 14px rgba(38,50,56,.05); }
    .scommerce-checkout-settings-title { display:flex; align-items:center; gap:8px; margin:0 0 14px; color:#1f2937; font-size:16px; font-weight:700; }
    .scommerce-checkout-settings-title i { color:#036efe; }
    .scommerce-checkout-settings-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px 22px; }
    .scommerce-checkout-settings-field { min-width:0; }
    .scommerce-checkout-settings-field--wide { grid-column:1 / -1; }
    .scommerce-checkout-settings-field > label { display:flex; align-items:center; gap:6px; margin:0 0 6px; font-weight:700; color:#344054; }
    .scommerce-checkout-settings-field .form-control { min-height:38px; }
    .scommerce-checkout-settings-help { display:block; margin-top:6px; color:#667085; line-height:1.35; }
    #form .scommerce-checkout-settings-field > .select2-container { width:100% !important; max-width:100%; min-width:0; }
    @media (max-width:991px) {
        .scommerce-checkout-settings-grid { grid-template-columns:1fr; }
        .scommerce-checkout-settings-field--wide { grid-column:auto; }
    }
    @media (min-width:1200px) {
        .scommerce-checkout-settings-layout { grid-template-columns:repeat(2,minmax(0,1fr)); align-items:start; }
    }
</style>

<h3>@lang('sCommerce::global.checkout_settings')</h3>
<p class="scommerce-checkout-settings-intro">@lang('sCommerce::global.checkout_settings_intro')</p>

<div class="scommerce-checkout-settings-layout">
    <section class="scommerce-checkout-settings-section">
        <h4 class="scommerce-checkout-settings-title">
            <i class="fa fa-shopping-cart"></i>
            @lang('sCommerce::global.managing_cart_functionality')
        </h4>
        <div class="scommerce-checkout-settings-grid">
            @include('sCommerce::partials.settingsCartConfiguration')
        </div>
    </section>

    <section class="scommerce-checkout-settings-section">
        <h4 class="scommerce-checkout-settings-title">
            <i class="fa fa-file-text-o"></i>
            @lang('sCommerce::global.orders_settings')
        </h4>
        <div class="scommerce-checkout-settings-grid">
            @include('sCommerce::partials.settingsOrdersConfiguration')
        </div>
    </section>
</div>
<div class="split my-3"></div>
