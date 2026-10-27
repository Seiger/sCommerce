<?php namespace Seiger\sCommerce\Models\Concerns;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Seiger\sCommerce\Models\sAttribute;
use Throwable;

/**
 * Store physical measurements as attributes and consume retained legacy columns on save.
 *
 * Measurements are whole numbers: weight in grams, width, height and length in millimetres,
 * volume in millilitres. Existing columns remain in upgraded schemas only. Positive whole
 * legacy values migrate on save and are zeroed in the same transaction. Reads use explicit
 * attribute methods.
 *
 * @since 1.4.0
 */
trait HasPhysicalAttributes
{
    public const PHYSICAL_ATTRIBUTES = ['weight', 'width', 'height', 'length', 'volume'];

    /** Display units per measurement, largest first: [symbol, base units per symbol]. */
    private const PHYSICAL_UNITS = [
        'weight' => [['kg', 1000], ['g', 1]],
        'width' => [['m', 1000], ['cm', 10], ['mm', 1]],
        'height' => [['m', 1000], ['cm', 10], ['mm', 1]],
        'length' => [['m', 1000], ['cm', 10], ['mm', 1]],
        'volume' => [['l', 1000], ['ml', 1]],
    ];

    /** @var array<string, int> Values assigned before a product has its database ID. */
    protected array $pendingPhysicalAttributes = [];

    /**
     * Persist explicit physical attributes and drain positive legacy columns atomically.
     *
     * A positive whole legacy column is consumed once and wins over the existing attribute.
     * A fractional legacy column has no known unit; it stays for manual entry in the product
     * form unless an explicit value for the same measurement replaces it. Zero columns do not
     * overwrite attributes. Ordinary saves and quiet saves use this path.
     *
     * @since 1.4.0
     * @param array<string, mixed> $options Eloquent save options.
     * @return bool Whether the product and its physical values were saved.
     */
    public function save(array $options = [])
    {
        $values = $this->pendingPhysicalAttributes;
        $legacy = [];
        foreach (self::PHYSICAL_ATTRIBUTES as $alias) {
            if (!array_key_exists($alias, $this->attributes) || (float) $this->attributes[$alias] <= 0) {
                continue;
            }
            try {
                $value = self::normalizePhysicalValue($this->attributes[$alias]);
            } catch (InvalidArgumentException) {
                if (!array_key_exists($alias, $values)) {
                    continue;
                }
                $value = $values[$alias];
            }
            $legacy[$alias] = $this->attributes[$alias];
            $values[$alias] = $value;
        }
        if ($values === []) {
            return parent::save($options);
        }
        $snapshot = [$this->attributes, $this->original, $this->changes, $this->exists, $this->wasRecentlyCreated];
        try {
            return $this->getConnection()->transaction(function () use ($options, $values, $legacy) {
                $definitionsByAlias = [];
                foreach ($values as $alias => $value) {
                    $definitions = sAttribute::on($this->getConnectionName())->where('alias', $alias)->get();
                    if ($definitions->count() !== 1 || !in_array((int) $definitions[0]->type, [sAttribute::TYPE_ATTR_NUMBER, sAttribute::TYPE_ATTR_SELECT], true)) {
                        throw new InvalidArgumentException("Physical attribute {$alias} requires one numeric or select definition; run the package migration first.");
                    }
                    $definitionsByAlias[$alias] = $definitions[0];
                }
                foreach ($legacy as $alias => $value) {
                    $this->attributes[$alias] = 0;
                }
                $saved = parent::save($options);
                if (!$saved) {
                    foreach ($legacy as $alias => $value) {
                        $this->attributes[$alias] = $value;
                    }
                    return false;
                }
                foreach ($values as $alias => $value) {
                    $attribute = $definitionsByAlias[$alias];
                    $optionId = (int) $attribute->type === sAttribute::TYPE_ATTR_SELECT
                        ? $this->physicalOptionId($attribute, $value) : 0;
                    $this->attrValues()->syncWithoutDetaching([
                        $attribute->id => ['valueid' => $optionId, 'value' => $optionId ?: $value],
                    ]);
                }
                $this->pendingPhysicalAttributes = [];
                $this->unsetRelation('attrValues');
                return true;
            });
        } catch (\Throwable $exception) {
            [$this->attributes, $this->original, $this->changes, $this->exists, $this->wasRecentlyCreated] = $snapshot;
            $this->unsetRelation('attrValues');
            throw $exception;
        }
    }

    /**
     * Queue an explicit attribute write for the next product save.
     *
     * @since 1.4.0
     * @param string $alias One of the five physical attribute aliases.
     * @param mixed $value Non-negative whole number of grams, millimetres or millilitres.
     * @return static
     */
    public function setPhysicalValue(string $alias, mixed $value): static
    {
        if (!in_array($alias, self::PHYSICAL_ATTRIBUTES, true)) {
            throw new InvalidArgumentException("Unknown physical attribute {$alias}.");
        }
        $this->pendingPhysicalAttributes[$alias] = self::normalizePhysicalValue($value);
        return $this;
    }

    /**
     * Read physical measurements exclusively from attributes, without legacy fallbacks.
     *
     * Missing attributes resolve to zero. Saving a legacy product first consumes its columns.
     *
     * @since 1.4.0
     * @return array<string, int> Five measurements in grams, millimetres and millilitres.
     * @throws InvalidArgumentException When a stored value is not a whole number.
     */
    public function physicalValues(): array
    {
        $attributes = $this->physicalAttributeRows();
        $values = [];
        foreach (self::PHYSICAL_ATTRIBUTES as $alias) {
            $values[$alias] = $this->readPhysicalValue($alias, $attributes);
        }
        return $values;
    }

    /**
     * Physical measurements for cart and order lines.
     *
     * Unlike physicalValues(), an unreadable measurement is left out and logged instead of
     * failing the purchase, so delivery integrations never receive an invented number.
     *
     * @since 1.4.0
     * @return array<string, int> Readable measurements in grams, millimetres and millilitres.
     */
    public function physicalPayload(): array
    {
        $attributes = $this->physicalAttributeRows();
        $values = [];
        foreach (self::PHYSICAL_ATTRIBUTES as $alias) {
            try {
                $values[$alias] = $this->readPhysicalValue($alias, $attributes);
            } catch (InvalidArgumentException $exception) {
                try {
                    Log::channel('scommerce')->warning('sCommerce physical attribute is unreadable', [
                        'product_id' => $this->getKey(), 'attribute' => $alias, 'error' => $exception->getMessage(),
                    ]);
                } catch (Throwable) {
                    // Logging must not break a cart or checkout.
                }
            }
        }
        return $values;
    }

    /**
     * Human-readable measurement in the largest unit that keeps a value of at least one.
     *
     * @since 1.4.0
     * @param string $alias One of the five physical attribute aliases.
     * @param int $value Measurement in grams, millimetres or millilitres.
     * @return string Label such as `1kg`, `250g`, `25.5cm` or `500ml`.
     */
    public static function physicalLabel(string $alias, int $value): string
    {
        foreach (self::PHYSICAL_UNITS[$alias] ?? [['', 1]] as [$unit, $factor]) {
            if ($value >= $factor || $factor === 1) {
                return rtrim(rtrim(number_format($value / $factor, 3, '.', ''), '0'), '.') . $unit;
            }
        }
        return (string) $value;
    }

    /**
     * Same as {@see self::physicalLabel()}, with the unit symbol and decimal separator of the language.
     *
     * @since 1.5.0
     * @param string $alias One of the five physical attribute aliases.
     * @param int $value Measurement in grams, millimetres or millilitres.
     * @param string|null $locale Language; the current one when null.
     * @return string Label such as `1 kg` or `1 кг`.
     */
    public static function physicalLabelLocalized(string $alias, int $value, ?string $locale = null): string
    {
        foreach (self::PHYSICAL_UNITS[$alias] ?? [] as [$unit, $factor]) {
            if ($value >= $factor || $factor === 1) {
                return \Seiger\sCommerce\Services\MeasuredQuantity::formatAmount($value, $factor, $unit, 0, $locale);
            }
        }
        return (string) $value;
    }

    /**
     * Display units of a measurement, largest first.
     *
     * @since 1.5.0
     * @param string $alias `weight`, `width`, `height`, `length` or `volume`.
     * @return list<array{0: string, 1: int}> Unit code and base units per unit; empty for other aliases.
     */
    public static function physicalUnits(string $alias): array
    {
        return self::PHYSICAL_UNITS[$alias] ?? [];
    }

    /**
     * Unit symbol in which a measurement is stored and entered.
     *
     * @since 1.4.0
     * @param string $alias One of the five physical attribute aliases.
     * @return string `g`, `mm` or `ml`; empty for other aliases.
     */
    public static function physicalUnit(string $alias): string
    {
        return isset(self::PHYSICAL_UNITS[$alias]) ? array_slice(self::PHYSICAL_UNITS[$alias], -1)[0][0] : '';
    }

    /**
     * @return \Illuminate\Support\Collection Attribute rows with pivots, keyed by alias.
     */
    private function physicalAttributeRows()
    {
        return $this->exists ? $this->getRelationValue('attrValues')->keyBy('alias') : collect();
    }

    /**
     * @param \Illuminate\Support\Collection $attributes Attribute rows keyed by alias.
     * @throws InvalidArgumentException When the stored value is not a whole number.
     */
    private function readPhysicalValue(string $alias, $attributes): int
    {
        if (array_key_exists($alias, $this->pendingPhysicalAttributes)) {
            return $this->pendingPhysicalAttributes[$alias];
        }
        $attribute = $attributes->get($alias);
        if (!$attribute) {
            return 0;
        }
        if ((int) $attribute->type === sAttribute::TYPE_ATTR_SELECT) {
            $option = $attribute->values()->where('avid', $attribute->pivot->valueid)->first();
            if (!$option) {
                throw new InvalidArgumentException("Missing physical option for {$alias}.");
            }
            return $this->physicalOptionNumber($option);
        }
        if ((int) $attribute->type !== sAttribute::TYPE_ATTR_NUMBER) {
            throw new InvalidArgumentException("Unsupported physical attribute type for {$alias}.");
        }
        return self::normalizePhysicalValue($attribute->pivot->value ?? 0);
    }

    /**
     * Find or create the select option of a measurement: alias is the number, base the label.
     *
     * @since 1.4.0
     * @param sAttribute $attribute Existing select definition.
     * @param int $value Measurement being written.
     * @return int Existing matching option ID or the newly created option ID.
     */
    private function physicalOptionId(sAttribute $attribute, int $value): int
    {
        $existing = $attribute->values()->where('alias', (string) $value)->lockForUpdate()->first();
        if ($existing) {
            return (int) $existing->avid;
        }
        return (int) $this->getConnection()->table('s_attribute_values')->insertGetId([
            'attribute' => $attribute->id, 'alias' => (string) $value,
            'base' => self::physicalLabel((string) $attribute->alias, $value), 'code' => '',
            'position' => (int) $attribute->values()->max('position') + 1,
            'created_at' => now(), 'updated_at' => now(),
        ], 'avid');
    }

    /**
     * Read the option alias as a measurement; the label is display text such as `1kg`.
     *
     * @since 1.4.0
     * @param object $option Stored select option.
     * @return int Measurement in grams, millimetres or millilitres.
     */
    private function physicalOptionNumber(object $option): int
    {
        $alias = trim((string) ($option->alias ?? ''));
        if (!preg_match('/^\d+$/D', $alias)) {
            throw new InvalidArgumentException("Physical option {$option->avid} must have a whole-number alias.");
        }
        return self::normalizePhysicalValue($alias);
    }

    /**
     * Validate a measurement as a non-negative whole number.
     *
     * Decimal notation is accepted only without a fraction (`1000.0000` from a decimal column).
     *
     * @since 1.4.0
     * @param mixed $value Non-negative whole number, empty value or null.
     * @return int Grams, millimetres or millilitres.
     * @throws InvalidArgumentException When the value is negative, fractional or not a number.
     */
    public static function normalizePhysicalValue(mixed $value): int
    {
        $value = str_replace(',', '.', trim((string) ($value ?? '')));
        if ($value === '') {
            return 0;
        }
        if (!preg_match('/^\+?(\d+)(?:\.0*)?$/D', $value, $match)
            || ($number = filter_var(ltrim($match[1], '0') ?: '0', FILTER_VALIDATE_INT)) === false) {
            throw new InvalidArgumentException('Physical attributes require a non-negative whole number of grams, millimetres or millilitres.');
        }
        return $number;
    }
}
