<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * sAttributeValue - Model for attribute values
 *
 * This model represents individual values for product attributes in the sCommerce system.
 * It provides a flexible way to store predefined values that can be assigned to
 * product attributes, enabling consistent data entry and validation.
 *
 * Key Features:
 * - Predefined attribute values for consistent data entry
 * - Relationship with parent attributes
 * - Flexible alias system for URL generation
 * - Position-based ordering for display
 * - Database schema introspection capabilities
 *
 * Database Table: s_attribute_values
 * Primary Key: avid (auto-increment)
 *
 * Relationships:
 * - belongsTo sAttribute (parent attribute)
 *
 * @package Seiger\sCommerce\Models
 * @author Seiger IT Team
 * @since 1.0.0
 */
class sAttributeValue extends Model
{
    protected $primaryKey = 'avid';
    protected $fillable = ['attribute', 'alias'];

    /**
     * Retrieve a collection of table columns with their metadata.
     *
     * This method performs database schema introspection to retrieve detailed
     * information about all columns in the s_attribute_values table. It returns
     * a collection where each item contains column metadata such as type,
     * constraints, default values, and other database-specific information.
     *
     * The returned collection includes:
     * - Column names as keys
     * - Column metadata as values (type, nullable, default, etc.)
     * - Normalized property names (lowercase)
     * - Complete schema information for each column
     *
     * @return \Illuminate\Support\Collection Collection of column metadata objects
     */
    public static function describe()
    {
        $collection = collect([]);
        $columns = Schema::getColumns(sAttributeValue::query()->from);
        if ($columns) {
            foreach ($columns as $column) {
                if ($column) {
                    $item = new \stdClass();
                    foreach ($column as $key => $value) {
                        $item->{strtolower($key)} = $value;
                    }
                    $collection->put($item->name, $item);
                }
            }
        }
        return $collection;
    }

    /**
     * Get the parent attribute that this value belongs to.
     *
     * This method defines a belongsTo relationship with the sAttribute model,
     * establishing a parent-child relationship where each attribute value
     * belongs to exactly one attribute definition.
     *
     * The relationship uses:
     * - Foreign key: 'attribute' (in s_attribute_values table)
     * - Local key: 'id' (in s_attributes table)
     * - Model: sAttribute
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo Relationship to parent attribute
     */
    public function attribute()
    {
        return $this->belongsTo(sAttribute::class, 'id', 'attribute');
    }
}