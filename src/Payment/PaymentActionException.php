<?php namespace Seiger\sCommerce\Payment;

/**
 * A manager payment action was refused before any provider call.
 *
 * @since 1.5.0
 */
final class PaymentActionException extends \RuntimeException
{
    /**
     * @param string $messageKey `sCommerce::global` translation key shown to the manager.
     * @param int $status HTTP status for the manager endpoint.
     */
    public function __construct(public readonly string $messageKey, public readonly int $status = 422)
    {
        parent::__construct($messageKey, $status);
    }
}
