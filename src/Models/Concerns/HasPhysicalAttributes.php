<?php namespace Seiger\sCommerce\Models\Concerns;

use InvalidArgumentException;
use Seiger\sCommerce\Models\sAttribute;

/**
 * Store physical measurements as attributes and consume retained legacy columns on save.
 *
 * Existing columns remain in upgraded schemas only. Positive legacy values migrate on save
 * and are zeroed in the same transaction. Reads use explicit attribute methods.
 *
 * @since 1.4.0
 */
trait HasPhysicalAttributes
{
    public const PHYSICAL_ATTRIBUTES = ['weight', 'width', 'height', 'length', 'volume'];

    /** @var array<string, string> Values assigned before a product has its database ID. */
    protected array $pendingPhysicalAttributes = [];

    /**
     * Persist explicit physical attributes and drain positive legacy columns atomically.
     *
     * A positive legacy column is consumed once and wins over the existing attribute.
     * Zero columns do not overwrite attributes. Ordinary saves and quiet saves use this path.
     *
     * @since 1.4.0
     * @param array<string, mixed> $options Eloquent save options.
     * @return bool Whether the product and its physical values were saved.
     */
    public function save(array $options = [])
    {
        $legacy = [];
        foreach (self::PHYSICAL_ATTRIBUTES as $alias) {
            if (array_key_exists($alias, $this->attributes) && (float) $this->attributes[$alias] > 0) {
                $legacy[$alias] = $this->attributes[$alias];
            }
        }
        $values = $this->pendingPhysicalAttributes;
        foreach ($legacy as $alias => $value) {
            $values[$alias] = self::normalizePhysicalValue($value);
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
     * Queue an explicit numeric attribute write for the next product save.
     *
     * @since 1.4.0
     * @param string $alias One of the five physical attribute aliases.
     * @param mixed $value Non-negative measurement in the store's existing units.
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
     * These scalar values are suitable for cart and delivery payloads.
     *
     * @since 1.4.0
     * @return array<string, string> Five measurements with four decimal places.
     */
    public function physicalValues(): array
    {
        $attributes = $this->exists ? $this->getRelationValue('attrValues')->keyBy('alias') : collect();
        $values = [];
        foreach (self::PHYSICAL_ATTRIBUTES as $alias) {
            $attribute = $attributes->get($alias);
            $raw = $attribute?->pivot->value ?? 0;
            if ($attribute && (int) $attribute->type === sAttribute::TYPE_ATTR_SELECT) {
                $option = $attribute->values()->where('avid', $attribute->pivot->valueid)->first();
                if (!$option) {
                    throw new InvalidArgumentException("Missing physical option for {$alias}.");
                }
                $raw = $this->physicalOptionNumber($option);
            } elseif ($attribute && (int) $attribute->type !== sAttribute::TYPE_ATTR_NUMBER) {
                throw new InvalidArgumentException("Unsupported physical attribute type for {$alias}.");
            }
            $values[$alias] = $this->pendingPhysicalAttributes[$alias] ?? self::normalizePhysicalValue($raw);
        }
        return $values;
    }

    /**
     * Resolve a numeric select option without changing the merchant's attribute type.
     *
     * @since 1.4.0
     * @param sAttribute $attribute Existing select definition.
     * @param string $value Normalized measurement being written.
     * @return int Existing matching option ID or the newly created numeric option ID.
     */
    private function physicalOptionId(sAttribute $attribute, string $value): int
    {
        $matches = [];
        foreach ($attribute->values()->lockForUpdate()->get() as $option) {
            try {
                if ($this->physicalOptionNumber($option) === $value) {
                    $matches[] = (int) $option->avid;
                }
            } catch (InvalidArgumentException) {
                // Non-numeric choices remain untouched and are not candidates for this measurement.
            }
        }
        if (count($matches) > 1) {
            throw new InvalidArgumentException("Ambiguous numeric options for physical attribute {$attribute->alias}.");
        }
        if ($matches !== []) {
            return $matches[0];
        }
        $connection = $this->getConnection();
        if ($connection->table('s_attribute_values')->where('attribute', $attribute->id)->where('alias', $value)->exists()) {
            throw new InvalidArgumentException("Conflicting physical option alias for {$attribute->alias}.");
        }
        return (int) $connection->table('s_attribute_values')->insertGetId([
            'attribute' => $attribute->id, 'alias' => $value, 'base' => $value, 'code' => '',
            'position' => (int) $attribute->values()->max('position') + 1,
            'created_at' => now(), 'updated_at' => now(),
        ], 'avid');
    }

    /**
     * Read the option label as a measurement; never interpret its database ID or slug.
     *
     * @since 1.4.0
     * @param object $option Stored select option with base label and alias.
     * @return string Four-decimal measurement.
     */
    private function physicalOptionNumber(object $option): string
    {
        $label = trim((string) ($option->base ?? ''));
        $label = $label !== '' ? $label : trim((string) $option->alias);
        if (!preg_match('/^\+?\d+(?:[.,]\d+)?$/D', $label)) {
            throw new InvalidArgumentException("Physical option {$option->avid} must have a plain numeric label.");
        }
        return self::normalizePhysicalValue($label);
    }

    /**
     * Validate physical values without truncating fractional weights or dimensions.
     *
     * No unit conversion is performed: migration preserves the store's existing units.
     *
     * @since 1.4.0
     * @param mixed $value Non-negative decimal, empty value or null.
     * @return string Value with the legacy four-decimal precision.
     * @throws InvalidArgumentException When the supplied value is invalid or negative.
     */
    public static function normalizePhysicalValue(mixed $value): string
    {
        $value = str_replace(',', '.', trim((string) ($value ?? '')));
        if ($value === '') {
            $value = '0';
        }
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) {
            throw new InvalidArgumentException('Physical attributes require a non-negative decimal value.');
        }
        return number_format((float) $value, 4, '.', '');
    }
}
