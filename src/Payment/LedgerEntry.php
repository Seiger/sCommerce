<?php namespace Seiger\sCommerce\Payment;

/**
 * One money movement to record in the order payments ledger.
 *
 * Pass it to {@see PaymentFlow::record()}. Entries are deduplicated by
 * `(provider, providerRef)`: repeat the same pair for every status update of one payment.
 *
 * @since 1.5.0
 */
final readonly class LedgerEntry
{
    /**
     * @param string $kind `deposit`, `final`, `manual` or `refund`.
     * @param string $status `pending`, `authorized`, `captured`, `failed`, `refunded`, `partially_refunded`,
     *                       `canceled`, `rejected`, `expired` or `disputed`.
     * @param float $amount Positive amount; for `refund` the amount returned.
     * @param string $currency ISO 4217 code; must equal the order currency. Empty means the order currency.
     * @param string $provider Stable lowercase provider/account/environment namespace, at most 32 characters.
     * @param string|null $providerRef PSP id of this money movement (payment, charge or refund id), not a webhook event id.
     * @param string|null $parentProviderRef For refunds: `providerRef` of the original payment.
     * @param array<string, mixed> $metadata Caller data, at most 64 KB when JSON-encoded.
     * @param \DateTimeImmutable|null $occurredAt When the PSP reports the movement; used for `captured_at`.
     */
    public function __construct(
        public string $kind,
        public string $status,
        public float $amount,
        public string $currency,
        public string $provider,
        public ?string $providerRef = null,
        public ?string $parentProviderRef = null,
        public array $metadata = [],
        public ?\DateTimeImmutable $occurredAt = null,
    ) {}
}
