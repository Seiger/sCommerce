<?php namespace Seiger\sCommerce;

use EvolutionCMS\Models\ClosureTable;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sCategory;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sProductTranslate;

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

    /**
     * The current currency for the session.
     * Loaded lazily and stored statically to optimize retrieval across multiple calls.
     *
     * @var string
     */
    protected static string $currentCurrency;

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

    public function getProducts(array $productIds, string $lang = null, int $perPage = 1000): object
    {
        if (!$lang) {
            $lang = evo()->getLocale();
        }

        return sProduct::lang($lang)->whereIn('id', $productIds)->active()->paginate($perPage);
    }

    /**
     * Retrieves the product based on the given ID and language.
     *
     * @param int $productId The ID of the product to retrieve.
     * @param string $lang (optional) The language to retrieve the product in. Default is an empty string.
     * @return object The product object matching the given ID and language, or a new empty product object if no match found.
     */
    public function getProduct(int $productId, string $lang = ''): object
    {
        if (!trim($lang)) {
            $sCommerceController = new sCommerceController();
            $lang = $sCommerceController->langDefault();
        }

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
        return sProduct::whereAlias($alias)->extractConstructor()->first() ?? new sProduct();
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
        $sCommerceController = new sCommerceController();
        $object = sCategory::find($category);
        return $sCommerceController->listSubCategories($object, $dept);
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
    public function getCategoryProducts(int $category = null, string $lang = null, int $perPage = 1000, int $dept = 10): object
    {
        $sCommerceController = new sCommerceController();

        if (!$lang) {
            $lang = evo()->getLocale();
        }

        if (!$category) {
            $category = evo()->documentIdentifier;
        }

        $categories = array_merge([$category], $sCommerceController->listAllActiveSubCategories($category, $dept));
        $productIds = DB::table('s_product_category')->select(['product'])->whereIn('category', $categories)->get()->pluck('product')->toArray();

        return $this->getProducts($productIds, $lang, $perPage);
    }

    /**
     * Retrieves the attribute object by its ID and language.
     *
     * @param int $attributeId The ID of the attribute to retrieve.
     * @param string $lang The language code to use. Defaults to an empty string.
     * @return object The attribute object if found, otherwise a new sAttribute object.
     */
    public function getAttribute(int $attributeId, string $lang = ''): object
    {
        if (!trim($lang)) {
            $sCommerceController = new sCommerceController();
            $lang = $sCommerceController->langDefault();
        }

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
    public function convertPriceNumber($price, $currencyFrom, $currencyTo): float
    {
        $rate = config('seiger.settings.sCommerceCurrencies.' . $currencyFrom . '_' . $currencyTo, 1);
        return floatval($price) * $rate;
    }

    /**
     * Retrieves the products listing from cache or sets it if not found.
     *
     * @return array The products listing retrieved from cache or an empty array if not found.
     */
    public function documentListing(): array
    {
        $productsListing = Cache::get('productsListing' . evo()->getConfig('site_key', ''));

        if (!$productsListing) {
            $sCommerceController = new sCommerceController();
            $sCommerceController->setProductsListing();
            $productsListing = Cache::get('productsListing');
        }

        return $productsListing ?? [];
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
        $saveUri = '&get=' . $tabId . ($dataInput['iUrl'] ?? '');
        $fullUrl = $fullUrl ?: $this->moduleUrl() . $saveUri;
        $tabName = $tabName ?: __('sCommerce::global.' . $tabId);
        $tabIcon = $tabIcon ?: __('sCommerce::global.' . $tabId . '_icon');
        $tabHelp = $tabHelp ?: __('sCommerce::global.' . $tabId . '_help');

        $data = compact(['tabId', 'tabTpl', 'saveUri', 'fullUrl', 'tabName', 'tabIcon', 'tabHelp']);

        return view('sCommerce::partials.tabRender', array_merge($data, $dataInput))->render();
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
     * Loads the current currency from the session or the default configuration.
     * This method is used internally to ensure the currency is properly initialized.
     *
     * @return string The currency code (e.g., USD, EUR, UAH).
     */
    protected static function loadCurrentCurrency(): string
    {
        // Try to retrieve currency from session
        $currency = $_SESSION['currency'];

        // Check cookies if currency is still not set
        if (!$currency && Cookie::has('currency')) {
            $currency = Cookie::get('currency');
        }

        // Fallback to default currency from configuration
        if (!$currency) {
            $currency = static::config('basic.main_currency', 'USD');
        }

        // Store currency in session if it is not already set
        if (!$_SESSION['currency'] || $_SESSION['currency'] !== $currency) {
            $_SESSION['currency'] = $currency;
        }

        return $currency;
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
}
