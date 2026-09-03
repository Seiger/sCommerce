@use(Seiger\sCommerce\Models\sOrder)
@php
    $statusClass = match ((int) $status) {
        sOrder::ORDER_STATUS_NEW => 'scom-orders-status--new',
        sOrder::ORDER_STATUS_PROCESSING => 'scom-orders-status--processing',
        sOrder::ORDER_STATUS_CONFIRMED => 'scom-orders-status--confirmed',
        sOrder::ORDER_STATUS_PACKING => 'scom-orders-status--packing',
        sOrder::ORDER_STATUS_READY_FOR_SHIPMENT => 'scom-orders-status--ready',
        sOrder::ORDER_STATUS_SHIPPED => 'scom-orders-status--shipped',
        sOrder::ORDER_STATUS_DELIVERED => 'scom-orders-status--delivered',
        sOrder::ORDER_STATUS_COMPLETED => 'scom-orders-status--completed',
        sOrder::ORDER_STATUS_ON_HOLD => 'scom-orders-status--on-hold',
        sOrder::ORDER_STATUS_RETURN_REQUESTED => 'scom-orders-status--return-requested',
        sOrder::ORDER_STATUS_RETURNED => 'scom-orders-status--returned',
        sOrder::ORDER_STATUS_FAILED => 'scom-orders-status--failed',
        sOrder::ORDER_STATUS_CANCELED => 'scom-orders-status--canceled',
        default => 'scom-orders-status--deleted',
    };
@endphp
<span class="scom-orders-status {{$statusClass}}">{{$orderStatusLabel ?? sOrder::getOrderStatusName($status)}}</span>
