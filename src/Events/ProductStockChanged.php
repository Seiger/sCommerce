<?php namespace Seiger\sCommerce\Events;

/**
 * `sCommerce.ProductStockChanged`: the inventory quantity or the availability of a product changed.
 *
 * Recorded by `sProduct` model hooks, so the manager form, the CSV import, {@see \Seiger\sCommerce\Services\StockWriter}
 * and order stock commitment are all covered. Changes of one product inside one event scope are
 * merged from the first old to the last new values; a merge that changes nothing is dropped.
 *
 * @since 1.5.0
 */
final readonly class ProductStockChanged extends DomainEvent
{
    /**
     * @param int $productId Product row id; a GROUP variant is its own product row.
     * @param string $sku Product SKU.
     * @param int $oldQuantity Inventory before the change.
     * @param int $newQuantity Inventory after the change.
     * @param int $oldAvailability Availability code before the change.
     * @param int $newAvailability Availability code after the change.
     * @param string $stockSource Writer of the change: `manager`, `import`, `order`, `api`, or a package name.
     */
    public function __construct(
        string $eventId,
        \DateTimeImmutable $occurredAt,
        string $source,
        int $actorId,
        ?string $correlationId,
        ?string $causationId,
        ?string $integrationActor,
        public int $productId,
        public string $sku,
        public int $oldQuantity,
        public int $newQuantity,
        public int $oldAvailability,
        public int $newAvailability,
        public string $stockSource,
    ) {
        parent::__construct($eventId, $occurredAt, $source, $actorId, $correlationId, $causationId, $integrationActor);
    }

    public static function name(): string
    {
        return 'sCommerce.ProductStockChanged';
    }

    /**
     * Capture a stock change of a product row.
     *
     * @since 1.5.0
     */
    public static function from(int $productId, string $sku, int $oldQuantity, int $newQuantity, int $oldAvailability, int $newAvailability, string $stockSource): self
    {
        return new self(...self::newContext(), productId: $productId, sku: $sku, oldQuantity: $oldQuantity, newQuantity: $newQuantity,
            oldAvailability: $oldAvailability, newAvailability: $newAvailability, stockSource: $stockSource);
    }

    public function aggregateKey(): string
    {
        return 'product:' . $this->productId;
    }

    public function coalesceKey(): ?string
    {
        return 'stock';
    }

    /** First old values to last new values. */
    public function merge(DomainEvent $later): DomainEvent
    {
        return $later instanceof self ? $this->with(['sku' => $later->sku, 'newQuantity' => $later->newQuantity,
            'newAvailability' => $later->newAvailability, 'stockSource' => $later->stockSource]) : $this;
    }

    public function isEmpty(): bool
    {
        return $this->oldQuantity === $this->newQuantity && $this->oldAvailability === $this->newAvailability;
    }

    /**
     * Listener payload. `source` is the event scope context; the writer is `stockSource`.
     *
     * @return array<string, mixed>
     * @since 1.5.0
     */
    public function toArray(): array
    {
        return [
            'event' => $this,
            'productId' => $this->productId,
            'modificationId' => 0,
            'sku' => $this->sku,
            'oldQuantity' => $this->oldQuantity,
            'newQuantity' => $this->newQuantity,
            'oldAvailability' => $this->oldAvailability,
            'newAvailability' => $this->newAvailability,
            'stockSource' => $this->stockSource,
        ] + $this->contextArray();
    }
}
