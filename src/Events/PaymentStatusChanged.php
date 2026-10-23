<?php namespace Seiger\sCommerce\Events;

use Seiger\sCommerce\Models\sOrderPayment;

/**
 * `sCommerce.PaymentStatusChanged`: the status of a payments ledger row changed.
 *
 * Never merged away by coalescing.
 *
 * @since 1.5.0
 */
final readonly class PaymentStatusChanged extends PaymentEvent
{
    public function __construct(
        string $eventId,
        \DateTimeImmutable $occurredAt,
        string $source,
        int $actorId,
        ?string $correlationId,
        ?string $causationId,
        ?string $integrationActor,
        array $attributes,
        ?string $orderUuid,
        public string $oldStatus,
        public string $newStatus,
    ) {
        parent::__construct($eventId, $occurredAt, $source, $actorId, $correlationId, $causationId, $integrationActor, $attributes, $orderUuid);
    }

    public static function name(): string
    {
        return 'sCommerce.PaymentStatusChanged';
    }

    /**
     * Capture a ledger status transition.
     *
     * @since 1.5.0
     */
    public static function from(sOrderPayment $payment, string $oldStatus, string $newStatus): self
    {
        return new self(...self::newContext(), ...self::capture($payment), oldStatus: $oldStatus, newStatus: $newStatus);
    }

    public static function dispatchRank(): int
    {
        return 30;
    }

    public function toArray(): array
    {
        return parent::toArray() + ['oldStatus' => $this->oldStatus, 'newStatus' => $this->newStatus];
    }
}
