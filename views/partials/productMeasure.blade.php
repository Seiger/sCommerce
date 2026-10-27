@php
    use Seiger\sCommerce\Models\sProduct;
    use Seiger\sCommerce\Services\MeasuredQuantity;

    $measureId = (int)($item->measure ?? 0);
    $measureAlias = sProduct::MEASURE_ALIASES[$measureId] ?? MeasuredQuantity::PIECE;
    $defaults = $measureAlias !== MeasuredQuantity::PIECE ? MeasuredQuantity::defaults($measureAlias) : null;
    $baseUnit = $defaults ? MeasuredQuantity::translate('unit_' . MeasuredQuantity::BASE_UNITS[$measureAlias]) : '';
@endphp
{{-- Products sold by weight, length, volume or count; quantities are entered in base units. --}}
<div class="row form-row" id="product-measure">
    <div class="row-col col-lg-3 col-md-6 col-12">
        <div class="row form-row">
            <div class="col-auto col-title">
                <label for="measure">@lang('sCommerce::global.measure')</label>
                <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.measure_help')"></i>
            </div>
            <div class="col">
                <select id="measure" name="measure" class="form-control" onchange="documentDirty=true;">
                    <option value="0" @selected($measureId === sProduct::MEASURE_PIECE)>@lang('sCommerce::global.measure_piece')</option>
                    @foreach(sProduct::MEASURE_ALIASES as $id => $alias)
                        <option value="{{$id}}" @selected($measureId === $id)>@lang('sCommerce::global.measure_' . $alias)</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="row-col col-lg-3 col-md-6 col-12" data-measured>
        <div class="row form-row">
            <div class="col-auto col-title">
                <label for="price_unit">@lang('sCommerce::global.price_unit')</label>
                <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_unit_help')"></i>
            </div>
            <div class="col">
                <select id="price_unit" name="price_unit" class="form-control" onchange="documentDirty=true;">
                    <option value="">@lang('sCommerce::global.site_default')</option>
                    @foreach(sProduct::MEASURE_ALIASES as $alias)
                        @foreach(MeasuredQuantity::units($alias) as [$unit, $factor])
                            @if($alias !== 'count')
                                <option value="{{$unit}}" data-measure="{{$alias}}" data-factor="{{$factor}}" @selected($measureAlias === $alias && ($item->price_unit ?? '') === $unit)>{{MeasuredQuantity::translate('unit_' . $unit)}}</option>
                            @endif
                        @endforeach
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="row-col col-lg-3 col-md-6 col-12" data-measured>
        <div class="row form-row">
            <div class="col-auto col-title">
                <label for="price_per">@lang('sCommerce::global.price_per')</label>
                <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_per_help')"></i>
            </div>
            <div class="col">
                <select id="price_per" name="price_per" class="form-control" onchange="documentDirty=true;">
                    <option value="">@lang('sCommerce::global.site_default')</option>
                    @foreach(array_unique(array_merge(MeasuredQuantity::PRICE_BASES, MeasuredQuantity::COUNT_BASES)) as $basis)
                        <option value="{{$basis}}" data-bases="{{implode(' ', array_merge(in_array($basis, MeasuredQuantity::PRICE_BASES, true) ? ['weight', 'length', 'volume'] : [], in_array($basis, MeasuredQuantity::COUNT_BASES, true) ? ['count'] : []))}}" @selected((int)($item->price_per ?? 0) === $basis)>{{number_format($basis, 0, '.', ' ')}}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="row-col col-lg-3 col-md-6 col-12" data-measured>
        <div class="row form-row">
            <div class="col-auto col-title">
                <label for="quantity_rounding">@lang('sCommerce::global.quantity_rounding')</label>
                <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.quantity_rounding_help')"></i>
            </div>
            <div class="col">
                <select id="quantity_rounding" name="quantity_rounding" class="form-control" onchange="documentDirty=true;">
                    <option value="">@lang('sCommerce::global.site_default')</option>
                    @foreach(MeasuredQuantity::ROUNDINGS as $rounding)
                        <option value="{{$rounding}}" @selected(($item->quantity_rounding ?? '') === $rounding)>@lang('sCommerce::global.quantity_rounding_' . $rounding)</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    @foreach(['quantity_step' => 'step', 'quantity_min' => 'min', 'quantity_tolerance' => 'tolerance'] as $column => $default)
        <div class="row-col col-lg-3 col-md-6 col-12" data-measured>
            <div class="row form-row">
                <div class="col-auto col-title">
                    <label for="{{$column}}">@lang('sCommerce::global.' . $column)</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.' . $column . '_help')"></i>
                </div>
                <div class="input-group col">
                    <input type="number" id="{{$column}}" name="{{$column}}" class="form-control" min="{{$column === 'quantity_tolerance' ? 0 : 1}}" step="1"
                           value="{{$item->{$column} ?? ''}}" placeholder="{{$defaults[$default] ?? ''}}" onchange="documentDirty=true;">
                    <div class="input-group-append"><span class="input-group-text"><small data-base-unit>{{$column === 'quantity_tolerance' ? '‱' : $baseUnit}}</small></span></div>
                </div>
            </div>
        </div>
    @endforeach
</div>
<script>
    (function () {
        const measures = @json(sProduct::MEASURE_ALIASES);
        const baseUnits = @json(collect(MeasuredQuantity::BASE_UNITS)->map(fn ($unit) => MeasuredQuantity::translate('unit_' . $unit)));
        const select = document.getElementById('measure');
        function sync() {
            const measure = measures[select.value] || 'piece';
            document.querySelectorAll('#product-measure [data-measured]').forEach(el => el.style.display = measure === 'piece' ? 'none' : '');
            document.querySelectorAll('#price_unit option[data-measure]').forEach(option => {
                option.hidden = option.dataset.measure !== measure;
                if (option.hidden && option.selected) option.parentElement.value = '';
            });
            document.getElementById('price_unit').closest('[data-measured]').style.display = measure === 'piece' || measure === 'count' ? 'none' : '';
            document.querySelectorAll('#price_per option[data-bases]').forEach(option => {
                option.hidden = !option.dataset.bases.split(' ').includes(measure);
                if (option.hidden && option.selected) option.parentElement.value = '';
            });
            document.querySelectorAll('#product-measure small[data-base-unit]').forEach(el => {
                if (el.textContent !== '‱') el.textContent = baseUnits[measure] || '';
            });
        }
        select.addEventListener('change', sync);
        sync();
    })();
</script>
