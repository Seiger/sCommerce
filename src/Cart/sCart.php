<?php namespace Seiger\sCommerce\Cart;

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
        'inventory',
        'price',
        'oldPrice'
    ];

    public function __construct()
    {
        $this->cartData = $this->loadCartData();
    }

    /**
     * Add a product to the cart.
     *
     * @param int $productId The ID of the product to add.
     * @param int $optionId The ID option associated with the product.
     * @param int $quantity The quantity of the product to add.
     * @return void
     */
    public function addProduct(int $productId = 0, int $optionId = 0, int $quantity = 1, string $trigger = null): array
    {
        if ($productId === 0) {
            $productId = request()->integer('productId', $productId);
        }

        if (!$trigger) {
            $trigger = request()->string('trigger')->trim()->value() ?? 'buy';
        }

        $optionId = request()->integer('optionId', $optionId);
        $quantity = max(request()->integer('quantity'), $quantity);

        $product = sCommerce::getProduct($productId);

        if (!$product || !$product->id) {
            $message = __('sCommerce::global.product_with_id', ['id' => $productId]) . __('sCommerce::global.not_found') . '.';
            Log::warning('sCart - ' . $message);

            return [
                'success' => false,
                'trigger' => $trigger,
                'message' =>  $message,
            ];
        }

        if (!isset($this->cartData[$productId][$optionId])) {
            $this->cartData[$productId][$optionId] = $quantity;
        }

        if (sCommerce::config('product.inventory_on', 0)) {
            if ($product->inventory < 0) {
                $message = __('sCommerce::global.product_title_is_out_of_stock', ['title' => $product->title]);
                Log::alert('sCart - ' . $message);

                return [
                    'success' => false,
                    'trigger' => $trigger,
                    'message' =>  $message,
                ];
            } else {
                $quantity = min($quantity, $product->inventory);
            }
        }

        $this->cartData[$productId][$optionId] = $quantity;
        $this->saveCartData();

        switch ($trigger) {
            case 'quantity':
                $message = __('sCommerce::global.product_with_id', ['id' => $productId]) . ' ' . __('sCommerce::global.changed_quantity') . '.';
                break;
            default:
                $message = __('sCommerce::global.product_with_id', ['id' => $productId]) . ' ' . __('sCommerce::global.added_to_cart') . '.';
                break;
        }

        return [
            'success' => true,
            'trigger' => $trigger,
            'message' => $message,
            'product' => array_merge($this->getProductFields($product), compact('quantity')),
            'miniCart' => $this->getMiniCart(),
        ];
    }

    /**
     * Remove a product from the cart.
     *
     * @param int $productId The ID of the product to remove.
     * @return void
     */
    public function removeProduct(int $productId = 0): array
    {
        if ($productId === 0) {
            $productId = request()->integer('productId');
        }

        unset($this->cartData[$productId]);
        $this->saveCartData();

        $message = __('sCommerce::global.product_with_id', ['id' => $productId]) . ' ' . __('sCommerce::global.removed_from_cart') . '.';

        return [
            'success' => true,
            'message' => $message,
            'product' => ['id' => $productId],
            'miniCart' => $this->getMiniCart(),
        ];
    }

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
                $items[] = array_merge($this->getProductFields($product), compact('quantity'));
                $price = sCommerce::convertPriceNumber($product->price, $product->currency, sCommerce::currentCurrency());
                $totalSum += $price * $quantity;
            }
        }

        $cart['totalSum'] = round($totalSum, 2);
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
        return $_SESSION['sCart'] ?? [];
    }

    /**
     * Save the cart data to the session or database.
     *
     * @return void
     */
    protected function saveCartData(): void
    {
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
}
