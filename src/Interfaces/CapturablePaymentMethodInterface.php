<?php namespace Seiger\sCommerce\Interfaces;

use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Payment\PaymentOperationResult;

/**
 * Payment method that can capture an authorized payment from the manager order screen.
 *
 * @since 1.5.0
 */
interface CapturablePaymentMethodInterface extends PaymentMethodInterface
{
    /**
     * Whether the provider can capture less than the authorized amount.
     *
     * The manager screen currently requests full captures only.
     *
     * @since 1.5.0
     */
    public function supportsPartialCapture(): bool;

    /**
     * Capture an authorized ledger row. MUST be idempotent per `$idempotencyKey`.
     *
     * @param sOrder $order Order of the payment.
     * @param sOrderPayment $payment Ledger row with status `authorized`.
     * @param float|null $amount Amount to capture; null captures the full authorized amount.
     * @param string $idempotencyKey Stable key of this operation; forward it to the provider.
     * @return PaymentOperationResult
     * @since 1.5.0
     */
    public function capture(sOrder $order, sOrderPayment $payment, ?float $amount, string $idempotencyKey): PaymentOperationResult;
}
