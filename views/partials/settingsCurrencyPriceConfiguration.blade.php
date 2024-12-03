<h3 class="sectionTrans">
    @lang('sCommerce::global.currency_price_configuration')
    <div class="btn-group">
        <span class="btn btn-primary" onclick="addCurrencyItem('available_currencies')">
            <i class="fa fa-plus"></i> <span>@lang('sCommerce::global.add')</span>
        </span>
    </div>
</h3>
<div class="row form-row">
    <div class="col-auto">
        <label for="basic__main_currency">@lang('sCommerce::global.main_currency')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.main_currency_help')"></i>
    </div>
    <div class="col col-4 col-md-3 col-lg-2">
        <select id="basic__main_currency" class="form-control" name="basic__main_currency" onchange="documentDirty=true;">
            @foreach(sCommerce::getCurrencies(sCommerce::config('basic.available_currencies', [])) as $cur)
                <option value="{{$cur['alpha']}}" @if(sCommerce::config('basic.main_currency', '') == $cur['alpha']) selected @endif title="{{$cur['name']}}">{{$cur['alpha']}} ({!!$cur['symbol']!!})</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="basic__available_currencies">@lang('sCommerce::global.available_currencies')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.available_currencies_help')"></i>
    </div>
    <div class="col">
        <div class="col col-12 col-sm-12 col-md-6">
            <div id="available_currencies" class="row form-row widgets sortable">
                @foreach(sCommerce::config('basic.available_currencies', []) as $cur)
                    @include('sCommerce::partials.settingsCurrencyItem', sCommerce::getCurrencies([$cur])->first())
                @endforeach
            </div>
        </div>
    </div>
</div>
<div class="split my-3"></div>

@push('scripts.bot')
    <div class="draft-currencies hidden">
        <select name="select_available_currencies" class="form-control niceSelect2SearchCurrencies">
            <option value=""></option>
            @foreach(sCommerce::getCurrencies() as $currency)
                <option value="{{$currency['alpha']}}">{{$currency['alpha']}} ({{$currency['symbol']}}) - {{$currency['name']}}</option>
            @endforeach
        </select>
    </div>
    <script>
        function addCurrencyItem(selector) {
            let options = {searchable: true};
            alertify.confirm(
                "@lang('sCommerce::global.add_currency')",
                "",
                function() {
                    let currency = document.querySelector('.ajs-content select').value;
                    fetch('{!!$moduleUrl!!}&get=getCurrencyItem', {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: 'item=' + encodeURIComponent(currency),
                        cache: "no-cache"
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success == 0) {
                                alertify.alert('@lang('sCommerce::global.attention')','@lang('sCommerce::global.no_data_found')');
                            } else {
                                document.getElementById('available_currencies').insertAdjacentHTML('beforeend', data.view);
                                documentDirty = true;
                            }
                        })
                        .catch(error => console.error('Error:', error));

                    document.querySelector('.ajs-content').replaceChildren();
                },
                function() {
                    document.querySelector('.ajs-content').replaceChildren();
                }
            ).set({
                labels: {ok:"@lang('sCommerce::global.add')", cancel:"@lang('global.cancel')"},
                transition: 'zoom',
                movable: false,
                closableByDimmer: false,
                pinnable: false,
                onfocus: function() {
                    NiceSelect.bind(document.querySelector('.alertify').querySelector(".niceSelect2SearchCurrencies"), options);
                    document.querySelectorAll('.niceSelect2Search')?.forEach(el => NiceSelect.bind(el, options));
                },
                onshow: function() {
                    document.querySelectorAll('.alertify')?.forEach(modal => {
                        modal.classList.add('scommerce-modal');
                    });
                    document.querySelectorAll('.ajs-ok')?.forEach(buttonOk => {
                        buttonOk.classList.add('ajs-ok-blue');
                    });
                },
                onclose: function() {
                    document.querySelectorAll('.scommerce-modal')?.forEach(modal => {
                        modal.classList.remove('scommerce-modal');
                    });
                    document.querySelectorAll('.ajs-ok-blue')?.forEach(buttonOk => {
                        buttonOk.classList.remove('ajs-ok-blue');
                    });
                },
            }).setContent(
                document.querySelector('.draft-currencies').innerHTML
            );
            document.querySelector('.ajs-ok').classList.add('ajs-ok-blue');
            document.querySelector('.alertify ').classList.add('scommerce-modal');
        }
    </script>
@endpush