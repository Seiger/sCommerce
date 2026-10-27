<?php namespace Seiger\sCommerce\Cart;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Services\ContainerDeposit;
use Seiger\sCommerce\Services\MeasuredQuantity;
use Seiger\sCommerce\Services\MeasuredQuantityException;
use Seiger\sCommerce\Services\OrderLineIdentity;
use Seiger\sCommerce\Services\QuantityRules;
use Seiger\sCommerce\Services\StockResolver;
use Seiger\sCommerce\Services\VatDisplay;
use Seiger\sCommerce\Services\sPriceResolver;

/**
 * Build cart payloads from configured product fields, pricing and displayed attributes.
 */
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
     * Show prices with (`gross`) or without (`net`) VAT to the current visitor.
     * Display only: the charged price does not change.
     *
     * @since 1.5.0
     */
    public function setVatDisplay(string $mode): void
    {
        VatDisplay::setMode($mode);
    }

    /**
     * VAT display mode of the current visitor: `gross` or `net`.
     *
     * @since 1.5.0
     */
    public function getVatDisplay(): string
    {
        return VatDisplay::mode();
    }

    /**
     * Return the visitor to the shop's default VAT display.
     *
     * @since 1.5.0
     */
    public function clearVatDisplay(): void
    {
        VatDisplay::clearMode();
    }

    /**
     * Add a product to the cart.
     *
     * Quantities are whole numbers: pieces, or base units (grams, millimetres, millilitres,
     * items) for products sold by measure. A request `amount` in the price unit (`2.53` metres)
     * takes precedence over `quantity` for those products; their quantity is fitted to the
     * minimum, step and stock, and the response reports an adjustment with `quantityAdjusted`.
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

        $rules = MeasuredQuantity::rules($product);
        $adjusted = false;
        if ($rules->isMeasured()) {
            $requested = $quantity;
            if (is_scalar($amount = request()->input('amount')) && trim((string)$amount) !== '') {
                try {
                    $requested = MeasuredQuantity::parseAmount((string)$amount, $rules);
                } catch (MeasuredQuantityException $e) {
                    return ['success' => false, 'trigger' => $trigger, 'message' => $e->translated()];
                }
            }
            $quantity = MeasuredQuantity::normalize($requested, $rules);
            $adjusted = $quantity !== $requested;
        }

        $this->storeQuantity($productId, $optionId, $quantity);

        // Virtual and service lines are not counted against inventory.
        if (sCommerce::config('product.inventory_on', 0) && $this->tracksInventory($product, $optionId)) {
            $available = app(StockResolver::class)->available($product, 0, ['optionId' => $optionId, 'quantity' => $quantity]);
            // Measured stock is capped in whole steps and must cover the minimum.
            $capped = $rules->isMeasured() ? MeasuredQuantity::normalize($quantity, $rules, MeasuredQuantity::MODE_CLAMP, $available) : null;
            if ($available < 0 || ($capped !== null && $capped < $rules->min)) {
                $message = __('sCommerce::global.product_title_is_out_of_stock', ['title' => $product->title]);
                Log::channel('scommerce')->alert('sCart - ' . $message);

                return [
                    'success' => false,
                    'trigger' => $trigger,
                    'message' =>  $message,
                ];
            } elseif ($capped !== null) {
                $adjusted = $adjusted || $capped !== $quantity;
                $quantity = $capped;
            } else {
                $quantity = min($quantity, $available);
            }
        }

        $this->storeQuantity($productId, $optionId, $quantity);
        $this->saveCartData();

        switch ($trigger) {
            case 'quantity':
                $message = __('sCommerce::global.product_with_id', ['id' => $productId]) . ' ' . __('sCommerce::global.changed_quantity') . '.';
                break;
            default:
                $message = __('sCommerce::global.product_with_title', ['title' => $product->title]) . ' ' . __('sCommerce::global.added_to_cart') . '.';
                break;
        }
        if ($adjusted && $trigger !== 'quantity') {
            $message .= ' ' . __('sCommerce::global.changed_quantity') . ': ' . MeasuredQuantity::label($quantity, $rules) . '.';
        }

        $response = [
            'success' => true,
            'trigger' => $trigger,
            'message' => $message,
            'product' => array_merge($this->getProductFields($product, $pricing = $this->resolveProductPricing($product, $optionId)),
                $this->vatPricing($product, $pricing, $optionId), compact('quantity'),
                MeasuredQuantity::lineFields($rules, $quantity, (string)($pricing['price'] ?? ''))),
            'miniCart' => $this->getMiniCart(),
        ];
        if ($adjusted) {
            $response['quantityAdjusted'] = true;
        }

        return $response;
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
     * Set how many empty returnable containers the customer exchanges for a cart line.
     *
     * Exchanged units carry no new deposit. The count is the customer's declaration, capped at
     * the line quantity; the courier or manager corrects the order when it differs.
     *
     * @param int $productId Product id; read from the request when 0.
     * @param int $optionId Cart option id; read from the request when not given.
     * @param int|null $exchange Number of containers; read from the request (`quantity`) when null.
     * @return array{success: bool, message: string, miniCart?: array}
     * @since 1.5.0
     */
    public function setContainerExchange(int $productId = 0, ?int $optionId = null, ?int $exchange = null): array
    {
        $productId = $productId ?: request()->integer('productId');
        $optionId ??= request()->integer('optionId');
        $exchange ??= request()->integer('quantity');
        $item = $this->cartData[$productId][$optionId] ?? null;
        if ($item === null) {
            return ['success' => false, 'message' => __('sCommerce::global.product_with_id', ['id' => $productId]) . __('sCommerce::global.not_found') . '.'];
        }

        $quantity = $this->getCartItemQuantity($item);
        $this->cartData[$productId][$optionId] = ['quantity' => $quantity, 'exchange' => max(0, min($exchange, $quantity))];
        $this->saveCartData();

        return ['success' => true, 'message' => '', 'miniCart' => $this->getMiniCart()];
    }

    /**
     * Get the cart items and totals in the current currency.
     *
     * Each item includes the numeric `sumAsFloat` and formatted `sum`
     * calculated from its effective price and quantity, the line identity
     * (`line_key`, `product_id`, `option_id`, `sku`, `product_sku`, `gtin`)
     * and the fulfilment flags `requires_shipping` and `tracks_inventory`.
     * Display keys add the prices and sums without VAT (`priceNet`, `sumNet`, `totalSumNet`)
     * and in the visitor's VAT display mode (`displayPrice`, `displaySum`, `totalSumDisplay`), and the
     * retail excise included in the prices (`retailExciseAmount`, `sumRetailExcise`, `totalRetailExcise`).
     * Lines with a returnable container deposit carry the deposit keys of {@see ContainerDeposit};
     * `totalSum` is what the customer pays: `totalGoods` plus `totalDeposit`.
     * Lines of products sold by measure carry their quantity in base units, the rule keys of
     * {@see QuantityRules::lineKeys()}, `quantity_label`, `unit_price_label` and `line_weight`;
     * their sum is rounded half up once from the unrounded price ({@see MeasuredQuantity}).
     *
     * @return array{totalSum: float, totalSumFormatted: string, totalSumNet: float, totalSumNetFormatted: string, totalSumDisplay: float, totalSumDisplayFormatted: string, totalRetailExcise: float, totalRetailExciseFormatted: string, vatDisplay: string, items: array<int, array<string, mixed>>}
     */
    public function getMiniCart(): array
    {
        $totalSum = 0;
        $totalNet = 0.0;
        $totalExcise = 0.0;
        $totalDeposit = 0.0;
        $currency = sCommerce::currentCurrency();
        $vatDisplay = VatDisplay::mode();
        $items = [];
        $productIds = array_keys($this->cartData);
        $products = sCommerce::getProducts($productIds);

        foreach ($products as $product) {
            $rules = MeasuredQuantity::rules($product);
            foreach ($this->cartData[$product->id] as $optionId => $cartItem) {
                $quantity = $this->getCartItemQuantity($cartItem, $rules->min);
                $deposit = ContainerDeposit::lineFields(ContainerDeposit::unit($product, (int)$optionId, $currency), $quantity,
                    is_array($cartItem) ? (int)($cartItem['exchange'] ?? 0) : 0);
                $pricing = $this->resolveProductPricing($product, (int)$optionId);
                $price = (float)$pricing['priceAsFloat'];
                $lineSum = $rules->isMeasured()
                    ? MeasuredQuantity::lineTotalMinor(MeasuredQuantity::scalePrice($price), $quantity, $rules->factor) / 100
                    : $price * $quantity;
                $sumAsFloat = round($lineSum, 3);
                $sum = sCommerce::convertPrice($sumAsFloat);
                $vat = $this->vatPricing($product, $pricing, (int)$optionId);
                $sumNetAsFloat = VatDisplay::net($sumAsFloat, (float)$vat['vatRate'], (float)$vat['retailExciseRate']);
                $sumExciseAsFloat = VatDisplay::excise($sumAsFloat, (float)$vat['retailExciseRate']);
                $displaySumAsFloat = $vatDisplay === VatDisplay::NET ? $sumNetAsFloat : $sumAsFloat;
                $item = array_merge(
                    $this->getProductFields($product, $pricing),
                    app(OrderLineIdentity::class)->snapshot($product, (int)$optionId),
                    $vat,
                    $deposit,
                    compact('quantity', 'sumAsFloat', 'sum'),
                    ['sumNetAsFloat' => $sumNetAsFloat, 'sumNet' => sCommerce::convertPrice($sumNetAsFloat),
                        'displaySumAsFloat' => $displaySumAsFloat, 'displaySum' => sCommerce::convertPrice($displaySumAsFloat),
                        'sumRetailExciseAsFloat' => $sumExciseAsFloat, 'sumRetailExcise' => $sumExciseAsFloat > 0 ? sCommerce::convertPrice($sumExciseAsFloat) : '']
                );
                if ($rules->isMeasured()) {
                    $item += MeasuredQuantity::lineFields($rules, $quantity, (string)($pricing['price'] ?? ''), (int)($item['weight'] ?? 0));
                }
                $items[] = $item;
                $totalSum += $lineSum;
                $totalNet += $sumNetAsFloat;
                $totalExcise += $sumExciseAsFloat;
                $totalDeposit += (float)($deposit['deposit_amount'] ?? 0);
            }
        }
        $totalDeposit = round($totalDeposit, 2);

        $cart['totalGoods'] = round($totalSum, 3);
        $cart['totalGoodsFormatted'] = sCommerce::convertPrice($totalSum);
        $cart['totalDeposit'] = $totalDeposit;
        $cart['totalDepositFormatted'] = $totalDeposit > 0 ? sCommerce::convertPrice($totalDeposit) : '';
        // Deposits are paid with the order and are outside VAT.
        $totalSum += $totalDeposit;
        $totalNet += $totalDeposit;
        $cart['totalSum'] = round($totalSum, 3);
        $cart['totalSumFormatted'] = sCommerce::convertPrice($totalSum);
        $cart['totalSumNet'] = round($totalNet, 2);
        $cart['totalSumNetFormatted'] = sCommerce::convertPrice($cart['totalSumNet']);
        $cart['totalSumDisplay'] = $vatDisplay === VatDisplay::NET ? $cart['totalSumNet'] : $cart['totalSum'];
        $cart['totalSumDisplayFormatted'] = sCommerce::convertPrice($cart['totalSumDisplay']);
        $cart['totalRetailExcise'] = round($totalExcise, 2);
        $cart['totalRetailExciseFormatted'] = $totalExcise > 0 ? sCommerce::convertPrice($cart['totalRetailExcise']) : '';
        $cart['vatDisplay'] = $vatDisplay;
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
        $priceMode = $this->resolveProductPriceMode($product, $optionId);
        return app(sPriceResolver::class)->resolve($product, sCommerce::currentCurrency(), $priceMode, $optionId);
    }

    /**
     * Display keys of a resolved price with and without VAT.
     */
    private function vatPricing(object $product, array $pricing, int $optionId): array
    {
        return VatDisplay::pricing($product, (float)$pricing['priceAsFloat'], (float)($pricing['oldPriceAsFloat'] ?? 0), sCommerce::currentCurrency(), $optionId);
    }

    private function tracksInventory(object $product, int $optionId): bool
    {
        return !method_exists($product, 'fulfilment') || (bool)($product->fulfilment($optionId)['inventory'] ?? true);
    }

    /**
     * Store a line quantity, keeping the declared container exchange within it.
     */
    private function storeQuantity(int $productId, int $optionId, int $quantity): void
    {
        $item = $this->cartData[$productId][$optionId] ?? null;
        $this->cartData[$productId][$optionId] = is_array($item) && (int)($item['exchange'] ?? 0) > 0
            ? ['quantity' => $quantity, 'exchange' => min((int)$item['exchange'], $quantity)]
            : $quantity;
    }

    /**
     * Stored quantity of a cart line, at least `$min` (the minimum of a measured product).
     */
    private function getCartItemQuantity(mixed $cartItem, int $min = 1): int
    {
        return is_array($cartItem) ? max($min, (int)($cartItem['quantity'] ?? 1)) : max($min, (int)$cartItem);
    }

    private function resolveProductPriceMode(object $product, int $optionId = 0): string
    {
        $priceMode = $this->getSessionPriceMode();

        foreach (Event::dispatch('sCommerce.ResolveProductPriceMode', [[
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
