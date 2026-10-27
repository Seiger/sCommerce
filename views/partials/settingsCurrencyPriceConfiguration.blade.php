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
        <div class="col-12">
            <div id="available_currencies" class="row form-row widgets scommerce-currency-list">
                @foreach(array_unique((array)sCommerce::config('basic.available_currencies', [])) as $cur)
                    @include('sCommerce::partials.settingsCurrencyItem', sCommerce::getCurrencies([$cur])->first())
                @endforeach
            </div>
        </div>
    </div>
</div>
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
        function sCommerceCurrencyItems(list) {
            return Array.from(list.children).filter(item => item.querySelector('input[name="basic__available_currencies[]"]'));
        }
        function sCommerceCurrencyCodes(list) {
            return sCommerceCurrencyItems(list).map(item => item.querySelector('input[name="basic__available_currencies[]"]').value);
        }
        {{-- One step up or down; the first item wraps to the end and the last one to the start. --}}
        function sCommerceCurrencyMove(item, step) {
            const items = sCommerceCurrencyItems(item.parentNode);
            if (items.length < 2) return;
            const target = (items.indexOf(item) + step + items.length) % items.length;
            const rest = items.filter(other => other !== item);
            item.parentNode.insertBefore(item, rest[target] ?? null);
            documentDirty = true;
        }
        {{-- Position among the other items: the number of item middles above the pointer. --}}
        function sCommerceCurrencyDropIndex(middles, y) {
            return middles.filter(middle => middle < y).length;
        }
        document.addEventListener('click', function(e) {
            const button = e.target.closest('#available_currencies [data-currency-move]');
            if (button) {
                sCommerceCurrencyMove(button.closest('.scommerce-currency-item'), parseInt(button.dataset.currencyMove, 10));
            }
        });
        {{-- Dragging collapses the cards to their headers, so any item can pass any other. --}}
        document.addEventListener('pointerdown', function(e) {
            const header = e.target.closest('#available_currencies .scommerce-currency-handle');
            if (!header || e.button !== 0 || e.target.closest('button, .close-icon')) return;
            const item = header.closest('.scommerce-currency-item');
            const list = item.parentNode;
            const startY = e.clientY;
            let dragging = false;
            const keepHeaderInPlace = function(change) {
                const top = header.getBoundingClientRect().top;
                change();
                window.scrollBy(0, header.getBoundingClientRect().top - top);
            };
            const move = function(ev) {
                if (!dragging) {
                    if (Math.abs(ev.clientY - startY) < 5) return;
                    dragging = true;
                    keepHeaderInPlace(() => { list.classList.add('is-sorting'); item.classList.add('is-dragging'); });
                }
                ev.preventDefault();
                const rest = sCommerceCurrencyItems(list).filter(other => other !== item);
                const before = rest[sCommerceCurrencyDropIndex(rest.map(other => {
                    const rect = other.getBoundingClientRect();
                    return rect.top + rect.height / 2;
                }), ev.clientY)] ?? null;
                if (item.nextElementSibling !== before) {
                    list.insertBefore(item, before);
                    documentDirty = true;
                }
                if (ev.clientY < 40) window.scrollBy(0, -20);
                else if (ev.clientY > window.innerHeight - 40) window.scrollBy(0, 20);
            };
            const end = function() {
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', end);
                document.removeEventListener('pointercancel', end);
                if (dragging) {
                    keepHeaderInPlace(() => { list.classList.remove('is-sorting'); item.classList.remove('is-dragging'); });
                }
            };
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', end);
            document.addEventListener('pointercancel', end);
        });
        function addCurrencyItem(selector) {
            let options = {searchable: true};
            const list = document.getElementById(selector);
            const draft = document.querySelector('.draft-currencies').cloneNode(true);
            const added = sCommerceCurrencyCodes(list);
            draft.querySelectorAll('option').forEach(option => { option.disabled = added.includes(option.value); });
            alertify.confirm(
                "@lang('sCommerce::global.add_currency')",
                "",
                function() {
                    let currency = document.querySelector('.ajs-content select').value;
                    document.querySelector('.ajs-content').replaceChildren();
                    if (!currency) return;
                    if (sCommerceCurrencyCodes(list).includes(currency)) {
                        alertify.warning(@json(__('sCommerce::global.currency_already_added')));
                        return;
                    }
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
                            } else if (!sCommerceCurrencyCodes(list).includes(currency)) {
                                list.insertAdjacentHTML('beforeend', data.view);
                                documentDirty = true;
                            }
                        })
                        .catch(error => console.error('Error:', error));
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
                draft.innerHTML
            );
            document.querySelector('.ajs-ok').classList.add('ajs-ok-blue');
            document.querySelector('.alertify ').classList.add('scommerce-modal');
        }
    </script>
@endpush
