<?php namespace Seiger\sCommerce\Checkout;

/**
 * A checkout was refused by its trusted context before any side effect: a resolver refused it,
 * for example because a test transaction cannot be isolated, or returned an invalid context.
 *
 * The message is safe to show to the customer.
 *
 * @since 1.5.0
 */
final class CheckoutContextException extends \RuntimeException
{
}
