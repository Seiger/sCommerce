<div class="row-col col-12">
    <div class="row form-row">
        <div class="col-auto col-title">
            <label for="{{$prefix}}{{$attribute->id}}">{{$attribute->pagetitle}}</label>
            @if(trim($attribute->helptext??''))<i class="fa fa-question-circle" data-tooltip="{{$attribute->helptext}}"></i>@endif
        </div>
        <div class="input-group mb-3 col">
            @if($sCommerceController->langDefault() == 'base')
                <div class="input-group-prepend"><span class="input-group-text"><small>@lang('sCommerce::global.type_attr_text')</small></span></div>
                <input type="text" id="{{$prefix}}{{$attribute->id}}_base" name="{{$prefix}}{{$attribute->id}}[base]" value="{{$value['base'] ?? ''}}" class="form-control" onchange="documentDirty=true;">
            @else
                <div class="input-group-prepend"><span class="input-group-text"><small>@lang('sCommerce::global.type_attr_text')</small></span></div>
                <input type="text" id="{{$prefix}}{{$attribute->id}}" name="{{$prefix}}{{$attribute->id}}" value="{{$attribute->value ?? ''}}" class="form-control" onchange="documentDirty=true;">
            @endif
        </div>
    </div>
</div>