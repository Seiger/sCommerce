<small>@lang('sCommerce::global.image_and_text')</small>
<div class="row form-row">
    <div class="col-auto col-title-7">
        <div id="image_for_img-{{$id ?? ''}}" class="image_for_field" data-image="{{$value['src'] ?? 'imgandtext'}}" onclick="BrowseServer('img-{{$id ?? ''}}')" style="background-image: url('{{MODX_SITE_URL.($value['src'] ?? '')}}')"></div>
    </div>
    <div class="col">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text">ALIGN</span>
            </div>
            <select class="form-control form-control-lg" name="builder[{{$i ?? '9999'}}][imgandtext][align]">
                <option value="left" {{((trim($value['align'] ?? '') && $value['align'] == "left") ? "selected" : "")}}>@lang('sCommerce::global.image_on_the_left')</option>
                <option value="right" {{((trim($value['align'] ?? '') && $value['align'] == "right") ? "selected" : "")}}>@lang('sCommerce::global.image_on_the_right')</option>
            </select>
            <div class="input-group-prepend">
                <span class="input-group-text">IMG</span>
            </div>
            <input id="img-{{$id ?? ''}}" type="text" class="form-control" name="builder[{{$i ?? '9999'}}][imgandtext][src]" value="{{$value['src'] ?? ''}}" placeholder="@lang('sCommerce::global.image_file')" onchange="documentDirty=true;">
            <div class="input-group-append">
                <button class="btn btn-outline-secondary" type="button" onclick="BrowseServer('img-{{$id ?? ''}}')"><i class="fas fa-image"></i></button>
            </div>
        </div>
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text">TITLE</span>
            </div>
            <input type="text" class="form-control" name="builder[{{$i ?? '9999'}}][imgandtext][title]" value="{{$value['title'] ?? ''}}" placeholder="@lang('sCommerce::global.image_caption')" onchange="documentDirty=true;">
            <div class="input-group-prepend">
                <span class="input-group-text">ALT</span>
            </div>
            <input type="text" class="form-control" name="builder[{{$i ?? '9999'}}][imgandtext][alt]" value="{{$value['alt'] ?? ''}}" placeholder="@lang('sCommerce::global.alternative_text')" onchange="documentDirty=true;">
        </div>
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text">LINK</span>
            </div>
            <input type="text" class="form-control" name="builder[{{$i ?? '9999'}}][imgandtext][link]" value="{{$value['link'] ?? ''}}" placeholder="@lang('sCommerce::global.image_link')" onchange="documentDirty=true;">
        </div>
        <script>document.getElementById('img-{{$id ?? ''}}').addEventListener('change', evoRenderImageCheck, false);</script>
    </div>
</div>
<textarea id="{{$id ?? ''}}" name="builder[{{$i ?? '9999'}}][imgandtext][text]" rows="3" onchange="documentDirty=true;">{!!$value['text'] ?? ''!!}</textarea>
