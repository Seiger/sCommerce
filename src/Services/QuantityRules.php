<?php namespace Seiger\sCommerce\Services;

/**
 * Effective quantity rules of a product or of a stored order line.
 *
 * Quantities are whole base units: grams, millimetres, millilitres, or items for `count`.
 * The price refers to `factor` base units: `unitFactor` base units per price unit times the
 * price basis `pricePer` (`€2.49 / 100 g`: unitFactor 1, pricePer 100, factor 100).
 *
 * @since 1.5.0
 */
final readonly class QuantityRules
{
    /**
     * @param string $measure `piece`, `weight`, `length`, `volume` or `count`.
     * @param string $baseUnit `g`, `mm`, `ml`, `unit`; empty for pieces.
     * @param string $priceUnit Unit code the price refers to, e.g. `kg`; empty for pieces.
     * @param int $tolerance Allowed actual-quantity overrun in basis points.
     * @param string $rounding Storefront rounding to the step: `up`, `nearest` or `down`.
     */
    public function __construct(
        public string $measure,
        public string $baseUnit,
        public string $priceUnit,
        public int $unitFactor,
        public int $pricePer,
        public int $factor,
        public int $step,
        public int $min,
        public int $max,
        public int $tolerance,
        public int $inputDecimals,
        public string $rounding,
    ) {
    }

    /**
     * Whether the quantity is a measurement rather than a piece count.
     *
     * @since 1.5.0
     */
    public function isMeasured(): bool
    {
        return $this->measure !== MeasuredQuantity::PIECE;
    }

    /**
     * Rule keys of an order-line snapshot; empty for pieces.
     *
     * @return array<string, int|string>
     * @since 1.5.0
     */
    public function lineKeys(): array
    {
        if (!$this->isMeasured()) {
            return [];
        }

        return [
            'measure' => $this->measure,
            'base_unit' => $this->baseUnit,
            'price_unit' => $this->priceUnit,
            'price_per' => $this->pricePer,
            'factor' => $this->factor,
            'quantity_rounding' => $this->rounding,
            'quantity_step' => $this->step,
            'quantity_min' => $this->min,
            'quantity_tolerance' => $this->tolerance,
        ];
    }
}
