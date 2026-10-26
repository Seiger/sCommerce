<?php namespace Seiger\sCommerce\Events;

/**
 * Request-local context for domain events: who/what caused the change.
 *
 * Scopes nest and inherit unspecified context from the enclosing scope. While a
 * scope is open, committed events are buffered; only the outermost scope flushes
 * them, so one business operation produces one coalesced set of events.
 *
 * @since 1.5.0
 */
final class EventScope
{
    /** Maximum length of opaque correlation and causation identifiers. */
    public const MAX_ID_LENGTH = 64;

    /** @var list<self> Open scopes, outermost first. */
    private static array $stack = [];

    /** @var array<string|int, callable> Work to run when the outermost scope ends. */
    private static array $deferred = [];

    private bool $ended = false;

    /**
     * @param array{source: string, actorId: int, correlationId: ?string, causationId: ?string, integrationActor: ?string} $context
     */
    private function __construct(private readonly array $context)
    {
    }

    /**
     * Open a scope. Call {@see self::end()} in `finally`, or use {@see self::run()}.
     *
     * @param string $source Execution context, e.g. `storefront`, `manager` or a package name.
     * @param int $actorId Manager or web user id; `0` inherits the enclosing scope's actor.
     * @param string|null $correlationId Opaque business-flow id (max 64 chars); inherited when null.
     * @param string|null $causationId Id of the event/command/operation that caused the write (max 64 chars); inherited when null.
     * @param string|null $integrationActor Opaque package name that performs the write; inherited when null.
     * @return self
     * @since 1.5.0
     */
    public static function begin(string $source, int $actorId = 0, ?string $correlationId = null, ?string $causationId = null, ?string $integrationActor = null): self
    {
        $source = trim($source);
        if ($source === '') {
            throw new \InvalidArgumentException('Event scope source must not be empty.');
        }
        foreach (['correlationId' => $correlationId, 'causationId' => $causationId] as $name => $value) {
            if ($value !== null && ($value === '' || strlen($value) > self::MAX_ID_LENGTH)) {
                throw new \InvalidArgumentException("Event scope $name must contain 1-" . self::MAX_ID_LENGTH . ' characters.');
            }
        }

        $parent = self::current();
        $scope = new self([
            'source' => $source,
            'actorId' => $actorId > 0 ? $actorId : $parent['actorId'],
            'correlationId' => $correlationId ?? $parent['correlationId'],
            'causationId' => $causationId ?? $parent['causationId'],
            'integrationActor' => $integrationActor ?? $parent['integrationActor'],
        ]);
        self::$stack[] = $scope;

        return $scope;
    }

    /**
     * Close the scope. Idempotent; closing the outermost scope flushes buffered events.
     *
     * Ending a scope also closes scopes that were left open inside it.
     *
     * @since 1.5.0
     */
    public function end(): void
    {
        if ($this->ended) {
            return;
        }
        $this->ended = true;

        $index = array_search($this, self::$stack, true);
        if ($index === false) {
            return;
        }
        foreach (array_slice(self::$stack, $index + 1) as $inner) {
            $inner->ended = true;
        }

        try {
            if ($index === 0) {
                // Deferred work runs while this scope is still open, so its events join the same flush.
                self::$stack = [$this];
                while (self::$deferred !== []) {
                    $tasks = self::$deferred;
                    self::$deferred = [];
                    foreach ($tasks as $task) {
                        $task();
                    }
                }
            }
        } finally {
            self::$stack = array_slice(self::$stack, 0, $index);
            if (self::$stack === []) {
                DomainEventRecorder::instance()->flush();
            }
        }
    }

    /**
     * Run internal follow-up work when the outermost scope ends, or now when no scope is open.
     *
     * Use it for work that must see the whole business operation, e.g. offline settlement
     * after a status change. The callback must handle its own errors.
     *
     * @param callable $task Work to run.
     * @param string|null $key Deduplication key; a later task with the same key replaces the earlier one.
     * @since 1.5.0
     */
    public static function defer(callable $task, ?string $key = null): void
    {
        if (!self::active()) {
            $task();
            return;
        }

        if ($key === null) {
            self::$deferred[] = $task;
        } else {
            unset(self::$deferred[$key]);
            self::$deferred[$key] = $task;
        }
    }

    /**
     * Run a callback inside a scope that is always closed afterwards.
     *
     * Events committed before an exception are still dispatched; rolled-back writes are not.
     *
     * @param string $source Execution context.
     * @param int $actorId Manager or web user id, or `0`.
     * @param callable $callback Work to run.
     * @param string|null $correlationId Opaque business-flow id.
     * @param string|null $causationId Id of the causing event/command/operation.
     * @param string|null $integrationActor Opaque package name that performs the write.
     * @return mixed The callback result.
     * @since 1.5.0
     */
    public static function run(string $source, int $actorId, callable $callback, ?string $correlationId = null, ?string $causationId = null, ?string $integrationActor = null): mixed
    {
        $scope = self::begin($source, $actorId, $correlationId, $causationId, $integrationActor);
        try {
            return $callback();
        } finally {
            $scope->end();
        }
    }

    /**
     * Context of the innermost open scope, or the `system` defaults.
     *
     * @return array{source: string, actorId: int, correlationId: ?string, causationId: ?string, integrationActor: ?string}
     * @since 1.5.0
     */
    public static function current(): array
    {
        $scope = end(self::$stack);

        return $scope ? $scope->context : [
            'source' => 'system',
            'actorId' => 0,
            'correlationId' => null,
            'causationId' => null,
            'integrationActor' => null,
        ];
    }

    /**
     * Whether any scope is open.
     *
     * @since 1.5.0
     */
    public static function active(): bool
    {
        return self::$stack !== [];
    }
}
