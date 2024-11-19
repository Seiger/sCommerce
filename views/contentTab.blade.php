<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=contentSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=content&lang={{request()->lang ?? 'base'}}&i={{(int)request()->input('i', 0)}}" />
    <input type="hidden" name="i" value="{{(int)request()->input('i', 0)}}" />
    <input type="hidden" name="lang" value="{{request()->input('lang', $sCommerceController->langDefault())}}" />
    <div class="row-col col-12">
        <div class="row form-row">
            <div class="col-auto col-title">
                <label for="pagetitle" class="warning">@lang('global.resource_title')</label>
                <i class="fa fa-question-circle" data-tooltip="@lang('global.resource_title_help')"></i>
            </div>
            <div class="col">
                <input type="text" id="pagetitle" class="form-control" name="pagetitle" maxlength="255" value="{{$item->pagetitle ?? ''}}" onchange="documentDirty=true;"/>
            </div>
        </div>
    </div>
    <div class="row-col col-12">
        <div class="row form-row">
            <div class="col-auto col-title">
                <label for="longtitle" class="warning">@lang('global.long_title')</label>
            </div>
            <div class="col">
                <input type="text" id="longtitle" class="form-control" name="longtitle" maxlength="255" value="{{$item->longtitle ?? ''}}" onchange="documentDirty=true;">
            </div>
        </div>
    </div>
    <div class="row-col col-12">
        <div class="row form-row">
            <div class="col-auto col-title">
                <label for="introtext">@lang('global.resource_summary')</label>
            </div>
            <div class="col">
                <textarea id="introtext" class="form-control" name="introtext" rows="3" wrap="soft" onchange="documentDirty=true;">{{$item->introtext ?? ''}}</textarea>
            </div>
        </div>
    </div>
    <div class="row-col col-12">
        <div class="row form-row form-row-richtext">
            <div class="col-auto col-title">
                <div class="sbuttons-wraper">
                    <label for="content" class="warning">@lang('sCommerce::global.add_block')</label><br><br>
                    @foreach($buttons as $button){!!$button!!}<br><br>@endforeach
                </div>
            </div>
            <div id="builder" class="col builder">
                @if(count($chunks))
                    @foreach($chunks as $chunk)
                        <div class="row col row-col-wrap col-12 b-draggable">
                            <div class="col-12 b-item">
                                <div class="row align-items-center">
                                    <div class="col-auto"><i title="@lang('sCommerce::global.sort_order')" class="fa fa-sort b-move"></i></div>
                                    <div class="col">{!!$chunk!!}</div>
                                    <div class="col-auto"><i title="@lang('global.remove')" onclick="onDeleteField($(this))" class="fa fa-minus-circle text-danger b-btn-del"></i></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="row col row-col-wrap col-12 b-draggable">
                        <div class="col-12 b-item">
                            <div class="row align-items-center">
                                <div class="col-auto"><i title="@lang('sCommerce::global.sort_order')" class="fa fa-sort b-move"></i></div>
                                <div class="col">
                                    <div class="col"><textarea id="richtext1" name="builder[1][richtext]" rows="3" onchange="documentDirty=true;"></textarea></div>
                                </div>
                                <div class="col-auto"><i title="@lang('global.remove')" onclick="onDeleteField($(this))" class="fa fa-minus-circle text-danger b-btn-del"></i></div>
                            </div>
                        </div>
                    </div>
                @endif
                <i class="b-resize b-resize-r"></i>
            </div>
        </div>
    </div>
    <div class="row-col col-12">
        @php($mainAttributes = sCommerce::config('constructor.main_product', []))
        @foreach($mainAttributes as $attribute)
            <input type="hidden" name="constructor[{{$attribute['key']}}]" value="{{$product->{'constructor_'.$attribute['key']}??''}}">
        @endforeach
        {{--@foreach($constructor as $item)
            @if(trim($item['type']??''))
                <div class="row form-row">
                    <div class="col-auto col-title">
                        <label for="{{$item['key']}}" class="warning">{{$item['name']}}</label>
                    </div>
                    <div class="col">
                        @switch($item['type'])
                            @case('Text')
                                <input id="{{$item['key']}}" name="constructor[{{$item['key']}}]" value="{{$item['value']}}" class="form-control" type="text" onchange="documentDirty=true;">
                                @break
                            @case('File')
                                <div class="input-group mb-3">
                                    <input id="{{$item['key']}}" name="constructor[{{$item['key']}}]" value="{{$item['value']}}" class="form-control" type="text" onchange="documentDirty=true;">
                                    <div class="input-group-append">
                                        <button onclick="BrowseFileServer('{{$item['key']}}')" class="btn btn-outline-secondary" type="button">@lang('global.insert')</button>
                                    </div>
                                </div>
                                @break
                            @case('Image')
                                <div class="input-group mb-3">
                                    <input id="{{$item['key']}}" name="constructor[{{$item['key']}}]" value="{{$item['value']}}" class="form-control" type="text" onchange="documentDirty=true;">
                                    <div class="input-group-append">
                                        <button onclick="BrowseServer('{{$item['key']}}')" class="btn btn-outline-secondary" type="button">@lang('global.insert')</button>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div id="image_for_{{$item['key']}}" data-image="{{$item['value']}}" onclick="BrowseServer('{{$item['key']}}')" class="image_for_field" style="background-image: url('{{MODX_SITE_URL . $item['value']}}');"></div>
                                    <script>document.getElementById('{{$item['key']}}').addEventListener('change', evoRenderImageCheck, false);</script>
                                </div>
                                @break
                            @default
                                <textarea id="{{$item['key']}}" class="form-control" name="constructor[{{$item['key']}}]" rows="3" wrap="soft" onchange="documentDirty=true;">{{$item['value']}}</textarea>
                        @endswitch
                    </div>
                </div>
            @endif
        @endforeach--}}
    </div>
    @if(is_array($events = evo()->invokeEvent('sCommerceFormFieldRender', ['field' => 'seo', 'lang' => request()->input('lang', $sCommerceController->langDefault()), 'dataInput' => $sCommerceController->getData()])))
        @foreach($events as $event){!!$event!!}@endforeach
    @endif
</form>

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!$moduleUrl!!}">
                <i class="fa fa-times-circle"></i><span>@lang('sCommerce::global.to_list_products')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fa fa-floppy-o"></i>
                <span>@lang('global.save')</span>
            </a>
            <a id="Button3" class="btn btn-danger" data-href="{!!$moduleUrl!!}&get=productDelete&i={{$item->id}}" data-delete="{{$item->id}}" data-name="{{$item->pagetitle}}">
                <i class="fa fa-trash"></i> <span>@lang('global.remove')</span>
            </a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-sortablejs@latest/jquery-sortable.js"></script>
    <div class="modal fade" id="confirmDelete" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">@lang('sCommerce::global.confirm_delete')</div>
                <div class="modal-body">
                    @lang('sCommerce::global.you_sure') <b id="confirm-name"></b> @lang('sCommerce::global.with_id') <b id="confirm-id"></b>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('global.cancel')</button>
                    <a class="btn btn-danger btn-ok">@lang('global.remove')</a>
                </div>
            </div>
        </div>
    </div>
    <div class="draft-elements">
        @foreach($elements as $element)
            <div class="element">
                <div class="row col row-col-wrap col-12 b-draggable">
                    <div class="col-12 b-item">
                        <div class="row align-items-center">
                            <div class="col-auto"><i title="@lang('sCommerce::global.sort_order')" class="fa fa-sort b-move"></i></div>
                            <div class="col">{!!$element!!}</div>
                            <div class="col-auto"><i title="@lang('global.remove')" onclick="onDeleteField($(this))" class="fa fa-minus-circle text-danger b-btn-del"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <style>.draft-elements{display:none;}</style>
    <script>
        $(document).on("click","[data-element]",function(){
            let attr=$(this).attr('data-element');
            let type=$(this).attr('data-type');
            if (type=='richtext'){tinymce.remove()}
            let cnts=$('.builder').find('.b-draggable').length+1;
            let elem=$('#'+attr).closest('.element').html();
            let enew=elem.replace('id="'+attr+'"','id="'+attr+cnts+'"')
                .replace('id="image_for_'+attr+'"','id="image_for_'+attr+cnts+'"')
                .replace('BrowseServer(\''+attr+'\')','BrowseServer(\''+attr+cnts+'\')')
                .replace('getElementById(\''+attr+'\')','getElementById(\''+attr+cnts+'\')')
                .replace(/builder\[9999\]\[/g,'builder['+cnts+'][');
            $(".b-resize").before(enew);documentDirty=true;
            if(type=='richtext'){custom.selector = selector_custom = selector_custom + ',#' + attr + cnts;tinymce.init(custom)}
        });
        sortableTabs();
        function sortableTabs(){$('#builder').sortable({animation:150,onChange:function(){
            tinymce.remove();
            $('#builder').find('.b-draggable').each(function(index){
                let parent=$('#builder').find('.b-draggable').eq(index);
                let elemId=parent.find('[name^="builder\["]').first().attr('name').replace("builder[","").split("][")[0];
                parent.find('.b-item [name^="builder\['+elemId+'\]"]').each(function(position){
                    this.name = this.name.replace("builder["+elemId+"]","builder["+index+"]");
                })
            });
            tinymce.init(custom)}
        })}
        function onDeleteField(target){let parent=target.closest('.b-draggable');alertify.confirm("@lang('sSettings::global.are_you_sure')","@lang('sSettings::global.deleted_irretrievably')",function(){alertify.error("@lang('sSettings::global.deleted')");parent.remove()},function(){alertify.success("@lang('sSettings::global.canceled')")}).set('labels',{ok:"@lang('global.delete')",cancel:"@lang('global.cancel')"}).set({transition:'zoom'});documentDirty=true}
    </script>
@endpush
