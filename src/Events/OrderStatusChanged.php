<?php namespace Seiger\sCommerce\Events;

use Seiger\sCommerce\Models\sOrder;

/**
 * `sCommerce.OrderStatusChanged`: the order status changed.
 *
 * @since 1.5.0
 */
final readonly class OrderStatusChanged extends OrderEvent
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
        public int $oldStatus,
        public int $newStatus,
    ) {
        parent::__construct($eventId, $occurredAt, $source, $actorId, $correlationId, $causationId, $integrationActor, $attributes);
    }

    public static function name(): string
    {
        return 'sCommerce.OrderStatusChanged';
    }

    /**
     * Capture a status transition.
     *
     * @since 1.5.0
     */
    public static function from(sOrder $order, int $oldStatus, int $newStatus): self
    {
        return new self(...self::newContext(), attributes: self::snapshot($order), oldStatus: $oldStatus, newStatus: $newStatus);
    }

    public static function dispatchRank(): int
    {
        return 30;
    }

    public function coalesceKey(): ?string
    {
        return 'status';
    }

    /** First old status to last new status. */
    public function merge(DomainEvent $later): DomainEvent
    {
        return $later instanceof self ? $this->with(['attributes' => $later->attributes, 'newStatus' => $later->newStatus]) : $this;
    }

    public function isEmpty(): bool
    {
        return $this->oldStatus === $this->newStatus;
    }

    public function toArray(): array
    {
        return parent::toArray() + [
            'oldStatus' => $this->oldStatus,
            'newStatus' => $this->newStatus,
            'oldStatusKey' => sOrder::orderStatusKey($this->oldStatus),
            'newStatusKey' => sOrder::orderStatusKey($this->newStatus),
        ];
    }
}
