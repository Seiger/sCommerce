@if(trim($field['label'] ?? ''))
    <div class="col-auto col-title">
        <label>{{$field['label'] ?? ''}}</label>
        @if(trim($field['helptext'] ?? ''))<i class="fa fa-question-circle" data-tooltip="{{$field['helptext'] ?? ''}}"></i>@endif
    </div>
@endif
<div class="col">
    <input
            type="checkbox"
            class="form-checkbox form-control"
            onchange="documentDirty=true;"
            onclick="changestate(document.form.{{$field['prefix'] ?? ''}}{{str_replace(['[', ']'], ['_', ''], $field['name'] ?? '')}});"
            @if(($field['value'] ?? 0) == 1) checked @endif>
    <input
            type="hidden"
            id="{{$field['prefix'] ?? ''}}{{str_replace(['[', ']'], ['_', ''], $field['name'] ?? '')}}"
            name="{{$field['prefix'] ?? ''}}{{$field['name'] ?? ''}}"
            value="{{$field['value'] ?? 0}}"
            onchange="documentDirty=true;">
    @if(trim($field['hint'] ?? '') || trim($field['description'] ?? ''))
        <small class="form-text text-muted">{!!$field['hint'] ?? $field['description'] ?? ''!!}</small>
    @endif
</div>

