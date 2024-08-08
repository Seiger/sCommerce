<?php namespace Seiger\sCommerce\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static object getProduct(int $productId, string $lang = '') Retrieves the product based on the given ID and language.
 * @method static object getProductByAlias(string $alias) Retrieves a product by its alias.
 * @method static object getTreeActiveCategories(int $category, int $dept = 10) Retrieves the active subcategories of a given category.
 * @method static object getCategoryProducts(int $category = null, string $lang = null, int $perPage = 1000, int $dept = 10) Retrieves the products belonging to a specific category.
 * @method static object getAttribute(int $attributeId, string $lang = '') Retrieves the attribute object by its ID and language.
 * @method static \Illuminate\Support\Collection getCurrencies(null|array $where = null) Retrieves the currencies from cache or includes them from a config file if not found.
 * @method static string convertPice(float $price, string $currencyFrom, string $currencyTo) Converts a price from one currency to another and returns it as a formatted string.
 * @method static float convertPiceNumber(float $price, string $currencyFrom, string $currencyTo) Converts a price from one currency to another.
 * @method static array documentListing() Retrieves the products listing from cache or sets it if not found.
 * @method static string tabRender(string $tabId, string $tabTpl = null, array $dataInput = [], string $tabName = null, string $tabIcon = null, string $tabHelp = null, string $fullUrl = null) Renders a tab with the given information and input data.
 * @method static string moduleUrl() Retrieves the module URL.
 * @method static mixed config(string $key, mixed $default = null) Retrieves the value from the config file based on the given key.
 *
 * @see \Seiger\sCommerce\sCommerce
 */
class sCommerce extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sCommerce';
    }
}
