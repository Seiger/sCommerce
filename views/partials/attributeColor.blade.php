<div class="row-col col-12">
    <div class="row form-row">
        <div class="col-auto col-title">
            <label for="{{$prefix}}{{$attribute->id}}">{{$attribute->pagetitle}}</label>
            @if(trim($attribute->helptext ?? ''))<i class="fa fa-question-circle" data-tooltip="{{$attribute->helptext}}"></i>@endif
        </div>
        <div class="input-group mb-3 col">
            <div class="input-group-prepend"><span class="input-group-text"><small>@lang('sCommerce::global.type_attr_select')</small></span></div>
            <select id="{{$prefix}}{{$attribute->id}}" class="form-control select2" name="{{$prefix}}{{$attribute->id}}" onchange="documentDirty=true;">
                <option value=""></option>
                @foreach(($options ?? []) as $key => $option)
                    <option value="{{$key}}" @if($key == ($attribute->value ?? '')) selected @endif>{{$option}} ({{$colors[$key]}})</option>
                @endforeach
            </select>
        </div>
    </div>
</div>