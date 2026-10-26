<?php namespace Seiger\sCommerce\Services;

use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Facades\sCommerce;

/**
 * Storefront display of prices with or without VAT.
 *
 * Stored prices include VAT. This only adds display values: the net price is
 * `gross / (1 + rate / 100)`, and the price that is charged never changes.
 * The visitor's mode comes from the session (`sCart::setVatDisplay()`), else from
 * `product.vat_display`; `sCommerce.ResolveVatDisplay` listeners may override it,
 * e.g. for logged-in business customers. The rate comes from `product.vat_rate`;
 * `sCommerce.ResolveProductVatRate` listeners may return another rate per product.
 *
 * Both are optional features: with `product.vat_display_on` off, prices are always shown with VAT
 * and the rate is 0; with `product.retail_excise_on` off, no excise is calculated.
 *
 * Retail excise (`product.retail_excise_rate`, per product via `sCommerce.ResolveProductRetailExciseRate`)
 * is charged on the price with VAT and included in the stored price: for a stored price P,
 * excise = P × r / (100 + r), and the price without VAT is (P − excise) / (1 + VAT / 100).
 *
 * @since 1.5.0
 */
final class VatDisplay
{
    public const GROSS = 'gross';
    public const NET = 'net';
    public const RATE_EVENT = 'sCommerce.ResolveProductVatRate';
    public const MODE_EVENT = 'sCommerce.ResolveVatDisplay';
    public const EXCISE_EVENT = 'sCommerce.ResolveProductRetailExciseRate';

    /**
     * Whether prices may be shown without VAT (`product.vat_display_on`).
     *
     * @since 1.5.0
     */
    public static function enabled(): bool
    {
        return (int)sCommerce::config('product.vat_display_on', 0) === 1;
    }

    /**
     * Whether the retail excise is used (`product.retail_excise_on`).
     *
     * @since 1.5.0
     */
    public static function exciseEnabled(): bool
    {
        return (int)sCommerce::config('product.retail_excise_on', 0) === 1;
    }

    /**
     * Display mode of the current visitor: `gross` or `net`; always `gross` while the feature is off.
     *
     * @since 1.5.0
     */
    public static function mode(): string
    {
        if (!self::enabled()) {
            return self::GROSS;
        }
        $session = is_array($_SESSION['sCommerce'] ?? null) ? ($_SESSION['sCommerce']['vatDisplay'] ?? null) : null;
        $mode = self::normalize((string)($session ?? sCommerce::config('product.vat_display', self::GROSS)));

        foreach (Event::dispatch(self::MODE_EVENT, [['mode' => $mode]]) as $override) {
            if (is_string($override) && in_array(strtolower(trim($override)), [self::GROSS, self::NET], true)) {
                return strtolower(trim($override));
            }
        }

        return $mode;
    }

    /**
     * Remember the visitor's display mode in the session.
     *
     * @since 1.5.0
     */
    public static function setMode(string $mode): void
    {
        if (!isset($_SESSION['sCommerce']) || !is_array($_SESSION['sCommerce'])) {
            $_SESSION['sCommerce'] = [];
        }
        $_SESSION['sCommerce']['vatDisplay'] = self::normalize($mode);
    }

    /**
     * Return the visitor to the shop default.
     *
     * @since 1.5.0
     */
    public static function clearMode(): void
    {
        unset($_SESSION['sCommerce']['vatDisplay']);
    }

    /**
     * VAT rate of a product in percent; the first numeric listener response from 0 to 100 wins.
     *
     * @param object $product Product model or object.
     * @param int $optionId Cart option id; 0 = none.
     * @since 1.5.0
     */
    public static function rate(object $product, int $optionId = 0): float
    {
        if (!self::enabled()) {
            return 0.0;
        }
        $rate = self::validRate(sCommerce::config('product.vat_rate', 0)) ?? 0.0;

        foreach (Event::dispatch(self::RATE_EVENT, [['product' => $product, 'optionId' => $optionId, 'rate' => $rate]]) as $override) {
            if (($valid = self::validRate($override)) !== null) {
                return $valid;
            }
        }

        return $rate;
    }

    /**
     * Retail excise rate of a product in percent; the first numeric listener response from 0 to 100 wins.
     *
     * @param object $product Product model or object.
     * @param int $optionId Cart option id; 0 = none.
     * @since 1.5.0
     */
    public static function exciseRate(object $product, int $optionId = 0): float
    {
        if (!self::exciseEnabled()) {
            return 0.0;
        }
        $rate = self::validRate(sCommerce::config('product.retail_excise_rate', 0)) ?? 0.0;

        foreach (Event::dispatch(self::EXCISE_EVENT, [['product' => $product, 'optionId' => $optionId, 'rate' => $rate]]) as $override) {
            if (($valid = self::validRate($override)) !== null) {
                return $valid;
            }
        }

        return $rate;
    }

    /**
     * Retail excise included in an amount with VAT and excise, rounded to cents.
     *
     * @since 1.5.0
     */
    public static function excise(float $gross, float $exciseRate): float
    {
        return $exciseRate > 0 ? round($gross * $exciseRate / (100 + $exciseRate), 2) : 0.0;
    }

    /**
     * Amount without VAT (and without the included retail excise), rounded to cents.
     *
     * @since 1.5.0
     */
    public static function net(float $gross, float $rate, float $exciseRate = 0.0): float
    {
        $base = $gross - self::excise($gross, $exciseRate);

        return $rate > 0 ? round($base / (1 + $rate / 100), 2) : round($base, 2);
    }

    /**
     * Display keys for a resolved price.
     *
     * @param object $product Product model or object.
     * @param float $gross Effective price (with VAT) in `$currency`.
     * @param float $oldGross Old price (with VAT) in `$currency`; 0 = none.
     * @param string $currency Display currency.
     * @param int $optionId Cart option id; 0 = none.
     * @return array<string, float|string>
     * @since 1.5.0
     */
    public static function pricing(object $product, float $gross, float $oldGross, string $currency, int $optionId = 0): array
    {
        return self::values($gross, $oldGross, self::rate($product, $optionId), self::mode(), $currency, self::exciseRate($product, $optionId));
    }

    /**
     * Display keys for given amounts, rate and mode.
     *
     * @return array<string, float|string>
     * @since 1.5.0
     */
    public static function values(float $gross, float $oldGross, float $rate, string $mode, string $currency, float $exciseRate = 0.0): array
    {
        $net = self::net($gross, $rate, $exciseRate);
        $oldNet = $oldGross > 0 ? self::net($oldGross, $rate, $exciseRate) : 0.0;
        $excise = self::excise($gross, $exciseRate);
        $format = static fn (float $value): string => sCommerce::convertPrice($value, $currency, $currency);
        $isNet = $mode === self::NET;

        return [
            'vatRate' => $rate,
            'vatDisplay' => $mode,
            'priceGrossAsFloat' => $gross,
            'priceNetAsFloat' => $net,
            'priceGross' => $format($gross),
            'priceNet' => $format($net),
            'oldPriceGrossAsFloat' => $oldGross,
            'oldPriceNetAsFloat' => $oldNet,
            'oldPriceGross' => $oldGross > 0 ? $format($oldGross) : '',
            'oldPriceNet' => $oldNet > 0 ? $format($oldNet) : '',
            'displayPriceAsFloat' => $isNet ? $net : $gross,
            'displayPrice' => $format($isNet ? $net : $gross),
            'displayOldPriceAsFloat' => $isNet ? $oldNet : $oldGross,
            'displayOldPrice' => ($isNet ? $oldNet : $oldGross) > 0 ? $format($isNet ? $oldNet : $oldGross) : '',
            'retailExciseRate' => $exciseRate,
            'retailExciseAmountAsFloat' => $excise,
            'retailExciseAmount' => $excise > 0 ? $format($excise) : '',
        ];
    }

    /**
     * Display-only keys that must not be stored in order lines.
     *
     * @return list<string>
     * @since 1.5.0
     */
    public static function keys(): array
    {
        return ['vatRate', 'vatDisplay', 'priceGrossAsFloat', 'priceNetAsFloat', 'priceGross', 'priceNet', 'oldPriceGrossAsFloat',
            'oldPriceNetAsFloat', 'oldPriceGross', 'oldPriceNet', 'displayPriceAsFloat', 'displayPrice', 'displayOldPriceAsFloat',
            'displayOldPrice', 'retailExciseRate', 'retailExciseAmountAsFloat', 'retailExciseAmount', 'sumNetAsFloat', 'sumNet',
            'displaySumAsFloat', 'displaySum', 'sumRetailExciseAsFloat', 'sumRetailExcise'];
    }

    private static function normalize(string $mode): string
    {
        return strtolower(trim($mode)) === self::NET ? self::NET : self::GROSS;
    }

    private static function validRate(mixed $rate): ?float
    {
        return is_numeric($rate) && (float)$rate >= 0 && (float)$rate <= 100 ? (float)$rate : null;
    }
}
