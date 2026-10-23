<?php namespace Seiger\sCommerce\Events;

use Seiger\sCommerce\Models\sOrderPayment;

/**
 * `sCommerce.PaymentRecorded`: a payments ledger row was created.
 *
 * Never merged away by coalescing.
 *
 * @since 1.5.0
 */
final readonly class PaymentRecorded extends PaymentEvent
{
    public static function name(): string
    {
        return 'sCommerce.PaymentRecorded';
    }

    /**
     * Capture a newly created ledger row.
     *
     * @since 1.5.0
     */
    public static function from(sOrderPayment $payment): self
    {
        return new self(...self::newContext(), ...self::capture($payment));
    }

    public static function dispatchRank(): int
    {
        return 10;
    }
}
