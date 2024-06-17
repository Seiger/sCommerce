@php use Seiger\sCommerce\Models\sAttribute; @endphp
<h3>{{(int)request()->input('i', 0) == 0 ? __('sCommerce::global.new_product') : ($product->pagetitle ?? __('sCommerce::global.no_text'))}}</h3>
<div class="split my-3"></div>

<div class="row-col col-12">
    <form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=prodattributesSave" onsubmit="documentDirty=false;">
        <input type="hidden" name="back" value="&get=prodattributes&i={{(int)request()->input('i', 0)}}" />
        <input type="hidden" name="i" value="{{(int)request()->input('i', 0)}}" />
        <div class="row form-row">
            @php($prefix = 'attribute__')
            @foreach($attributes as $attribute)
                @switch($attribute->type)
                    @case(sAttribute::TYPE_ATTR_NUMBER)
                        @include('sCommerce::partials.attributeNumber')
                        @break
                    @case(sAttribute::TYPE_ATTR_SELECT)
                        @include('sCommerce::partials.attributeSelect')
                        @break
                    @case(sAttribute::TYPE_ATTR_MULTISELECT)
                        @php($value = json_decode($attribute->value ?? '', true))
                        @include('sCommerce::partials.attributeMultiselect')
                        @break
                    @case(sAttribute::TYPE_ATTR_TEXT)
                        @php($value = json_decode($attribute->value ?? '', true))
                        @include('sCommerce::partials.attributeText')
                        @break
                    @case(sAttribute::TYPE_ATTR_CUSTOM)
                        @php(View::getFinder()->setPaths([MODX_BASE_PATH . 'assets/modules/scommerce/attribute']))
                        @include($attribute->alias)
                        @break
                @endswitch
            @endforeach
        </div>
    </form>
</div>
<div class="split my-3"></div>

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!$moduleUrl!!}{{request()->has('page') ? '&page=' . request()->page : ''}}">
                <i class="fa fa-times-circle"></i><span>@lang('sCommerce::global.to_list_products')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fa fa-floppy-o"></i>
                <span>@lang('global.save')</span>
            </a>
            <a id="Button3" class="btn btn-danger" data-href="{!!$moduleUrl!!}&get=productDelete&i={{$product->id}}" data-delete="{{$product->id}}" data-name="{{$product->pagetitle}}">
                <i class="fa fa-trash"></i> <span>@lang('global.remove')</span>
            </a>
        </div>
    </div>
@endpush
