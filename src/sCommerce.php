<?php namespace Seiger\sCommerce;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Models\sProduct;

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

        return sProduct::lang($lang)->whereProduct($productId)->first() ?? new sProduct();
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