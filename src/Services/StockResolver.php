<?php namespace Seiger\sCommerce\Services;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Interfaces\StockProviderInterface;
use Seiger\sCommerce\Models\sProduct;

/**
 * Available quantity of a product for storefront decisions.
 *
 * Resolution order: the bound {@see StockProviderInterface}, then the first integer returned by a
 * `sCommerce.ResolveProductStock` listener, then the local `s_products.inventory`.
 *
 * @since 1.5.0
 */
final class StockResolver
{
    /**
     * @param object $product Product, normally an {@see sProduct}; a GROUP variant is its own product row.
     *                        The provider is asked only for {@see sProduct} instances.
     * @param int $modificationId Reserved; 0 in core, a cart option id is not a modification.
     * @param array<string, mixed> $context E.g. `optionId` and the requested `quantity`.
     * @since 1.5.0
     */
    public function available(object $product, int $modificationId = 0, array $context = []): int
    {
        $provider = $product instanceof sProduct ? self::provider() : null;
        if ($provider && ($quantity = $provider->available($product, $modificationId, $context)) !== null) {
            return $quantity;
        }

        $local = (int)$product->inventory;
        foreach (Event::dispatch('sCommerce.ResolveProductStock', [[
            'product' => $product,
            'modificationId' => $modificationId,
            'quantity' => $local,
            'context' => $context,
        ]]) as $override) {
            if (is_int($override)) {
                return $override;
            }
        }

        return $local;
    }

    /**
     * The bound stock provider, if any.
     *
     * @since 1.5.0
     */
    public static function provider(): ?StockProviderInterface
    {
        $container = Container::getInstance();

        return $container->bound(StockProviderInterface::class) ? $container->make(StockProviderInterface::class) : null;
    }
}
