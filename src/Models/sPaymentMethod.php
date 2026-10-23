<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class sPaymentMethod extends Model
{
    /**
     * Canonical name of the credentials column.
     *
     * @since 1.5.0
     */
    public const CREDENTIALS = 'credentials';

    /**
     * Legacy column name that starts with Cyrillic U+0441. It stays readable and
     * writable through the model until the rename migration has run.
     *
     * @since 1.5.0
     */
    public const LEGACY_CREDENTIALS = "\u{0441}redentials";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'class',
        'identifier',
        'active',
        'position',
        'title',
        'description',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /** Physical credentials column detected for the current process. */
    private static ?string $credentialsColumn = null;

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string)Str::uuid();
            }
        });
    }

    /**
     * Return the physical credentials column of `s_payment_methods`.
     *
     * Prefers the canonical name and falls back to the legacy spelling on schemas
     * that have not been migrated yet. The result is cached once a column is found.
     *
     * @return string Either {@see self::CREDENTIALS} or {@see self::LEGACY_CREDENTIALS}.
     * @since 1.5.0
     */
    public static function credentialsColumn(): string
    {
        if (self::$credentialsColumn !== null) {
            return self::$credentialsColumn;
        }

        $model = new static();
        $schema = $model->getConnection()->getSchemaBuilder();
        foreach ([self::CREDENTIALS, self::LEGACY_CREDENTIALS] as $column) {
            if ($schema->hasColumn($model->getTable(), $column)) {
                return self::$credentialsColumn = $column;
            }
        }

        return self::CREDENTIALS;
    }

    /**
     * Reset the cached column name after a schema change.
     *
     * @internal Used by the credentials rename migration and tests.
     * @since 1.5.0
     */
    public static function forgetCredentialsColumn(): void
    {
        self::$credentialsColumn = null;
    }

    /**
     * Read either credentials spelling from the physical column.
     *
     * @since 1.5.0
     */
    public function getAttribute($key)
    {
        return parent::getAttribute($this->credentialsKey($key));
    }

    /**
     * Write either credentials spelling to the physical column.
     *
     * @since 1.5.0
     */
    public function setAttribute($key, $value)
    {
        return parent::setAttribute($this->credentialsKey($key), $value);
    }

    /**
     * Unset either credentials spelling from the physical column.
     *
     * @since 1.5.0
     */
    public function offsetUnset($offset): void
    {
        parent::offsetUnset($this->credentialsKey($offset));
    }

    /**
     * Scope a query to only include active payment methods.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Map both credentials spellings to the column this model was loaded with.
     */
    private function credentialsKey(mixed $key): mixed
    {
        if ($key !== self::CREDENTIALS && $key !== self::LEGACY_CREDENTIALS) {
            return $key;
        }
        foreach ([self::CREDENTIALS, self::LEGACY_CREDENTIALS] as $column) {
            if (array_key_exists($column, $this->attributes)) {
                return $column;
            }
        }

        return static::credentialsColumn();
    }
}
