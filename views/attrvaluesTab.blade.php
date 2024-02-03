@php use Seiger\sCommerce\Models\sAttribute; @endphp
<h3>{{(int)request()->input('i', 0) == 0 ? __('sCommerce::global.new_attribute') : ($item->pagetitle ?? __('sCommerce::global.no_text'))}}</h3>
<div class="split my-3"></div>

<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=attrvaluesSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=attrvalues&i={{(int)request()->input('i', 0)}}" />
    <input type="hidden" name="i" value="{{(int)request()->input('i', 0)}}" />
    <div class="row form-row widgets sortable">
        @foreach($values as $value)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <i style="cursor:pointer;" class="fas fa-sort"></i>&emsp; {{$value->alias}}
                        <span class="close-icon" onclick="deleteItem(this.closest('.card'))"><i class="fa fa-times"></i></span>
                    </div>
                    <div class="card-block">
                        <div class="userstable">
                            <div class="card-body">
                                @foreach($sCommerceController->langList() as $lang)
                                    <div class="row form-row">
                                        <div class="col-auto">
                                            <label>@lang('sCommerce::global.value')</label>
                                            @if($lang != 'base')<span class="badge bg-seigerit">{{$lang}}</span>@endif
                                        </div>
                                        <div class="col">
                                            <input type="text" class="form-control" name="values[{{$lang}}][]" value="{{ $value->{$lang} }}" onchange="documentDirty=true;">
                                        </div>
                                    </div>
                                @endforeach
                                <div class="row form-row">
                                    <div class="col-auto">
                                        <label>@lang('sCommerce::global.key')</label>
                                    </div>
                                    <div class="col">
                                        <input type="text" class="form-control" name="values[alias][]" value="{{$value->alias}}" onchange="documentDirty=true;">
                                    </div>
                                </div>
                                <input type="hidden" name="values[avid][]" value="{{$value->avid}}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</form>
<div class="split my-3"></div>

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!$moduleUrl!!}">
                <i class="fa fa-times-circle"></i><span>@lang('sCommerce::global.to_list_attributes')</span>
            </a>
            <a id="Button2" class="btn btn-primary" href="javascript:void(0);" onclick="addItem();">
                <i class="fa fa-plus"></i><span>@lang('global.add')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fa fa-floppy-o"></i>
                <span>@lang('global.save')</span>
            </a>
        </div>
    </div>
    <div class="draft-value hidden">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    <i style="cursor:pointer;" class="fas fa-sort"></i>&emsp;
                    <span class="close-icon" onclick="deleteItem(this.closest('.card'))"><i class="fa fa-times"></i></span>
                </div>
                <div class="card-block">
                    <div class="userstable">
                        <div class="card-body">
                            @foreach($sCommerceController->langList() as $lang)
                                <div class="row form-row">
                                    <div class="col-auto">
                                        <label>@lang('sCommerce::global.value')</label>
                                        @if($lang != 'base')<span class="badge bg-seigerit">{{$lang}}</span>@endif
                                    </div>
                                    <div class="col">
                                        <input type="text" class="form-control" name="values[{{$lang}}][]" value="" onchange="documentDirty=true;">
                                    </div>
                                </div>
                            @endforeach
                            <div class="row form-row">
                                <div class="col-auto">
                                    <label>@lang('sCommerce::global.key')</label>
                                </div>
                                <div class="col">
                                    <div class="col">
                                        <input type="text" class="form-control" name="values[alias][]" value="" onchange="documentDirty=true;">
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="values[avid][]" value="">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const widgets=document.querySelector('.widgets');
        function addItem(){draft=document.querySelector('.draft-value');widgets.insertAdjacentHTML('beforeend', draft.innerHTML);documentDirty=true}
        function deleteItem(element){alertify.confirm("@lang('sCommerce::global.are_you_sure')","@lang('sCommerce::global.deleted_irretrievably')",function(){alertify.error("@lang('sCommerce::global.deleted')");element.remove()},function(){alertify.success("@lang('sCommerce::global.canceled')")}).set('labels',{ok:"@lang('global.delete')",cancel:"@lang('global.cancel')"}).set({transition:'zoom'});documentDirty=true}
    </script>
@endpush
