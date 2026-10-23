<?php namespace Seiger\sCommerce\Events;

use Seiger\sCommerce\Models\sOrder;

/**
 * `sCommerce.OrderUpdated`: committed changes of order columns.
 *
 * `changes` maps each changed column to `['old' => ..., 'new' => ...]` with model casts
 * applied (JSON columns as arrays). `updated_at` is excluded.
 *
 * @since 1.5.0
 */
final readonly class OrderUpdated extends OrderEvent
{
    /**
     * @param array<string, array{old: mixed, new: mixed}> $changes
     */
    public function __construct(
        string $eventId,
        \DateTimeImmutable $occurredAt,
        string $source,
        int $actorId,
        ?string $correlationId,
        ?string $causationId,
        ?string $integrationActor,
        array $attributes,
        public array $changes,
    ) {
        parent::__construct($eventId, $occurredAt, $source, $actorId, $correlationId, $causationId, $integrationActor, $attributes);
    }

    public static function name(): string
    {
        return 'sCommerce.OrderUpdated';
    }

    /**
     * Capture an updated order with its changes.
     *
     * @param array<string, array{old: mixed, new: mixed}> $changes Result of {@see self::diff()}.
     * @since 1.5.0
     */
    public static function from(sOrder $order, array $changes): self
    {
        return new self(...self::newContext(), attributes: self::snapshot($order), changes: $changes);
    }

    /**
     * Changed columns of an order inside its `updated` model event.
     *
     * Eloquent fires `updated` before syncing the original, so old values are still available.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     * @since 1.5.0
     */
    public static function diff(sOrder $order): array
    {
        $plain = static fn ($value) => $value instanceof \DateTimeInterface ? $order->fromDateTime($value) : $value;
        $changes = [];
        foreach (array_keys($order->getChanges()) as $key) {
            if ($key === $order->getUpdatedAtColumn()) {
                continue;
            }
            $changes[$key] = ['old' => $plain($order->getOriginal($key)), 'new' => $plain($order->getAttribute($key))];
        }

        return $changes;
    }

    public static function dispatchRank(): int
    {
        return 20;
    }

    public function coalesceKey(): ?string
    {
        return 'snapshot';
    }

    /** Keep the first old and the last new value; drop columns that returned to their old value. */
    public function merge(DomainEvent $later): DomainEvent
    {
        if (!$later instanceof self) {
            return $this;
        }
        $changes = $this->changes;
        foreach ($later->changes as $key => $change) {
            $old = array_key_exists($key, $changes) ? $changes[$key]['old'] : $change['old'];
            $changes[$key] = ['old' => $old, 'new' => $change['new']];
            if (self::same($old, $change['new'])) {
                unset($changes[$key]);
            }
        }

        return $this->with(['attributes' => $later->attributes, 'changes' => $changes]);
    }

    public function isEmpty(): bool
    {
        return $this->changes === [];
    }

    public function toArray(): array
    {
        return parent::toArray() + ['changes' => $this->changes];
    }

    /** Numeric values compare by value, as Eloquent does for dirty checks; others strictly. */
    private static function same(mixed $a, mixed $b): bool
    {
        return is_numeric($a) && is_numeric($b) ? $a == $b : $a === $b;
    }
}
