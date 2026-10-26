<?php namespace Seiger\sCommerce\Events;

use Seiger\sCommerce\Checkout\CheckoutContext;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderShipment;
use Seiger\sCommerce\Services\OrderShipments;

/**
 * `sCommerce.ShipmentUpdated`: a shipment was created, or its status, tracking, carrier,
 * service, items or dates changed.
 *
 * `attributes` is the raw committed `s_order_shipments` row captured at record time; the
 * `shipment` listener key is a detached copy built from it. `fullyShipped` and
 * `fullyDelivered` describe the whole order at that time. Never merged away by coalescing.
 *
 * @since 1.5.0
 */
final readonly class ShipmentUpdated extends DomainEvent
{
    /**
     * @param array<string, mixed> $attributes Raw `s_order_shipments` attributes.
     * @param string|null $orderUuid Public UUID of the order, when it has one.
     * @param string $environment Checkout environment of the order: `live` or `test`.
     * @param bool $created Whether the shipment was created by this change.
     * @param string|null $oldStatus Status before the change; null when created.
     * @param bool $fullyShipped Every shipped order line is covered by sent parcels.
     * @param bool $fullyDelivered Every shipped order line is covered by delivered parcels.
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
        public ?string $orderUuid,
        public string $environment,
        public bool $created,
        public ?string $oldStatus,
        public bool $fullyShipped,
        public bool $fullyDelivered,
    ) {
        parent::__construct($eventId, $occurredAt, $source, $actorId, $correlationId, $causationId, $integrationActor);
    }

    public static function name(): string
    {
        return 'sCommerce.ShipmentUpdated';
    }

    /**
     * Capture a saved shipment and the coverage of its order.
     *
     * @param sOrderShipment $shipment Saved shipment.
     * @param bool $created Whether the shipment was just created.
     * @param string|null $oldStatus Previous status; null when created.
     * @since 1.5.0
     */
    public static function from(sOrderShipment $shipment, bool $created, ?string $oldStatus): self
    {
        $order = sOrder::withTrashed()->find($shipment->order_id);
        $uuid = $order?->getAttributes()['uuid'] ?? null;
        $coverage = $order ? OrderShipments::coverage($order) : ['fullyShipped' => false, 'fullyDelivered' => false];

        return new self(...self::newContext(),
            attributes: array_map(static fn ($value) => $value instanceof \DateTimeInterface ? $shipment->fromDateTime($value) : $value, $shipment->getAttributes()),
            orderUuid: is_string($uuid) && $uuid !== '' ? $uuid : null,
            environment: $order ? CheckoutContext::environmentOf($order) : CheckoutContext::LIVE,
            created: $created,
            oldStatus: $oldStatus,
            fullyShipped: $coverage['fullyShipped'],
            fullyDelivered: $coverage['fullyDelivered'],
        );
    }

    /**
     * Shipment row id.
     *
     * @since 1.5.0
     */
    public function shipmentId(): int
    {
        return (int)($this->attributes['id'] ?? 0);
    }

    /**
     * Detached shipment built from the snapshot.
     *
     * @since 1.5.0
     */
    public function shipment(): sOrderShipment
    {
        return (new sOrderShipment())->newFromBuilder($this->attributes);
    }

    public function aggregateKey(): string
    {
        return 'shipment:' . $this->shipmentId();
    }

    /**
     * Listener payload. `source` is the event scope context; the writer stored on the
     * shipment is `shipmentSource`.
     *
     * @return array<string, mixed>
     * @since 1.5.0
     */
    public function toArray(): array
    {
        $shipment = $this->shipment();
        $date = static fn ($value) => $value instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($value)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z') : null;

        return [
            'event' => $this,
            'shipment' => $shipment,
            'shipmentId' => $this->shipmentId(),
            'shipmentUuid' => isset($this->attributes['uuid']) ? (string)$this->attributes['uuid'] : null,
            'orderId' => (int)($this->attributes['order_id'] ?? 0),
            'orderUuid' => $this->orderUuid,
            'sequence' => (int)($this->attributes['sequence'] ?? 0),
            'created' => $this->created,
            'oldStatus' => $this->oldStatus,
            'newStatus' => (string)($this->attributes['status'] ?? ''),
            'carrier' => $shipment->carrier,
            'service' => $shipment->service,
            'trackingNumber' => $shipment->tracking_number,
            'trackingUrl' => $shipment->tracking_url,
            'items' => $shipment->items,
            'shippedAt' => $date($shipment->shipped_at),
            'deliveredAt' => $date($shipment->delivered_at),
            'fullyShipped' => $this->fullyShipped,
            'fullyDelivered' => $this->fullyDelivered,
            'shipmentSource' => (string)($this->attributes['source'] ?? ''),
            'externalRef' => isset($this->attributes['external_ref']) && $this->attributes['external_ref'] !== '' ? (string)$this->attributes['external_ref'] : null,
            'metadata' => is_array($shipment->metadata) ? $shipment->metadata : [],
            'environment' => $this->environment,
        ] + $this->contextArray();
    }
}
