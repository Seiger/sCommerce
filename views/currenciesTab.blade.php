<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=currenciesSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=currencies" />
    @if(count(sCommerce::config('basic.available_currencies', [])) && trim(sCommerce::config('basic.main_currency', '')))
        <h3>@lang('sCommerce::global.currency_conversion')</h3>
        @php($currs = sCommerce::getCurrencies(sCommerce::config('basic.available_currencies', [])))
        @foreach($currs as $curr)
            @foreach($currs as $cur)
                @if($curr['alpha'] != $cur['alpha'])
                    <div class="row form-row">
                        <div class="col-auto">
                            <label for="currencies__{{$curr['alpha']}}_{{$cur['alpha']}}">{{$curr['alpha']}} --> {{$cur['alpha']}}</label>
                        </div>
                        <div class="col col-4 col-md-3 col-lg-1">
                            <input type="number" id="currencies__{{$curr['alpha']}}_{{$cur['alpha']}}" name="currencies__{{$curr['alpha']}}_{{$cur['alpha']}}" class="form-control" value="{{config('seiger.settings.sCommerceCurrencies.' . $curr['alpha'].'_'.$cur['alpha'], 1)}}" onchange="documentDirty=true;">
                        </div>
                    </div>
                @endif
            @endforeach
        @endforeach
        <div class="split my-3"></div>
    @endif
</form>

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!$moduleUrl!!}">
                <i class="fa fa-times-circle"></i><span>@lang('global.cancel')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fas fa-save"></i><span>@lang('global.save')</span>
            </a>
        </div>
    </div>
@endpush
