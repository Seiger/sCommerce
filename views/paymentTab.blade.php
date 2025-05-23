<h3>{!!$item->type!!}</h3>
<div class="split my-3"></div>

<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!sCommerce::moduleUrl()!!}&get=paymentSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=payment&i={{request()->integer('i')}}" />
    <input type="hidden" name="i" value="{{request()->integer('i')}}" />
    <div class="row-col col-12">
        <div class="row form-row">
            <div class="row-col col-lg-1 col-md-2 col-4">
                <div class="row form-row form-row-checkbox">
                    <div class="col-auto col-title">
                        <label for="publishedcheck">@lang('sCommerce::global.visibility')</label>
                        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.published_help')"></i>
                    </div>
                    <div class="col">
                        <input type="checkbox" id="publishedcheck" class="form-checkbox form-control" name="publishedcheck" value="" onchange="documentDirty=true;" onclick="changestate(document.form.active);" @if(isset($item->active) && $item->active) checked @endif>
                        <input type="hidden" id="active" name="active" value="{{$item->active ?? 0}}" onchange="documentDirty=true;">
                    </div>
                </div>
            </div>
            @if(count($item->instance->defineAvailableModes()))
                <div class="row-col col-lg-2 col-md-4 col-6">
                    <div class="row form-row">
                        <div class="col-auto">
                            <label for="position">@lang('sCommerce::global.mode')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.mode_help')"></i>
                        </div>
                        <div class="input-group col">
                            <select class="form-control" name="mode" id="mode" onchange="documentDirty=true" size="1">
                                @foreach($item->instance->defineAvailableModes() as $key => $title)
                                    <option value="{{$key}}" @if(($item->mode ?? '') == $key) selected @endif>{{$title}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            @endif
            <div class="row-col col-lg-2 col-md-4 col-6">
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
        </div>
    </div>
    <div class="split my-3"></div>
    @foreach($item->instance->defineCredentials() as $group)
        <h4><b>{{$group['label'] ?? ''}}</b>@if(trim($group['helptext'] ?? '')) <i class="fa fa-question-circle" data-tooltip="{{$group['helptext'] ?? ''}}"></i>@endif</h4>
        @if(count($group['fields'] ?? []))
            @foreach($group['fields'] as $key => $field)
                <div class="row-col col-12">
                    <div class="row form-row">
                        @php($field['key'] = $key)
                        @include('sCommerce::partials.' . ($field['type'] ?? '') . 'Field', ['field' => $field])
                    </div>
                    @if(($field['type'] ?? '') == 'dynamic')
                        <div class="row form-row">
                            <span class="btn btn-success" onclick="add{{$field['key']}}()">{{trim($field['button_label'] ?? '') ?:  __('global.add')}}</span>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
        <div class="split my-3"></div>
    @endforeach
    <h4><b>@lang('global.description')</b></h4>
    <div class="row-col col-12">
        <div class="row form-row">
            <div class="col-auto col-title">
                <label for="title">@lang('sCommerce::global.payment_name')</label>
            </div>
            <div class="col">
                @foreach($sCommerceController->langList() as $lang)
                    <div class="input-group mb-3 col">
                        <div class="input-group-prepend">@if($lang != 'base')<span class="input-group-text"><span class="badge bg-seigerit">{{$lang}}</span></span>@endif</div>
                        <input type="text" id="{{$lang}}_title" class="form-control" name="title[{{$lang}}]" value="{{$item->instance->getTitle($lang)}}" onchange="documentDirty=true;">
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="row-col col-12">
        <div class="row form-row">
            <div class="col-auto col-title">
                <label for="description">@lang('global.description')</label>
            </div>
            <div class="col">
                @foreach($sCommerceController->langList() as $lang)
                    <div class="input-group mb-3 col">
                        <div class="input-group-prepend">@if($lang != 'base')<span class="input-group-text"><span class="badge bg-seigerit">{{$lang}}</span></span>@endif</div>
                        <input type="text" id="{{$lang}}_description" class="form-control" name="description[{{$lang}}]" value="{{$item->instance->getDescription($lang)}}" onchange="documentDirty=true;">
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="split my-3"></div>
    @foreach($item->instance->defineSettings() as $group)
        <h4><b>{{$group['label'] ?? ''}}</b></h4>
        @if(count($group['fields'] ?? []))
            @foreach($group['fields'] as $key => $field)
                <div class="row-col col-12">
                    <div class="row form-row">
                        @php($field['key'] = $key)
                        @include('sCommerce::partials.' . ($field['type'] ?? '') . 'Field', ['field' => $field])
                    </div>
                    @if(($field['type'] ?? '') == 'dynamic')
                        <div class="row form-row">
                            <span class="btn btn-success" onclick="add{{$field['key']}}()">{{trim($field['button_label'] ?? '') ?:  __('global.add')}}</span>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
        <div class="split my-3"></div>
    @endforeach
</form>

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!$moduleUrl!!}&get=payments{{request()->has('page') ? '&page=' . request()->page : ''}}">
                <i class="fa fa-times-circle"></i><span>@lang('sCommerce::global.to_list_payments')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fa fa-floppy-o"></i>
                <span>@lang('global.save')</span>
            </a>
        </div>
    </div>
@endpush
