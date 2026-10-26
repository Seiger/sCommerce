<?php namespace Seiger\sCommerce\Payment;

use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;

/**
 * Manager payment operation handed to a delegated operation store or a provider lookup.
 *
 * @since 1.5.0
 */
final readonly class PaymentOperationRequest
{
    /**
     * @param string $action `capture`, `refund` or `cancel`.
     * @param sOrder $order Order of the payment.
     * @param sOrderPayment $payment Ledger row the action applies to.
     * @param float|null $amount Refund amount; null for capture (full) and cancel.
     * @param string $currency Order currency.
     * @param string $reason Manager-entered reason; may be empty.
     * @param string $idempotencyKey Stable key; the same key always means the same request.
     * @param int $actorId Manager user id.
     */
    public function __construct(
        public string $action,
        public sOrder $order,
        public sOrderPayment $payment,
        public ?float $amount,
        public string $currency,
        public string $reason,
        public string $idempotencyKey,
        public int $actorId,
    ) {}
}
