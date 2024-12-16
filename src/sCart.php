<?php namespace Seiger\sCommerce;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sProduct;

class sCart
{
    protected $cartData;
    protected $productDetails = [
        'id',
        'title',
        'link',
        'coverSrc',
        'category',
        'sku',
        'quantity',
        'price',
        'specialPrice'
    ];

    public function __construct()
    {
        $this->cartData = $this->loadCartData();
    }

    /**
     * Get a list of items in the cart with their IDs and quantities.
     *
     * @return array An array of items, each with product ID and quantity.
     */
    /*public function getItems(): array
    {
        $items = [];

        foreach ($this->cartData as $productId => $optionId) {
            foreach ($optionId as $quantity) {
                $items[] = [
                    'productId' => $productId,
                    //'optionId' => $optionId,
                    'quantity' => $quantity
                ];
            }
        }

        return $items;
    }*/

    /**
     * Retrieve detailed information about items in the cart.
     *
     * This method returns a list of items in the cart with detailed information,
     * including the product details and any attributes associated with the items.
     *
     * @return array An array of detailed items, each including the product ID, quantity,
     *               and additional details such as product attributes and their values.
     */
    /*public function getDetailedItems(): array
    {
        $items = [];

        foreach ($this->cartData as $productId => $optionId) {
            $product = sProduct::find($productId);

            if (!$product) {
                continue;
            }

            $items[] = [
                'product_id' => $productId,
                'product' => $product,
                'quantity' => $attributes['quantity'] ?? 1,
            ];
        }

        return $items;
    }*/

    /**
     * Add a product to the cart.
     *
     * @param int $productId The ID of the product to add.
     * @param int $optionId The ID option associated with the product.
     * @param int $quantity The quantity of the product to add.
     * @return void
     */
    public function addProduct(int $productId = 0, int $optionId = 0, int $quantity = 1): array
    {
        if ($productId === 0) {
            $productId = request()->integer('productId');
            $optionId = request()->integer('optionId');
            $quantity = max(request()->integer('quantity'), 1);
        }

        $product = sCommerce::getProduct($productId);

        if (!$product || !$product->id) {
            Log::warning('sCart - ' . __('sCommerce::global.product_with_id_not_found', ['id' => $productId]));

            return [
                'success' => false,
                'message' =>  __('sCommerce::global.product_with_id_not_found', ['id' => $productId]),
            ];
        }

        if (!isset($this->cartData[$productId][$optionId])) {
            $this->cartData[$productId][$optionId] = 0;
        }

        if (sCommerce::config('product.quantity_on', 0)) {
            if ($product->quantity < 0) {
                Log::alert('sCart - ' . __('sCommerce::global.product_title_is_out_of_stock', ['title' => $product->title]));

                return [
                    'success' => false,
                    'message' =>  __('sCommerce::global.product_title_is_out_of_stock', ['title' => $product->title]),
                ];
            } else {
                $quantity = min($quantity, $product->quantity);
            }
        }

        $this->cartData[$productId][$optionId] = ($quantity == 1 ? 1 : $quantity);
        $this->saveCartData();

        return [
            'success' => true,
            'message' => "Product with ID {$productId} added to Cart.",
            'product' => $this->getProductFields($product),
            'quantity' => $this->cartData[$productId][$optionId],
            'miniCart' => $this->getMiniCart(),
        ];
    }

    /**
     * Remove a product from the cart.
     *
     * @param int $productId The ID of the product to remove.
     * @return void
     */
    /*public function removeProduct(int $productId): void
    {
        unset($this->cartData[$productId]);
        $this->saveCartData();
    }*/

    /**
     * Update the quantity of a product in the cart.
     *
     * @param int $productId The ID of the product.
     * @param int $quantity The new quantity of the product.
     * @return void
     */
    /*public function updateQuantity(int $productId, int $quantity): void
    {
        if (isset($this->cartData[$productId])) {
            $this->cartData[$productId]['quantity'] = $quantity;
            $this->saveCartData();
        }
    }*/

    /**
     * Get the total sum of items in the cart in current Currency.
     *
     * @return array The total sum  and items in the cart.
     */
    public function getMiniCart(): array
    {
        $totalSum = 0;
        $items = [];
        $productIds = array_keys($this->cartData);
        $products = sCommerce::getProducts($productIds);

        foreach ($products as $product) {
            foreach ($this->cartData[$product->id] as $optionId => $quantity) {
                $items[] = $this->getProductFields($product);
                $price = $product->price_special > 0 ? $product->price_special : $product->price_regular;
                $price = sCommerce::convertPriceNumber($price, $product->currency, sCommerce::currentCurrency());
                $totalSum += $price * $quantity;
            }
        }

        $cart['totalSum'] = $totalSum;
        $cart['totalSumFormatted'] = sCommerce::convertPrice($totalSum);
        $cart['items'] = $items;

        return $cart;
    }

    /**
     * Load cart data from the session or database.
     *
     * @return array The cart data.
     */
    protected function loadCartData(): array
    {
        //return session('sCart', []);
        return $_SESSION['sCart'] ?? [];
    }

    /**
     * Save the cart data to the session or database.
     *
     * @return void
     */
    protected function saveCartData(): void
    {
        // Save cart data to the session or database
        // Implementation needed based on your storage method
        //session(['sCart' => $this->cartData]);
        $_SESSION['sCart'] = $this->cartData;
    }

    /**
     * Get details of a product by its ID with selected fields.
     *
     * This method retrieves detailed information about a product from the database.
     *
     * @param object $product The product.
     * @throws \UnexpectedValueException If the returned object is not a product.
     */
    private function getProductFields(object $product): ?array
    {
        $attributes = [];
        if (count($attributesDisplay = sCommerce::config('cart.product_attributes_display', []))) {
            foreach ($attributesDisplay as $attrDisplay) {
                $ad = $product->attribute($attrDisplay);
                if ($ad) {
                    $attributes[$ad['alias']] = array_diff($ad->only(['id', 'position', 'type', 'alias', 'title', 'value', 'code', 'label']), [null]);
                }
            }
        }

        return array_merge($product->only($this->productDetails), $attributes);
    }

    /**
     * Retrieve attributes for a given product ID from the cart data.
     *
     * @param int $productId The ID of the product.
     * @param array $attributes The attributes associated with the product in the cart.
     * @return array An array of attributes and their values for the product.
     */
    /*private function getProductAttributes(int $productId, array $attributes): array
    {
        $productAttributes = [];

        foreach ($attributes['attributes'] ?? [] as $attributeId => $value) {
            $attribute = sAttribute::find($attributeId);

            // Add attribute to the array if it exists
            if ($attribute) {
                $productAttributes[] = [
                    'attribute_id' => $attributeId,
                    'attribute' => $attribute,
                    'value' => $value
                ];
            }
        }

        return $productAttributes;
    }*/
}
