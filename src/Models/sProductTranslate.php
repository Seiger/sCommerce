<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class sProductTranslate extends Model
{
    protected $primaryKey = 'tid';
    protected $fillable = ['product', 'lang'];

    public function product()
    {
        return $this->belongsTo(sProduct::class, 'id', 'product');
    }
}