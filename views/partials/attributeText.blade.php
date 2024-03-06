@php($vals = json_decode($attrValues[$item->id]->pivot->value ?? '', true))
<div class="row-col col-12">
    <div class="row form-row">
        <div class="col-auto col-title">
            <label for="attribute__{{$item->id}}">{{$item->pagetitle}}</label>
            @if(trim($item->helptext))<i class="fa fa-question-circle" data-tooltip="{{$item->helptext}}"></i>@endif
        </div>
        <div class="col">
            <div class="input-group">
                <span class="input-group-text"><small>@lang('sCommerce::global.type_attr_text')</small></span>
                <input type="text" id="attribute__{{$item->id}}" name="attribute__{{$item->id}}[base]" class="form-control" value="{{$vals['base'] ?? ''}}" onchange="documentDirty=true;">
            </div>
        </div>
    </div>
</div>