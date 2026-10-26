<?php namespace Seiger\sCommerce\Interfaces;

use Seiger\sCommerce\Payment\PaymentOperationRequest;
use Seiger\sCommerce\Payment\PaymentOperationResult;

/**
 * Payment method that runs manager operations through its own durable operation store.
 *
 * sCommerce checks the manager guards and ledger eligibility, then hands the request over
 * instead of creating a second executable operation in `s_payment_operations`. The
 * implementation MUST deduplicate by `idempotencyKey`, reserve the amount, recover uncertain
 * provider results and record confirmed money facts through
 * {@see \Seiger\sCommerce\Payment\PaymentFlow} before it returns `succeeded`.
 *
 * @since 1.5.0
 */
interface DelegatedPaymentOperationsInterface extends PaymentMethodInterface
{
    /**
     * Whether this method can run the action.
     *
     * @param string $action `capture`, `refund` or `cancel`.
     * @since 1.5.0
     */
    public function supportsPaymentOperation(string $action): bool;

    /**
     * Run or resume the operation identified by `$request->idempotencyKey`.
     *
     * @since 1.5.0
     */
    public function runPaymentOperation(PaymentOperationRequest $request): PaymentOperationResult;
}
