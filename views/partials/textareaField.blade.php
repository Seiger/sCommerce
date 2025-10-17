@if(trim($field['label'] ?? ''))
    <div class="col-auto col-title">
        <label>{{$field['label'] ?? ''}}</label>
        @if(trim($field['helptext'] ?? ''))<i class="fa fa-question-circle" data-tooltip="{{$field['helptext'] ?? ''}}"></i>@endif
    </div>
@endif
<div class="col">
    <textarea
            id="{{$field['prefix'] ?? ''}}{{str_replace(['[', ']'], ['_', ''], $field['name'] ?? '')}}"
            name="{{$field['prefix'] ?? ''}}{{$field['name'] ?? ''}}"
            placeholder="{{$field['placeholder'] ?? ''}}"
            class="form-control"
            rows="{{$field['rows'] ?? 5}}"
            onchange="documentDirty=true;">{{$field['value'] ?? ''}}</textarea>
</div>
