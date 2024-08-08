<?php namespace Seiger\sCommerce;

use Illuminate\Support\Facades\DB;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sProduct;

class sCart
{
    protected $cartData;

    public function __construct()
    {
        $this->cartData = $this->loadCartData();
    }

    /**
     * Load cart data from the session or database.
     *
     * @return array The cart data.
     */
    protected function loadCartData(): array
    {
        // Load cart data from the session or database
        // Implementation needed based on your storage method
        return session('cart', []);
    }

    /**
     * Get a list of items in the cart with their IDs and quantities.
     *
     * @return array An array of items, each with product ID and quantity.
     */
    public function getItems(): array
    {
        $items = [];

        foreach ($this->cartData as $productId => $quantity) {
            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity
            ];
        }

        return $items;
    }

    /**
     * Retrieve detailed information about items in the cart.
     *
     * This method returns a list of items in the cart with detailed information,
     * including the product details and any attributes associated with the items.
     *
     * @return array An array of detailed items, each including the product ID, quantity,
     *               and additional details such as product attributes and their values.
     */
    public function getDetailedItems(): array
    {
        $items = [];

        foreach ($this->cartData as $productId => $attributes) {
            // Retrieve product details from the database
            $product = sProduct::find($productId);

            // If product is not found, skip this item
            if (!$product) {
                continue;
            }

            // Get attributes for the product in the cart
            $productAttributes = $this->getProductAttributes($productId, $attributes);

            $items[] = [
                'product_id' => $productId,
                'product' => $product,
                'quantity' => $attributes['quantity'] ?? 1, // Default to 1 if quantity is not set
                'attributes' => $productAttributes
            ];
        }

        return $items;
    }

    /**
     * Retrieve attributes for a given product ID from the cart data.
     *
     * @param int $productId The ID of the product.
     * @param array $attributes The attributes associated with the product in the cart.
     * @return array An array of attributes and their values for the product.
     */
    private function getProductAttributes(int $productId, array $attributes): array
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
    }

    /**
     * Add a product to the cart.
     *
     * @param int $productId The ID of the product to add.
     * @param array $attributes The attributes associated with the product.
     * @param int $quantity The quantity of the product to add.
     * @return void
     */
    public function addProduct(int $productId, array $attributes = [], int $quantity = 1): void
    {
        if (!isset($this->cartData[$productId])) {
            $this->cartData[$productId] = [
                'quantity' => 0,
                'attributes' => []
            ];
        }

        $this->cartData[$productId]['quantity'] += $quantity;
        $this->cartData[$productId]['attributes'] = array_merge($this->cartData[$productId]['attributes'], $attributes);

        $this->saveCartData();
    }

    /**
     * Remove a product from the cart.
     *
     * @param int $productId The ID of the product to remove.
     * @return void
     */
    public function removeProduct(int $productId): void
    {
        unset($this->cartData[$productId]);
        $this->saveCartData();
    }

    /**
     * Update the quantity of a product in the cart.
     *
     * @param int $productId The ID of the product.
     * @param int $quantity The new quantity of the product.
     * @return void
     */
    public function updateQuantity(int $productId, int $quantity): void
    {
        if (isset($this->cartData[$productId])) {
            $this->cartData[$productId]['quantity'] = $quantity;
            $this->saveCartData();
        }
    }

    /**
     * Get the total number of items in the cart.
     *
     * @return int The total number of items in the cart.
     */
    public function getTotalItems(): int
    {
        $total = 0;

        foreach ($this->cartData as $item) {
            $total += $item['quantity'];
        }

        return $total;
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
        session(['cart' => $this->cartData]);
    }
}
