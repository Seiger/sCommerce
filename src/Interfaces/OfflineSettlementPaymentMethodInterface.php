<?php namespace Seiger\sCommerce\Interfaces;

/**
 * Marker: an offline payment method (cash on delivery, bank transfer) that sCommerce may
 * settle automatically when an order reaches one of the `settle_on_statuses` of its settings.
 *
 * Offline methods charge nothing at checkout, so they also accept payment schedules.
 *
 * @since 1.5.0
 */
interface OfflineSettlementPaymentMethodInterface extends InstallmentPaymentMethodInterface
{
}
