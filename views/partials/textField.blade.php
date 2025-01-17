@if(trim($field['label'] ?? ''))
    <div class="col-auto col-title">
        <label>{{$field['label'] ?? ''}}</label>
        @if(trim($field['helptext'] ?? ''))<i class="fa fa-question-circle" data-tooltip="{{$field['helptext'] ?? ''}}"></i>@endif
    </div>
@endif
<div class="col">
    <input
            type="text"
            id="{{$field['prefix'] ?? ''}}{{str_replace(['[', ']'], ['_', ''], $field['name'] ?? '')}}"
            name="{{$field['prefix'] ?? ''}}{{$field['name'] ?? ''}}"
            value="{{$field['value'] ?? ''}}"
            placeholder="{{$field['placeholder'] ?? ''}}"
            class="form-control"
            onchange="documentDirty=true;">
</div>