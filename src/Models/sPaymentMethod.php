<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class sPaymentMethod extends Model
{
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

    protected static function booted(): void
    {
        /*static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string)Str::uuid();
            }
        });*/
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
}
