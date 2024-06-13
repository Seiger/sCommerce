<h3>
    {{(int)request()->input('i', 0) == 0 ? __('sCommerce::global.new_review') : $item->name ?? ''}}
    {{($item->toProduct ?? false) ? ' for ' . $item->toProduct->title : ''}}
</h3>
<div class="split my-3"></div>

<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=reviewSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=review&i={{(int)request()->input('i', 0)}}" />
    <input type="hidden" name="i" value="{{(int)request()->input('i', 0)}}" />
    <div class="row-col col-12">
        <div class="row form-row">
            <div class="row-col col-lg-3 col-md-6 col-12">
                <div class="row form-row form-row-checkbox">
                    <div class="col-auto col-title">
                        <label for="publishedcheck">@lang('sCommerce::global.visibility')</label>
                        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.published_help')"></i>
                    </div>
                    <div class="col">
                        <input type="checkbox" id="publishedcheck" class="form-checkbox form-control" name="publishedcheck" value="" onchange="documentDirty=true;" onclick="changestate(document.form.published);" @if(isset($item->published) && $item->published) checked @endif>
                        <input type="hidden" id="published" name="published" value="{{$item->published ?? 0}}" onchange="documentDirty=true;">
                    </div>
                </div>
            </div>
            <div class="row-col col-lg-3 col-md-6 col-12">
                <div class="row form-row form-row-checkbox">
                    <div class="col-auto col-title">
                        <label for="publishedcheck">@lang('sCommerce::global.rating')</label>
                    </div>
                    <div class="col">
                        <select id="rating" class="form-control" name="rating" style="width: 50px;" onchange="documentDirty=true;">
                            @foreach(range(1, 5) as $value)
                                <option value="{{$value}}" @if($value == ($item->rating ?? 0)) selected @endif>{{$value}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="row-col col-lg-3 col-md-6 col-12">
                <div class="row form-row form-row-date">
                    <div class="col-auto col-title-9">
                        <label for="created_at">@lang('global.publish_date')</label>
                    </div>
                    <div class="input-group col">
                        <div class="input-group-append">
                            <input id="created_at" class="form-control DatePicker" name="created_at" value="{{$item->created_at ?? ''}}" onblur="documentDirty=true;" placeholder="dd-mm-YYYY hh:mm:ss" autocomplete="off">
                            <span class="input-group-append">
                                <a class="btn text-danger" href="javascript:(0);" onclick="document.form.created_at.value='';documentDirty=true; return true;">
                                    <i class="fa fa-calendar-times-o" title="@lang('global.remove_date')"></i>
                                </a>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row-col col-lg-3 col-md-6 col-12">
                <div class="row form-row form-row-date">
                    <div class="col-auto col-title-9">
                        <label for="product">@lang('sCommerce::global.product') (ID)</label>
                    </div>
                    <div class="col">
                        <input id="product" class="form-control" name="product" value="{{$item->product ?? 0}}" onchange="documentDirty=true;">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row-col col-12">
        <div class="row form-row">
            <div class="col-auto col-title">
                <label for="name" class="warning">@lang('global.user')</label>
            </div>
            <div class="col">
                <input type="text" id="name" class="form-control" name="name" value="{{$item->name ?? ''}}" onchange="documentDirty=true;">
            </div>
        </div>
    </div>
    <div class="row-col col-12">
        <div class="row form-row">
            <div class="col-auto col-title">
                <label for="message">@lang('sCommerce::global.message')</label>
            </div>
            <div class="col">
                <textarea id="message" class="form-control" name="message" rows="3" wrap="soft" onchange="documentDirty=true;">{{$item->message ?? ''}}</textarea>
            </div>
        </div>
    </div>
</form>
<div class="split my-3"></div>

@if($item->toProduct ?? false)
    <div class="row-col col-12">
        <img src="{{$item->toProduct->coverSrc}}" alt="{{$item->toProduct->coverSrc}}" style="width:70px;height:45px;">
        <a href="{{$item->toProduct->link}}" target="_blank"><b>{{$item->toProduct->title}}</b></a>
    </div>
    <div class="split my-3"></div>
@endif

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!$moduleUrl!!}&get=reviews{{request()->has('page') ? '&page=' . request()->page : ''}}">
                <i class="fa fa-times-circle"></i><span>@lang('sCommerce::global.to_list_reviews')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fa fa-floppy-o"></i>
                <span>@lang('global.save')</span>
            </a>
            <a id="Button3" class="btn btn-danger" data-href="{!!$moduleUrl!!}&get=reviewDelete&i={{$item->id ?? 0}}" data-delete="{{$item->id ?? 0}}" data-name="{{(int)request()->input('i', 0) == 0 ? __('sCommerce::global.new_review') : $item->name ?? ''}}">
                <i class="fa fa-trash"></i> <span>@lang('global.remove')</span>
            </a>
        </div>
    </div>
@endpush
