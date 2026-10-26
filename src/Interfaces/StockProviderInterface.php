<?php namespace Seiger\sCommerce\Interfaces;

use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sProduct;

/**
 * External stock source, e.g. an ERP or a warehouse system.
 *
 * Bind one implementation from a package service provider:
 * `app()->bind(StockProviderInterface::class, MyProvider::class)`. At most one provider is used.
 *
 * `commit()` and `release()` may call remote systems after the order has been committed, and a
 * database rollback cannot undo them, so both MUST be idempotent per order (for example keyed by
 * the order id or UUID): sCommerce retries them until they report success.
 *
 * @since 1.5.0
 */
interface StockProviderInterface
{
    /**
     * Available quantity for storefront decisions.
     *
     * @param sProduct $product Product; a GROUP variant is its own product row.
     * @param int $modificationId Reserved; always 0 in core, a cart option id is not a modification.
     * @param array<string, mixed> $context E.g. `optionId` and the requested `quantity`.
     * @return int|null Quantity, or null when the product is not managed here (sCommerce inventory is used).
     * @since 1.5.0
     */
    public function available(sProduct $product, int $modificationId = 0, array $context = []): ?int;

    /**
     * Commit the stock of an order, once.
     *
     * @return bool True when handled; sCommerce then skips its local decrement.
     * @since 1.5.0
     */
    public function commit(sOrder $order): bool;

    /**
     * Release the stock of an order this provider committed, once it was cancelled or deleted.
     * Orders committed by a local decrement are restored locally without asking the provider.
     *
     * @return bool True when handled; otherwise sCommerce keeps the order committed and retries later.
     * @since 1.5.0
     */
    public function release(sOrder $order): bool;
}
