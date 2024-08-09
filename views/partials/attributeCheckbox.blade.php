<div class="row-col col-12">
    <div class="row form-row">
        <div class="col-auto col-title">
            <label for="{{$prefix}}{{$attribute->id}}">{{$attribute->pagetitle}}</label>
            @if(trim($attribute->helptext??''))<i class="fa fa-question-circle" data-tooltip="{{$attribute->helptext}}"></i>@endif
        </div>
        <div class="input-group mb-3 col">
            <div class="input-group-prepend">
                <div class="input-group-text">
                    <small>@lang('sCommerce::global.type_attr_checkbox')</small>
                </div>
                <div class="input-group-text">
                    <input type="hidden" name="{{$prefix}}{{$attribute->id}}" value="0">
                    <input id="{{$prefix}}{{$attribute->id}}" name="{{$prefix}}{{$attribute->id}}" value="1" @if((int)$attribute->value > 0) checked @endif type="checkbox" onchange="documentDirty=true;">
                </div>
            </div>
        </div>
    </div>
</div>