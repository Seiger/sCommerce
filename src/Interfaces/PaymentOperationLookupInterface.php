<?php namespace Seiger\sCommerce\Interfaces;

use Seiger\sCommerce\Payment\PaymentOperationRequest;
use Seiger\sCommerce\Payment\PaymentOperationResult;

/**
 * Payment method that can look up the provider result of an operation by its idempotency key.
 *
 * sCommerce never repeats a capture, refund or cancel whose result is unknown (a timeout or
 * a crash after the provider call). Without this interface such an operation stays `unknown`
 * and keeps its reserved amount until it is resolved at the provider.
 *
 * @since 1.5.0
 */
interface PaymentOperationLookupInterface extends PaymentMethodInterface
{
    /**
     * Find the provider result of an operation.
     *
     * @param PaymentOperationRequest $request The original request, including its idempotency key.
     * @return PaymentOperationResult|null The provider result, or null when the provider has definitely
     *                                     not received the operation; it is then sent again with the same key.
     * @since 1.5.0
     */
    public function findPaymentOperation(PaymentOperationRequest $request): ?PaymentOperationResult;
}
