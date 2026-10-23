<?php namespace Seiger\sCommerce\Events;

use Seiger\sCommerce\Models\sOrder;

/**
 * `sCommerce.OrderCancelled`: the order status became `ORDER_STATUS_CANCELED`.
 *
 * Never merged away by coalescing.
 *
 * @since 1.5.0
 */
final readonly class OrderCancelled extends OrderEvent
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
        public int $previousStatus,
    ) {
        parent::__construct($eventId, $occurredAt, $source, $actorId, $correlationId, $causationId, $integrationActor, $attributes);
    }

    public static function name(): string
    {
        return 'sCommerce.OrderCancelled';
    }

    /**
     * Capture a cancellation.
     *
     * @since 1.5.0
     */
    public static function from(sOrder $order, int $previousStatus): self
    {
        return new self(...self::newContext(), attributes: self::snapshot($order), previousStatus: $previousStatus);
    }

    public static function dispatchRank(): int
    {
        return 50;
    }

    public function toArray(): array
    {
        return parent::toArray() + ['previousStatus' => $this->previousStatus];
    }
}
