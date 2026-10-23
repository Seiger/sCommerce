<?php namespace Seiger\sCommerce\Events;

use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Payment\PaymentFlow;

/**
 * Base payload of `sCommerce.Payment*` ledger events.
 *
 * `attributes` is the raw committed `s_order_payments` row captured at record time. The
 * `payment` listener key is a detached copy built from it: use it for reading only.
 *
 * @since 1.5.0
 */
abstract readonly class PaymentEvent extends DomainEvent
{
    /**
     * @param array<string, mixed> $attributes Raw `s_order_payments` attributes.
     * @param string|null $orderUuid Public UUID of the order, when it has one.
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
    ) {
        parent::__construct($eventId, $occurredAt, $source, $actorId, $correlationId, $causationId, $integrationActor);
    }

    /**
     * Ledger row id.
     *
     * @since 1.5.0
     */
    public function paymentId(): int
    {
        return (int)($this->attributes['id'] ?? 0);
    }

    /**
     * Detached ledger row built from the snapshot.
     *
     * @since 1.5.0
     */
    public function payment(): sOrderPayment
    {
        return (new sOrderPayment())->newFromBuilder($this->attributes);
    }

    public function aggregateKey(): string
    {
        return 'payment:' . $this->paymentId();
    }

    /**
     * Listener payload: the ledger row summary plus event context.
     *
     * @return array<string, mixed>
     * @since 1.5.0
     */
    public function toArray(): array
    {
        $attributes = $this->attributes;
        $metadata = PaymentFlow::normalizeMetadata($attributes['metadata'] ?? null);
        $amountMinor = PaymentFlow::toMinor($attributes['amount'] ?? 0);

        return [
            'event' => $this,
            'payment' => $this->payment(),
            'paymentId' => $this->paymentId(),
            'orderId' => (int)($attributes['order_id'] ?? 0),
            'orderUuid' => $this->orderUuid,
            'sequence' => (int)($attributes['sequence'] ?? 0),
            'kind' => (string)($attributes['kind'] ?? ''),
            'status' => (string)($attributes['status'] ?? ''),
            'amount' => round($amountMinor / 100, 2),
            'amountMinor' => $amountMinor,
            'currency' => strtoupper((string)($attributes['currency'] ?? '')),
            'provider' => (string)($attributes['provider'] ?? ''),
            'providerRef' => isset($attributes['provider_ref']) && $attributes['provider_ref'] !== '' ? (string)$attributes['provider_ref'] : null,
            'parentProviderRef' => $metadata[PaymentFlow::CONTEXT_KEY]['parent_provider_ref'] ?? null,
            'metadata' => $metadata,
        ] + $this->contextArray();
    }

    /**
     * Constructor arguments that capture a saved ledger row.
     *
     * @return array{attributes: array<string, mixed>, orderUuid: ?string}
     */
    protected static function capture(sOrderPayment $payment): array
    {
        $order = sOrder::withTrashed()->find($payment->order_id);
        $uuid = $order?->getAttributes()['uuid'] ?? null;

        return [
            'attributes' => array_map(static fn ($value) => $value instanceof \DateTimeInterface ? $payment->fromDateTime($value) : $value, $payment->getAttributes()),
            'orderUuid' => is_string($uuid) && $uuid !== '' ? $uuid : null,
        ];
    }
}
