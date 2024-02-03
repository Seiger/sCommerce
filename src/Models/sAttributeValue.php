<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class sAttributeValue extends Model
{
    protected $primaryKey = 'avid';
    protected $fillable = ['attribute', 'alias'];

    /**
     * Retrieve a collection of sAttributeValue table columns with their corresponding metadata.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function describe()
    {
        $collection = collect([]);
        $columns = DB::select("SHOW COLUMNS FROM ".DB::getTablePrefix().sAttributeValue::query()->from);
        if ($columns) {
            foreach ($columns as $column) {
                if ($column) {
                    $item = new \stdClass();
                    foreach ($column as $key => $value) {
                        $item->{strtolower($key)} = $value;
                    }
                    $collection->put($item->field, $item);
                }
            }
        }
        return $collection;
    }

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