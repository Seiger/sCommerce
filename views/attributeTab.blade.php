@php use Seiger\sCommerce\Models\sAttribute; @endphp
<h3>{{(int)request()->input('i', 0) == 0 ? __('sCommerce::global.new_attribute') : ($item->pagetitle ?? __('sCommerce::global.no_text'))}}</h3>
<div class="split my-3"></div>

<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=attributeSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=attribute&i={{(int)request()->input('i', 0)}}" />
    <input type="hidden" name="i" value="{{(int)request()->input('i', 0)}}" />
    <div class="row form-row">
        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row form-row-checkbox">
                <div class="col-auto">
                    <label for="publishedcheck" class="warning">@lang('sCommerce::global.visibility')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.published_help')"></i>
                </div>
                <div class="col">
                    <input type="checkbox" id="publishedcheck" class="form-checkbox form-control" name="publishedcheck" value="" onchange="documentDirty=true;" onclick="changestate(document.form.published);" @if(isset($item->published) && $item->published) checked @endif>
                    <input type="hidden" id="published" name="published" value="{{$item->published ?? 0}}" onchange="documentDirty=true;">
                </div>
            </div>
        </div>
        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row form-row-checkbox">
                <div class="col-auto">
                    <label for="asfiltercheck" class="warning">@lang('sCommerce::global.as_filter')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.as_filter_help')"></i>
                </div>
                <div class="col">
                    <input type="checkbox" id="asfiltercheck" class="form-checkbox form-control" name="asfiltercheck" value="" onchange="documentDirty=true;" onclick="changestate(document.form.asfilter);" @if(isset($item->asfilter) && $item->asfilter) checked @endif>
                    <input type="hidden" id="asfilter" name="asfilter" value="{{$item->asfilter ?? 0}}" onchange="documentDirty=true;">
                </div>
            </div>
        </div>
        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row">
                <div class="col-auto">
                    <label for="position">@lang('sCommerce::global.position')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.position_help')"></i>
                </div>
                <div class="input-group col">
                    <div class="input-group-prepend">
                        <span class="btn btn-secondary" onclick="let elm = document.form.position;let v=parseInt(elm.value+'')-1;elm.value=v>0? v:0;elm.focus();documentDirty=true;return false;" style="cursor: pointer;"><i class="fa fa-angle-left"></i></span>
                        <span class="btn btn-secondary" onclick="let elm = document.form.position;let v=parseInt(elm.value+'')+1;elm.value=v>0? v:0;elm.focus();documentDirty=true;return false;" style="cursor: pointer;"><i class="fa fa-angle-right"></i></span>
                    </div>
                    <input type="text" id="position" name="position" class="form-control" value="{{$item->position ?? 0}}" maxlength="11" onchange="documentDirty=true;">
                </div>
            </div>
        </div>
        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row">
                <div class="col-auto">
                    <label for="alias" class="warning">@lang('global.resource_alias')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('global.resource_alias_help')"></i>
                </div>
                <div class="input-group col">
                    <input type="text" id="alias" class="form-control" name="alias" maxlength="512" value="{{$item->alias ?? 'new-attribute'}}" onchange="documentDirty=true;">
                </div>
            </div>
        </div>
        <div class="row-col col-lg-6 col-md-6 col-12">
            <div class="row form-row">
                <div class="col-auto">
                    <label for="categories" class="warning">@lang('sCommerce::global.categories')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.categories_attribute_help')"></i>
                </div>
                <div class="col">
                    <select id="categories" class="form-control select2" name="categories[]" multiple onchange="documentDirty=true;">
                        @foreach($sCommerceController->listCategories() as $key => $value)
                            <option value="{{$key}}" @if(in_array($key, $categories)) selected @endif>{{$value}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row form-row-checkbox">
                <div class="col-auto">
                    <label for="type" class="warning">@lang('sCommerce::global.type_input')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.type_input_help')"></i>
                </div>
                <div class="col">
                    <select id="type" class="form-control select2" name="type" onchange="documentDirty=true;">
                        @foreach(sAttribute::listType() as $key => $value)
                            <option value="{{$key}}" @if($key == $item->type) selected @endif>{{$value}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="row-col col-lg-3 col-md-3 col-12">
            <div class="row form-row">
                <div class="col-auto">
                    <label for="helptext" class="warning">@lang('sCommerce::global.helptext')</label>
                    <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.helptext_help')"></i>
                </div>
                <div class="col">
                    <textarea name="helptext" class="form-control">{!!$item->helptext ?? ''!!}</textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="split my-3"></div>
    <div class="row form-row">
        @foreach($sCommerceController->langList() as $lang)
            <div class="row-col col-12">
                @if($lang != 'base')<span class="badge bg-seigerit">{{$lang}}</span>@endif
                <div class="row form-row">
                    <div class="col-auto col-title">
                        <label for="{{$lang}}_pagetitle">@lang('sCommerce::global.attribute_name')</label>
                        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.attribute_name_help')"></i>
                    </div>
                    <div class="col">
                        <input type="text" id="{{$lang}}_pagetitle" class="form-control" name="texts[{{$lang}}][pagetitle]" maxlength="255" value="{{$texts[$lang]['pagetitle'] ?? ''}}" onchange="documentDirty=true;">
                    </div>
                </div>
                <div class="row form-row">
                    <div class="col-auto col-title">
                        <label for="{{$lang}}_introtext">@lang('sCommerce::global.attribute_introtext')</label>
                        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.attribute_introtext_help')"></i>
                    </div>
                    <div class="col">
                        <textarea id="{{$lang}}_introtext" class="form-control" name="texts[{{$lang}}][introtext]" rows="2" onchange="documentDirty=true;">{{$texts[$lang]['introtext'] ?? ''}}</textarea>
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
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fa fa-floppy-o"></i>
                <span>@lang('global.save')</span>
            </a>
            <a id="Button3" class="btn btn-danger" data-href="{!!$moduleUrl!!}&get=attributeDelete&i={{$item->id}}" data-delete="{{$item->id}}" data-name="{{$item->pagetitle}}">
                <i class="fa fa-trash"></i> <span>@lang('global.remove')</span>
            </a>
        </div>
    </div>
@endpush
