<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class sAttributeTranslate extends Model
{
    protected $primaryKey = 'atid';
    protected $fillable = ['lang'];

    /**
     * Get the related sAttribute model for this attribute.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function attribute()
    {
        return $this->belongsTo(sAttribute::class, 'id', 'attribute');
    }
}