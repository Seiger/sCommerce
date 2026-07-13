<?php namespace Seiger\sCommerce\Filter;

use EvolutionCMS\Facades\UrlProcessor;
use Illuminate\Database\Query\Builder;
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

        // OPTIMIZATION: Split into two queries to avoid complex JOIN with whereIn
        // Step 1: Get unique attribute/value/valueid combinations for products
        $uniqueAttributes = DB::table('s_product_attribute_values as pav')
            ->select('pav.attribute', 'pav.value', 'pav.valueid')
            ->join('s_attributes as sa', function ($join) {
                $join->on('pav.attribute', '=', 'sa.id')
                    ->where('sa.asfilter', '=', 1);
            })
            ->whereIn('pav.product', $productIds)
            ->groupBy('pav.attribute', 'pav.value', 'pav.valueid')
            ->get();

        // Step 2: Count products for each combination
        if ($uniqueAttributes->isEmpty()) {
            $values = collect();
        } else {
            // Build conditions for all unique combinations
            $values = DB::table('s_product_attribute_values')
                ->select(
                    'attribute',
                    'value',
                    'valueid',
                    DB::raw('COUNT(DISTINCT product) as count')
                )
                ->whereIn('product', $productIds)
                ->where(function ($query) use ($uniqueAttributes) {
                    foreach ($uniqueAttributes as $attr) {
                        $query->orWhere(function ($q) use ($attr) {
                            $q->where('attribute', $attr->attribute)
                                ->where('value', $attr->value)
                                ->where('valueid', $attr->valueid);
                        });
                    }
                })
                ->groupBy('attribute', 'value', 'valueid')
                ->get();
        }

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
            })
            ->filter(function ($item) {
                if ($item->type == sAttribute::TYPE_ATTR_PRICE_RANGE) {
                    return true;
                }

                if ($item->type == sAttribute::TYPE_ATTR_CHECKBOX) {
                    return (int)($item->values->first()?->count ?? 0) > 0
                        || (int)($item->values->first()?->checked ?? 0) === 1;
                }

                return $item->values instanceof Collection && $item->values->isNotEmpty();
            })
            ->values();

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
                $filters = $this->normalizeFilters($result['filters']);
                $filtersIds = $result['filtersIds'] ?? [];

                if (count($filters)) {
                    // Construct the filter string
                    $sFilters = $this->buildFiltersString($filters);

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
            } elseif (empty($path['filtersPart'])) {
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
     *
     * @since 1.2.0 Supports trusted programmatic filters for attributes that are not exposed as storefront filters.
     */
    public static function force(array $filters, ?int $categoryId = null): void
    {
        $categoryId = $categoryId ?? (int)evo()->documentIdentifier;
        $instance = new static();
        $result = $instance->validateFiltersStructure(['category' => $categoryId], $filters, false);
        $normalizedFilters = $instance->normalizeFilters($result['filters']);

        static::$cachedResult = [
            'filters' => $normalizedFilters,
            'filtersIds' => $result['filtersIds'],
            'category' => $categoryId,
        ];
        static::$isValidated = true;
        static::syncResolvedInstance();

        if (count(static::$cachedResult['filters'])) {
            $sFilters = $instance->buildFiltersString(static::$cachedResult['filters']);

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
        static::syncResolvedInstance();
        evo()->setPlaceholder('sFilters', '');
        evo()->setPlaceholder('sFiltersArray', []);
    }

    /**
     * Keep the resolved singleton instance in sync with the static cache.
     *
     * This matters when filters were already parsed earlier in the request
     * (for example in OnPageNotFound) and later overridden via force().
     *
     * @return void
     */
    protected static function syncResolvedInstance(): void
    {
        if (!app()->bound('sFilter')) {
            return;
        }

        $resolved = app('sFilter');
        if (!$resolved instanceof self) {
            return;
        }

        $resolved->filters = static::$cachedResult['filters'];
        $resolved->filtersIds = static::$cachedResult['filtersIds'];
        $resolved->currentCategory = (int)static::$cachedResult['category'];
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
     * Apply validated attribute filters to a product-category query.
     *
     * Checkbox value `1` requires a checked attribute row. Checkbox value `0`
     * matches products without a checked row, including products with no pivot row.
     * Supplying both values leaves the checkbox unrestricted.
     *
     * @param Builder $query Product-category query containing a `product` column.
     * @param array<int|string, array<int|string>> $filters Validated filters keyed by attribute ID.
     * @return Builder
     *
     * @since 1.2.0
     */
    public function applyAttributeFilters(Builder $query, array $filters): Builder
    {
        unset($filters['priceRange']);

        $attributeIds = array_values(array_filter(
            array_keys($filters),
            static fn($attributeId) => is_numeric($attributeId)
        ));

        $checkboxIds = empty($attributeIds)
            ? []
            : sAttribute::query()
                ->whereIn('id', $attributeIds)
                ->where('type', sAttribute::TYPE_ATTR_CHECKBOX)
                ->pluck('id')
                ->mapWithKeys(static fn($attributeId) => [(int)$attributeId => true])
                ->all();

        foreach ($filters as $attributeId => $values) {
            if (!is_numeric($attributeId)) {
                continue;
            }

            $attributeId = (int)$attributeId;
            $values = array_values(array_unique((array)$values, SORT_REGULAR));

            if (isset($checkboxIds[$attributeId])) {
                $values = array_values(array_unique(array_map('intval', $values)));
                $hasUnchecked = in_array(0, $values, true);
                $hasChecked = in_array(1, $values, true);

                if ($hasUnchecked && $hasChecked) {
                    continue;
                }

                if ($hasUnchecked) {
                    $query->whereNotIn('product', function ($subQuery) use ($attributeId) {
                        $subQuery->select('product')
                            ->from('s_product_attribute_values')
                            ->where('attribute', $attributeId)
                            ->where('value', 1);
                    });
                } elseif ($hasChecked) {
                    $query->whereIn('product', function ($subQuery) use ($attributeId) {
                        $subQuery->select('product')
                            ->from('s_product_attribute_values')
                            ->where('attribute', $attributeId)
                            ->where('value', 1);
                    });
                }

                continue;
            }

            if (empty($values)) {
                continue;
            }

            $query->whereIn('product', function ($subQuery) use ($attributeId, $values) {
                $subQuery->select('product')
                    ->from('s_product_attribute_values')
                    ->where('attribute', $attributeId)
                    ->whereIn('value', $values);
            });
        }

        return $query;
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
        })->filter(function ($val) {
            return (int)($val->count ?? 0) > 0 || (int)($val->checked ?? 0) === 1;
        })->values();

        return $item;
    }

    /**
     * Builds a price range filter for the given attribute,
     * with dynamic min/max based on other filters.
     *
     * @param sAttribute $item       The attribute model (price_range).
     * @return sAttribute
     *
     * @since 1.2.0 Uses the shared attribute-query filtering semantics, including unchecked checkboxes.
     */
    protected function buildPriceRangeFilter(sAttribute $item, $category): sAttribute
    {
        $dept = $this->currentDept ? $this->currentDept : 10;
        $categories = array_merge([$category], $this->controller->listAllActiveSubCategories($category, $dept));

        $query = DB::table('s_product_category')->select(['product'])->whereIn('category', $categories);

        if (!empty(evo()->getPlaceholder('checkAsSearch')) && evo()->getPlaceholder('checkAsSearch')) {
            $query->whereIn('product', sProduct::search()->pluck('id')->toArray());
        } elseif (!empty(evo()->getPlaceholder('checkAsWishlist')) && evo()->getPlaceholder('checkAsWishlist')) {
            $query->whereIn('product', sWishlist::getWishlist());
        }

        $filtersIds = $this->getValidatedFiltersIds();
        unset($filtersIds['priceRange']);
        $this->applyAttributeFilters($query, $filtersIds);

        $nonPriceProductIds = $query->pluck('product')->toArray();

        if (empty($nonPriceProductIds)) {
            $value = (object)[
                'link' => ($item->alias ?? ''),
                'min' => 0,
                'max' => 0,
                'min_value' => 0,
                'max_value' => 0
            ];
            $item->values = collect([$value]);
            return $item;
        }

        $minMax = DB::table('s_products')
            ->selectRaw("
                MIN(CASE
                    WHEN price_special > 0 AND price_special < price_regular THEN price_special
                    ELSE price_regular
                END) as min_price,
                MAX(CASE
                    WHEN price_special > 0 AND price_special < price_regular THEN price_special
                    ELSE price_regular
                END) as max_price
            ")
            ->whereIn('id', $nonPriceProductIds)
            ->where('published', 1)
            ->first();

        $effectiveMin = (float)($minMax?->min_price ?? 0);
        $effectiveMax = (float)($minMax?->max_price ?? 0);

        $fullMin = max(0, (int)floor($effectiveMin) - 1);
        $fullMax = max($fullMin, (int)ceil($effectiveMax) + 1);

        $userMin = $fullMin;
        $userMax = $fullMax;

        if (isset($this->filters[$item->alias][0])) {
            $userMin = (int)$this->filters[$item->alias][0];
        }
        if (isset($this->filters[$item->alias][1])) {
            $userMax = (int)$this->filters[$item->alias][1];
        }

        $value = (object)[];
        $value->link = ($item->alias ?? '');
        $value->min = $fullMin;
        $value->max = $fullMax;

        $value->min_value = max($fullMin, $userMin);
        $value->max_value = min($fullMax, $userMax);

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
        $currentLang = trim((string)evo()->getConfig('lang', evo()->getLocale()), '/');

        if ($currentLang !== '' && str_starts_with($currentPath, $currentLang . '/')) {
            $currentPath = substr($currentPath, strlen($currentLang . '/'));
        }

        foreach ($routes as $route => $id) {
            $normalizedRoute = rtrim($route, '/');
            if (
                $normalizedRoute !== ''
                && (
                    $currentPath === $normalizedRoute
                    || str_starts_with($currentPath, $normalizedRoute . '/')
                )
            ) {
                $routeLength = strlen($normalizedRoute);
                if ($routeLength > $maxMatchLength) {
                    $maxMatchLength = $routeLength;
                    $path = $normalizedRoute;
                    $category = $id;
                }
            }
        }

        $filtersPart = '';
        if ($path) {
            $filtersPart = trim(substr($currentPath, strlen($path)), '/');
        }

        return compact('path', 'category', 'currentPath', 'filtersPart');
    }

    /**
     * Extracts filters from the given URL path.
     *
     * @param array $path The known path to the category (e.g., 'catalog/bicycles').
     * @return array An associative array of filters where keys are filter names and values are their respective values.
     *
     * @since 1.2.0 Preserves zero values so unchecked checkbox filters can be represented in URLs.
     */
    protected function extractFilters(array $path): array
    {
        $filtersPart = $path['filtersPart'] ?? trim(str_replace($path['path'], '', $path['currentPath']), '/');

        if (empty($filtersPart)) {
            return [];
        }

        $filterPairs = explode(';', $filtersPart);
        $filters = [];

        foreach ($filterPairs as $filterPair) {
            [$key, $value] = array_pad(explode('=', $filterPair, 2), 2, null);
            if ($key !== null && $key !== '' && $value !== null && $value !== '') {
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
     * @param bool $filterableOnly Whether attributes must be exposed as storefront filters.
     * @return array
     *
     * @since 1.2.0 Supports checkbox values `0` (unchecked) and `1` (checked).
     */
    protected function validateFiltersStructure(array $path, array $requestedFilters, bool $filterableOnly = true): array
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

            $attributesQuery = sAttribute::whereHas('categories', function ($q) use ($categoryParentsIds) {
                $q->whereIn('category', $categoryParentsIds);
            });

            if ($filterableOnly) {
                $attributesQuery->where('asfilter', 1);
            }

            $attributes = $attributesQuery->get();

            foreach ($requestedFilters as $filterName => $filterValues) {
                $attribute = $attributes->where('alias', $filterName)->first();
                if (!$attribute) {
                    continue;
                }

                $newValues = [];
                $newValuesIds = [];
                $values = $attribute->values;

                foreach ($filterValues as $filterValue) {
                    if ($attribute->type == sAttribute::TYPE_ATTR_CHECKBOX) {
                        $val = filter_var($filterValue, FILTER_VALIDATE_INT);
                        if ($val === false || !in_array($val, [0, 1], true)) {
                            continue;
                        }

                        $newValues[] = $val;
                        $newValuesIds[] = $val;
                    } elseif ($attribute->type == sAttribute::TYPE_ATTR_PRICE_RANGE) {
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

    /**
     * Normalize filters for stable SEO-friendly URLs.
     *
     * @param array<string, array<int|string>> $filters
     * @return array<string, array<int|string>>
     */
    protected function normalizeFilters(array $filters): array
    {
        ksort($filters, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($filters as $filterName => $filterValues) {
            $uniqueValues = array_values(array_unique($filterValues, SORT_REGULAR));

            $allNumeric = count($uniqueValues) > 0 && count(array_filter($uniqueValues, static fn($value) => is_numeric($value))) === count($uniqueValues);

            if ($allNumeric) {
                sort($uniqueValues, SORT_NUMERIC);
            } else {
                sort($uniqueValues, SORT_NATURAL | SORT_FLAG_CASE);
            }

            $filters[$filterName] = $uniqueValues;
        }

        return $filters;
    }

    /**
     * Build a canonical string representation of filters.
     *
     * @param array<string, array<int|string>> $filters
     * @return string
     */
    protected function buildFiltersString(array $filters): string
    {
        $filters = $this->normalizeFilters($filters);

        return implode(';', array_map(function ($filterName, $filterValues) {
            return $filterName . '=' . implode(',', $filterValues);
        }, array_keys($filters), array_values($filters)));
    }
}
