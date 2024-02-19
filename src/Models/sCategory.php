<?php namespace Seiger\sCommerce\Models;

use EvolutionCMS\Models\SiteContent;

/**
 * Class sCategory
 * This class extends the SiteContent class and represents a category.
 *
 * @package Seiger\sCommerce
 */
class sCategory extends SiteContent
{
    /**
     * Retrieve the products associated with this category.
     *
     * This method establishes a many-to-many relationship between the current category
     * and the products using the "s_product_category" pivot table. It returns an instance
     * of the Eloquent \Illuminate\Database\Eloquent\Relations\BelongsToMany class.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products()
    {
        return $this->belongsToMany(sProduct::class, 's_product_category', 'category', 'product');
    }
}