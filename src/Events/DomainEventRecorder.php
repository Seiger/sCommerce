<?php namespace Seiger\sCommerce\Events;

use Illuminate\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;

/**
 * Records domain events after commit and dispatches them to Laravel listeners.
 *
 * Events of rolled-back transactions are discarded. Inside an {@see EventScope} events
 * are buffered and coalesced per aggregate until the outermost scope ends. Dispatch is
 * best effort: it is not a transactional outbox and makes no zero-loss guarantee.
 *
 * @since 1.5.0
 */
final class DomainEventRecorder
{
    /** @var array<int, DomainEvent> Committed events waiting for the outermost scope to end, keyed by recording sequence. */
    private array $buffer = [];

    /** Recording order; after-commit callbacks of nested transactions run before earlier outer ones. */
    private int $sequence = 0;

    /**
     * Shared recorder from the container; registers the singleton on first use.
     *
     * @since 1.5.0
     */
    public static function instance(): self
    {
        $container = Container::getInstance();
        if (!$container->bound(self::class)) {
            $container->singleton(self::class);
        }

        return $container->make(self::class);
    }

    /**
     * Record an event once the current transaction commits; drop it on rollback.
     *
     * Without a transactions manager (minimal bootstraps only) the event is accepted immediately.
     *
     * @param DomainEvent $event Event with a snapshot captured now.
     * @param ConnectionInterface|null $connection Connection that wrote the change; the default one when null.
     * @since 1.5.0
     */
    public function record(DomainEvent $event, ?ConnectionInterface $connection = null): void
    {
        $connection ??= DB::connection();
        $sequence = ++$this->sequence;
        $accepted = false;
        try {
            $connection->afterCommit(function () use ($event, $sequence, &$accepted): void {
                $accepted = true;
                $this->accept($event, $sequence);
            });
        } catch (\RuntimeException $exception) {
            if ($accepted) {
                throw $exception;
            }
            $this->accept($event, $sequence);
        }
    }

    /**
     * Coalesce and dispatch buffered events; called by the outermost {@see EventScope::end()}.
     *
     * Per aggregate, events are dispatched by {@see DomainEvent::dispatchRank()}; aggregates
     * keep the order in which they were first recorded.
     *
     * @since 1.5.0
     */
    public function flush(): void
    {
        $buffer = $this->buffer;
        $this->buffer = [];
        ksort($buffer);

        $aggregates = [];
        foreach ($buffer as $event) {
            $events = &$aggregates[$event->aggregateKey()];
            $events ??= [];
            $key = $event->coalesceKey();
            if ($key === null) {
                $events[] = $event;
            } else {
                $events[$key] = isset($events[$key]) ? $events[$key]->merge($event) : $event;
            }
            unset($events);
        }

        foreach ($aggregates as $events) {
            $events = array_values(array_filter($events, static fn (DomainEvent $event) => !$event->isEmpty()));
            usort($events, static fn (DomainEvent $a, DomainEvent $b) => $a::dispatchRank() <=> $b::dispatchRank());
            foreach ($events as $event) {
                $this->dispatch($event);
            }
        }
    }

    /**
     * Buffer inside a scope, otherwise dispatch now.
     */
    private function accept(DomainEvent $event, int $sequence): void
    {
        if (EventScope::active()) {
            $this->buffer[$sequence] = $event;
            return;
        }

        $this->dispatch($event);
    }

    /**
     * A failing listener is logged and never turns a committed change into an error.
     */
    private function dispatch(DomainEvent $event): void
    {
        $app = Facade::getFacadeApplication();
        if (!$app || !isset($app['events'])) {
            return;
        }

        try {
            Event::dispatch($event::name(), [$event->toArray()]);
        } catch (\Throwable $exception) {
            try {
                Log::channel('scommerce')->error('sCommerce event listener failed', [
                    'event' => $event::name(),
                    'id' => $event->aggregateKey(),
                    'exception' => $exception,
                ]);
            } catch (\Throwable) {
                // Logging must not break the committed operation either.
            }
        }
    }
}
