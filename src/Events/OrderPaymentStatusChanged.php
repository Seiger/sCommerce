<?php namespace Seiger\sCommerce\Events;

use Seiger\sCommerce\Models\sOrder;

/**
 * `sCommerce.OrderPaymentStatusChanged`: the order payment status projection changed.
 *
 * This is a projection on the order, not a payment fact: it never means that money was captured.
 *
 * @since 1.5.0
 */
final readonly class OrderPaymentStatusChanged extends OrderEvent
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
        public int $oldPaymentStatus,
        public int $newPaymentStatus,
    ) {
        parent::__construct($eventId, $occurredAt, $source, $actorId, $correlationId, $causationId, $integrationActor, $attributes);
    }

    public static function name(): string
    {
        return 'sCommerce.OrderPaymentStatusChanged';
    }

    /**
     * Capture a payment status transition.
     *
     * @since 1.5.0
     */
    public static function from(sOrder $order, int $oldPaymentStatus, int $newPaymentStatus): self
    {
        return new self(...self::newContext(), attributes: self::snapshot($order), oldPaymentStatus: $oldPaymentStatus, newPaymentStatus: $newPaymentStatus);
    }

    public static function dispatchRank(): int
    {
        return 40;
    }

    public function coalesceKey(): ?string
    {
        return 'payment_status';
    }

    /** First old payment status to last new payment status. */
    public function merge(DomainEvent $later): DomainEvent
    {
        return $later instanceof self ? $this->with(['attributes' => $later->attributes, 'newPaymentStatus' => $later->newPaymentStatus]) : $this;
    }

    public function isEmpty(): bool
    {
        return $this->oldPaymentStatus === $this->newPaymentStatus;
    }

    public function toArray(): array
    {
        return parent::toArray() + [
            'oldPaymentStatus' => $this->oldPaymentStatus,
            'newPaymentStatus' => $this->newPaymentStatus,
            'oldPaymentStatusKey' => sOrder::paymentStatusKey($this->oldPaymentStatus),
            'newPaymentStatusKey' => sOrder::paymentStatusKey($this->newPaymentStatus),
        ];
    }
}
