<?php namespace Seiger\sCommerce\Payment;

use Seiger\sCommerce\Models\sOrder;

/**
 * `settle_on_statuses` setting of offline payment methods.
 *
 * @see \Seiger\sCommerce\Interfaces\OfflineSettlementPaymentMethodInterface
 * @since 1.5.0
 */
trait OfflineSettlementSettings
{
    /**
     * Settings group with the order statuses that settle the unpaid balance.
     *
     * @return array<string, array<string, mixed>>
     * @since 1.5.0
     */
    protected function offlineSettlementSettings(): array
    {
        $statuses = sOrder::listOrderStatuses();
        unset($statuses[sOrder::ORDER_STATUS_DELETED]);

        return [
            'settlement' => [
                'label' => __('sCommerce::global.payment_settlement'),
                'fields' => [
                    'settle_on_statuses' => [
                        'type' => 'multiselect',
                        'label' => __('sCommerce::global.settle_on_statuses'),
                        'name' => 'settle_on_statuses[]',
                        'value' => $this->getSettings()['settle_on_statuses'] ?? [],
                        'options' => $statuses,
                        'hint' => __('sCommerce::global.settle_on_statuses_help'),
                    ],
                ],
            ],
        ];
    }
}
