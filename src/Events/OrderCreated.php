<?php namespace Seiger\sCommerce\Events;

use Seiger\sCommerce\Models\sOrder;

/**
 * `sCommerce.OrderCreated`: an order was inserted and committed.
 *
 * Inside an {@see EventScope}, later updates of the same order are merged into this
 * event, so it carries the final snapshot of the operation.
 *
 * @since 1.5.0
 */
final readonly class OrderCreated extends OrderEvent
{
    public static function name(): string
    {
        return 'sCommerce.OrderCreated';
    }

    /**
     * Capture a newly created order.
     *
     * @since 1.5.0
     */
    public static function from(sOrder $order): self
    {
        return new self(...self::newContext(), attributes: self::snapshot($order));
    }

    public static function dispatchRank(): int
    {
        return 10;
    }

    public function coalesceKey(): ?string
    {
        return 'snapshot';
    }

    /** A later `OrderUpdated` only refreshes the snapshot of the creation event. */
    public function merge(DomainEvent $later): DomainEvent
    {
        return $later instanceof OrderEvent ? $this->with(['attributes' => $later->attributes]) : $this;
    }
}
