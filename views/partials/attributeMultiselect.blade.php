<div class="row-col col-12">
    <div class="row form-row">
        <div class="col-auto col-title">
            <label for="{{$prefix}}{{$attribute->id}}">{{$attribute->pagetitle}}</label>
            @if(trim($attribute->helptext ?? ''))<i class="fa fa-question-circle" data-tooltip="{{$attribute->helptext}}"></i>@endif
        </div>
        <div class="input-group mb-3 col">
            <input type="hidden" name="{{$prefix}}{{$attribute->id}}" value="">
            <div class="input-group-prepend"><span class="input-group-text"><small>@lang('sCommerce::global.type_attr_multiselect')</small></span></div>
            <select id="{{$prefix}}{{$attribute->id}}" class="form-control select2" name="{{$prefix}}{{$attribute->id}}[]" multiple onchange="documentDirty=true;">
                @foreach(($options ?? []) as $option)
                    <option value="{{$option}}" @if(in_array($option, $value)) selected @endif>{{$option}}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>