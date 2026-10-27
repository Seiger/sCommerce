@php($measuredUnits = fn (string $measure) => \Seiger\sCommerce\Services\MeasuredQuantity::units($measure))
{{-- The block title carries the label and help of the defaults table. --}}
<div class="row form-row">
    <div class="col scom-measures-scroll">
        <table class="table table-condensed scom-table">
            <thead>
            <tr>
                <th>@lang('sCommerce::global.measure')</th>
                <th>@lang('sCommerce::global.price_unit')</th>
                <th>@lang('sCommerce::global.price_per')</th>
                <th>@lang('sCommerce::global.quantity_step')</th>
                <th>@lang('sCommerce::global.quantity_min')</th>
                <th>@lang('sCommerce::global.quantity_max')</th>
                <th>@lang('sCommerce::global.quantity_tolerance'), ‱</th>
                <th>@lang('sCommerce::global.quantity_input_decimals')</th>
            </tr>
            </thead>
            <tbody>
            @foreach(\Seiger\sCommerce\Services\MeasuredQuantity::DEFAULTS as $measure => $builtIn)
                @php($values = \Seiger\sCommerce\Services\MeasuredQuantity::defaults($measure))
                @php($name = 'product__measures[' . $measure . ']')
                <tr>
                    <td>@lang('sCommerce::global.measure_' . $measure), {{\Seiger\sCommerce\Services\MeasuredQuantity::translate('unit_' . \Seiger\sCommerce\Services\MeasuredQuantity::BASE_UNITS[$measure]) ?: '1'}}</td>
                    <td>
                        <select name="{{$name}}[price_unit]" class="form-control" onchange="documentDirty=true;">
                            @foreach($measuredUnits($measure) as [$unit, $factor])
                                <option value="{{$unit}}" @selected($values['price_unit'] === $unit)>{{\Seiger\sCommerce\Services\MeasuredQuantity::translate('unit_' . $unit) ?: '1'}}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select name="{{$name}}[price_per]" class="form-control" onchange="documentDirty=true;">
                            @foreach($measure === 'count' ? \Seiger\sCommerce\Services\MeasuredQuantity::COUNT_BASES : \Seiger\sCommerce\Services\MeasuredQuantity::PRICE_BASES as $basis)
                                <option value="{{$basis}}" @selected($values['price_per'] === $basis)>{{number_format($basis, 0, '.', ' ')}}</option>
                            @endforeach
                        </select>
                    </td>
                    @foreach(['step', 'min', 'max', 'tolerance', 'input_decimals'] as $key)
                        <td><input type="number" name="{{$name}}[{{$key}}]" class="form-control" min="{{in_array($key, ['tolerance', 'input_decimals'], true) ? 0 : 1}}" step="1" value="{{$values[$key]}}" onchange="documentDirty=true;"></td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label>@lang('sCommerce::global.quantity_rounding_settings')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.quantity_rounding_settings_help')"></i>
    </div>
    <div class="col">
        <table class="table table-condensed scom-table scom-rounding-table">
            <tbody>
            @foreach(\Seiger\sCommerce\Services\MeasuredQuantity::DEFAULTS as $measure => $builtIn)
                <tr>
                    <td>@lang('sCommerce::global.measure_' . $measure)</td>
                    <td>
                        <div class="scom-rounding-units">
                            @foreach($measuredUnits($measure) as [$unit, $factor])
                                <label>
                                    <span>{{\Seiger\sCommerce\Services\MeasuredQuantity::translate('unit_' . $unit) ?: '1'}}</span>
                                    <select name="product__quantity_rounding[{{$unit}}]" class="form-control" onchange="documentDirty=true;">
                                        @foreach(\Seiger\sCommerce\Services\MeasuredQuantity::ROUNDINGS as $rounding)
                                            <option value="{{$rounding}}" @selected(\Seiger\sCommerce\Services\MeasuredQuantity::rounding($unit) === $rounding)>@lang('sCommerce::global.quantity_rounding_' . $rounding)</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endforeach
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
