@if(trim($field['label'] ?? ''))
    <div class="col-auto col-title">
        <label>{{$field['label'] ?? ''}}</label>
        @if(trim($field['helptext'] ?? ''))<i class="fa fa-question-circle" data-tooltip="{{$field['helptext'] ?? ''}}"></i>@endif
    </div>
@endif
<div class="col">
    {!!$field['value'] ?? ''!!}
    @if(trim($field['hint'] ?? '') || trim($field['description'] ?? ''))
        <small class="form-text text-muted">{!!$field['hint'] ?? $field['description'] ?? ''!!}</small>
    @endif
</div>