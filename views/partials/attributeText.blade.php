@php
    $langList = $sCommerceController->langList();
    $rawValue = $value ?? [];

    if (!is_array($rawValue)) {
        $rawValue = json_decode((string)($attribute->value ?? ''), true) ?? [];
    }

    if (empty($langList)) {
        $langList = ['base'];
    }
@endphp
<div class="row-col col-12">
    <div class="row form-row">
        <div class="col-auto col-title">
            <label>{{$attribute->pagetitle}}</label>
            @if(trim($attribute->helptext ?? ''))<i class="fa fa-question-circle" data-tooltip="{{$attribute->helptext}}"></i>@endif
        </div>
        <div class="col">
            @foreach($langList as $lang)
                <div class="input-group mb-2">
                    <div class="input-group-prepend">
                        <span class="input-group-text">
                            <small>
                                @lang('sCommerce::global.type_attr_text')
                                @if($lang !== 'base')
                                    [{{$lang}}]
                                @endif
                            </small>
                        </span>
                    </div>
                    <input
                            type="text"
                            id="{{$prefix}}{{$attribute->id}}_{{$lang}}"
                            name="{{$prefix}}{{$attribute->id}}[{{$lang}}]"
                            value="{{$rawValue[$lang] ?? ''}}"
                            class="form-control"
                            onchange="documentDirty=true;">
                </div>
            @endforeach
        </div>
    </div>
</div>
