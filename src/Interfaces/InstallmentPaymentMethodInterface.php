<?php namespace Seiger\sCommerce\Interfaces;

/**
 * Marker: the payment method charges `payment_request.amount` when it is present.
 *
 * Checkout refuses an order with a payment schedule (deposit + final) when its payment
 * method does not implement this interface, so a method that always charges the order
 * cost cannot overcollect.
 *
 * @since 1.5.0
 */
interface InstallmentPaymentMethodInterface extends PaymentMethodInterface
{
}
