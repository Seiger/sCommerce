@php use Seiger\sCommerce\Models\sOrder; @endphp
<form id="form" name="form" method="post" enctype="multipart/form-data" action="{!!sCommerce::moduleUrl()!!}&get=orderSave" onsubmit="documentDirty=false;">
    <input type="hidden" name="back" value="&get=order&i={{(int)request()->input('i', 0)}}"/>
    <input type="hidden" name="i" value="{{(int)request()->input('i', 0)}}"/>
    <input type="hidden" name="products_data" id="products-data" value="{{json_encode($item->products)}}"/>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md">
                <h3>
                    <b>#{{$item->id}}</b>
                    @if($item->is_quick) <span class="badge bg-super bg-seigerit"><i class="fas fa-clock"></i> @lang('sCommerce::global.one_click')</span>@endif
                    <b>{{implode(' ', array_diff([$item->user_info['first_name'] ?? '', $item->user_info['middle_name'] ?? '', $item->user_info['last_name'] ?? ''], ['']))}} {{$item->user_info['phone']}}</b>
                </h3>
                @if($domains)
                    <p>
                        <span @class(['badge', 'domain-badge']) @style(['font-size:100%;', 'line-height:inherit;', 'background-color:'.($domains[$item->domain]->site_color ?? '#60a5fa')])>
                            {{$domains[$item->domain]->domain}}
                        </span>
                    </p>
                @endif
                <p>
                    <strong>@lang('sCommerce::global.created'):</strong>
                    <span @class(['badge', 'bg-disactive' => in_array($item->status, $unprocessedes), 'bg-progress' => in_array($item->status, $workings), 'bg-active' => in_array($item->status, $completeds)])>
                        {{$item->created_at}} &mdash; {{sOrder::getOrderStatusName($item->status)}}
                    </span>
                </p>
                <p>
                    <strong>@lang('sCommerce::global.sum'):</strong>
                    <span @class(['badge', 'bg-paid' => $item->payment_status == sOrder::PAYMENT_STATUS_PAID, 'bg-pending' => $item->payment_status != sOrder::PAYMENT_STATUS_PAID])>
                        {{sCommerce::convertPrice($item->cost, $item->currency)}} &mdash; {{sOrder::getPaymentStatusName($item->payment_status)}}
                    </span>
                </p>
                @if(trim($item->comment ?? ''))
                    <p><strong>@lang('sCommerce::global.comment_to_order'):</strong> {{$item->comment}}</p>
                @endif
                <p>
                    <strong>@lang('sCommerce::global.order_status'):</strong>
                    <select name="status" @style(['display:inline-block', 'width:auto', 'margin-left:10px', 'padding:5px'])>
                        @foreach(sOrder::listOrderStatuses() as $sId => $sName)
                            <option value="{{$sId}}" @if($sId == $item->status) selected @endif>{{$sName}}</option>
                        @endforeach
                    </select>
                </p>
                <div class="split my-3"></div>
            </div>
            <div class="col-md">
                <h3>@lang('sCommerce::global.payment_information')</h3>
                <p></p>
                <p><strong>@lang('sCommerce::global.order_currency'):</strong> {{$item->currency}}</p>
                <p><strong>@lang('sCommerce::global.order_cost'):</strong> {{$item->cost}}</p>
                <p><strong>@lang('sCommerce::global.payment_name'):</strong>@if(!$payment) @lang('sCommerce::global.not_selected_or_unknown') @else {{$payment['title']}}@endif</p>
                <p>
                    <strong>@lang('sCommerce::global.payment_status'):</strong>
                    <select name="payment_status" @style(['display:inline-block', 'width:auto', 'margin-left:10px', 'padding:5px'])>
                        @foreach(sOrder::listPaymentStatuses() as $psId => $psName)
                            <option value="{{$psId}}" @if($psId == $item->payment_status) selected @endif>{{$psName}}</option>
                        @endforeach
                    </select>
                </p>
                <div class="split my-3"></div>
            </div>
            <div class="col-md">
                <h3>@lang('sCommerce::global.shipping_information')</h3>
                <p><strong>@lang('sCommerce::global.delivery_name'):</strong>@if(!$delivery) @lang('sCommerce::global.not_selected_or_unknown') @else {{$delivery['title']}}@endif</p>
                @if($delivery)
                    <p><strong>@lang('sCommerce::global.shipping_cost'):</strong> {{sCommerce::convertPrice(floatval($item->delivery_info['cost'] ?? 0), $item->currency)}}</p>
                    @if(isset($item->delivery_info[$item->delivery_info['method']]) && is_array($item->delivery_info[$item->delivery_info['method']]))
                        @foreach($item->delivery_info[$item->delivery_info['method']] as $key => $value)
                            <p><strong>{{$key}}:</strong> {{$value}}</p>
                        @endforeach
                    @endif
                @endif
                <div class="split my-3"></div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <h3>@lang('sCommerce::global.customer_information')</h3>
                <p>
                    <strong>@lang('global.user_full_name'):</strong>
                    @if((int)$item->user_info['id'] > 0)
                        <a href="/manager/index.php?a=88&id={{(int)$item->user_info['id']}}" target="_blank">
                            {{implode(' ', array_diff([$item->user_info['first_name'] ?? '', $item->user_info['middle_name'] ?? '', $item->user_info['last_name'] ?? ''], ['']))}}
                        </a>
                    @else
                        {{implode(' ', array_diff([$item->user_info['first_name'] ?? '', $item->user_info['middle_name'] ?? '', $item->user_info['last_name'] ?? ''], ['']))}}
                    @endif
                </p>
                <p><strong>@lang('global.user_phone'):</strong> {{$item->user_info['phone'] ?? ''}}</p>
                <p><strong>@lang('global.user_email'):</strong> {{$item->user_info['email'] ?? ''}}</p>
                <div class="split my-3"></div>
            </div>
            <div class="col-md-8">
                <h3>@lang('sCommerce::global.comments_and_notes')</h3>
                <textarea name="note" class="form-control" rows="4" placeholder="@lang('sCommerce::global.add_comment')"></textarea>
                @foreach(array_reverse($item->manager_notes) as $note)
                    <p>
                        <strong>{{$note['timestamp'] ?? ''}}:</strong>
                        @if((int)$note['user_id'] > 0)
                            <a href="/manager/index.php?a=88&id={{(int)$note['user_id']}}" target="_blank">{{evo()->getUserInfo((int)$note['user_id'])['username']}}</a>
                        @else
                            @lang('sCommerce::global.system')
                        @endif
                    </p>
                    <p>{!!$note['comment']!!}</p>
                @endforeach
                <div class="split my-3"></div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">@lang('sCommerce::global.products_in_order')</h3>
        <button type="button" class="btn btn-primary" id="open-add-product-modal" onclick="openAddProductModal(); return false;">
            <i class="fa fa-plus"></i> @lang('sCommerce::global.add_product')
        </button>
    </div>
    <table class="table table-condensed table-hover sectionTrans scom-table" id="order-products-table">
        <thead>
        <tr>
            <th>@lang('sCommerce::global.product_name')</th>
            <th>@lang('sCommerce::global.price')</th>
            <th>@lang('sCommerce::global.quantity')</th>
            <th>@lang('sCommerce::global.sum')</th>
            <th style="width: 80px;">@lang('global.onlineusers_action')</th>
        </tr>
        </thead>
        <tbody>
        @foreach($item->products as $index => $product)
            @php($info = '')
            @foreach($product as $p)
                @if(is_array($p) && isset($p['title']))
                    @php($info .= '<b>' . htmlentities($p['title']) . ':</b> ' . htmlentities($p['label'] ?? '') . '<br>')
                @endif
            @endforeach
            <tr data-product-index="{{$index}}" style="height: 42px;">
                <td>
                    <img src="{{$product['coverSrc']}}" class="product-thumbnail">
                    <a href="{{$product['link']}}" target="_blank"><b>{{$product['title']}}</b></a>
                    @if(trim($info))<i class="fa fa-question-circle" data-tooltip="{!!$info!!}"></i>@endif
                </td>
                <td>{{$product['price']}}</td>
                <td>
                    <input type="number" name="products[{{$index}}][quantity]" class="form-control product-quantity" value="{{$product['quantity']}}" min="1" style="width: 70px;">
                </td>
                <td class="product-sum">{{sCommerce::convertPrice($product['quantity'] * sCommerce::convertPriceNumber($product['price'], $item->currency, $item->currency), $item->currency)}}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger remove-product" data-product-index="{{$index}}" title="@lang('global.delete')">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td colspan="3" class="text-end"><strong>@lang('sCommerce::global.total'):</strong></td>
            <td><strong id="order-total">{{sCommerce::convertPrice($item->cost, $item->currency)}}</strong></td>
            <td></td>
        </tr>
        </tfoot>
    </table>
    <div class="split my-3"></div>
</form>

<!-- Modal backdrop -->
<div id="addProductModalBackdrop" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1040; display: none;"></div>

<!-- Modal for adding products -->
<div id="addProductModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; display: none; overflow: auto;">
    <div class="modal-dialog modal-lg" style="position: relative; margin: 30px auto; max-width: 800px; width: 90%;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProductModalLabel">@lang('sCommerce::global.add_product')</h5>
                <button type="button" class="close close-modal-btn" aria-label="Close" style="cursor: pointer; float: right; font-size: 28px; font-weight: bold; line-height: 1; color: #000; opacity: 0.5;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="product-search">@lang('sCommerce::global.search_among_products')</label>
                    <input type="text" class="form-control" id="product-search" placeholder="@lang('sCommerce::global.sku') / @lang('sCommerce::global.product_name')">
                    <div id="product-search-results" class="mt-3" style="max-height: 400px; overflow-y: auto;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-modal-btn">@lang('global.cancel')</button>
            </div>
        </div>
    </div>
</div>

<div class="order-history">
    <h3>@lang('sCommerce::global.history_changes')</h3>
    <table class="history-table">
        <thead>
        <tr>
            <th>@lang('global.mgrlog_time')</th>
            <th>@lang('sCommerce::global.status')</th>
            <th>@lang('global.mgrlog_user')</th>
        </tr>
        </thead>
        <tbody>
        @foreach(array_reverse($item->history) as $history)
            <tr>
                <td>{{$history['timestamp'] ?? ''}}</td>
                <td>
                    @if(isset($history['status']))
                        <span @class(['badge', 'bg-disactive' => in_array((int)$history['status'], $unprocessedes), 'bg-progress' => in_array((int)$history['status'], $workings), 'bg-active' => in_array((int)$history['status'], $completeds)])>
                            {{sOrder::getOrderStatusName((int)$history['status'])}}
                        </span>
                    @elseif(isset($history['payment_status']))
                        <span @class(['badge', 'bg-paid' => (int)$history['payment_status'] == sOrder::PAYMENT_STATUS_PAID, 'bg-pending' => (int)$history['payment_status'] != sOrder::PAYMENT_STATUS_PAID])>
                            {{sOrder::getPaymentStatusName((int)$history['payment_status'])}}
                        </span>
                    @elseif(isset($history['products_updated']) && $history['products_updated'])
                        <span class="badge bg-info">
                            @lang('sCommerce::global.products_in_order') змінено
                        </span>
                    @endif
                </td>
                <td>
                    @if((int)$history['user_id'] > 0)
                        <a href="/manager/index.php?a=88&id={{(int)$history['user_id']}}" target="_blank">{{evo()->getUserInfo((int)$history['user_id'])['username']}}</a>
                    @else
                        @lang('sCommerce::global.system')
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<style>
    .history-table {
        width: 100%;
        border-collapse: collapse;
    }
    .history-table th, .history-table td {
        padding: 8px;
        border: 1px solid #ddd;
    }
</style>

@push('scripts.bot')
    <div id="actions">
        <div class="btn-group">
            <a id="Button5" class="btn btn-secondary" href="{!!sCommerce::moduleUrl()!!}&get=orders{{request()->has('page') ? '&page=' . request()->page : ''}}">
                <i class="fa fa-times-circle"></i><span>@lang('sCommerce::global.to_list_orders')</span>
            </a>
            <a id="Button1" class="btn btn-success" href="javascript:void(0);" onclick="saveForm('#form');">
                <i class="fa fa-floppy-o"></i>
                <span>@lang('global.save')</span>
            </a>
        </div>
    </div>
    <script>
        // Automatic contrast text color for domain buttons
        $(document).ready(function() {
            function hexToRgb(hex) {
                hex = hex.replace('#', '');
                if (hex.length === 3) {
                    hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
                }
                return {
                    r: parseInt(hex.substr(0, 2), 16),
                    g: parseInt(hex.substr(2, 2), 16),
                    b: parseInt(hex.substr(4, 2), 16)
                };
            }

            function getContrastTextColor(r, g, b) {
                const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                return luminance > 0.5 ? '#000000' : '#ffffff';
            }

            function setButtonTextColor(btn) {
                // Try to get color from inline style first
                const style = btn.getAttribute('style') || '';
                const match = style.match(/background-color:\s*([^;]+)/);
                if (match) {
                    const colorStr = match[1].trim();
                    if (colorStr.startsWith('#')) {
                        const rgb = hexToRgb(colorStr);
                        btn.style.color = getContrastTextColor(rgb.r, rgb.g, rgb.b);
                        return;
                    }
                }

                // Fallback: try computed style
                const bgColor = window.getComputedStyle(btn).backgroundColor;
                const rgbMatch = bgColor.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
                if (rgbMatch) {
                    const r = parseInt(rgbMatch[1]);
                    const g = parseInt(rgbMatch[2]);
                    const b = parseInt(rgbMatch[3]);
                    btn.style.color = getContrastTextColor(r, g, b);
                }
            }

            $('.domain-badge').each(function() {
                setButtonTextColor(this);
            });

            // Open add product modal function
            window.openAddProductModal = function() {
                console.log('openAddProductModal called'); // Debug
                $('#addProductModalBackdrop').show();
                $('#addProductModal').show();
                $('body').css('overflow', 'hidden');
            };

            // Also bind jQuery event as backup
            $('#open-add-product-modal').on('click', function(e) {
                e.preventDefault();
                openAddProductModal();
            });

            // Close modal handlers
            $(document).on('click', '.close-modal-btn', function(e) {
                e.preventDefault();
                $('#addProductModalBackdrop').hide();
                $('#addProductModal').hide();
                $('body').css('overflow', '');
            });

            $(document).on('click', '#addProductModalBackdrop', function(e) {
                if (e.target === this) {
                    $('#addProductModalBackdrop').hide();
                    $('#addProductModal').hide();
                    $('body').css('overflow', '');
                }
            });

            // Order products management
            let orderProducts = JSON.parse($('#products-data').val() || '[]');
            const orderId = {{$item->id}};
            const orderCurrency = '{{$item->currency}}';

            // Update products data in hidden field
            function updateProductsData() {
                $('#products-data').val(JSON.stringify(orderProducts));
                documentDirty = true;
            }

            // Update products before form submit - make it global
            window.updateProductsBeforeSubmit = function() {
                updateProductsData();
            };

            // Also bind to form submit event
            $('#form').on('submit', function() {
                updateProductsData();
            });

            // Update product sum in row
            function updateProductSum(index) {
                const product = orderProducts[index];
                if (!product) return;

                // Extract numeric value from price string (handles currency symbols)
                const priceStr = product.price.toString().replace(/[^\d.,]/g, '').replace(',', '.');
                const price = parseFloat(priceStr) || 0;
                const quantity = parseInt(product.quantity) || 1;
                const sum = price * quantity;

                // Format sum - simple format for now, can be improved with sCommerce.convertPrice if available
                const $row = $(`tr[data-product-index="${index}"]`);
                $row.find('.product-sum').text(sum.toFixed(2) + ' ' + orderCurrency);
            }

            // Recalculate order total
            function recalculateTotal() {
                let total = 0;
                orderProducts.forEach(function(product) {
                    // Extract numeric value from price string (handles currency symbols)
                    const priceStr = product.price.toString().replace(/[^\d.,]/g, '').replace(',', '.');
                    const price = parseFloat(priceStr) || 0;
                    const quantity = parseInt(product.quantity) || 0;
                    total += price * quantity;
                });
                // Format total using the same format as sCommerce::convertPrice
                $('#order-total').text(total.toFixed(2) + ' ' + orderCurrency);
            }

            // Handle quantity change
            $(document).on('change', '.product-quantity', function() {
                const $input = $(this);
                const $row = $input.closest('tr');
                const index = parseInt($row.data('product-index'));
                const newQuantity = parseInt($input.val()) || 1;

                if (newQuantity < 1) {
                    $input.val(1);
                    orderProducts[index].quantity = 1;
                } else {
                    orderProducts[index].quantity = newQuantity;
                }

                updateProductSum(index);
                updateProductsData();
                recalculateTotal();
            });

            // Remove product from order
            $(document).on('click', '.remove-product', function() {
                const index = $(this).data('product-index');
                if (confirm('@lang("sCommerce::global.confirm_delete_product")')) {
                    orderProducts.splice(index, 1);
                    updateProductsData();
                    $(this).closest('tr').remove();
                    // Reindex rows
                    $('#order-products-table tbody tr').each(function(i) {
                        $(this).attr('data-product-index', i);
                        $(this).find('.remove-product').attr('data-product-index', i);
                    });
                    recalculateTotal();
                }
            });

            // Product search
            let searchTimeout;
            $('#product-search').on('input', function() {
                clearTimeout(searchTimeout);
                const query = $(this).val();
                if (query.length < 2) {
                    $('#product-search-results').html('');
                    return;
                }
                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: '{!!sCommerce::moduleUrl()!!}&get=orderSearchProducts',
                        method: 'GET',
                        data: { search: query, currency: orderCurrency },
                        success: function(response) {
                            if (response.success && response.products) {
                                let html = '<div class="list-group">';
                                response.products.forEach(function(product) {
                                    html += '<a href="#" class="list-group-item list-group-item-action add-product-item" data-product-id="' + product.id + '" data-product-title="' + (product.title || '').replace(/'/g, "&#39;") + '" data-product-price="' + (product.price || '') + '" data-product-price-number="' + (product.priceNumber || 0) + '" data-product-cover="' + (product.coverSrc || '') + '" data-product-link="' + (product.link || '#') + '">';
                                    html += '<div class="d-flex align-items-center">';
                                    html += '<img src="' + product.coverSrc + '" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;">';
                                    html += '<div><strong>' + product.title + '</strong><br><small>' + product.sku + ' - ' + product.price + '</small></div>';
                                    html += '</div></a>';
                                });
                                html += '</div>';
                                $('#product-search-results').html(html);
                            } else {
                                $('#product-search-results').html('<div class="alert alert-info">@lang("sCommerce::global.no_products_found")</div>');
                            }
                        }
                    });
                }, 300);
            });

            // Add product to order
            $(document).on('click', '.add-product-item', function(e) {
                e.preventDefault();
                const $item = $(this);
                const product = {
                    id: $item.data('product-id'),
                    title: $item.data('product-title'),
                    price: $item.data('product-price'),
                    priceNumber: $item.data('product-price-number') || parseFloat($item.data('product-price').toString().replace(/[^\d.,]/g, '').replace(',', '.')) || 0,
                    coverSrc: $item.data('product-cover'),
                    link: $item.data('product-link'),
                    quantity: 1,
                    sku: $item.find('small').text().split(' - ')[0]
                };
                orderProducts.push(product);
                updateProductsData();

                // Add row to table
                const index = orderProducts.length - 1;
                const productSum = (parseFloat(product.priceNumber || 0) * product.quantity).toFixed(2) + ' ' + orderCurrency;
                const row = '<tr data-product-index="' + index + '" style="height: 42px;">' +
                    '<td><img src="' + product.coverSrc + '" class="product-thumbnail">' +
                    '<a href="' + product.link + '" target="_blank"><b>' + product.title + '</b></a></td>' +
                    '<td>' + product.price + '</td>' +
                    '<td><input type="number" name="products[' + index + '][quantity]" class="form-control product-quantity" value="' + product.quantity + '" min="1" style="width: 70px;"></td>' +
                    '<td class="product-sum">' + productSum + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-danger remove-product" data-product-index="' + index + '" title="@lang("global.delete")"><i class="fa fa-trash"></i></button></td>' +
                    '</tr>';
                $('#order-products-table tbody').append(row);
                updateProductSum(index);
                recalculateTotal();
                $('#product-search').val('');
                $('#product-search-results').html('');
            });
        });
    </script>
@endpush