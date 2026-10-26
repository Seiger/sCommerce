<?php namespace Seiger\sCommerce\Services;

use Illuminate\Support\Facades\DB;
use Seiger\sCommerce\Models\sProduct;

/**
 * Public write API for product stock, e.g. for an ERP that is the stock source.
 *
 * Writes go through the `sProduct` model, so they record `sCommerce.ProductStockChanged`
 * after commit. Nothing in core caches stock values; the product listing cache holds URLs only.
 *
 * @since 1.5.0
 */
final class StockWriter
{
    /** Columns read and written; legacy columns stay untouched by stock writes. */
    private const COLUMNS = ['id', 'sku', 'inventory', 'availability', 'updated_at'];

    /**
     * Set the inventory quantity and, when given, the availability of a product.
     *
     * An identical write changes nothing and records no event.
     *
     * @param sProduct|int $product Product or its id; a GROUP variant is its own product row.
     * @param int $quantity New inventory quantity.
     * @param int $modificationId Must be 0: modification rows have no identity of their own. Pass the variant product instead.
     * @param string $source Writer reported as `stockSource`, e.g. `erp`.
     * @param int|null $availability `0` not available, `1` in stock, `2` on order; null keeps the current value.
     * @return bool Whether anything changed.
     * @throws \InvalidArgumentException When the product is unknown or an argument is invalid.
     * @since 1.5.0
     */
    public function set(sProduct|int $product, int $quantity, int $modificationId = 0, string $source = 'api', ?int $availability = null): bool
    {
        if ($modificationId !== 0) {
            throw new \InvalidArgumentException('Stock of a modification is written on its own product row; pass the variant product id.');
        }
        if ($availability !== null && !in_array($availability, [sProduct::AVAILABILITY_NOT_AVAILABLE, sProduct::AVAILABILITY_IN_STOCK, sProduct::AVAILABILITY_ON_ORDER], true)) {
            throw new \InvalidArgumentException("Invalid availability {$availability}.");
        }
        $productId = $product instanceof sProduct ? (int)$product->getKey() : $product;
        $source = trim($source) !== '' ? trim($source) : 'api';

        return DB::transaction(function () use ($productId, $quantity, $availability, $source): bool {
            $row = self::lock([$productId])[$productId] ?? throw new \InvalidArgumentException("Product {$productId} not found.");

            return self::write($row, $quantity, $availability, $source);
        });
    }

    /**
     * Lock product rows in id order, so concurrent multi-product writes cannot deadlock.
     * Call inside a transaction.
     *
     * @param list<int> $productIds
     * @return array<int, sProduct> Locked rows keyed by id; unknown ids are missing.
     * @internal
     */
    public static function lock(array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        sort($productIds);
        if ($productIds === []) {
            return [];
        }

        return sProduct::query()->withoutGlobalScopes()->select(self::COLUMNS)->whereIn('id', $productIds)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id')->all();
    }

    /**
     * Save new stock values of a locked row.
     *
     * @internal
     */
    public static function write(sProduct $row, int $quantity, ?int $availability, string $source): bool
    {
        $row->inventory = $quantity;
        if ($availability !== null) {
            $row->availability = $availability;
        }
        if (!$row->isDirty(['inventory', 'availability'])) {
            return false;
        }
        $row->stockSource = $source;
        $row->save();

        return true;
    }
}
