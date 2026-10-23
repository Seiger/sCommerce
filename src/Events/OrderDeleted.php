<?php namespace Seiger\sCommerce\Events;

use Seiger\sCommerce\Models\sOrder;

/**
 * `sCommerce.OrderDeleted`: the order was soft-deleted (`forced = false`) or removed.
 *
 * Deletion is neither a refund nor a cancellation; consumers apply their own policy.
 * Never merged away by coalescing.
 *
 * @since 1.5.0
 */
final readonly class OrderDeleted extends OrderEvent
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
        public bool $forced,
    ) {
        parent::__construct($eventId, $occurredAt, $source, $actorId, $correlationId, $causationId, $integrationActor, $attributes);
    }

    public static function name(): string
    {
        return 'sCommerce.OrderDeleted';
    }

    /**
     * Capture a deletion.
     *
     * @since 1.5.0
     */
    public static function from(sOrder $order, bool $forced): self
    {
        return new self(...self::newContext(), attributes: self::snapshot($order), forced: $forced);
    }

    public static function dispatchRank(): int
    {
        return 60;
    }

    public function toArray(): array
    {
        return parent::toArray() + ['forced' => $this->forced];
    }
}
