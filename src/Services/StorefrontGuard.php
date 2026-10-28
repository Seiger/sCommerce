<?php namespace Seiger\sCommerce\Services;

use Illuminate\Cache\RateLimiter;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Facades\sCommerce;

/**
 * Checks of anonymous storefront requests: which products may be bought and how many orders
 * one client may place.
 *
 * @since 1.5.0
 */
final class StorefrontGuard
{
    /** Window of `orders.rate_limit`, in seconds. */
    public const ORDER_WINDOW = 600;

    /**
     * Whether a product can be put in the cart or ordered: it exists and is published.
     * Objects without a `published` attribute count as published.
     *
     * @since 1.5.0
     */
    public static function purchasable(?object $product): bool
    {
        if (!$product || (int)($product->id ?? 0) < 1) {
            return false;
        }

        return !isset($product->published) || (int)$product->published === 1;
    }

    /**
     * Whether the client may place another order: fewer than `orders.rate_limit` orders
     * from its IP address in {@see ORDER_WINDOW}. 0 disables the limit. Without a cache store
     * orders are allowed, so a cache fault never stops sales.
     *
     * @since 1.5.0
     */
    public static function orderAllowed(): bool
    {
        [$limiter, $key, $limit] = self::orderLimiter();

        return $limiter === null || !$limiter->tooManyAttempts($key, $limit);
    }

    /**
     * Count a placed order against the client's limit.
     *
     * @since 1.5.0
     */
    public static function orderPlaced(): void
    {
        [$limiter, $key] = self::orderLimiter();
        $limiter?->hit($key, self::ORDER_WINDOW);
    }

    /**
     * @return array{0: ?RateLimiter, 1: string, 2: int}
     */
    private static function orderLimiter(): array
    {
        $limit = (int)sCommerce::config('orders.rate_limit', 10);
        $key = 'scommerce-orders:' . sha1((string)(request()->ip() ?? ''));
        if ($limit < 1) {
            return [null, $key, $limit];
        }
        try {
            $cache = Container::getInstance()->make('cache')->store();
        } catch (\Throwable $exception) {
            Log::channel('scommerce')->warning('sCommerce order rate limit is unavailable without a cache store', ['error' => $exception->getMessage()]);
            return [null, $key, $limit];
        }

        return [new RateLimiter($cache), $key, $limit];
    }
}
