<?php namespace Seiger\sCommerce;

use EvolutionCMS\Facades\UrlProcessor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Facades\sWishlist;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sAttributeValue;
use Seiger\sCommerce\Models\sProduct;

/**
 * Class sFilter
 *
 * Handles filtering of products based on attributes, including price range filtering.
 */
class sFilter
{
    /**
     * @var sCommerceController Instance of the sCommerceController.
     */
    protected $controller;

    /**
     * @var array Stores validated filters for reuse.
     */
    protected $filters = [];

    /**
     * @var array Stores validated filter IDs for reuse.
     */
    protected $filtersIds = [];

    /**
     * @var int
     */
    protected $currentCategory;

    /**
     * @var int
     */
    protected $currentDept;

    /**
     * Indicates whether validateFilters() was already performed for this request.
     *
     * @var bool
     */
    protected static bool $isValidated = false;

    /**
     * Stores the validated filters across multiple sFilter instances for the duration of one request.
     *
     * @var array
     */
    protected static array $cachedResult = [
        'filters' => [],
        'filtersIds' => [],
        'category' => 0,
    ];

    /**
     * sFilter constructor.
     */
    public function __construct()
    {
        $this->controller = new sCommerceController();
    }

    /**
     * Retrieves filters associated with a specific category.
     *
     * This method generates a collection of filters based on attributes
     * and their corresponding values for the products within the specified category.
     *
     * @param int|null $category The ID of the category for which filters are retrieved.
     * @param string|null $lang  The language locale for the filters.
     * @param int $dept          The depth level for category traversal.
     *
     * @return object|Collection A collection of filters for the specified category.
     */
    public function byCategory(?int $category = null, ?string $lang = null, int $dept = 10): object
    {
        if (empty($category)) {
            $category = static::$isValidated ? (int)static::$cachedResult['category'] : evo()->documentIdentifier;

            if (
                (!empty(evo()->getPlaceholder('checkAsSearch')) && evo()->getPlaceholder('checkAsSearch')) ||
                (!empty(evo()->getPlaceholder('checkAsWishlist')) && evo()->getPlaceholder('checkAsWishlist'))
            ) {
                $category = sCommerce::config('basic.catalog_root', $category);
            }
        }

        $lang = $lang ?? evo()->getLocale();
        $this->currentDept = $dept;
        $productIds = $this->controller->productIds($category, $dept);

        // If no products found, return empty collection (hides PRICE_RANGE as well)
        if (empty($productIds)) {
            return collect();
        }

        $categoryParentsIds = $this->controller->categoryParentsIds($category);

        // Retrieve (attribute, value, valueid, count) info from DB
        $values = DB::table('s_product_attribute_values as pav')
            ->select(
                'pav.attribute',
                'pav.value',
                'pav.valueid',
                DB::raw('COUNT(DISTINCT ' . DB::getTablePrefix() . 'pac.product) as count')
            )->leftJoin('s_product_attribute_values as pac', function ($join) use ($productIds) {
                $join->on('pac.value', '=', 'pav.value')
                    ->on('pac.attribute', '=', 'pav.attribute')
                    ->whereIn('pac.product', $productIds);
            })->join('s_attributes as sa', function ($join) {
                $join->on('pav.attribute', '=', 'sa.id')
                    ->where('sa.asfilter', '=', 1);
            })->whereIn('pav.product', $productIds)
            ->groupBy('pav.attribute', 'pav.value', 'pav.valueid')
            ->get();

        // Retrieve attributes that are marked as filters (asfilter=1)
        // and attributes that belong to these categories
        // and include PRICE_RANGE even if it doesn't appear in the products
        $filters = sAttribute::with(['values'])
            ->lang($lang)
            ->whereHas('categories', function ($q) use ($categoryParentsIds) {
                $q->whereIn('category', $categoryParentsIds);
            })->where('asfilter', 1)
            ->where(function ($q) use ($productIds) {
                $q->whereIn('id', function ($sub) use ($productIds) {
                    $sub->select('attribute')
                        ->from('s_product_attribute_values')
                        ->whereIn('product', $productIds);
                })->orWhere('type', sAttribute::TYPE_ATTR_PRICE_RANGE);
            })->orderBy('position')
            ->get()
            ->map(function ($item) use ($values, $productIds, $category) {
                // Ensure $item->values is a collection
                if (!$item->values) {
                    $item->values = collect();
                }

                switch ($item->type) {
                    case sAttribute::TYPE_ATTR_CHECKBOX:
                        $item = $this->buildCheckboxFilter($item, $values);
                        break;
                    case sAttribute::TYPE_ATTR_SELECT:
                    case sAttribute::TYPE_ATTR_COLOR:
                        $item = $this->buildSelectFilter($item, $values);
                        break;
                    case sAttribute::TYPE_ATTR_PRICE_RANGE:
                        $item = $this->buildPriceRangeFilter($item, $category);
                        break;
                }

                return $item;
            });

        return $filters;
    }

    /**
     * Validates the filters in the current request.
     *
     * @return int
     * @throws InvalidFilterException
     */
    public function validateFilters(): int
    {
        if (static::$isValidated) {
            $this->filters = static::$cachedResult['filters'];
            $this->filtersIds = static::$cachedResult['filtersIds'];
            return (int)static::$cachedResult['category'];
        }

        $path = $this->processPath();

        $categoryId = 0;
        if ($path && $path['path'] && $path['category']) {
            $requestedFilters = $this->extractFilters($path);

            // If there are requested filters, validate them
            if (is_array($requestedFilters) && count($requestedFilters)) {
                $result = $this->validateFiltersStructure($path, $requestedFilters);
                $filters = $result['filters'];
                $filtersIds = $result['filtersIds'] ?? [];

                if (count($filters)) {
                    // Construct the filter string
                    $sFilters = implode(';', array_map(function ($filterName, $filterValues) {
                        return $filterName . '=' . implode(',', $filterValues);
                    }, array_keys($filters), array_values($filters)));

                    // Compare current path vs. expected path
                    if ($path['currentPath'] !== $path['path'] . '/' . $sFilters) {
                        static::$isValidated = true;
                        static::$cachedResult['filters'] = [];
                        static::$cachedResult['filtersIds'] = [];
                        static::$cachedResult['category'] = 0;
                        return 0;
                    }

                    // Store validated filters
                    $this->filters = $filters;
                    $this->filtersIds = $filtersIds;

                    evo()->setPlaceholder('sFilters', $sFilters);
                    evo()->setPlaceholder('sFiltersArray', $filters);

                    $categoryId = (int)$path['category'];
                }
            }  else {
                $categoryId = (int)$path['category'];
            }
        }

        static::$isValidated = true;
        static::$cachedResult['filters'] = $this->filters;
        static::$cachedResult['filtersIds'] = $this->filtersIds;
        static::$cachedResult['category'] = $categoryId;

        return $categoryId;
    }

    /**
     * Retrieves the validated filters.
     *
     * @return array
     */
    public function getValidatedFilters(): array
    {
        if (count($this->filters) === 0) {
            $this->validateFilters();
        }
        return $this->filters;
    }

    /**
     * Force filters for the current request, bypassing URL detection.
     *
     * @param array<string, array<int|string>> $filters Filters indexed by attribute alias.
     * @param int|null $categoryId Optional category context.
     * @return void
     */
    public static function force(array $filters, ?int $categoryId = null): void
    {
        $categoryId = $categoryId ?? (int)evo()->documentIdentifier;
        $instance = new static();
        $result = $instance->validateFiltersStructure(['category' => $categoryId], $filters);

        static::$cachedResult = [
            'filters' => $result['filters'],
            'filtersIds' => $result['filtersIds'],
            'category' => $categoryId,
        ];
        static::$isValidated = true;

        if (count(static::$cachedResult['filters'])) {
            $sFilters = implode(';', array_map(function ($filterName, $filterValues) {
                return $filterName . '=' . implode(',', $filterValues);
            }, array_keys(static::$cachedResult['filters']), array_values(static::$cachedResult['filters'])));

            evo()->setPlaceholder('sFilters', $sFilters);
            evo()->setPlaceholder('sFiltersArray', static::$cachedResult['filters']);
        } else {
            evo()->setPlaceholder('sFilters', '');
            evo()->setPlaceholder('sFiltersArray', []);
        }
    }

    /**
     * Clear previously forced filters.
     *
     * @return void
     */
    public static function release(): void
    {
        static::$cachedResult = [
            'filters' => [],
            'filtersIds' => [],
            'category' => 0,
        ];
        static::$isValidated = false;
        evo()->setPlaceholder('sFilters', '');
        evo()->setPlaceholder('sFiltersArray', []);
    }

    /**
     * Retrieves the validated Ids filters.
     *
     * @return array
     */
    public function getValidatedFiltersIds(): array
    {
        if (count($this->filtersIds) === 0) {
            $this->validateFilters();
        }
        return $this->filtersIds;
    }

    /**
     * Returns product IDs that pass all validated filters,
     * optionally ignoring a given subset of filters (like price).
     *
     * @param array $ignoreFilterAliases e.g. ['price_range'] to ignore price filters.
     * @return array
     */
    protected function filteredProductIds(array $ignoreFilterAliases = []): array
    {
        // Get all validated filters (already set in $this->filters after validateFilters())
        $filters = $this->filters;

        // Remove filters that we want to ignore
        foreach ($ignoreFilterAliases as $alias) {
            unset($filters[$alias]);
        }

        // If no filters remain, we can just return the raw productIds from the category
        // (or from the controller if you have a method that returns everything).
        if (empty($filters)) {
            // By default, just return the full list from the original category/dept logic
            // (You might store $this->productIds somewhere or recalc them)
            $category = static::$isValidated ? (int)static::$cachedResult['category'] : evo()->documentIdentifier;
            return $this->controller->productIds($category, $this->currentDept);
        }

        // Otherwise, build a query that selects products that satisfy all filters
        // This depends on your DB structure. Pseudocode below:
        // We'll do a simple approach: intersect product IDs for each filter.

        $filteredProductIds = null;

        foreach ($filters as $filterAlias => $values) {
            // Find the attribute by alias
            $attribute = sAttribute::where('alias', $filterAlias)->first();
            if (!$attribute) {
                continue; // or skip
            }

            // Build a set of product IDs that match the given attribute and values
            $query = DB::table('s_product_attribute_values')
                ->select('product')
                ->where('attribute', $attribute->id);

            if ($attribute->type == sAttribute::TYPE_ATTR_PRICE_RANGE) {
                // skip here if ignoring or handle differently
                // but normally we skip because this is the ignoreFilter
            } else {
                // for select / checkbox / color: check the alias or value
                $valueIds = sAttributeValue::whereIn('alias', $values)
                    ->where('attribute', $attribute->id)
                    ->pluck('avid')
                    ->all();

                if (!empty($valueIds)) {
                    $query->whereIn('valueid', $valueIds);
                } else {
                    // or if attribute is checkbox -> value = 1, etc.
                    // adjust logic accordingly
                }
            }

            $subProductIds = $query->pluck('product')->all();

            // Intersect with existing $filteredProductIds
            if (is_null($filteredProductIds)) {
                $filteredProductIds = $subProductIds;
            } else {
                // intersect
                $filteredProductIds = array_intersect($filteredProductIds, $subProductIds);
            }

            // If at any point we get an empty set, we can break early
            if (empty($filteredProductIds)) {
                return [];
            }
        }

        return $filteredProductIds ?: [];
    }

    /**
     * Builds a checkbox filter for the given attribute.
     *
     * @param sAttribute $item   The attribute model.
     * @param Collection $values Collection of attribute values with counts.
     * @return sAttribute
     */
    protected function buildCheckboxFilter(sAttribute $item, Collection $values): sAttribute
    {
        $valueObj = new sAttributeValue();
        $valueObj->link = ($item->alias ?? '') . '=1';
        $valueObj->value = 1;
        $valueObj->label = $item->pagetitle;
        $valueObj->count = (int)$values
            ->where('value', 1)
            ->where('attribute', $item->id)
            ->first()?->count ?? 0;
        $valueObj->checked = (int)(isset($this->filters[$item->alias]) && in_array(1, $this->filters[$item->alias]));

        $item->values = collect([$valueObj]);

        return $item;
    }

    /**
     * Builds a select or color filter for the given attribute.
     *
     * @param sAttribute $item   The attribute model.
     * @param Collection $values Collection of attribute values with counts.
     * @return sAttribute
     */
    protected function buildSelectFilter(sAttribute $item, Collection $values): sAttribute
    {
        $item->values = $item->values->map(function ($val) use ($item, $values) {
            $val->link = ($item->alias ?? '') . '=' . ($val->alias ?? '');
            $val->value = $val->alias ?? '';
            $val->label = $val->{evo()->getLocale()} ?? $val->base ?? '';
            $val->count = (int)$values
                ->where('valueid', $val->avid)
                ->where('attribute', $item->id)
                ->first()?->count ?? 0;
            $val->checked = (int)(isset($this->filters[$item->alias]) && in_array($val->alias, $this->filters[$item->alias]));
            return $val;
        });

        return $item;
    }

    /**
     * Builds a price range filter for the given attribute,
     * with dynamic min/max based on other filters.
     *
     * @param sAttribute $item       The attribute model (price_range).
     * @return sAttribute
     */
    protected function buildPriceRangeFilter(sAttribute $item, $category): sAttribute
    {
        // Get product IDs ignoring the price filter itself
        $dept = $this->currentDept ? $this->currentDept : 10;
        $categories = array_merge([$category], $this->controller->listAllActiveSubCategories($category, $dept));
        $nonPriceProductIds = DB::table('s_product_category')->select(['product'])->whereIn('category', $categories)->pluck('product')->toArray();

        if (!empty(evo()->getPlaceholder('checkAsSearch')) && evo()->getPlaceholder('checkAsSearch')) {
            $nonPriceProductIds = array_intersect($nonPriceProductIds, sProduct::search()->pluck('id')->toArray());
        } elseif (!empty(evo()->getPlaceholder('checkAsWishlist')) && evo()->getPlaceholder('checkAsWishlist')) {
            $nonPriceProductIds = array_intersect($nonPriceProductIds, sWishlist::getWishlist());
        }

        // If empty, means no products left after other filters
        if (empty($nonPriceProductIds)) {
            // We can set min=max=0 or do something else
            $value = (object) [
                'link' => ($item->alias ?? ''),
                'min' => 0,
                'max' => 0,
                'min_value' => 0,
                'max_value' => 0
            ];
            $item->values = collect([$value]);
            return $item;
        }

        // Calculate min/max for these non-price-filtered products
        $minMax = DB::table('s_products')
            ->selectRaw('
                MIN(price_regular) as min_regular,
                MAX(price_regular) as max_regular,
                MIN(price_special) as min_special,
                MAX(price_special) as max_special
            ')
            ->whereIn('id', $nonPriceProductIds)
            ->first();

        $min_regular = $minMax?->min_regular ?? 0;
        $min_special = $minMax?->min_special ?? 0;
        $max_regular = $minMax?->max_regular ?? 0;
        $max_special = $minMax?->max_special ?? 0;

        $fullMin = (($min_special > 0 && $min_special < $min_regular) ? $min_special : $min_regular) - 1;
        $fullMax = (($max_special > 0 && $max_special > $max_regular) ? $max_special : $max_regular) + 1;

        // Figure out user-chosen filter range if any
        $userMin = $fullMin;
        $userMax = $fullMax;

        if (isset($this->filters[$item->alias][0])) {
            $userMin = (int)$this->filters[$item->alias][0];
        }
        if (isset($this->filters[$item->alias][1])) {
            $userMax = (int)$this->filters[$item->alias][1];
        }

        // Build final object
        $value = (object)[];
        $value->link = ($item->alias ?? '');
        $value->min = $fullMin; // always full range
        $value->max = $fullMax; // always full range

        // clamp
        $value->min_value = max($fullMin, $userMin);
        $value->max_value = min($fullMax, $userMax);

        // Set item->values
        $item->values = collect([$value]);
        return $item;
    }

    /**
     * Processes and validates the path from the URL.
     *
     * @return array
     */
    protected function processPath(): array
    {
        $matchedId = null;
        $maxMatchLength = 0;
        $path = null;
        $category = null;
        $routes = UrlProcessor::getFacadeRoot()->documentListing;
        $currentPath = request()->path();

        foreach ($routes as $route => $id) {
            $normalizedRoute = rtrim($route, '/');
            if (str_starts_with($currentPath, $normalizedRoute)) {
                $routeLength = strlen($normalizedRoute);
                if ($routeLength > $maxMatchLength) {
                    $maxMatchLength = $routeLength;
                    $path = $normalizedRoute;
                    $category = $id;
                }
            }
        }

        return compact('path', 'category', 'currentPath');
    }

    /**
     * Extracts filters from the given URL path.
     *
     * @param array $path The known path to the category (e.g., 'catalog/bicycles').
     * @return array An associative array of filters where keys are filter names and values are their respective values.
     */
    protected function extractFilters(array $path): array
    {
        $filtersPart = trim(str_replace($path['path'], '', $path['currentPath']), '/');

        if (empty($filtersPart)) {
            return [];
        }

        $filterPairs = explode(';', $filtersPart);
        $filters = [];

        foreach ($filterPairs as $filterPair) {
            [$key, $value] = array_pad(explode('=', $filterPair, 2), 2, null);
            if ($key && $value) {
                $filters[$key] = explode(',', $value);
            }
        }

        return $filters;
    }

    /**
     * Validates the structure and values of the filters.
     *
     * @param array $path
     * @param array $requestedFilters
     * @return array
     */
    protected function validateFiltersStructure(array $path, array $requestedFilters): array
    {
        $filters = [];
        $filtersIds = [];

        if (count($requestedFilters)) {
            // Get the parent categories for the current category
            $category = static::$isValidated ? (int)static::$cachedResult['category'] : (int)$path['category'];

            if (
                (!empty(evo()->getPlaceholder('checkAsSearch')) && evo()->getPlaceholder('checkAsSearch')) ||
                (!empty(evo()->getPlaceholder('checkAsWishlist')) && evo()->getPlaceholder('checkAsWishlist'))
            ) {
                $category = sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1));
            }

            $categoryParentsIds = $this->controller->categoryParentsIds($category);

            // Retrieve attributes that belong to these categories and are marked as filters
            $attributes = sAttribute::whereHas('categories', function ($q) use ($categoryParentsIds) {
                $q->whereIn('category', $categoryParentsIds);
            })->where('asfilter', 1)->get();

            foreach ($requestedFilters as $filterName => $filterValues) {
                $attribute = $attributes->where('alias', $filterName)->first();
                if (!$attribute) {
                    continue;
                }

                $newValues = [];
                $newValuesIds = [];
                $values = $attribute->values;

                foreach ($filterValues as $filterValue) {
                    if ($attribute->type == sAttribute::TYPE_ATTR_PRICE_RANGE) {
                        $val = (int)filter_var($filterValue, FILTER_VALIDATE_INT, [
                            'options' => ['min_range' => 0, 'max_range' => 999999999]
                        ]);
                        $newValues[] = $val;
                        $newValuesIds[] = $val;
                    } else {
                        // For other types, find the matching sAttributeValue by alias
                        $foundValue = $values->where('alias', $filterValue)->first();
                        if ($foundValue) {
                            $newValues[] = $foundValue->alias;
                            $newValuesIds[] = (int)$foundValue->avid;
                        } else {
                            continue;
                        }
                    }
                }

                if ($attribute->type == sAttribute::TYPE_ATTR_PRICE_RANGE) {
                    // For price range, expect 2 values [min, max]
                    $filters[$attribute->alias] = [
                        $newValues[0] ?? 0,
                        $newValues[1] ?? 999999999
                    ];
                    $filtersIds['priceRange'] = [
                        $newValuesIds[0] ?? 0,
                        $newValuesIds[1] ?? 999999999
                    ];
                } else {
                    $filters[$attribute->alias] = $newValues;
                    $filtersIds[$attribute->id] = $newValuesIds;
                }
            }
        }

        return [
            'filters' => $filters,
            'filtersIds' => $filtersIds
        ];
    }
}
