<?php namespace Seiger\sCommerce\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Seiger\sCommerce\Wishlist\sWishlist
 */
class sWishlist extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sWishlist';
    }
}
