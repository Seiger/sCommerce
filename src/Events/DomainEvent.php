<?php namespace Seiger\sCommerce\Events;

use Illuminate\Support\Str;

/**
 * Base payload of sCommerce domain events.
 *
 * Events hold immutable scalar/array snapshots captured when the change was recorded.
 * Listeners receive {@see self::toArray()} as the single dispatch argument.
 *
 * @since 1.5.0
 */
abstract readonly class DomainEvent
{
    /**
     * @param string $eventId UUID v4 generated at record time.
     * @param \DateTimeImmutable $occurredAt UTC time of recording.
     * @param string $source Execution context from {@see EventScope}.
     * @param int $actorId Manager or web user id, or `0`.
     * @param string|null $correlationId Opaque business-flow id.
     * @param string|null $causationId Id of the causing event/command/operation.
     * @param string|null $integrationActor Opaque package name that performed the write.
     */
    public function __construct(
        public string $eventId,
        public \DateTimeImmutable $occurredAt,
        public string $source,
        public int $actorId,
        public ?string $correlationId,
        public ?string $causationId,
        public ?string $integrationActor,
    ) {}

    /**
     * Dispatcher event name, e.g. `sCommerce.OrderCreated`.
     *
     * @since 1.5.0
     */
    abstract public static function name(): string;

    /**
     * The single array argument passed to listeners; always contains `'event' => $this`.
     *
     * @since 1.5.0
     */
    abstract public function toArray(): array;

    /**
     * Entity type and id of the aggregate, e.g. `order:15`.
     *
     * @since 1.5.0
     */
    abstract public function aggregateKey(): string;

    /**
     * Dispatch position among buffered events of one aggregate (lower first).
     *
     * @since 1.5.0
     */
    public static function dispatchRank(): int
    {
        return 100;
    }

    /**
     * Events of one aggregate that share this key are merged in recording order.
     * `null` keeps every event of this type.
     *
     * @since 1.5.0
     */
    public function coalesceKey(): ?string
    {
        return null;
    }

    /**
     * Merge a later buffered event that has the same aggregate and coalesce key.
     *
     * @since 1.5.0
     */
    public function merge(DomainEvent $later): DomainEvent
    {
        return $this;
    }

    /**
     * Whether coalescing left nothing to report.
     *
     * @since 1.5.0
     */
    public function isEmpty(): bool
    {
        return false;
    }

    /**
     * Constructor arguments that identify this occurrence and its context.
     *
     * @return array{eventId: string, occurredAt: \DateTimeImmutable, source: string, actorId: int, correlationId: ?string, causationId: ?string, integrationActor: ?string}
     */
    protected static function newContext(): array
    {
        return [
            'eventId' => (string)Str::uuid(),
            'occurredAt' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        ] + EventScope::current();
    }

    /**
     * Context keys shared by every listener payload.
     */
    protected function contextArray(): array
    {
        return [
            'source' => $this->source,
            'actorId' => $this->actorId,
            'correlationId' => $this->correlationId,
            'causationId' => $this->causationId,
            'integrationActor' => $this->integrationActor,
            'eventId' => $this->eventId,
            'occurredAt' => $this->occurredAt->format('Y-m-d\TH:i:s.v\Z'),
        ];
    }

    /**
     * Copy of this event with some constructor arguments replaced.
     */
    protected function with(array $arguments): static
    {
        return new static(...array_replace(get_object_vars($this), $arguments));
    }
}
