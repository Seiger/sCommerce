<small>Список текстів</small>
@if (is_array($value ?? []) && is_array($value['title'] ?? []) && count($value['title'] ?? []))
    @php($idOrig = ($id ?? 'accordion'))
    @foreach ($value['title'] as $key => $title)
        @if (trim($title))
            @if ($key > 0)
                @php($id = $idOrig.$key)
            @endif
            <div class="accord row form-row">
                <div class="col">
                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text">Заголовок</span>
                        </div>
                        <input name="builder[{{$i ?? '9999'}}][accordion][title][]" value="{{$title}}" type="text" class="form-control" placeholder="Заголовок" onchange="documentDirty=true;">
                        <div class="input-group-prepend">
                            <span class="input-group-text">Іконка</span>
                        </div>
                        <input id="icon-{{$id ?? ''}}" name="builder[{{$i ?? '9999'}}][accordion][icon][]" value="{{$value['icon'][$key] ?? ''}}" type="text" class="form-control" placeholder="Файл іконки" onchange="documentDirty=true;">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" onclick="BrowseServer('icon-{{$id ?? ''}}')"><i class="fas fa-image"></i></button>
                        </div>
                    </div>
                    <textarea id="{{$id ?? ''}}" name="builder[{{$i ?? '9999'}}][accordion][richtext][]" data-id="{{$idOrig ?? ''}}" rows="3" onchange="documentDirty=true;">{!!$value['richtext'][$key]!!}</textarea>
                    <button onclick="onAddAccord($(this))" type="button" class="btn btn-primary btn-xs btn-block">Додати текст</button>
                </div>
                <div class="col-auto"><i onclick="onDeleteAccord($(this))" class="fa fa-minus-circle text-danger b-btn-del"></i></div>
            </div>
        @endif
    @endforeach
@else
    <div class="accord row form-row">
        <div class="col">
            <div class="input-group mb-3">
                <div class="input-group-prepend">
                    <span class="input-group-text">Title</span>
                </div>
                <input name="builder[{{$i ?? '9999'}}][accordion][title][]" value="" type="text" class="form-control" placeholder="Заголовок" onchange="documentDirty=true;">
                <div class="input-group-prepend">
                    <span class="input-group-text">Іконка</span>
                </div>
                <input id="icon-{{$id ?? ''}}" name="builder[{{$i ?? '9999'}}][accordion][icon][]" value="" type="text" class="form-control" placeholder="Файл іконки" onchange="documentDirty=true;">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary" type="button" onclick="BrowseServer('icon-{{$id ?? ''}}')"><i class="fas fa-image"></i></button>
                </div>
            </div>
            <textarea id="{{$id ?? ''}}" name="builder[{{$i ?? '9999'}}][accordion][richtext][]" data-id="" rows="3" onchange="documentDirty=true;"></textarea>
            <button onclick="onAddAccord($(this))" type="button" class="btn btn-primary btn-xs btn-block">Додати текст</button>
        </div>
        <div class="col-auto"><i onclick="onDeleteAccord($(this))" class="fa fa-minus-circle text-danger b-btn-del"></i></div>
    </div>
@endif
