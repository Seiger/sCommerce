@php
    $legacyMeasurements = [];
    foreach (\Seiger\sCommerce\Models\sProduct::PHYSICAL_ATTRIBUTES as $field) {
        $value = $item->getAttributes()[$field] ?? 0;
        if ((float) $value > 0) {
            $legacyMeasurements[$field] = $value;
        }
    }
@endphp
@if(count($legacyMeasurements))
    <div class="split my-3"></div>
    <div class="row form-row">
        @foreach($legacyMeasurements as $field => $value)
            <div class="row-col col-lg-3 col-md-6 col-12">
                <div class="row form-row">
                    <div class="col-auto col-title">
                        <label for="{{$field}}">@lang('sCommerce::global.' . $field)</label>
                    </div>
                    <div class="col input-group">
                        <input id="{{$field}}" class="form-control" name="{{$field}}" value="{{round($value, 4)}}" onblur="documentDirty=true;">
                        <div class="input-group-append"><span class="input-group-text">{{\Seiger\sCommerce\Models\sProduct::physicalUnit($field)}}</span></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
