<div class="row-col col-12">
    <div class="row form-row">
        <div class="col-auto col-title">
            <label for="attribute__{{$item->id}}">{{$item->pagetitle}}</label>
            @if(trim($item->helptext))<i class="fa fa-question-circle" data-tooltip="{{$item->helptext}}"></i>@endif
        </div>
        <div class="input-group col">
            <div class="input-group-prepend"><span class="input-group-text"><small>@lang('sCommerce::global.type_attr_number')</small></span></div>
            <input type="number" id="attribute__{{$item->id}}" name="attribute__{{$item->id}}" class="form-control" value="{{$attrValues[$item->id]->pivot->value ?? ''}}" onchange="documentDirty=true;">
        </div>
    </div>
</div>