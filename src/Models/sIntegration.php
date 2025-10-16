<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @deprecated
 * sIntegration - Model for integration configurations
 *
 * This model represents integration configurations in the sCommerce system.
 * It stores metadata about available integrations, their classes, active status,
 * and positioning for the administrative interface.
 *
 * Key Features:
 * - Integration registration and management
 * - Active/inactive status control
 * - Position-based ordering for admin interface
 * - Class name storage for dynamic instantiation
 * - Unique key-based identification
 *
 * Database Table: s_integrations
 * Primary Key: id (auto-increment)
 *
 * @package Seiger\sCommerce\Models
 * @author Seiger IT Team
 * @since 1.0.0
 */
class sIntegration extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'key',        // Unique integration identifier
        'class',      // Full class name for instantiation
        'active',     // Active status (boolean)
        'position',   // Display order in admin interface
        'hidden',     // Hide visible in admin interface
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Scope a query to only include active integrations.
     *
     * This scope filters the query to only return integrations that are
     * currently active and available for use. It's commonly used in
     * administrative interfaces and integration resolution.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The query builder instance
     * @return \Illuminate\Database\Eloquent\Builder The modified query builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
