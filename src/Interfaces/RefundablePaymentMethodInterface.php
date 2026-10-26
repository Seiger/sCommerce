<?php namespace Seiger\sCommerce\Interfaces;

use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Payment\PaymentOperationResult;

/**
 * Payment method that can refund a captured payment from the manager order screen.
 *
 * @since 1.5.0
 */
interface RefundablePaymentMethodInterface extends PaymentMethodInterface
{
    /**
     * Whether the provider can refund less than the captured amount.
     *
     * @since 1.5.0
     */
    public function supportsPartialRefund(): bool;

    /**
     * Return money of a captured ledger row. MUST be idempotent per `$idempotencyKey`.
     *
     * Return the provider refund id as `providerRef`; sCommerce records it as a `refund` row.
     *
     * @param sOrder $order Order of the payment.
     * @param sOrderPayment $payment Captured ledger row.
     * @param float $amount Amount to return, in the order currency.
     * @param string $reason Manager-entered reason; may be empty.
     * @param string $idempotencyKey Stable key of this operation; forward it to the provider.
     * @return PaymentOperationResult
     * @since 1.5.0
     */
    public function refund(sOrder $order, sOrderPayment $payment, float $amount, string $reason, string $idempotencyKey): PaymentOperationResult;
}
