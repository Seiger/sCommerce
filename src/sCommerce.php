<?php namespace Seiger\sCommerce;

use EvolutionCMS\Models\ClosureTable;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Facades\sCheckout;
use Seiger\sCommerce\Facades\sFilter;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sCategory;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sPaymentMethod;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sProductTranslate;
use View;

/**
 * Class sCommerce
 *
 * This class handles various functionalities of the sCommerce package including product retrieval, category management, currency conversion, and configuration handling.
 *
 * @package Seiger\sCommerce
 */
class sCommerce
{
    /**
     * Cached currencies.
     *
     * @var Collection
     */
    protected $currencies;
    protected $controller;
    protected $categoryId;
    protected ?array $sort = null;

    /**
     * The current currency for the session.
     * Loaded lazily and stored statically to optimize retrieval across multiple calls.
     *
     * @var string
     */
    protected static string $currentCurrency;

    public function __construct()
    {
        $this->controller = new sCommerceController();
    }

    /**
     * Retrieve a paginated list of products based on provided product IDs.
     *
     * This method fetches products filtered by their IDs and active status,
     * with optional language support and pagination settings.
     *
     * @param array $productIds An array of product IDs to fetch.
     * @param string|null $lang The language to fetch the products in. Defaults to the current locale.
     * @param int $perPage The number of products to return per page. Defaults to 1000.
     * @return object The paginated list of products as a Laravel collection.
     */
    public function getProducts(array $productIds, string $lang = null, int $perPage = 10000): object
    {
        $lang = !$lang ? evo()->getLocale() : $lang;
        $this->sort = empty($this->sort) ? [$this->controller->validateSort()] : $this->sort;

        $query = sProduct::lang($lang)->withCount('reviews')
            ->addSelect(['position' =>
                DB::table('s_product_category')
                    ->select('position')
                    ->whereColumn('s_product_category.product', 's_products.id')
                    ->where(function($subQuery) {
                        $subQuery
                            ->where('s_product_category.category', $this->getCategoryId())
                            ->orWhere('s_product_category.scope', 'primary');
                    })
                    ->limit(1)
            ])
            ->whereIn('id', $productIds)
            ->active();

        if (!empty(evo()->getPlaceholder('checkAsSearch')) && evo()->getPlaceholder('checkAsSearch')) {
            $query->search();
        }

        if (!empty($this->sort)) {
            foreach ($this->sort as $sortParam) {
                ['sort' => $sort, 'order' => $order, 'table' => $table] = $sortParam;

                if ($table) {
                    switch ($table) {
                        case 'attribute':
                            $hasLang = Schema::hasColumn('s_attribute_values', $lang);

                            $query->addSelect(['sort' =>
                                DB::table('s_attribute_values')
                                    ->select(DB::raw(
                                        $hasLang
                                            ? "CASE WHEN " . $lang . " IS NOT NULL AND " . $lang . " != '' THEN " . $lang . " ELSE base END as value"
                                            : "base as value"
                                    ))
                                    ->where('s_attribute_values.avid', function ($q) use ($sort) {
                                        $q->select('valueid')
                                            ->from('s_product_attribute_values')
                                            ->where('s_product_attribute_values.attribute', function ($q) use ($sort) {
                                                $q->select('id')
                                                    ->from('s_attributes')
                                                    ->where('s_attributes.alias', $sort);
                                            })
                                            ->whereColumn('s_product_attribute_values.product', 's_products.id')
                                            ->limit(1);
                                    })
                                    ->union(DB::table('s_product_attribute_values')
                                        ->select('value')
                                        ->where('s_product_attribute_values.attribute', function ($q) use ($sort) {
                                            $q->select('id')
                                                ->from('s_attributes')
                                                ->where('s_attributes.alias', $sort);
                                        })
                                        ->whereColumn('s_product_attribute_values.product', 's_products.id')
                                        ->limit(1)
                                    )
                                    ->limit(1)
                            ]);
                            $query->orderBy('sort', $order);
                            break;
                    }
                } else {
                    if (is_scalar($sort) && trim($sort)) {
                        $query->orderBy($sort, $order ?? 'asc');
                    }
                }
            }
        }

        return $query->orderBy('position')->paginate($perPage);
    }

    /**
     * Retrieves the product based on the given ID and language.
     *
     * @param int $productId The ID of the product to retrieve.
     * @param string $lang (optional) The language to retrieve the product in. Default is an empty string.
     * @return object The product object matching the given ID and language, or a new empty product object if no match found.
     */
    public function getProduct(int $productId, string $lang = null): object
    {
        $lang = !$lang ? $this->controller->langDefault() : $lang;
        $product = sProduct::lang($lang)->whereId($productId)->extractConstructor()->first();

        if (!$product) {
            $translate = sProductTranslate::whereProduct($productId)->first();
            if ($translate) {
                $product = sProduct::lang($translate->lang)->whereId($productId)->extractConstructor()->first();
            }
        }

        return $product ?? new sProduct();
    }

    /**
     * Retrieves a product by its alias.
     *
     * @param string $alias The alias of the product.
     * @return object The product object found with the given alias or an empty sProduct object if not found.
     */
    public function getProductByAlias(string $alias): object
    {
        return sProduct::whereAlias(trim($alias, evo()->getConfig('friendly_url_suffix', '')))->extractConstructor()->first() ?? new sProduct();
    }

    /**
     * Retrieves the active subcategories of a given category.
     *
     * @param int $category The id of the category whose subcategories need to be retrieved.
     * @param int $dept The depth level up to which the subcategories should be retrieved. Default value is 10.
     * @return object The list of active subcategories of the given category.
     */
    public function getTreeActiveCategories(int $category, int $dept = 10): object
    {
        $object = sCategory::find($category);
        return $this->controller->listSubCategories($object, $dept);
    }

    /**
     * Retrieves the products belonging to a specific category.
     *
     * @param int|null $category The ID of the category. If not provided, it will default to the current document identifier.
     * @param string|null $lang The language code for the product names. If not provided, it will default to the application's locale.
     * @param int $perPage The number of products to return per page. Default value is 1000.
     * @param int $dept The depth of sub-categories to include in the query. Default value is 10.
     * @return object The products belonging to the specified category, filtered by language and category ID.
     */
    public function getCategoryProducts(int $perPage = 1000, ?int $category = null, ?string $lang = null, int $dept = 10): object
    {
        $category = $this->getCategoryId($category);
        $productIds = $this->controller->productIds($category, $dept);
        return $this->getProducts($productIds, $lang, $perPage);
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
    public function getCategoryFilters(int $category = null, string $lang = null, int $dept = 10): object
    {
        return sFilter::byCategory($category, $lang, $dept);
    }

    /**
     * Retrieves the attribute object by its ID and language.
     *
     * @param int $attributeId The ID of the attribute to retrieve.
     * @param string $lang The language code to use. Defaults to an empty string.
     * @return object The attribute object if found, otherwise a new sAttribute object.
     */
    public function getAttribute(int $attributeId, string $lang = null): object
    {
        $lang = !$lang ? $this->controller->langDefault() : $lang;
        return sAttribute::lang($lang)->whereAttribute($attributeId)->first() ?? new sAttribute();
    }

    /**
     * Retrieves the currencies from cache or includes them from a config file if not found.
     *
     * @param array|null $where An optional array of criteria to filter the currencies.
     * @return Collection The currencies retrieved from cache or an empty collection if not found.
     */
    public function getCurrencies(null|array $where = null): Collection
    {
        if (!$this->currencies) {
            $this->currencies = Cache::remember('currencies', 2629743, function () {
                $reflector = new \ReflectionClass(self::class);
                $list = include_once str_replace('src/sCommerce.php', 'config/currencies.php', $reflector->getFileName());
                return $list->map(function ($item) {
                    if ($this->config('currencies.'.$item['alpha'])) {
                        $item = array_merge($item, $this->config('currencies.'.$item['alpha']));
                    }
                    return $item;
                });
            });
        }

        $currencies = $this->currencies;

        if ($where) {
            $currencies = $this->currencies->whereIn('alpha', $where)->values();
        }

        return $currencies ?? collect([]);
    }

    /**
     * Converts a price from one currency to another and returns it as a formatted string.
     *
     * @param float $price The price to convert.
     * @param string $currencyFrom The currency to convert from.
     * @param string $currencyTo The currency to convert to.
     * @return string The converted price as a formatted string.
     */
    public function convertPrice($price, $currencyFrom = null, $currencyTo = null): string
    {
        $currencyFrom = $currencyFrom ?? static::loadCurrentCurrency();
        $currencyTo = $currencyTo ?? $currencyFrom;
        $curr = $this->getCurrencies([$currencyTo])->first();

        $price = number_format(
            $this->convertPriceNumber($price, $currencyFrom, $currencyTo),
            ($curr['exp'] ?? 2),
            ($curr['decimals'] ?? '.'),
            str_replace('&nbsp;', ' ', trim($curr['thousands'] ?? '&nbsp;'))
        );

        if ($curr['show'] ?? 1) {
            $symbol = str_replace('&nbsp;', ' ', trim($curr['symbol'] ?? '&nbsp;'));
            if (($curr['position'] ?? 'before') == 'after') {
                $price = $price . $symbol;
            } else {
                $price = $symbol . $price;
            }
        }

        return $price;
    }

    /**
     * Converts a price from one currency to another.
     *
     * @param float $price The price to be converted.
     * @param string $currencyFrom The currency to convert from.
     * @param string $currencyTo The currency to convert to.
     *
     * @return float The converted price.
     */
    public function convertPriceNumber($price, $currencyFrom = null, $currencyTo = null): float
    {
        $currencyFrom = $currencyFrom ?? static::loadCurrentCurrency();
        $currencyTo = $currencyTo ?? $currencyFrom;

        $price = preg_replace('/[^\d\.]+/', '', ($price ?? ''));
        $rate = config('seiger.settings.sCommerceCurrencies.' . $currencyFrom . '_' . $currencyTo, 1);
        return floatval($price) * $rate;
    }

    /**
     * Retrieves the products listing from cache files.
     *
     * @return array The products listing retrieved from cache files, or an empty array if not found or error occurs.
     */
    public function documentListing(): array
    {
        $siteKey = evo()->getConfig('site_key', '');
        $scopeSuffix = trim($siteKey) ? '.' . $siteKey : '';
        $cacheFile = evo()->getCachePath() . 'sCommerceProductsListing' . $scopeSuffix . '.php';

        if (file_exists($cacheFile)) {
            try {
                $data = include $cacheFile;
                return is_array($data) ? $data : [];
            } catch (\Throwable $e) {
                @unlink($cacheFile);
            }
        }

        return [];
    }

    /**
     * Renders a tab with the given information and input data.
     *
     * @param string $tabId The ID of the tab.
     * @param string|null $tabTpl The template for the tab. Default is null.
     * @param array $dataInput The input data for the tab. Default is an empty array.
     * @param string|null $tabName The name of the tab. Default is null, which fetches the name from the language files.
     * @param string|null $tabIcon The icon for the tab. Default is null, which fetches the icon from the language files.
     * @param string|null $tabHelp The help text for the tab. Default is null, which fetches the help text from the language files.
     * @param string|null $fullUrl The full URL for the tab. Default is null, which is generated using the module URL and tab ID.
     *
     * @return string The rendered tab content.
     */
    public function tabRender($tabId, $tabTpl = null, $dataInput = [], $tabName = null, $tabIcon = null, $tabHelp = null, $fullUrl = null)
    {
        $saveUri = '&get=' . $tabId . ($dataInput['iUrl'] ?? '') . ($dataInput['pUrl'] ?? '');
        $fullUrl = $fullUrl ?: $this->moduleUrl() . $saveUri;
        $tabName = $tabName ?: __('sCommerce::global.' . $tabId);
        $tabIcon = $tabIcon ?: __('sCommerce::global.' . $tabId . '_icon');
        $tabHelp = $tabHelp ?: __('sCommerce::global.' . $tabId . '_help');

        $data = compact(['tabId', 'tabTpl', 'saveUri', 'fullUrl', 'tabName', 'tabIcon', 'tabHelp']);

        return view('sCommerce::partials.tabRender', array_merge($data, $dataInput))->render();
    }

    /**
     * Sets the sorting parameters for retrieving products.
     *
     * This method allows you to specify sorting options for product queries.
     * You can pass either a sorting key and an optional order, or an array
     * containing detailed sorting parameters.
     *
     * @param string|array $key The sorting key (e.g., 'cheap', 'expensive', 'rating')
     *                          or an associative array with sorting parameters:
     *                          - 'sort': The sorting key.
     *                          - 'order': The sorting order ('asc' or 'desc').
     * @param string|null $order The sorting order ('asc' or 'desc').
     *                           Defaults to 'asc'. Ignored if $key is an array.
     *
     * @return self Returns the current instance for method chaining.
     *
     * @example
     * // Simple sorting by price (low to high)
     * $service->setSort('cheap');
     *
     * // Sorting by a specific attribute in descending order
     * $service->setSort('attribute.name', 'desc');
     *
     * // Advanced sorting with multiple parameters
     * $service->setSort(['sort' => 'popularity', 'order' => 'desc']);
     */
    public function setSort(string|array $key, ?string $order = 'asc'): self
    {
        if (is_array($key)) {
            if (isset($key[0]) && is_array($key[0])) {
                $validatedSorts = [];
                foreach ($key as $sortItem) {
                    $validated = $this->controller->validateSort($sortItem);
                    if ($validated) {
                        $validatedSorts[] = $validated;
                    }
                }
                $this->sort = $validatedSorts;
            } else {
                $validated = $this->controller->validateSort($key);
                $this->sort = $validated ? [$validated] : [];
            }
        } else {
            $validated = $this->controller->validateSort(['sort' => $key, 'order' => $order]);
            $this->sort = $validated ? [$validated] : [];
        }
        return $this;
    }

    /**
     * Retrieves the module URL.
     *
     * @return string The module URL.
     */
    public function moduleUrl(): string
    {
        return 'index.php?a=112&id=' . md5(__('sCommerce::global.title'));
    }

    /**
     * Retrieves the value from the config file based on the given key.
     *
     * @param string $key The key to retrieve the value from the config file.
     * @param mixed $default (optional) The default value to return if the key does not exist. Default is null.
     * @return mixed The value retrieved from the config file or the default value if the key does not exist.
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        return config('seiger.settings.sCommerce.' . $key, $default);
    }

    /**
     * Retrieves the current currency for the session.
     * If the currency is not yet loaded, it initializes it using the loadCurrentCurrency method.
     *
     * @return string The currency code (e.g., USD, EUR).
     */
    public static function currentCurrency(): string
    {
        if (!isset(static::$currentCurrency)) {
            static::$currentCurrency = static::loadCurrentCurrency();
        }

        return static::$currentCurrency;
    }

    /**
     * Render the payment button for the order.
     *
     * This method is responsible for rendering the payment button by:
     * 1. Determining the payment system based on the provided order details.
     * 2. Finding the corresponding payment method from the database.
     * 3. Dynamically calling the corresponding payment system class and invoking its `payButton` method.
     *
     * If the payment method is not available or if the method `payButton` does not exist for the payment system, an empty string is returned.
     *
     * @param int|string|array $data The order ID, order key, or an array containing order data.
     *  - If it's an integer, the method assumes it's the order ID.
     *  - If it's a string, the method assumes it's the order key (identifier).
     *  - If it's an array, it assumes it's the complete order data.
     *
     * @return string The HTML for the payment button or an empty string if the payment method is not found or invalid.
     *
     * @throws \Exception If there is an issue accessing the payment method class or other unforeseen issues.
     */
    public static function payButton(int|string|array $data): string
    {
        if (is_int($data)) {
            $order = sOrder::whereId((int)$data)->first()->toArray();
        } elseif (is_string($data)) {
            $order = sOrder::whereIdentifier(trim($data))->first()->toArray();
        } elseif (is_array($data)) {
            $order = $data;
        }

        if (!$order || empty($order) || !isset($order['payment_info']['method']) || empty($order['payment_info']['method'])) {
            return '';
        }

        $paymentMethod = sPaymentMethod::find((int)sCheckout::getPayment($order['payment_info']['method'])['id']);

        if (!$paymentMethod || empty($paymentMethod) || !class_exists($paymentMethod->class)) {
            return '';
        }

        $instance = new $paymentMethod->class($paymentMethod->identifier);

        return $instance->payButton($data);
    }

    /**
     * Sends an email notification to the specified recipients using a template and data.
     *
     * This method processes the recipient list, message template, and additional data,
     * renders the template using the View, and sends the email via the `evo()->sendMail` method.
     *
     * @param array|string $to The recipients of the email. Can be a single email or a comma-separated list.
     * @param string $template The email template or the text to be sent.
     * @param array $data      Additional data for the template (optional).
     *
     * @return void
     *
     * @throws \Exception If there are errors during the template rendering or email sending.
     */
    public static function notifyEmail(array|string $to, string $template, array $data = []): void
    {
        if (is_scalar($to)) {
            $to = explode(',', $to);
        }

        $to = array_diff($to, ['', null]);

        if (!empty($to)) {
            $to = array_map('trim', $to);
            $params['to'] = implode(',', $to);

            if (trim($template)) {
                $params['subject'] = 'sCommerce notify - ' . evo()->getConfig('site_name');
                if (Str::endsWith($template, '.blade.php')) {
                    try {
                        $template = rtrim($template, '.blade.php');
                        $view = View::make($template, $data);
                        $renderSections = $view->renderSections();

                        if (isset($renderSections['subject'])) {
                            $params['subject'] = trim($renderSections['subject']);
                        }

                        $params['body'] = $view->render();
                    } catch (\Exception $e) {
                        Log::channel('scommerce')->error("sCommerce. Render template. " . $e->getMessage());
                    }
                } else {
                    $params['body'] = $template;
                }

                try {
                    evo()->sendMail($params);
                } catch (\Exception $e) {
                    Log::channel('scommerce')->error("sCommerce. Send Email. " . $e->getMessage());
                }
            } else {
                Log::channel('scommerce')->alert("sCommerce. User notify by Email template or text missing.");
            }
        } else {
            Log::channel('scommerce')->alert("sCommerce. User notify by Email address is empty.");
        }
    }

    /**
     * Convert data to a string representation.
     *
     * @param mixed $data The data to convert.
     * @return string The string representation of the data.
     */
    public static function logPrepare(mixed $data): string
    {
        ob_start();
        var_dump($data);
        $data = ob_get_contents();
        ob_end_clean();

        $data = Str::of($data)->replaceMatches('/string\(\d+\) .*/', function ($match) {
            return substr($match[0], (strpos($match[0], ') ') + 2)) . ',';
        })->replaceMatches('/bool\(\w+\)/', function ($match) {
            return str_replace(['bool(', ')'], ['', ','], $match[0]);
        })->replaceMatches('/int\(\d+\)/', function ($match) {
            return str_replace(['int(', ')'], ['', ','], $match[0]);
        })->replaceMatches('/float\(\d+\.\d+\)/', function ($match) {
            return str_replace(['float(', ')'], ['', ','], $match[0]);
        })->replaceMatches('/array\(\d+\) /', function ($match) {
            return str_replace($match[0], '', $match[0]);
        })->replaceMatches('/=>\n[ \t]{1,}/', function () {
            return ' => ';
        })->replaceMatches('/  /', function () {
            return '    ';
        })->remove('[')->remove(']')->replace('{', '[')->replace('}', '],')->rtrim(",\n");

        return $data;
    }

    /**
     * Loads the current currency from the session or the default configuration.
     * This method is used internally to ensure the currency is properly initialized.
     *
     * @return string The currency code (e.g., USD, EUR, UAH).
     */
    protected static function loadCurrentCurrency(): string
    {
        $currency = $_SESSION['currency'] ?? null;

        if (!$currency && Cookie::has('currency')) {
            $currency = Cookie::get('currency');
        }

        if ($currency && !in_array($currency, static::config('basic.available_currencies', []))) {
            $currency = null;
        }

        if (!$currency) {
            $currency = static::config('basic.main_currency', 'USD');
        }

        if (!isset($_SESSION['currency']) || $_SESSION['currency'] !== $currency) {
            $_SESSION['currency'] = $currency;
        }

        return $currency;
    }

    /**
     * Retrieves or sets the category ID.
     *
     * @param mixed|null $category The category ID to set, or null to use the existing value or the document identifier.
     * @return int The resolved category ID.
     */
    protected function getCategoryId($category = null): int
    {
        if ($category) {
            $this->categoryId = $category;
        } elseif (!$this->categoryId) {
            $this->categoryId = evo()->documentIdentifier;
        }
        return (int)$this->categoryId;
    }
}
