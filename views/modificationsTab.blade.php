@php use Seiger\sCommerce\Models\sAttribute; @endphp
<h3>{{request()->integer('i') == 0 ? __('sCommerce::global.new_product') : ($product->pagetitle ?? __('sCommerce::global.no_text'))}}</h3>
<div class="split my-3"></div>

<div class="row-col col-12">
    <form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!$moduleUrl!!}&get=modificationsSave" onsubmit="documentDirty=false;">
        <input type="hidden" name="back" value="&get=modifications&i={{request()->integer('i')}}" />
        <input type="hidden" name="i" value="{{request()->integer('i')}}" />
        <div class="row form-row">
            <div class="col-auto">
                <label for="parameters">@lang('sCommerce::global.modification_attributes')</label>
                {{--<i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.additional_fields_to_products_tab_help')"></i>--}}
            </div>
            <div class="col">
                <select id="parameters" class="form-control select2" name="parameters[]" multiple onchange="documentDirty=true;">
                    @foreach($listAttributes as $item)
                        <option value="{{$item->alias}}" @if(in_array($item->alias, $parameters)) selected @endif>{{$item->pagetitle}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="split my-3"></div>
        <h4><strong>@lang('sCommerce::global.added_products')</strong></h4>
        <div class="table-responsive seiger__module-table">
            <table class="table table-condensed table-hover sectionTrans scom-table">
                <thead>
                <tr>
                    @if (sCommerce::config('products.show_field_id', 1) == 1)
                        <th>ID</th>
                    @endif
                    @if (sCommerce::config('products.show_field_sku', 1) && sCommerce::config('product.show_field_sku', 1))
                        <th>@lang('sCommerce::global.sku')</th>
                    @endif
                    <th>@lang('sCommerce::global.product_name')</th>
                    @if(count($parameters))
                        @foreach($parameters as $parameter)
                            <th>{{$listAttributes->where('alias', $parameter)->first()?->pagetitle ?? ''}}</th>
                        @endforeach
                    @endif
                    @if (sCommerce::config('products.show_field_price', 1) && sCommerce::config('product.show_field_price', 1))
                        <th>@lang('sCommerce::global.price')</th>
                    @endif
                    @if (sCommerce::config('products.show_field_price_special', 1) && sCommerce::config('product.show_field_price_special', 1))
                        <th>@lang('sCommerce::global.price_special')</th>
                    @endif
                    @if (sCommerce::config('products.show_field_inventory', 1) && sCommerce::config('product.inventory_on', 1))
                        <th>@lang('sCommerce::global.inventory')</th>
                    @endif
                    <th id="action-btns">@lang('global.onlineusers_action')</th>
                </tr>
                </thead>
                <tbody>
                    @include('sCommerce::partials.modificationProductRow', ['item' => $product, 'disableId' => request()->integer('i'), 'parameters' => $parameters]))
                    @foreach($modifications as $modification)
                        @include('sCommerce::partials.modificationProductRow', ['item' => $modification, 'parameters' => $parameters])
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="split my-3"></div>
        <h4><strong>@lang('sCommerce::global.add_product')</strong></h4>
        <div class="table-responsive seiger__module-table">
            <table class="table table-condensed table-hover sectionTrans scom-table">
                <thead>
                <tr>
                    @if (sCommerce::config('products.show_field_id', 1) == 1)
                        <th>ID</th>
                    @endif
                    @if (sCommerce::config('products.show_field_sku', 1) && sCommerce::config('product.show_field_sku', 1))
                        <th>@lang('sCommerce::global.sku')</th>
                    @endif
                    <th>@lang('sCommerce::global.product_name')</th>
                    @if(count($parameters))
                        @foreach($parameters as $parameter)
                            <th>{{$listAttributes->where('alias', $parameter)->first()?->pagetitle ?? ''}}</th>
                        @endforeach
                    @endif
                    @if (sCommerce::config('products.show_field_price', 1) && sCommerce::config('product.show_field_price', 1))
                        <th>@lang('sCommerce::global.price')</th>
                    @endif
                    @if (sCommerce::config('products.show_field_price_special', 1) && sCommerce::config('product.show_field_price_special', 1))
                        <th>@lang('sCommerce::global.price_special')</th>
                    @endif
                    @if (sCommerce::config('products.show_field_inventory', 1) && sCommerce::config('product.inventory_on', 1))
                        <th>@lang('sCommerce::global.inventory')</th>
                    @endif
                    <th id="action-btns">@lang('global.onlineusers_action')</th>
                </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        @include('sCommerce::partials.modificationProductRow', ['item' => $product, 'add' => true, 'parameters' => $parameters]))
                    @endforeach
                </tbody>
            </table>
        </div>
    </form>
</div>
<div class="split my-3"></div>

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!$moduleUrl!!}&get=products{{request()->has('page') ? '&page=' . request()->page : ''}}">
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
    <script>
        document.addEventListener("click", async function(e) {
            if (e.target) {
                if (Boolean(e.target.closest('span')?.classList.contains('addProduct'))) {
                    e.preventDefault();
                    if ('disabled' in e.target) e.target.disabled = true;
                    productId = parseInt(e.target.closest('tr')?.getAttribute('id').replace('product-', '')) || 0;

                    let form = new FormData();
                    form.append('i', productId);
                    form.append('view', 'partials.modificationProductRow');
                    form.append('parameters', JSON.stringify(@json($parameters)));
                    let data = await callApi('{!!sCommerce::moduleUrl()!!}&get=productForView', form);

                    if (data.success == 1) {
                        document.querySelector('#form table:nth-of-type(1) tbody').insertAdjacentHTML('beforeend', data.view);
                        e.target.closest('tr').remove();
                    }

                    if ('disabled' in e.target) e.target.disabled = false;
                }
            }
        });

        async function callApi(url, form, method = 'POST', type = 'json') { // text, json, blob, formData, arrayBuffer
            try {
                const response = await fetch(url, {
                    method: method,
                    cache: "no-store",
                    headers: {"X-Requested-With": "XMLHttpRequest"},
                    body: form
                });

                if (!response.ok) {
                    if (response.status === 404) throw new Error('404, Not found');
                    if (response.status === 500) throw new Error('500, Internal server error');
                    throw new Error(`HTTP error: ${response.status}`);
                }

                switch (type) {
                    case 'text':
                        return await response.text();
                    case 'json':
                        return await response.json();
                    case 'blob':
                        return await response.blob();
                    case 'formData':
                        return await response.formData();
                    case 'arrayBuffer':
                        return await response.arrayBuffer();
                    default:
                        throw new Error('Unsupported response type');
                }
            } catch (error) {
                console.error('Request failed:', error);
                return null;
            }
        }
    </script>
@endpush
