<?php namespace Seiger\sCommerce\Checkout;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Models\sOrder;

/**
 * Trusted transaction context of a checkout.
 *
 * Every checkout is live unless a registered server-side `sCommerce.ResolveCheckoutContext`
 * listener selects `test` for an entitled, authenticated user. Customer input never selects it.
 * The context is resolved before the first side effect, stored once in `s_orders.meta.integration`
 * and read back by pay links, payment methods, callbacks and jobs; it cannot change afterwards.
 *
 * @since 1.5.0
 */
final class CheckoutContext
{
    public const EVENT = 'sCommerce.ResolveCheckoutContext';
    public const LIVE = 'live';
    public const TEST = 'test';
    /** Key of the context in `s_orders.meta`. */
    public const META_KEY = 'integration';

    /** Request keys a client could use to claim an environment. */
    private const CLIENT_FLAGS = ['checkout_context', 'checkoutContext', 'environment', 'test', 'is_test', 'isTest',
        'test_mode', 'testMode', 'sandbox', 'livemode', 'meta', 'integration'];

    /** Maximum JSON size of an opaque resolver reference, in bytes. */
    private const MAX_REFERENCE_BYTES = 2048;

    /**
     * Resolve the context of a new checkout or payment attempt.
     *
     * @param array<string, mixed> $data Checkout data; client environment flags are removed before listeners see it.
     * @param string $source Event scope source of the checkout path, e.g. `storefront`.
     * @param int $actorId Authenticated web or manager user id; 0 for guests.
     * @return array{environment: string, actorId: int, source: string, reference?: string|array, testRecipients?: list<string>}
     * @throws CheckoutContextException When a listener refuses the checkout or returns an invalid context.
     * @since 1.5.0
     */
    public static function resolve(array $data, string $source, int $actorId): array
    {
        $context = ['environment' => self::LIVE, 'actorId' => $actorId];
        Event::dispatch(self::EVENT, [['data' => self::withoutClientFlags($data), 'source' => $source, 'context' => &$context]]);

        return self::validate($context, $source, $actorId);
    }

    /**
     * Remove keys a client could use to claim an environment or inject a context.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @since 1.5.0
     */
    public static function withoutClientFlags(array $data): array
    {
        return array_diff_key($data, array_flip(self::CLIENT_FLAGS));
    }

    /**
     * Persisted context of an order. Orders created before 1.5.0 are live.
     *
     * @return array{environment: string, actorId?: int, source?: string, reference?: string|array, testRecipients?: list<string>}
     * @since 1.5.0
     */
    public static function of(sOrder $order): array
    {
        $context = self::fromMeta($order->meta ?? null);
        if ($context === null) {
            return ['environment' => self::LIVE];
        }
        $context['environment'] = self::normalizeStored($context['environment'] ?? null);

        return $context;
    }

    /**
     * Environment of an order: `live` or `test`. A stored value that is neither counts as `test`,
     * so a damaged context never causes live effects.
     *
     * @param sOrder|array<string, mixed>|string|null $order Order, or its raw `meta` value.
     * @since 1.5.0
     */
    public static function environmentOf(sOrder|array|string|null $order): string
    {
        $context = self::fromMeta($order instanceof sOrder ? ($order->meta ?? null) : $order);

        return $context === null ? self::LIVE : self::normalizeStored($context['environment'] ?? null);
    }

    /**
     * Whether live business effects (stock, fulfilment, offline settlement, normal notifications) may run.
     *
     * @since 1.5.0
     */
    public static function isLive(sOrder $order): bool
    {
        return self::environmentOf($order) === self::LIVE;
    }

    /**
     * Store the context in `meta.integration` of an unsaved order, keeping other `meta` keys.
     *
     * @since 1.5.0
     */
    public static function apply(sOrder $order, array $context): void
    {
        $meta = is_array($order->meta) ? $order->meta : [];
        $meta[self::META_KEY] = $context;
        $order->meta = $meta;
    }

    /**
     * Notification recipients for an order created in this context: unchanged for live orders,
     * only the designated test recipients (possibly none) for test orders.
     *
     * @param array<string, mixed> $context Resolved or persisted context.
     * @param string|array<int, string> $liveRecipients Normal recipients.
     * @return string|array<int, string>
     * @since 1.5.0
     */
    public static function recipients(array $context, string|array $liveRecipients): string|array
    {
        if (self::normalizeStored($context['environment'] ?? null) === self::LIVE) {
            return $liveRecipients;
        }

        return array_values(array_filter((array)($context['testRecipients'] ?? []), static fn ($email) => is_string($email) && $email !== ''));
    }

    /**
     * Decode `meta.integration`.
     *
     * @return array<string, mixed>|null
     */
    private static function fromMeta(array|string|null $meta): ?array
    {
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }
        $context = is_array($meta) ? ($meta[self::META_KEY] ?? null) : null;

        return is_array($context) ? $context : null;
    }

    private static function normalizeStored(mixed $environment): string
    {
        return $environment === self::LIVE ? self::LIVE : self::TEST;
    }

    /**
     * Keep the supported keys; actor and source always come from the checkout itself.
     *
     * @throws CheckoutContextException
     */
    private static function validate(mixed $context, string $source, int $actorId): array
    {
        if (!is_array($context)) {
            self::refuse('context is not an array', $source, $actorId);
        }
        if (is_string($context['refusal'] ?? null) && trim($context['refusal']) !== '') {
            Log::channel('scommerce')->warning('sCommerce checkout refused by its context resolver', ['source' => $source, 'actor_id' => $actorId, 'reason' => $context['refusal']]);
            throw new CheckoutContextException(trim($context['refusal']));
        }
        $environment = $context['environment'] ?? null;
        if (!in_array($environment, [self::LIVE, self::TEST], true)) {
            self::refuse('unsupported environment', $source, $actorId);
        }
        if ($environment === self::TEST && $actorId < 1) {
            self::refuse('a test context needs an authenticated user', $source, $actorId);
        }

        $valid = ['environment' => $environment, 'actorId' => $actorId, 'source' => $source];
        $reference = $context['reference'] ?? null;
        if ($reference !== null) {
            $json = json_encode($reference);
            if (!(is_string($reference) || is_array($reference)) || $json === false || strlen($json) > self::MAX_REFERENCE_BYTES) {
                self::refuse('invalid reference', $source, $actorId);
            }
            $valid['reference'] = $reference;
        }
        if ($environment === self::TEST) {
            $valid['testRecipients'] = array_values(array_filter((array)($context['testRecipients'] ?? []),
                static fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)));
        }

        return $valid;
    }

    /**
     * @throws CheckoutContextException
     */
    private static function refuse(string $reason, string $source, int $actorId): never
    {
        Log::channel('scommerce')->error('sCommerce rejected an invalid checkout context', ['source' => $source, 'actor_id' => $actorId, 'reason' => $reason]);

        throw new CheckoutContextException(__('sCommerce::global.checkout_context_unavailable'));
    }
}
