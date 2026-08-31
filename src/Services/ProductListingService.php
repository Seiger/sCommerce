<?php namespace Seiger\sCommerce\Services;

use EvolutionCMS\Facades\UrlProcessor;
use Illuminate\Support\Facades\DB;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sProduct;

/**
 * Build storefront card arrays without serializing complete product models.
 *
 * Existing sProduct APIs remain unchanged; list consumers opt into this batched
 * read path when they need predictable query cost.
 *
 * @since 1.4.0
 */
final class ProductListingService
{
    private array $baseUrlCache = [];

    /**
     * Create the listing service with the shared storefront price resolver.
     *
     * @since 1.4.0
     */
    public function __construct(private readonly sPriceResolver $priceResolver)
    {
    }

    /**
     * Build card data for an ordered set of product identifiers.
     *
     * @param array<int, int|string> $productIds Ordered product identifiers
     * @return array<int, array<string, mixed>> Blade-compatible product cards
     * @since 1.4.0
     */
    public function cards(array $productIds, ?string $locale = null, ?string $siteKey = null): array
    {
        $productIds = array_values(array_unique(array_filter(
            array_map('intval', $productIds),
            static fn (int $id): bool => $id > 0,
        )));
        if ($productIds === []) {
            return [];
        }

        $locale = trim((string)$locale) ?: (string)evo()->getLocale();
        $siteKey = trim((string)$siteKey) ?: (string)evo()->getConfig('site_key', 'default');
        $positions = array_flip($productIds);
        $documentPaths = array_flip(UrlProcessor::getFacadeRoot()->documentListing ?? []);
        $catalogRoot = (int)sCommerce::config(
            'basic.catalog_root' . $siteKey,
            sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1)),
        );

        $translations = DB::table('s_product_translates')
            ->whereIn('product', $productIds)
            ->whereIn('lang', [$locale, 'base'])
            ->orderByRaw("CASE lang WHEN ? THEN 0 WHEN 'base' THEN 1 ELSE 2 END", [$locale])
            ->get(['product', 'pagetitle'])
            ->unique('product')
            ->keyBy('product');
        $categories = $this->primaryCategories($productIds, $siteKey, $catalogRoot);

        return DB::table('s_products')
            ->whereIn('id', $productIds)
            ->where('published', 1)
            ->get([
                'id', 'sku', 'alias', 'cover', 'price_regular', 'price_special',
                'price_opt_regular', 'price_opt_special', 'currency', 'inventory', 'availability',
            ])
            ->sortBy(static fn (object $row): int => $positions[(int)$row->id] ?? PHP_INT_MAX)
            ->map(function (object $row) use ($translations, $categories, $catalogRoot, $documentPaths, $siteKey): array {
                $productId = (int)$row->id;
                $categoryId = (int)($categories[$productId] ?? $catalogRoot);
                $product = new sProduct();
                $product->setRawAttributes((array)$row, true);
                $pricing = $this->priceResolver->resolve($product);
                $title = (string)($translations->get($productId)?->pagetitle ?: $row->sku);

                return [
                    'product' => $productId,
                    'id' => $productId,
                    'sku' => (string)$row->sku,
                    'pagetitle' => $title,
                    'title' => $title,
                    'cover' => (string)$row->cover,
                    'link' => $this->productLink($product, $categoryId, $catalogRoot, $siteKey, $documentPaths),
                    'category' => $categoryId,
                    'price' => (string)($pricing['price'] ?? ''),
                    'oldPrice' => (string)($pricing['oldPrice'] ?? ''),
                    'price_regular' => (float)$row->price_regular,
                    'price_special' => (float)$row->price_special,
                    'currency' => (string)$row->currency,
                    'inventory' => (int)$row->inventory,
                    'availability' => (int)$row->availability,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build a product URL from the request-scoped document index.
     *
     * @since 1.4.0
     */
    private function productLink(
        sProduct $product,
        int $categoryId,
        int $catalogRoot,
        string $siteKey,
        array $documentPaths,
    ): string {
        $baseId = match (sCommerce::config('product.link_rule', 'root')) {
            'catalog' => $catalogRoot,
            'category' => $this->visibleCategoryId($categoryId),
            default => (int)evo()->getConfig('site_start', 1),
        };
        $cachedPath = $documentPaths[$baseId] ?? null;
        if (trim((string)$cachedPath) === '') {
            return $product->getLinkAttribute($categoryId);
        }

        $cacheKey = $siteKey . ':' . $baseId;
        if (!isset($this->baseUrlCache[$cacheKey])) {
            $url = rtrim((string)evo()->getConfig('base_url', '/'), '/') . '/' . trim($cachedPath, '/');
            $suffix = (string)evo()->getConfig('friendly_url_suffix', '');
            if ($suffix !== '' && !str_ends_with($url, $suffix)) {
                $url .= $suffix;
            }
            $eventOutput = evo()->invokeEvent('OnMakeDocUrl', ['id' => $baseId, 'url' => $url]);
            $this->baseUrlCache[$cacheKey] = is_array($eventOutput) && $eventOutput
                ? (string)array_pop($eventOutput)
                : $url;
        }

        $baseUrl = $this->baseUrlCache[$cacheKey];
        $suffix = (string)evo()->getConfig('friendly_url_suffix', '');
        if ($suffix !== '' && str_ends_with($baseUrl, $suffix)) {
            $baseUrl = substr($baseUrl, 0, -strlen($suffix));
        }

        return rtrim($baseUrl, '/') . '/' . trim((string)$product->alias, '/') . $suffix;
    }

    /**
     * Resolve the nearest category visible in generated URLs.
     *
     * @since 1.4.0
     */
    private function visibleCategoryId(int $categoryId): int
    {
        $aliases = UrlProcessor::getFacadeRoot()->aliasListing ?? [];
        while ($categoryId > 0 && isset($aliases[$categoryId])) {
            if ((int)($aliases[$categoryId]['alias_visible'] ?? 1) === 1
                || (int)($aliases[$categoryId]['parent'] ?? 0) <= 0) {
                break;
            }
            $categoryId = (int)$aliases[$categoryId]['parent'];
        }

        return $categoryId;
    }

    /**
     * Resolve the preferred category for every product in one query.
     *
     * @return array<int, int>
     * @since 1.4.0
     */
    private function primaryCategories(array $productIds, string $siteKey, int $fallback): array
    {
        $categories = [];
        $siteScope = 'primary_' . $siteKey;
        $rows = DB::table('s_product_category')
            ->whereIn('product', $productIds)
            ->whereIn('scope', [$siteScope, 'primary'])
            ->orderByRaw("CASE scope WHEN ? THEN 0 WHEN 'primary' THEN 1 ELSE 2 END", [$siteScope])
            ->get(['product', 'category']);

        foreach ($rows as $row) {
            $productId = (int)$row->product;
            $categories[$productId] ??= (int)$row->category ?: $fallback;
        }

        return $categories;
    }
}
