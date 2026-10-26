<?php namespace Seiger\sCommerce\Payment;

/**
 * Provider answer to a capture, refund or cancel request.
 *
 * Only `succeeded` applies the completed transition to the ledger; `pending` waits for a
 * webhook with the same `providerRef`; `failed` changes no money fact.
 *
 * @since 1.5.0
 */
final readonly class PaymentOperationResult
{
    public const SUCCEEDED = 'succeeded';
    public const PENDING = 'pending';
    public const FAILED = 'failed';

    /**
     * @param string $outcome `succeeded`, `pending` or `failed`.
     * @param string|null $providerRef Provider id of the refund or capture.
     * @param string $message Safe to show to a manager.
     * @param array<string, mixed> $metadata Provider details stored with the ledger row.
     */
    public function __construct(
        public string $outcome,
        public ?string $providerRef = null,
        public string $message = '',
        public array $metadata = [],
    ) {}
}
