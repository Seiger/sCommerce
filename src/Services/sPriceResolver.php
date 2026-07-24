<?php namespace Seiger\sCommerce\Services;

use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\sCommerce;
use Traversable;

/**
 * Resolve and apply the effective storefront price for sCommerce products.
 *
 * Optional packages subscribe to the public pricing event. This keeps the
 * legacy sCommerce contract intact while allowing sPricing to own its price
 * strategy and cache.
 *
 * @since 1.0.0
 */
final class sPriceResolver
{
    public function __construct(private readonly sCommerce $commerce)
    {
    }

    /**
     * Resolve a product's effective storefront price.
     *
     * @param object $product Product to price.
     * @param string|null $currency Requested display currency.
     * @param string $priceMode Legacy sCommerce price mode.
     * @param int $optionId Product option identifier, when applicable.
     * @return array{priceAsFloat: float, oldPriceAsFloat: float, price: string, oldPrice: string, priceMode?: string}
     */
    public function resolve(
        object $product,
        ?string $currency = null,
        string $priceMode = 'auto',
        int $optionId = 0,
    ): array {
        $currency = strtoupper($currency ?: sCommerce::currentCurrency());
        $legacyPrice = method_exists($product, 'legacyPriceToNumber')
            ? $product->legacyPriceToNumber($currency, $priceMode)
            : $product->priceToNumber($currency, $priceMode);
        $legacyOldPrice = method_exists($product, 'legacyOldPriceToNumber')
            ? $product->legacyOldPriceToNumber($currency, $priceMode)
            : $product->oldPriceToNumber($currency, $priceMode);
        $hasCustomPrice = false;
        $hasCustomOldPrice = false;
        $pricing = [
            'priceMode' => $priceMode,
            'priceAsFloat' => $legacyPrice,
            'oldPriceAsFloat' => $legacyOldPrice,
            'price' => '',
            'oldPrice' => '',
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
                $pricing['oldPriceAsFloat'] = 0.0;
                $hasCustomPrice = true;
                $hasCustomOldPrice = true;
                break;
            }

            if (is_array($override)) {
                $hasCustomPrice = array_key_exists('price', $override);
                $hasCustomOldPrice = array_key_exists('oldPrice', $override);
                $pricing = array_replace($pricing, array_intersect_key($override, $pricing));
                $pricing['priceAsFloat'] = (float)($pricing['priceAsFloat'] ?? 0);
                $pricing['oldPriceAsFloat'] = max(0, (float)($pricing['oldPriceAsFloat'] ?? 0));
                $pricing['priceMode'] = $this->normalizePriceMode((string)($pricing['priceMode'] ?? $priceMode));
                break;
            }
        }

        if (!$hasCustomPrice) {
            $pricing['price'] = $this->commerce->convertPrice($pricing['priceAsFloat'], $currency, $currency);
        }
        if (!$hasCustomOldPrice) {
            $pricing['oldPrice'] = $pricing['oldPriceAsFloat'] > 0
                ? $this->commerce->convertPrice($pricing['oldPriceAsFloat'], $currency, $currency)
                : '';
        }

        if ($pricing['priceMode'] === 'auto') {
            unset($pricing['priceMode']);
        }

        return $pricing;
    }

    /**
     * Apply request-scoped effective prices to cached product objects.
     *
     * @param mixed $value A product, collection, paginator, or nested array.
     * @param string|null $currency Requested display currency.
     * @return void
     */
    public function hydrate(mixed $value, ?string $currency = null): void
    {
        if ($value instanceof sProduct) {
            $currency = strtoupper($currency ?: sCommerce::currentCurrency());
            $value->applyResolvedPricing($this->resolve($value, $currency), $currency);
            return;
        }

        if ($value instanceof Traversable || is_array($value)) {
            foreach ($value as $item) {
                $this->hydrate($item, $currency);
            }
        }
    }

    /**
     * Normalize legacy price-mode aliases.
     *
     * @param string $priceMode Price mode to normalize.
     * @return string
     */
    private function normalizePriceMode(string $priceMode): string
    {
        return in_array(strtolower(trim($priceMode)), ['wholesale', 'opt'], true)
            ? 'wholesale'
            : 'auto';
    }
}
