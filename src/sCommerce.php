<?php namespace Seiger\sCommerce;

use EvolutionCMS\Models\ClosureTable;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sProductTranslate;

class sCommerce
{
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

        $product = sProduct::lang($lang)->whereProduct($productId)->first();

        if (!$product) {
            $translate = sProductTranslate::whereProduct($productId)->first();
            if ($translate) {
                $product = sProduct::lang($translate->lang)->whereProduct($productId)->first();
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
        return sProduct::whereAlias($alias)->first() ?? new sProduct();
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
        $object = SiteContent::find($category);
        return $sCommerceController->listSubCategories($object, $dept);
    }

    /**
     * Retrieves the products belonging to a specific category.
     *
     * @param int|null $category The ID of the category. If not provided, it will default to the current document identifier.
     * @param string|null $lang The language code for the product names. If not provided, it will default to the application's locale.
     * @param int $dept The depth of sub-categories to include in the query. Defaults to 10.
     * @return object The products belonging to the specified category, filtered by language and category ID.
     */
    public function getCategoryProducts(int $category = null, string $lang = null, int $dept = 10): object
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

        return sProduct::lang($lang)->whereIn('category', $categories)->orWhereIn('id', $productIds)->active()->get();
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
     * Retrieves the products listing from cache or sets it if not found.
     *
     * @return array The products listing retrieved from cache or an empty array if not found.
     */
    public function documentListing(): array
    {
        $productsListing = Cache::get('productsListing');

        if (!$productsListing) {
            $sCommerceController = new sCommerceController();
            $sCommerceController->setProductsListing();
            $productsListing = Cache::get('productsListing');
        }

        return $productsListing ?? [];
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
    public function config(string $key, mixed $default = null): mixed
    {
        return config('seiger.settings.sCommerce.' . $key, $default);
    }
}