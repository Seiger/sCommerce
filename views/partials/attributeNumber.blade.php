<div class="row-col col-12">
    <div class="row form-row">
        <div class="col-auto col-title">
            <label for="{{$prefix}}{{$attribute->id}}">{{$attribute->pagetitle}}</label>
            @if(trim($attribute->helptext??''))<i class="fa fa-question-circle" data-tooltip="{{$attribute->helptext}}"></i>@endif
        </div>
        <div class="input-group mb-3 col">
            <div class="input-group-prepend"><span class="input-group-text"><small>@lang('sCommerce::global.type_attr_number')</small></span></div>
            <input type="number" id="{{$prefix}}{{$attribute->id}}" name="{{$prefix}}{{$attribute->id}}" class="form-control" value="{{$attribute->value ?? 0}}" onchange="documentDirty=true;">
        </div>
    </div>
</div>