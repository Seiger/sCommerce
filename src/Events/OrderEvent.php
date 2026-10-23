<?php namespace Seiger\sCommerce\Events;

use Seiger\sCommerce\Models\sOrder;

/**
 * Base payload of `sCommerce.Order*` events.
 *
 * `attributes` is the raw committed `s_orders` row captured at record time. The `order`
 * listener key is a detached copy built from it: use it for reading, never to write the
 * live order or as a substitute for re-reading current state.
 *
 * @since 1.5.0
 */
abstract readonly class OrderEvent extends DomainEvent
{
    /**
     * @param array<string, mixed> $attributes Raw `s_orders` attributes (scalars and JSON strings).
     */
    public function __construct(
        string $eventId,
        \DateTimeImmutable $occurredAt,
        string $source,
        int $actorId,
        ?string $correlationId,
        ?string $causationId,
        ?string $integrationActor,
        public array $attributes,
    ) {
        parent::__construct($eventId, $occurredAt, $source, $actorId, $correlationId, $causationId, $integrationActor);
    }

    /**
     * Local order id.
     *
     * @since 1.5.0
     */
    public function orderId(): int
    {
        return (int)($this->attributes['id'] ?? 0);
    }

    /**
     * Detached order model built from the snapshot.
     *
     * @since 1.5.0
     */
    public function order(): sOrder
    {
        return (new sOrder())->newFromBuilder($this->attributes);
    }

    public function aggregateKey(): string
    {
        return 'order:' . $this->orderId();
    }

    /**
     * Listener payload: the snapshot summary plus event context.
     *
     * @return array<string, mixed>
     * @since 1.5.0
     */
    public function toArray(): array
    {
        $attributes = $this->attributes;

        return [
            'event' => $this,
            'order' => $this->order(),
            'orderId' => $this->orderId(),
            'orderUuid' => isset($attributes['uuid']) && $attributes['uuid'] !== '' ? (string)$attributes['uuid'] : null,
            'reference' => isset($attributes['reference']) && $attributes['reference'] !== '' ? (string)$attributes['reference'] : null,
            'status' => (int)($attributes['status'] ?? sOrder::ORDER_STATUS_NEW),
            'paymentStatus' => (int)($attributes['payment_status'] ?? sOrder::PAYMENT_STATUS_PENDING),
            'cost' => (float)($attributes['cost'] ?? 0),
            'currency' => (string)($attributes['currency'] ?? ''),
            'isQuick' => (bool)($attributes['is_quick'] ?? false),
            'siteKey' => isset($attributes['domain']) ? (string)$attributes['domain'] : null,
        ] + $this->contextArray();
    }

    /**
     * Raw attributes of a saved order with date objects converted to storage strings.
     *
     * @return array<string, mixed>
     */
    protected static function snapshot(sOrder $order): array
    {
        return array_map(static fn ($value) => $value instanceof \DateTimeInterface ? $order->fromDateTime($value) : $value, $order->getAttributes());
    }
}
