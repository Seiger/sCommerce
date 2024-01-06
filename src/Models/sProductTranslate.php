<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent;

class sProductTranslate extends Eloquent\Model
{
    protected $primaryKey = 'tid';
    protected $fillable = ['lang'];

    public function product()
    {
        return $this->belongsTo(sProduct::class, 'product');
    }
}