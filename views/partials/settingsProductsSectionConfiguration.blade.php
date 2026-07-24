<style>
    .scommerce-products-settings-intro { margin:0 0 18px; color:#475467; line-height:1.45; }
    .scommerce-products-settings-layout { display:grid; grid-template-columns:1fr; gap:18px; margin-bottom:18px; }
    .scommerce-products-settings-section { min-width:0; padding:18px; border:1px solid #dde3ea; background:#fff; box-shadow:0 4px 14px rgba(38,50,56,.05); }
    .scommerce-products-settings-title { display:flex; align-items:center; gap:8px; margin:0 0 14px; color:#1f2937; font-size:16px; font-weight:700; }
    .scommerce-products-settings-title i { color:#036efe; }
    .scommerce-products-settings-section .row.form-row { min-width:0; }
    .scommerce-products-settings-section .row.form-row > .col { min-width:0; }
    #form .scommerce-products-settings-section .select2-container { width:100% !important; max-width:100%; min-width:0; }
    @media (min-width:1200px) {
        .scommerce-products-settings-layout { grid-template-columns:repeat(2,minmax(0,1fr)); align-items:start; }
    }
</style>

<h3>@lang('sCommerce::global.products_settings')</h3>
<p class="scommerce-products-settings-intro">@lang('sCommerce::global.products_settings_intro')</p>

<div class="scommerce-products-settings-layout">
    <section class="scommerce-products-settings-section scommerce-products-settings-section--functionality">
        <h4 class="scommerce-products-settings-title">
            <i class="fa fa-cube"></i>
            @lang('sCommerce::global.management_product_functionality')
        </h4>
        @include('sCommerce::partials.settingsProductConfiguration')
    </section>

    <section class="scommerce-products-settings-section scommerce-products-settings-section--list">
        <h4 class="scommerce-products-settings-title">
            <i class="fa fa-list"></i>
            @lang('sCommerce::global.representation_products_fields')
        </h4>
        @include('sCommerce::partials.settingsProductsConfiguration')
    </section>
</div>
<div class="split my-3"></div>
