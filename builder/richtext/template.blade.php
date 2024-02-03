<small>Текстовий блок</small>
<textarea id="{{$id ?? ''}}" name="builder[{{$i ?? '9999'}}][richtext]" rows="3" onchange="documentDirty=true;">{!!$value ?? ''!!}</textarea>