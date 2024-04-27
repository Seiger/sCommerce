<small>@lang('sCommerce::global.text_block')</small>
<textarea id="{{$id ?? ''}}" name="builder[{{$i ?? '9999'}}][richtext]" rows="3" onchange="documentDirty=true;">{!!$value ?? ''!!}</textarea>