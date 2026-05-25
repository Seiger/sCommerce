<?php namespace Seiger\sCommerce\Cart;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sProduct;

class sCart
{
    protected $cartData;
    protected $productDetails = [
        'id',
        'sku',
        'link',
        'title',
        'introtext',
        'coverSrc',
        'category',
        'rating',
        'reviewsCount',
        'inventory',
        'price',
        'priceAsFloat',
        'oldPrice',
        'oldPriceAsFloat',
    ];

    public function __construct()
    {
        $this->cartData = $this->loadCartData();
    }

    /**
     * Set the default pricing mode for the current customer session.
     */
    public function setPriceMode(string $priceMode = 'auto'): void
    {
        if (!isset($_SESSION['sCommerce']) || !is_array($_SESSION['sCommerce'])) {
            $_SESSION['sCommerce'] = [];
        }

        $_SESSION['sCommerce']['priceMode'] = $this->normalizePriceMode($priceMode);
    }

    /**
     * Get the default pricing mode for the current customer session.
     */
    public function getPriceMode(): string
    {
        return $this->getSessionPriceMode();
    }

    /**
     * Reset the customer session to the default retail pricing behavior.
     */
    public function clearPriceMode(): void
    {
        unset($_SESSION['sCommerce']['priceMode'], $_SESSION['sCommercePriceMode']);
    }

    /**
     * Add a product to the cart.
     *
     * @param int $productId The ID of the product to add.
     * @param int $optionId The ID option associated with the product.
     * @param int $quantity The quantity of the product to add.
     * @return void
     */
    public function addProduct(int $productId = 0, int $optionId = 0, int $quantity = 1, ?string $trigger = null): array
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
            Log::channel('scommerce')->warning('sCart - ' . $message);

            return [
                'success' => false,
                'trigger' => $trigger,
                'message' =>  $message,
            ];
        }

        $this->cartData[$productId][$optionId] = $quantity;

        if (sCommerce::config('product.inventory_on', 0)) {
            if ($product->inventory < 0) {
                $message = __('sCommerce::global.product_title_is_out_of_stock', ['title' => $product->title]);
                Log::channel('scommerce')->alert('sCart - ' . $message);

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
                $message = __('sCommerce::global.product_with_title', ['title' => $product->title]) . ' ' . __('sCommerce::global.added_to_cart') . '.';
                break;
        }

        return [
            'success' => true,
            'trigger' => $trigger,
            'message' => $message,
            'product' => array_merge($this->getProductFields($product, $this->resolveProductPricing($product, $optionId)), compact('quantity')),
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
            $productId = request()->input('productId', 0);

            if ($productId === 'all') {
                $this->cartData = [];
                $this->saveCartData();

                return [
                    'success' => true,
                    'message' => __('sCommerce::global.removed_from_cart'),
                    'miniCart' => $this->getMiniCart(),
                ];
            }
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
            foreach ($this->cartData[$product->id] as $optionId => $cartItem) {
                $quantity = $this->getCartItemQuantity($cartItem);
                $pricing = $this->resolveProductPricing($product, (int)$optionId);
                $items[] = array_merge($this->getProductFields($product, $pricing), compact('quantity'));
                $price = (float)$pricing['priceAsFloat'];
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
    private function getProductFields(object $product, array $pricing = []): ?array
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

        $productFields = array_merge($product->only($this->productDetails), $pricing);

        return array_merge($productFields, $attributes);
    }

    /**
     * Resolve product pricing from session mode and optional project hooks.
     */
    private function resolveProductPricing(object $product, int $optionId = 0): array
    {
        $currency = sCommerce::currentCurrency();
        $priceMode = $this->resolveProductPriceMode($product, $optionId);
        $pricing = [
            'priceMode' => $priceMode,
            'price' => $product->priceTo($currency, $priceMode),
            'priceAsFloat' => $product->priceToNumber($currency, $priceMode),
            'oldPrice' => $product->oldPriceTo($currency, $priceMode),
            'oldPriceAsFloat' => $product->oldPriceToNumber($currency, $priceMode),
        ];

        foreach (Event::dispatch('evolution.sCommerceResolveProductPrice', [[
            'product' => $product,
            'optionId' => $optionId,
            'priceMode' => $priceMode,
            'currency' => $currency,
            'pricing' => $pricing,
        ]]) as $override) {
            if (is_numeric($override)) {
                $pricing['priceAsFloat'] = (float)$override;
                $pricing['price'] = sCommerce::convertPrice($pricing['priceAsFloat']);
                $pricing['oldPrice'] = '';
                $pricing['oldPriceAsFloat'] = 0;
                break;
            }

            if (is_array($override)) {
                $pricing = array_replace($pricing, array_intersect_key($override, $pricing));
                $pricing['priceAsFloat'] = (float)($pricing['priceAsFloat'] ?? 0);
                $pricing['oldPriceAsFloat'] = (float)($pricing['oldPriceAsFloat'] ?? 0);

                if (!isset($override['price'])) {
                    $pricing['price'] = sCommerce::convertPrice($pricing['priceAsFloat']);
                }

                if (!isset($override['oldPrice'])) {
                    $pricing['oldPrice'] = $pricing['oldPriceAsFloat'] > 0
                        ? sCommerce::convertPrice($pricing['oldPriceAsFloat'])
                        : '';
                }

                $pricing['priceMode'] = $this->normalizePriceMode((string)($pricing['priceMode'] ?? $priceMode));
                break;
            }
        }

        if ($pricing['priceMode'] === 'auto') {
            unset($pricing['priceMode']);
        }

        return $pricing;
    }

    private function getCartItemQuantity(mixed $cartItem): int
    {
        return is_array($cartItem) ? max(1, (int)($cartItem['quantity'] ?? 1)) : max(1, (int)$cartItem);
    }

    private function resolveProductPriceMode(object $product, int $optionId = 0): string
    {
        $priceMode = $this->getSessionPriceMode();

        foreach (Event::dispatch('evolution.sCommerceResolveProductPriceMode', [[
            'product' => $product,
            'optionId' => $optionId,
            'priceMode' => $priceMode,
        ]]) as $override) {
            if (is_string($override) && trim($override) !== '') {
                $priceMode = $this->normalizePriceMode($override);
                break;
            }
        }

        return $priceMode;
    }

    private function getSessionPriceMode(): string
    {
        $sessionPriceMode = is_array($_SESSION['sCommerce'] ?? null)
            ? ($_SESSION['sCommerce']['priceMode'] ?? null)
            : null;

        return $this->normalizePriceMode(
            (string)($sessionPriceMode ?? $_SESSION['sCommercePriceMode'] ?? 'auto')
        );
    }

    private function normalizePriceMode(string $priceMode = 'auto'): string
    {
        $priceMode = strtolower(trim($priceMode));

        return in_array($priceMode, ['wholesale', 'opt'], true) ? 'wholesale' : 'auto';
    }
}
