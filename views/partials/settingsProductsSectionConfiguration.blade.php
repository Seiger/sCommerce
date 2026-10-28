<style>
    .scommerce-products-settings-intro { margin:0 0 18px; color:#475467; line-height:1.45; }
    .scommerce-products-settings-layout { display:grid; grid-template-columns:1fr; gap:18px; margin-bottom:18px; }
    .scommerce-products-settings-section { min-width:0; padding:18px; border:1px solid #dde3ea; background:#fff; box-shadow:0 4px 14px rgba(38,50,56,.05); }
    .scommerce-products-settings-title { display:flex; align-items:center; gap:8px; margin:0 0 14px; color:#1f2937; font-size:16px; font-weight:700; }
    .scommerce-products-settings-title .tabler-icon { color:#036efe; }
    .scommerce-products-settings-section .row.form-row { min-width:0; }
    .scommerce-products-settings-section .row.form-row > .col { min-width:0; }
    #form .scommerce-products-settings-section .select2-container { width:100% !important; max-width:100%; min-width:0; }
    .scommerce-products-settings-column { display:flex; flex-direction:column; gap:18px; min-width:0; }
    .scommerce-products-settings-section--measured .scom-measures-scroll { overflow-x:auto; }
    .scommerce-products-settings-section--measured .scom-table { margin:0; }
    /* The list tables keep a gutter for drag handles; settings tables align with the block padding. */
    .scommerce-products-settings-section--measured .scom-table :is(thead th, tbody td):first-child { padding-left:0; white-space:nowrap; }
    .scommerce-products-settings-section--measured .scom-table :is(thead th, tbody td):last-child { padding-right:0; }
    .scommerce-products-settings-section--measured .scom-table .form-control { min-width:64px; }
    .scommerce-products-settings-section--measured .scom-table input[name$="[max]"] { min-width:108px; }
    .scommerce-products-settings-section--measured .scom-rounding-table td { vertical-align:middle; }
    .scommerce-products-settings-section--measured .scom-rounding-table td:first-child { width:1%; padding-right:18px; }
    .scom-rounding-units { display:flex; flex-wrap:wrap; gap:8px 18px; }
    #form .scom-rounding-units label { display:flex; flex-direction:column; align-items:flex-start; gap:4px; margin:0; font-weight:400; }
    #form .scom-rounding-units .form-control { width:auto; min-width:110px; }
    .scommerce-products-settings-title .fa-question-circle { font-size:14px; font-weight:400; }
    @media (min-width:1200px) {
        .scommerce-products-settings-layout { grid-template-columns:repeat(2,minmax(0,1fr)); align-items:start; }
    }
</style>

<h3>@lang('sCommerce::global.products_settings')</h3>
<p class="scommerce-products-settings-intro">@lang('sCommerce::global.products_settings_intro')</p>

<div class="scommerce-products-settings-layout">
    <section class="scommerce-products-settings-section scommerce-products-settings-section--functionality">
        <h4 class="scommerce-products-settings-title">
            @svg('tabler-package', 'tabler-icon')
            @lang('sCommerce::global.management_product_functionality')
        </h4>
        @include('sCommerce::partials.settingsProductConfiguration')
    </section>

    <div class="scommerce-products-settings-column">
        <section class="scommerce-products-settings-section scommerce-products-settings-section--list">
            <h4 class="scommerce-products-settings-title">
                @svg('tabler-list', 'tabler-icon')
                @lang('sCommerce::global.representation_products_fields')
            </h4>
            @include('sCommerce::partials.settingsProductsConfiguration')
        </section>

        <section class="scommerce-products-settings-section scommerce-products-settings-section--measured">
            <h4 class="scommerce-products-settings-title">
                @svg('tabler-ruler-measure', 'tabler-icon')
                @lang('sCommerce::global.measures_settings')
                <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.measures_settings_help')"></i>
            </h4>
            @include('sCommerce::partials.settingsMeasuredQuantitiesConfiguration')
        </section>
    </div>
</div>
<div class="split my-3"></div>
