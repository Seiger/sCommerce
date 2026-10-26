<?php namespace Seiger\sCommerce\Shipping;

/**
 * Shipment state to write through {@see \Seiger\sCommerce\Services\OrderShipments::upsert()}.
 *
 * When the shipment already exists, `null` values keep the stored carrier, service, tracking,
 * items and dates; `metadata` is merged into the stored metadata.
 *
 * @since 1.5.0
 */
final readonly class ShipmentData
{
    /**
     * @param string $status `pending`, `ready`, `shipped`, `in_transit`, `delivered`, `failed`, `returned` or `canceled`.
     * @param string|null $carrier Lowercase carrier code, e.g. `packeta`, `dpd`, `gls`, `inpost`.
     * @param string|null $service Carrier service, e.g. `pickup_point` or `home`.
     * @param string|null $trackingNumber Carrier tracking number.
     * @param string|null $trackingUrl Public `http(s)` tracking page.
     * @param list<array{line?: int, line_key?: string, product_id?: int, sku?: string, quantity: int}>|null $items
     *        Shipped quantities by order line index (`line`, the index in `s_orders.products`) or `line_key`;
     *        `null` ships the whole order.
     * @param string $source Writer namespace: `manual`, `erp`, `carrier`, `delivery_method`, `api` or a package name.
     * @param string|null $externalRef Id in the source system; makes the write idempotent by `(source, externalRef)`.
     * @param array<string, mixed> $metadata Caller data.
     * @param \DateTimeImmutable|null $shippedAt When the parcel was handed over; now when status becomes `shipped`.
     * @param \DateTimeImmutable|null $deliveredAt When the parcel was delivered; now when status becomes `delivered`.
     */
    public function __construct(
        public string $status = 'pending',
        public ?string $carrier = null,
        public ?string $service = null,
        public ?string $trackingNumber = null,
        public ?string $trackingUrl = null,
        public ?array $items = null,
        public string $source = 'manual',
        public ?string $externalRef = null,
        public array $metadata = [],
        public ?\DateTimeImmutable $shippedAt = null,
        public ?\DateTimeImmutable $deliveredAt = null,
    ) {}
}
