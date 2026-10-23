<?php namespace Seiger\sCommerce\Payment;

use Seiger\sCommerce\Models\sOrderPayment;

/**
 * Outcome of a ledger write.
 *
 * @since 1.5.0
 */
final readonly class LedgerResult
{
    /**
     * @param sOrderPayment $payment The created or existing ledger row.
     * @param bool $created A new row was inserted.
     * @param bool $changed The row was inserted or its status changed.
     * @param string|null $previousStatus Status before this write; null for a new row.
     */
    public function __construct(
        public sOrderPayment $payment,
        public bool $created,
        public bool $changed,
        public ?string $previousStatus,
    ) {}
}
