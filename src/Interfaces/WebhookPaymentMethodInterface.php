<?php namespace Seiger\sCommerce\Interfaces;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payment method that receives provider webhooks at `payment-callback/{method}[/{identifier}]`.
 *
 * @since 1.5.0
 */
interface WebhookPaymentMethodInterface extends PaymentMethodInterface
{
    /**
     * Cheap, side-effect-free check used when the URL does not name the method.
     * MUST NOT verify signatures with I/O.
     *
     * @param Request $request Incoming callback.
     * @return bool Whether this method should handle the request.
     * @since 1.5.0
     */
    public function matchesWebhook(Request $request): bool;

    /**
     * Verify the provider signature, record the result through
     * {@see \Seiger\sCommerce\Payment\PaymentFlow} and return the response the provider expects.
     *
     * @param Request $request Incoming callback.
     * @return Response Returned to the provider unchanged.
     * @since 1.5.0
     */
    public function handleWebhook(Request $request): Response;
}
