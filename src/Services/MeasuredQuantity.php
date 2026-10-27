<?php namespace Seiger\sCommerce\Services;

use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sProduct;

/**
 * Products sold by weight, length, volume or count, in whole base units.
 *
 * A measured quantity is an integer number of grams, millimetres, millilitres or items. The
 * price refers to `factor` base units (`€1.20 / m`: 1000 mm), and a line total is
 * `round_half_up(price × quantity ÷ factor)` in minor units, computed in integers only.
 * Piece products have `factor = 1` and keep their existing arithmetic.
 *
 * @since 1.5.0
 */
final class MeasuredQuantity
{
    public const PIECE = 'piece';
    public const MODE_CLAMP = 'clamp';
    public const MODE_STRICT = 'strict';
    public const ROUND_UP = 'up';
    public const ROUND_NEAREST = 'nearest';
    public const ROUND_DOWN = 'down';
    public const ROUNDINGS = [self::ROUND_UP, self::ROUND_NEAREST, self::ROUND_DOWN];

    /** Base unit of each measure. */
    public const BASE_UNITS = ['weight' => 'g', 'length' => 'mm', 'volume' => 'ml', 'count' => 'unit'];

    /** Price bases of weight, length and volume; the basis may not exceed one largest unit. */
    public const PRICE_BASES = [1, 10, 100, 1000];

    /** Price bases of `count`: per item, per thousand, per million. */
    public const COUNT_BASES = [1, 1000, 1000000];

    /** Largest quantity of a product that tracks stock (`s_products.inventory` is a signed INT). */
    public const INVENTORY_MAX = 2000000000;

    /** Largest quantity of a line without stock tracking. */
    public const QUANTITY_MAX = 1000000000000;

    /** Limits of piece lines, as before measured quantities. */
    public const PIECE_MAX = 1000000;

    /** Built-in site defaults, overridden by `product.measures`. */
    public const DEFAULTS = [
        'weight' => ['price_unit' => 'kg', 'price_per' => 1, 'step' => 1, 'min' => 1, 'max' => 1000000, 'tolerance' => 0, 'input_decimals' => 3],
        'length' => ['price_unit' => 'm', 'price_per' => 1, 'step' => 10, 'min' => 10, 'max' => 1000000, 'tolerance' => 0, 'input_decimals' => 2],
        'volume' => ['price_unit' => 'l', 'price_per' => 1, 'step' => 1, 'min' => 1, 'max' => 1000000, 'tolerance' => 0, 'input_decimals' => 3],
        'count' => ['price_unit' => 'unit', 'price_per' => 1000, 'step' => 1, 'min' => 1, 'max' => 1000000000, 'tolerance' => 0, 'input_decimals' => 0],
    ];

    /** Built-in rounding per price unit, overridden by `product.quantity_rounding`; missing units round up. */
    public const ROUNDING_DEFAULTS = [
        'kg' => self::ROUND_NEAREST, 'g' => self::ROUND_NEAREST,
        'm' => self::ROUND_UP, 'cm' => self::ROUND_UP, 'mm' => self::ROUND_UP,
        'l' => self::ROUND_UP, 'ml' => self::ROUND_UP,
        'unit' => self::ROUND_UP,
    ];

    /** Rule keys of measured order lines, see {@see QuantityRules::lineKeys()}. */
    public const LINE_KEYS = ['measure', 'base_unit', 'price_unit', 'price_per', 'factor', 'quantity_rounding', 'quantity_step', 'quantity_min', 'quantity_tolerance'];

    /** Display keys derived from the quantity. */
    public const DISPLAY_KEYS = ['quantity_label', 'unit_price_label', 'actual_quantity_label', 'line_weight'];

    /** Actual-quantity keys of a line, set by {@see OrderActualQuantity}. */
    public const ACTUAL_KEYS = ['actual_quantity', 'actual_quantity_at', 'actual_quantity_by', 'actual_quantity_source'];

    /** Product columns that hold the quantity settings. */
    public const PRODUCT_COLUMNS = ['measure', 'price_unit', 'price_per', 'quantity_step', 'quantity_min', 'quantity_tolerance', 'quantity_rounding'];

    /** English fallbacks when a language file lacks a key. */
    private const FALLBACK = [
        'unit_g' => 'g', 'unit_kg' => 'kg', 'unit_mm' => 'mm', 'unit_cm' => 'cm', 'unit_m' => 'm', 'unit_ml' => 'ml', 'unit_l' => 'l', 'unit_unit' => '',
        'number_decimal_separator' => '.', 'number_thousands_separator' => ',',
        'quantity_label' => ':quantity :unit', 'unit_price_label' => ':price / :basis :unit',
        'count_basis_1000' => '1,000', 'count_basis_1000000' => '1,000,000',
    ];

    /** @var array<string, string> Translations resolved in this process, by `locale|key`. */
    private static array $translations = [];

    /**
     * Effective rules of a product: its own columns, then the site defaults.
     *
     * @param object $product Product model or object with the quantity columns; missing columns mean a piece product.
     * @since 1.5.0
     */
    public static function rules(object $product): QuantityRules
    {
        return method_exists($product, 'quantityRules') ? $product->quantityRules() : self::resolve($product);
    }

    /**
     * Rules from product columns and site defaults, without the product's memoized copy.
     *
     * @internal Use {@see self::rules()}.
     */
    public static function resolve(object $product): QuantityRules
    {
        $measure = sProduct::MEASURE_ALIASES[(int)($product->measure ?? 0)] ?? self::PIECE;
        if ($measure === self::PIECE) {
            return self::piece();
        }
        $defaults = self::defaults($measure);
        $priceUnit = (string)($product->price_unit ?? '');
        if (self::unitFactor($measure, $priceUnit) === null) {
            $priceUnit = $defaults['price_unit'];
        }
        $unitFactor = self::unitFactor($measure, $priceUnit);
        $pricePer = self::nullableInt($product->price_per ?? null) ?? $defaults['price_per'];
        if (!self::validBasis($measure, $unitFactor, $pricePer)) {
            $pricePer = self::validBasis($measure, $unitFactor, $defaults['price_per']) ? $defaults['price_per'] : 1;
        }
        $step = max(1, self::nullableInt($product->quantity_step ?? null) ?? $defaults['step']);
        $min = max(1, self::nullableInt($product->quantity_min ?? null) ?? max($defaults['min'], $step));
        $max = $defaults['max'];
        if ($max > self::INVENTORY_MAX && self::tracksStock($product)) {
            $max = self::INVENTORY_MAX;
        }
        $tolerance = self::nullableInt($product->quantity_tolerance ?? null) ?? $defaults['tolerance'];
        $rounding = (string)($product->quantity_rounding ?? '');

        return new QuantityRules($measure, self::BASE_UNITS[$measure], $priceUnit, $unitFactor, $pricePer, $unitFactor * $pricePer,
            $step, $min, max($max, $min), max(0, $tolerance), $defaults['input_decimals'],
            in_array($rounding, self::ROUNDINGS, true) ? $rounding : self::rounding($priceUnit));
    }

    /**
     * Rules frozen in an order line at checkout; the current product is not consulted.
     *
     * Lines without measured keys are piece lines.
     *
     * @param array<string, mixed> $line Order-line snapshot.
     * @since 1.5.0
     */
    public static function fromLine(array $line): QuantityRules
    {
        $measure = is_string($line['measure'] ?? null) ? $line['measure'] : self::PIECE;
        if (!isset(self::BASE_UNITS[$measure])) {
            return self::piece();
        }
        $priceUnit = is_string($line['price_unit'] ?? null) ? $line['price_unit'] : '';
        $unitFactor = self::unitFactor($measure, $priceUnit);
        $factor = self::nullableInt($line['factor'] ?? null);
        if ($unitFactor === null) {
            $priceUnit = self::BASE_UNITS[$measure];
            $unitFactor = 1;
        }
        $pricePer = self::nullableInt($line['price_per'] ?? null) ?? ($factor !== null ? max(1, intdiv($factor, $unitFactor)) : 1);
        $step = max(1, self::nullableInt($line['quantity_step'] ?? null) ?? 1);
        $defaults = self::defaults($measure);
        $rounding = (string)($line['quantity_rounding'] ?? '');

        return new QuantityRules($measure, self::BASE_UNITS[$measure], $priceUnit, $unitFactor, $pricePer, $factor ?? $unitFactor * $pricePer,
            $step, max(1, self::nullableInt($line['quantity_min'] ?? null) ?? $step),
            OrderLineIdentity::tracksInventory($line) ? self::INVENTORY_MAX : self::QUANTITY_MAX,
            max(0, self::nullableInt($line['quantity_tolerance'] ?? null) ?? 0), $defaults['input_decimals'],
            in_array($rounding, self::ROUNDINGS, true) ? $rounding : self::ROUND_UP);
    }

    /**
     * Rules of piece products, matching the limits that applied before measured quantities.
     *
     * @since 1.5.0
     */
    public static function piece(): QuantityRules
    {
        return new QuantityRules(self::PIECE, '', '', 1, 1, 1, 1, 1, self::PIECE_MAX, 0, 0, self::ROUND_UP);
    }

    /**
     * Fit a quantity to the rules.
     *
     * `MODE_CLAMP` (storefront) raises to the minimum, rounds to the step with the rule's rounding,
     * caps at the maximum and at `$available` rounded down to a step. The result may fall below
     * the minimum only when stock is short. `MODE_STRICT` (manager, repeat, API, ERP) returns
     * valid quantities unchanged and refuses others.
     *
     * @param int|null $available Available stock in base units; null = not capped.
     * @throws MeasuredQuantityException In strict mode, when the quantity breaks a rule.
     * @since 1.5.0
     */
    public static function normalize(int $quantity, QuantityRules $rules, string $mode = self::MODE_CLAMP, ?int $available = null): int
    {
        $step = $rules->step;
        if ($mode === self::MODE_STRICT) {
            if ($quantity < 1) {
                throw new MeasuredQuantityException('measured_quantity_invalid');
            }
            if ($quantity < $rules->min) {
                throw new MeasuredQuantityException('measured_quantity_below_min', ['min' => self::label($rules->min, $rules)]);
            }
            if ($quantity % $step !== 0) {
                throw new MeasuredQuantityException('measured_quantity_step', ['step' => self::label($step, $rules)]);
            }
            if ($quantity > $rules->max) {
                throw new MeasuredQuantityException('measured_quantity_above_max', ['max' => self::label($rules->max, $rules)]);
            }
            return $quantity;
        }

        $quantity = max($quantity, $rules->min);
        $quantity = match ($rules->rounding) {
            self::ROUND_DOWN => intdiv($quantity, $step) * $step,
            // Ties round up: 1.05 kg in 100 g steps is 1.1 kg.
            self::ROUND_NEAREST => intdiv(2 * $quantity + $step, 2 * $step) * $step,
            default => intdiv($quantity + $step - 1, $step) * $step,
        };
        $lowest = intdiv($rules->min + $step - 1, $step) * $step;
        $quantity = max($quantity, $lowest);
        if ($quantity > $rules->max) {
            $quantity = max(intdiv($rules->max, $step) * $step, $lowest);
        }
        if ($available !== null) {
            $quantity = min($quantity, intdiv(max(0, $available), $step) * $step);
        }

        return $quantity;
    }

    /**
     * Parse an amount in the price unit (`"2.53"` metres) into base units (2530 mm).
     *
     * Accepts digits with an optional `.` fraction of at most as many significant decimals as
     * the price unit has base units (3 for kg, m and l). No exponent, sign or locale separators.
     *
     * @throws MeasuredQuantityException When the input is not such an amount.
     * @since 1.5.0
     */
    public static function parseAmount(string $input, QuantityRules $rules): int
    {
        if (!preg_match('/^(\d{1,15})(?:\.(\d{1,15}))?$/D', trim($input), $match)) {
            throw new MeasuredQuantityException('measured_quantity_invalid');
        }
        $decimals = self::decimals($rules->unitFactor);
        $fraction = rtrim($match[2] ?? '', '0');
        if (strlen($fraction) > $decimals) {
            throw new MeasuredQuantityException('measured_quantity_precision', ['decimals' => $decimals]);
        }

        return (int)$match[1] * $rules->unitFactor + (int)str_pad($fraction, $decimals, '0');
    }

    /**
     * Line total in minor units, rounded half up once: `price × quantity ÷ factor`.
     *
     * @param int $unitPriceScaled Price in 1e-5 currency units, see {@see self::scalePrice()}.
     * @param int $quantity Base units.
     * @param int $factor Base units the price refers to.
     * @throws \InvalidArgumentException On negative input, or when the result does not fit an integer.
     * @since 1.5.0
     */
    public static function lineTotalMinor(int $unitPriceScaled, int $quantity, int $factor): int
    {
        if ($unitPriceScaled < 0 || $quantity < 0 || $factor < 1) {
            throw new \InvalidArgumentException('Line totals need a non-negative price and quantity and a positive factor.');
        }
        if ($unitPriceScaled === 0 || $quantity === 0) {
            return 0;
        }
        // 1e-5 units to minor units (1e-2) divide by 1000 on top of the factor.
        $divisor = $factor * 1000;
        if ($unitPriceScaled <= intdiv(intdiv(PHP_INT_MAX - $divisor, 2), $quantity)) {
            return intdiv(2 * $unitPriceScaled * $quantity + $divisor, 2 * $divisor);
        }
        if (!extension_loaded('bcmath')) {
            throw new \InvalidArgumentException('The line total is too large; install ext-bcmath for such values.');
        }
        $total = bcdiv(bcadd(bcmul(bcmul('2', (string)$unitPriceScaled), (string)$quantity), (string)$divisor), bcmul('2', (string)$divisor), 0);
        if (bccomp($total, (string)PHP_INT_MAX) > 0) {
            throw new \InvalidArgumentException('The line total is too large.');
        }

        return (int)$total;
    }

    /**
     * Price as an integer of 1e-5 units, the scale of the `s_products` price columns. Never rounded to cents.
     *
     * @throws \InvalidArgumentException On a negative or non-numeric price.
     * @since 1.5.0
     */
    public static function scalePrice(float|int|string $price): int
    {
        if (is_string($price) && preg_match('/^\s*(\d{1,13})(?:\.(\d*))?\s*$/D', $price, $match)) {
            $fraction = str_pad(substr($match[2] ?? '', 0, 6), 6, '0');
            return (int)$match[1] * 100000 + intdiv((int)$fraction, 10) + ((int)$fraction[5] >= 5 ? 1 : 0);
        }
        if (!is_numeric($price) || !is_finite((float)$price) || (float)$price < 0) {
            throw new \InvalidArgumentException('Prices must be non-negative numbers.');
        }

        return (int)round((float)$price * 100000);
    }

    /**
     * Exact decimal string of a quantity in units of `$factor` base units: (2530, 1000) → `2.530`.
     *
     * @param int $factor Power of ten.
     * @since 1.5.0
     */
    public static function toPriceUnits(int $quantity, int $factor): string
    {
        $decimals = self::decimals($factor);
        if ($decimals === 0) {
            return (string)$quantity;
        }
        $digits = str_pad((string)abs($quantity), $decimals + 1, '0', STR_PAD_LEFT);

        return ($quantity < 0 ? '-' : '') . substr($digits, 0, -$decimals) . '.' . substr($digits, -$decimals);
    }

    /**
     * Express a line for accounting: weight, length and volume in the largest unit (kg, m, l)
     * with the price per that unit; `count` in price-basis units with the price unchanged.
     * Both are exact because every factor is a power of ten that divides the conversion.
     *
     * @param int $quantity Base units, normally {@see self::effective()}.
     * @param float|int|string $price Price per price basis.
     * @return array{quantity: string, unit: string, basis: int, unit_price: string}
     * @since 1.5.0
     */
    public static function forAccounting(int $quantity, float|int|string $price, QuantityRules $rules): array
    {
        $scaled = self::scalePrice($price);
        if ($rules->measure === self::PIECE) {
            return ['quantity' => (string)$quantity, 'unit' => '', 'basis' => 1, 'unit_price' => self::decimalPrice($scaled)];
        }
        if ($rules->measure === 'count') {
            return ['quantity' => self::toPriceUnits($quantity, $rules->pricePer), 'unit' => 'unit', 'basis' => $rules->pricePer, 'unit_price' => self::decimalPrice($scaled)];
        }
        [$unit, $largest] = self::units($rules->measure)[0];

        return ['quantity' => self::toPriceUnits($quantity, $largest), 'unit' => $unit, 'basis' => 1,
            'unit_price' => self::decimalPrice($scaled * intdiv($largest, $rules->factor))];
    }

    /**
     * Quantity for people: `2.53 m` (`2,53 м` in Ukrainian), `3 l`, `125,000` for count, the number for
     * pieces. The decimals are exact; trailing zeros are dropped.
     *
     * @param string|null $locale Language; the current one when null.
     * @since 1.5.0
     */
    public static function label(int $quantity, QuantityRules $rules, ?string $locale = null): string
    {
        if ($rules->measure === self::PIECE) {
            return (string)$quantity;
        }
        if ($rules->measure === 'count') {
            return self::formatNumber((string)$quantity, 0, $locale);
        }

        return self::formatAmount($quantity, $rules->unitFactor, $rules->priceUnit, 0, $locale);
    }

    /**
     * Price per price basis for people: `€1.20 / m`, `€2.49 / 100 g`, `€2.50 / 1,000`.
     *
     * @param string $price Formatted price, e.g. from `sCommerce::convertPrice()`.
     * @param string|null $locale Language; the current one when null.
     * @since 1.5.0
     */
    public static function unitPriceLabel(string $price, QuantityRules $rules, ?string $locale = null): string
    {
        if ($rules->measure === self::PIECE) {
            return $price;
        }
        if ($rules->measure === 'count') {
            $basis = $rules->pricePer > 1 ? self::translate('count_basis_' . $rules->pricePer, [], $locale) : '';
            $unit = '';
        } else {
            $basis = $rules->pricePer > 1 ? self::formatNumber((string)$rules->pricePer, 0, $locale) : '';
            $unit = self::translate('unit_' . $rules->priceUnit, [], $locale);
        }
        if ($basis === '' && $unit === '') {
            return $price;
        }
        $label = self::translate('unit_price_label', ['price' => $price, 'basis' => $basis, 'unit' => $unit], $locale);

        return trim(preg_replace('/ {2,}/u', ' ', $label));
    }

    /**
     * Billable quantity of a stored line: the actual quantity when set, else the ordered one.
     *
     * @param array<string, mixed> $line
     * @since 1.5.0
     */
    public static function effective(array $line): int
    {
        $actual = $line['actual_quantity'] ?? null;
        if (isset($line['measure']) && (is_int($actual) || (is_string($actual) && ctype_digit($actual))) && (int)$actual > 0) {
            return (int)$actual;
        }

        return (int)($line['quantity'] ?? 0);
    }

    /**
     * Weight of a whole measured line in grams.
     *
     * Physical attributes of a measured product describe one price unit (a cable's grams per
     * metre). Weight products weigh their quantity. Piece lines and count lines return null,
     * as do lines without a product weight.
     *
     * @param array<string, mixed> $line Line with rule keys and the per-unit `weight`.
     * @since 1.5.0
     */
    public static function lineWeight(array $line): ?int
    {
        $rules = self::fromLine($line);
        $quantity = self::effective($line);
        if ($rules->measure === 'weight') {
            return $quantity;
        }
        $weight = (int)($line['weight'] ?? 0);
        if (!in_array($rules->measure, ['length', 'volume'], true) || $weight <= 0) {
            return null;
        }

        return intdiv(2 * $weight * $quantity + $rules->unitFactor, 2 * $rules->unitFactor);
    }

    /**
     * Total of a stored line from its numeric price.
     *
     * Piece lines keep the historical `price × quantity`. Measured lines use the exact helper
     * on the effective quantity and prefer the unrounded `priceNumber`/`priceAsFloat`.
     *
     * @param array<string, mixed> $line Order-line snapshot.
     * @param float $price Numeric price the caller resolved for the line.
     * @since 1.5.0
     */
    public static function lineSum(array $line, float $price): float
    {
        if (!isset($line['measure']) || !isset(self::BASE_UNITS[$line['measure']])) {
            return $price * intval($line['quantity'] ?? 1);
        }
        foreach (['priceNumber', 'priceAsFloat'] as $key) {
            if (isset($line[$key]) && is_numeric($line[$key])) {
                $price = (float)$line[$key];
                break;
            }
        }

        return self::lineTotalMinor(self::scalePrice(max(0.0, $price)), max(0, self::effective($line)), self::fromLine($line)->factor) / 100;
    }

    /**
     * Quantity-dependent display keys of a new measured line.
     *
     * @param string $price Formatted price for the unit price label.
     * @param int $weight Product weight per price unit in grams; 0 = none.
     * @param string|null $locale Order language.
     * @return array<string, int|string>
     * @since 1.5.0
     */
    public static function lineFields(QuantityRules $rules, int $quantity, string $price, int $weight = 0, ?string $locale = null): array
    {
        if (!$rules->isMeasured()) {
            return [];
        }
        $line = $rules->lineKeys() + ['quantity' => $quantity, 'weight' => $weight];
        $fields = ['quantity_label' => self::label($quantity, $rules, $locale), 'unit_price_label' => self::unitPriceLabel($price, $rules, $locale)];
        if (($lineWeight = self::lineWeight($line)) !== null) {
            $fields['line_weight'] = $lineWeight;
        }

        return $fields;
    }

    /**
     * Refresh the quantity labels and weight of a stored measured line after a quantity change.
     *
     * @param array<string, mixed> $line
     * @param string|null $locale Order language.
     * @return array<string, mixed>
     * @since 1.5.0
     */
    public static function refreshLine(array $line, ?string $locale = null): array
    {
        $rules = self::fromLine($line);
        if (!$rules->isMeasured()) {
            return $line;
        }
        $line['quantity_label'] = self::label((int)($line['quantity'] ?? 0), $rules, $locale);
        if (isset($line['actual_quantity'])) {
            $line['actual_quantity_label'] = self::label(self::effective($line), $rules, $locale);
        } else {
            unset($line['actual_quantity_label']);
        }
        $weight = self::lineWeight($line);
        if ($weight !== null) {
            $line['line_weight'] = $weight;
        } else {
            unset($line['line_weight']);
        }

        return $line;
    }

    /**
     * Keep the measured keys of edited order lines server-side.
     *
     * Every client-provided rule, actual-quantity and display key is dropped. A line whose
     * `line_key` matches a stored line takes the stored keys; other lines with a `line_key` take
     * the current product's rules. Lines without `line_key` get them from
     * {@see OrderLineIdentity::fillMissing()}.
     *
     * @param array<int, mixed> $stored Stored order lines.
     * @param array<int, mixed> $edited Lines posted by the manager form.
     * @return array<int, mixed>
     * @since 1.5.0
     */
    public static function restoreStoredRules(array $stored, array $edited): array
    {
        $byKey = [];
        foreach ($stored as $line) {
            if (is_array($line) && is_string($line['line_key'] ?? null) && !isset($byKey[$line['line_key']])) {
                $byKey[$line['line_key']] = $line;
            }
        }
        $server = array_merge(self::LINE_KEYS, self::ACTUAL_KEYS, self::DISPLAY_KEYS);
        foreach ($edited as $index => $line) {
            if (!is_array($line)) {
                continue;
            }
            $line = array_diff_key($line, array_flip($server));
            $key = is_string($line['line_key'] ?? null) && $line['line_key'] !== '' ? $line['line_key'] : null;
            if ($key !== null && isset($byKey[$key])) {
                $line += array_intersect_key($byKey[$key], array_flip(array_merge(self::LINE_KEYS, self::ACTUAL_KEYS, ['unit_price_label'])));
            } elseif ($key !== null) {
                $productId = (int)($line['product_id'] ?? $line['id'] ?? 0);
                $product = $productId > 0 ? sCommerce::getProduct($productId) : null;
                if ($product && (int)($product->id ?? 0) > 0) {
                    $line += self::rules($product)->lineKeys();
                }
            }
            $edited[$index] = $line;
        }

        return $edited;
    }

    /**
     * Site defaults of a measure: `product.measures` when valid, else the built-in values.
     *
     * @return array{price_unit: string, price_per: int, step: int, min: int, max: int, tolerance: int, input_decimals: int}
     * @since 1.5.0
     */
    public static function defaults(string $measure): array
    {
        $builtIn = self::DEFAULTS[$measure] ?? self::DEFAULTS['weight'];
        $configured = self::setting('product.measures', []);
        $configured = is_array($configured) && is_array($configured[$measure] ?? null) ? $configured[$measure] : [];
        $values = $builtIn;
        foreach ($builtIn as $key => $default) {
            if (array_key_exists($key, $configured) && $configured[$key] !== '' && $configured[$key] !== null) {
                $values[$key] = is_int($default) ? (int)$configured[$key] : (string)$configured[$key];
            }
        }

        return self::validateDefaults($measure, $values, false) === [] ? $values : $builtIn;
    }

    /**
     * Rounding of a price unit: `product.quantity_rounding`, then the built-in map, then `up`.
     *
     * @since 1.5.0
     */
    public static function rounding(string $priceUnit): string
    {
        $map = self::setting('product.quantity_rounding', []);
        $value = is_array($map) ? ($map[$priceUnit] ?? null) : null;
        if (is_string($value) && in_array($value, self::ROUNDINGS, true)) {
            return $value;
        }

        return self::ROUNDING_DEFAULTS[$priceUnit] ?? self::ROUND_UP;
    }

    /**
     * Units of a measure, largest first: `[code, base units per unit]`.
     *
     * @return list<array{0: string, 1: int}>
     * @since 1.5.0
     */
    public static function units(string $measure): array
    {
        return $measure === 'count' ? [['unit', 1]] : sProduct::physicalUnits($measure);
    }

    /**
     * Base units per price unit, or null when the unit does not belong to the measure.
     *
     * @since 1.5.0
     */
    public static function unitFactor(string $measure, string $unit): ?int
    {
        foreach (self::units($measure) as [$code, $factor]) {
            if ($code === $unit) {
                return $factor;
            }
        }

        return null;
    }

    /**
     * Whether a price basis is allowed: weight, length and volume up to one largest unit
     * (per 100 g, per 10 cm, per 1 kg; not per 10 kg); count per 1, 1,000 or 1,000,000.
     *
     * @since 1.5.0
     */
    public static function validBasis(string $measure, ?int $unitFactor, int $pricePer): bool
    {
        if ($unitFactor === null) {
            return false;
        }
        if ($measure === 'count') {
            return in_array($pricePer, self::COUNT_BASES, true);
        }

        return in_array($pricePer, self::PRICE_BASES, true) && $unitFactor * $pricePer <= 1000;
    }

    /**
     * Problems of a measure's site defaults, as translation keys; empty when valid.
     *
     * @param array<string, mixed> $values Keys of {@see self::DEFAULTS}.
     * @param bool $tracksStock Whether products of the measure track stock (signed INT limit).
     * @return list<string>
     * @since 1.5.0
     */
    public static function validateDefaults(string $measure, array $values, bool $tracksStock = true): array
    {
        if (!isset(self::DEFAULTS[$measure])) {
            return ['measured_settings_measure'];
        }
        $int = static fn (string $key): ?int => isset($values[$key]) && filter_var($values[$key], FILTER_VALIDATE_INT) !== false ? (int)$values[$key] : null;
        $errors = [];
        $unitFactor = self::unitFactor($measure, (string)($values['price_unit'] ?? ''));
        if ($unitFactor === null) {
            $errors[] = 'measured_settings_price_unit';
        } elseif (($pricePer = $int('price_per')) === null || !self::validBasis($measure, $unitFactor, $pricePer)) {
            $errors[] = 'measured_settings_price_per';
        }
        [$step, $min, $max] = [$int('step'), $int('min'), $int('max')];
        if ($step === null || $step < 1) {
            $errors[] = 'measured_settings_step';
        } elseif ($min === null || $min < $step) {
            $errors[] = 'measured_settings_min';
        } elseif ($max === null || $max < $min || $max > ($tracksStock ? self::INVENTORY_MAX : self::QUANTITY_MAX)) {
            $errors[] = 'measured_settings_max';
        }
        if (($tolerance = $int('tolerance')) === null || $tolerance < 0 || $tolerance > 10000) {
            $errors[] = 'measured_settings_tolerance';
        }
        if (($decimals = $int('input_decimals')) === null || $decimals < 0 || $decimals > 6) {
            $errors[] = 'measured_settings_decimals';
        }

        return $errors;
    }

    /**
     * Validate the measured settings of a settings form: an invalid measure or rounding keeps
     * its previous value and reports a translation key.
     *
     * @param array<string, mixed> $product Submitted `product` settings.
     * @param array<string, mixed> $previous Stored `product` settings.
     * @param list<string> $errors Receives translation keys of refused values.
     * @return array<string, mixed> Settings to store.
     * @since 1.5.0
     */
    public static function sanitizeSettings(array $product, array $previous, array &$errors = []): array
    {
        if (is_array($product['measures'] ?? null)) {
            $measures = [];
            foreach (self::DEFAULTS as $measure => $builtIn) {
                $submitted = is_array($product['measures'][$measure] ?? null) ? $product['measures'][$measure] : [];
                $values = [];
                foreach ($builtIn as $key => $default) {
                    $value = $submitted[$key] ?? $default;
                    $values[$key] = is_int($default) && filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : $value;
                }
                $problems = self::validateDefaults($measure, $values, $measure !== 'count');
                if ($problems === []) {
                    $measures[$measure] = $values;
                } else {
                    array_push($errors, ...$problems);
                    $measures[$measure] = is_array($previous['measures'][$measure] ?? null) ? $previous['measures'][$measure] : $builtIn;
                }
            }
            $product['measures'] = $measures;
        }
        if (is_array($product['quantity_rounding'] ?? null)) {
            $rounding = [];
            foreach ($product['quantity_rounding'] as $unit => $value) {
                if (!isset(self::ROUNDING_DEFAULTS[$unit])) {
                    continue;
                }
                if (in_array($value, self::ROUNDINGS, true)) {
                    $rounding[$unit] = $value;
                } else {
                    $errors[] = 'measured_settings_rounding';
                    $rounding[$unit] = $previous['quantity_rounding'][$unit] ?? self::ROUNDING_DEFAULTS[$unit];
                }
            }
            $product['quantity_rounding'] = $rounding;
        }
        $errors = array_values(array_unique($errors));

        return $product;
    }

    /**
     * Validate submitted quantity settings of a product and return its column values.
     *
     * Empty values mean the site default (NULL). Changing the measure of a product with stock
     * or with orders is refused, because it would change the meaning of stored quantities.
     * Measured products cannot have a returnable container deposit.
     *
     * @param array<string, mixed> $input `measure` (id or alias) and the other {@see self::PRODUCT_COLUMNS}.
     * @param object $product Stored product; `deposit` should already hold the submitted deposit.
     * @return array<string, int|string|null> Column values; piece products get NULL settings.
     * @throws MeasuredQuantityException When a value is refused.
     * @since 1.5.0
     */
    public static function productSettings(array $input, object $product): array
    {
        $raw = $input['measure'] ?? 0;
        $measureId = is_string($raw) && ($found = array_search($raw, sProduct::MEASURE_ALIASES, true)) !== false
            ? (int)$found : (in_array($raw, ['', null, self::PIECE], true) ? sProduct::MEASURE_PIECE : filter_var($raw, FILTER_VALIDATE_INT));
        if ($measureId === false || ($measureId !== sProduct::MEASURE_PIECE && !isset(sProduct::MEASURE_ALIASES[$measureId]))) {
            throw new MeasuredQuantityException('measured_settings_measure');
        }
        $stored = (int)(method_exists($product, 'getRawOriginal') && ($product->exists ?? false) ? $product->getRawOriginal('measure') : ($product->measure ?? 0));
        $productId = (int)($product->id ?? 0);
        if ($measureId !== $stored && $productId > 0) {
            $inventory = (int)(method_exists($product, 'getRawOriginal') ? $product->getRawOriginal('inventory') : ($product->inventory ?? 0));
            if ($inventory !== 0 || self::productInOrders($productId)) {
                throw new MeasuredQuantityException('measured_change_refused');
            }
        }
        $columns = array_fill_keys(array_slice(self::PRODUCT_COLUMNS, 1), null);
        $columns = ['measure' => $measureId] + $columns;
        if ($measureId === sProduct::MEASURE_PIECE) {
            return $columns;
        }
        if ((float)($product->deposit ?? 0) > 0) {
            throw new MeasuredQuantityException('measured_deposit_refused');
        }
        $measure = sProduct::MEASURE_ALIASES[$measureId];
        $value = static fn (string $key) => is_scalar($input[$key] ?? null) && trim((string)$input[$key]) !== '' ? trim((string)$input[$key]) : null;
        $integer = static function (string $key, int $min, int $max) use ($value): ?int {
            $raw = $value($key);
            if ($raw === null) {
                return null;
            }
            $number = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
            if ($number === false) {
                throw new MeasuredQuantityException('measured_settings_' . str_replace('quantity_', '', $key));
            }
            return $number;
        };

        $columns['price_unit'] = $value('price_unit');
        if ($columns['price_unit'] !== null && self::unitFactor($measure, $columns['price_unit']) === null) {
            throw new MeasuredQuantityException('measured_settings_price_unit');
        }
        $columns['price_per'] = $integer('price_per', 1, 1000000);
        $unit = $columns['price_unit'] ?? self::defaults($measure)['price_unit'];
        if ($columns['price_per'] !== null && !self::validBasis($measure, self::unitFactor($measure, $unit), $columns['price_per'])) {
            throw new MeasuredQuantityException('measured_settings_price_per');
        }
        $columns['quantity_step'] = $integer('quantity_step', 1, self::INVENTORY_MAX);
        $columns['quantity_min'] = $integer('quantity_min', 1, self::INVENTORY_MAX);
        $step = $columns['quantity_step'] ?? self::defaults($measure)['step'];
        if ($columns['quantity_min'] !== null && $columns['quantity_min'] < $step) {
            throw new MeasuredQuantityException('measured_settings_min');
        }
        $columns['quantity_tolerance'] = $integer('quantity_tolerance', 0, 10000);
        $rounding = $value('quantity_rounding');
        if ($rounding !== null && !in_array($rounding, self::ROUNDINGS, true)) {
            throw new MeasuredQuantityException('measured_settings_rounding');
        }
        $columns['quantity_rounding'] = $rounding;

        return $columns;
    }

    /**
     * Whether a product appears in a non-deleted order.
     *
     * @since 1.5.0
     */
    public static function productInOrders(int $productId): bool
    {
        $found = false;
        sOrder::query()->select(['id', 'products'])->chunkById(500, static function ($orders) use ($productId, &$found): bool {
            foreach ($orders as $order) {
                foreach (is_array($order->products) ? $order->products : [] as $line) {
                    if (is_array($line) && (int)($line['product_id'] ?? $line['id'] ?? 0) === $productId) {
                        $found = true;
                        return false;
                    }
                }
            }
            return true;
        });

        return $found;
    }

    /**
     * A quantity in a unit for people, e.g. `2.53 m`; trailing zeros are trimmed down to `$minDecimals`.
     *
     * @param int $factor Base units per unit, a power of ten.
     * @since 1.5.0
     */
    public static function formatAmount(int $quantity, int $factor, string $unit, int $minDecimals = 0, ?string $locale = null): string
    {
        $number = self::formatNumber(self::toPriceUnits($quantity, $factor), $minDecimals, $locale);
        $symbol = self::translate('unit_' . $unit, [], $locale);

        return $symbol === '' ? $number : self::translate('quantity_label', ['quantity' => $number, 'unit' => $symbol], $locale);
    }

    /**
     * Translated package string with an English fallback.
     *
     * @param array<string, string|int> $replace
     * @since 1.5.0
     */
    public static function translate(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= self::currentLocale();
        $cacheKey = $locale . '|' . $key;
        $text = $replace === [] ? (self::$translations[$cacheKey] ?? null) : null;
        if ($text === null) {
            $full = 'sCommerce::global.' . $key;
            try {
                $text = __($full, $replace, $locale);
                if (!is_string($text) || $text === $full) {
                    $text = __($full, $replace, 'en');
                }
            } catch (\Throwable) {
                $text = $full;
            }
            if (!is_string($text) || $text === $full) {
                $text = self::FALLBACK[$key] ?? $key;
                foreach ($replace as $name => $value) {
                    $text = str_replace(':' . $name, (string)$value, $text);
                }
            }
            if ($replace === []) {
                self::$translations[$cacheKey] = $text;
            }
        }

        return $text;
    }

    /**
     * @internal Clear cached translations, e.g. after the language files change in tests.
     */
    public static function flushTranslations(): void
    {
        self::$translations = [];
    }

    private static function currentLocale(): string
    {
        try {
            return (string)app('translator')->getLocale();
        } catch (\Throwable) {
            return 'en';
        }
    }

    /**
     * Format an exact decimal string with the language's separators, trimming trailing zeros down to `$minDecimals`.
     */
    private static function formatNumber(string $decimal, int $minDecimals, ?string $locale): string
    {
        $negative = str_starts_with($decimal, '-');
        [$integer, $fraction] = array_pad(explode('.', ltrim($decimal, '-'), 2), 2, '');
        $keep = max($minDecimals, strlen(rtrim($fraction, '0')));
        $fraction = substr(str_pad($fraction, $keep, '0'), 0, $keep);
        $thousands = self::translate('number_thousands_separator', [], $locale);
        $grouped = strrev(implode(strrev($thousands), str_split(strrev($integer), 3)));

        return ($negative ? '-' : '') . $grouped . ($fraction !== '' ? self::translate('number_decimal_separator', [], $locale) . $fraction : '');
    }

    /**
     * Price of 1e-5 units as a decimal string with at least two decimals.
     */
    private static function decimalPrice(int $scaled): string
    {
        $decimal = self::toPriceUnits($scaled, 100000);
        [$integer, $fraction] = explode('.', $decimal);
        $fraction = rtrim($fraction, '0');

        return $integer . '.' . str_pad($fraction, 2, '0');
    }

    /**
     * Number of decimals of a power of ten.
     */
    private static function decimals(int $factor): int
    {
        return max(0, strlen((string)$factor) - 1);
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        return (int)$value;
    }

    private static function tracksStock(object $product): bool
    {
        return !method_exists($product, 'fulfilment') || (bool)($product->fulfilment()['inventory'] ?? true);
    }

    private static function setting(string $key, mixed $default): mixed
    {
        try {
            return sCommerce::config($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
