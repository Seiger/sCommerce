@if(trim($field['label'] ?? ''))
    <div class="col-auto col-title">
        <label>{{$field['label'] ?? ''}}</label>
        @if(trim($field['helptext'] ?? ''))<i class="fa fa-question-circle" data-tooltip="{{$field['helptext'] ?? ''}}"></i>@endif
    </div>
@endif
@php($selectedValues = array_map('strval', is_array($field['value'] ?? null) ? $field['value'] : []))
<div class="col">
    <select
            id="{{$field['prefix'] ?? ''}}{{str_replace(['[', ']'], ['_', ''], $field['name'] ?? '')}}"
            name="{{$field['prefix'] ?? ''}}{{$field['name'] ?? ''}}"
            class="form-control"
            multiple
            size="{{min(8, max(3, count($field['options'] ?? [])))}}"
            onchange="documentDirty=true;">
        @foreach(($field['options'] ?? []) as $optValue => $optLabel)
            <option value="{{$optValue}}" @if(in_array((string)$optValue, $selectedValues, true)) selected @endif>{{$optLabel}}</option>
        @endforeach
    </select>
    @if(trim($field['hint'] ?? '') || trim($field['description'] ?? ''))
        <small class="form-text text-muted">{!!$field['hint'] ?? $field['description'] ?? ''!!}</small>
    @endif
</div>
