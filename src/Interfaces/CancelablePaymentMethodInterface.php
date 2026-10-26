<?php namespace Seiger\sCommerce\Interfaces;

use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Payment\PaymentOperationResult;

/**
 * Payment method that can void an authorization or cancel a pending payment.
 *
 * @since 1.5.0
 */
interface CancelablePaymentMethodInterface extends PaymentMethodInterface
{
    /**
     * Void an authorization / cancel a pending payment. MUST be idempotent per `$idempotencyKey`.
     *
     * @param sOrder $order Order of the payment.
     * @param sOrderPayment $payment Ledger row with status `pending` or `authorized`.
     * @param string $reason Manager-entered reason; may be empty.
     * @param string $idempotencyKey Stable key of this operation; forward it to the provider.
     * @return PaymentOperationResult
     * @since 1.5.0
     */
    public function cancel(sOrder $order, sOrderPayment $payment, string $reason, string $idempotencyKey): PaymentOperationResult;
}
