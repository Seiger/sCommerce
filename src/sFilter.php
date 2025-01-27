<?php namespace Seiger\sCommerce;

use EvolutionCMS\Facades\UrlProcessor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sAttributeValue;
use Seiger\sCommerce\Models\sProduct;

class sFilter
{
    protected $controller;

    /**
     * Stores validated filters for reuse.
     *
     * @var array
     */
    protected $filters = [];

    /**
     * Stores validated filters for reuse Ids only.
     *
     * @var array
     */
    protected $filtersIds = [];

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
     *                            If null, all categories are considered.
     * @param string|null $lang   The language locale for the filters.
     *                            Defaults to the application's current locale.
     * @param int $dept           The depth level for category traversal.
     *                            Determines how deep the category hierarchy should be explored.
     *                            Defaults to 10.
     *
     * @return object|Collection A collection of filters for the specified category.
     *                            Each filter includes attribute details and value counts.
     */
    public function byCategory(int $category = null, string $lang = null, int $dept = 10): object
    {
        $cacheKey = 'filters_' . md5($category . '_' . $lang . '_' . $dept);
        return Cache::remember($cacheKey, 3600, function () use ($category, $lang, $dept) {
            $lang = $lang ?? evo()->getLocale();
            $productIds = $this->controller->productIds($category, $dept);

            if (empty($productIds)) {
                return collect();
            }

            $values = DB::table('s_product_attribute_values as pav')
                ->select('pav.attribute', 'pav.value', 'pav.valueid', DB::raw('COUNT(DISTINCT ' . DB::getTablePrefix() . 'pac.product) as count'))
                ->leftJoin('s_product_attribute_values as pac', function ($join) use ($productIds) {
                    $join->on('pac.value', '=', 'pav.value')->on('pac.attribute', '=', 'pav.attribute')->whereIn('pac.product', $productIds);
                })
                ->join('s_attributes as sa', function ($join) {
                    $join->on('pav.attribute', '=', 'sa.id')->where('sa.asfilter', '=', 1);
                })
                ->whereIn('pav.product', $productIds)
                ->groupBy('pav.attribute', 'pav.value', 'pav.valueid')
                ->get();

            $filters = sAttribute::with(['values'])
                ->lang($lang)
                ->where('asfilter', 1)
                ->whereIn('id', function ($query) use ($productIds) {
                    $query->select('attribute')
                        ->from('s_product_attribute_values')
                        ->whereIn('product', $productIds);
                })
                ->orderBy('position')
                ->get()
                ->map(function($item) use ($values, $productIds) {
                    switch ($item->type) {
                        //case sAttribute::TYPE_ATTR_NUMBER:
                        case sAttribute::TYPE_ATTR_CHECKBOX:
                            $value = new sAttributeValue();
                            $value->link = $item?->alias ?? '' . '=' . 1;
                            $value->value = 1;
                            $value->label = $item->pagetitle;
                            $value->count = intval($values->where('value', 1)->where('attribute', $item->id)->first()?->count ?? 0);
                            $value->checked = intval(isset($this->filters[$item?->alias]) && in_array(1, $this->filters[$item?->alias]));
                            $item->values = collect([$value]);
                            break;
                        case sAttribute::TYPE_ATTR_SELECT:
                        case sAttribute::TYPE_ATTR_COLOR:
                            $item->values = $item->values->map(function ($value) use ($item, $values) {
                                $value->link = ($item?->alias ?? '') . '=' . ($value?->alias ?? '');
                                $value->value = $value?->alias ?? '';
                                $value->label = $value?->{evo()->getLocale()} ?? $value?->base ?? '';
                                $value->count = intval($values->where('valueid', $value->avid)->where('attribute', $item->id)->first()?->count ?? 0);
                                $value->checked = intval(isset($this->filters[$item?->alias]) && in_array($value?->alias, $this->filters[$item?->alias]));
                                return $value;
                            });
                            break;
                        case sAttribute::TYPE_ATTR_PRICE_RANGE:
                            $minMax = DB::table('s_products')
                                ->selectRaw('min(price_regular) as min_regular, max(price_regular) as max_regular, min(price_special) as min_special, max(price_special) as max_special')
                                ->whereIn('id', $productIds)
                                ->first();
                            $min_regular = $minMax?->min_regular ?? 0;
                            $min_special = $minMax?->min_special ?? 0;
                            $max_regular = $minMax?->max_regular ?? 0;
                            $max_special = $minMax?->max_special ?? 0;
                            $min = ($min_special > 0 && $min_special < $min_regular) ? $min_special : $min_regular;
                            $max = ($max_special > 0 && $max_special > $max_regular) ? $max_special : $max_regular;
                            $value = (object)[];
                            $value->link = $item?->alias ?? '' . '=' . 1;
                            $value->min = $min;
                            $value->max = $max;
                            $value->min_value = min($min, intval($this->filters[$item?->alias][0] ?? $min));
                            $value->max_value = max($max, intval($this->filters[$item?->alias][1] ?? $max));
                            $item->values = collect([$value]);
                            break;
                    }
                    return $item;
                });

            return $filters;
        });
    }

    /**
     * Validates the filters in the current request.
     *
     * @return int
     * @throws InvalidFilterException
     */
    public function validateFilters(): int
    {
        $path = $this->processPath();

        if ($path && $path['path'] && $path['category']) {
            $dirtyFilters = $this->extractFilters($path);
            if (is_array($dirtyFilters) && count($dirtyFilters)) {
                extract($this->validateFiltersStructure($path, $dirtyFilters));
            }
        }

        if (isset($filters) && is_array($filters) && count($filters)) {
            $sFilters = implode(';', array_map(function ($filterName, $filterValues) {
                return $filterName . '=' . implode(',', $filterValues);
            }, array_keys($filters), array_values($filters)));

            if ($path['currentPath'] !== $path['path'] . '/' . $sFilters) {
                return 0;
            }

            $this->filters = $filters;
            $this->filtersIds = $filtersIds;
            evo()->setPlaceholder('sFilters', $sFilters);
        }

        return (int)$path['category'];
    }

    /**
     * Retrieves the validated filters.
     *
     * @return array
     */
    public function getValidatedFilters(): array
    {
        if (count($this->filters) == 0) {
            $this->validateFilters();
        }
        return $this->filters;
    }

    /**
     * Retrieves the validated Ids filters.
     *
     * @return array
     */
    public function getValidatedFiltersIds(): array
    {
        if (count($this->filtersIds) == 0) {
            $this->validateFilters();
        }
        return $this->filtersIds;
    }

    /**
     * Processes and validates path from the URL.
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
     * @param string $path The known path to the category (e.g., 'catalog/bicycles').
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
     * @param array $dirtyFilters
     * @return array
     */
    protected function validateFiltersStructure(array $path, array $dirtyFilters): array
    {
        $filters = [];
        $filtersIds = [];

        if (count($dirtyFilters)) {
            $categoryParentsIds = $this->controller->categoryParentsIds((int)$path['category']);
            $attributes = sAttribute::whereHas('categories', function ($q) use ($categoryParentsIds) {
                $q->whereIn('category', $categoryParentsIds);
            })->where('asfilter', 1)->get();

            foreach ($dirtyFilters as $filterName => $filterValues) {
                $attribute = $attributes->where('alias', $filterName)->first();
                if ($attribute) {
                    $newValues = [];
                    $newValuesIds = [];
                    $values = $attribute->values;
                    foreach ($filterValues as $filterValue) {
                        $value = $values->where('alias', $filterValue)->first();
                        if ($value) {
                            $newValues[] = $value?->alias;
                            $newValuesIds[] = (int)$value?->avid ?? 0;
                        } else {
                            if ($attribute?->type == sAttribute::TYPE_ATTR_PRICE_RANGE) {
                                $value = (int)filter_var($filterValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999999999]]);
                                $newValues[] = $value;
                                $newValuesIds[] = $value;
                            } else {
                                return [];
                            }
                        }
                    }

                    if ($attribute?->type == sAttribute::TYPE_ATTR_PRICE_RANGE) {
                        $filters[$attribute?->alias] = [$newValues[0] ?? 0, $newValues[1] ?? 999999999];
                        $filtersIds['priceRange'] = [$newValuesIds[0] ?? 0, $newValuesIds[1] ?? 999999999];
                    } else {
                        $filters[$attribute?->alias] = $newValues;
                        $filtersIds[$attribute?->id] = $newValuesIds;
                    }
                } else {
                    return [];
                }
            }
        }

        return compact('filters', 'filtersIds');
    }
}
