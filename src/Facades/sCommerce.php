<?php namespace Seiger\sCommerce\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Seiger\sCommerce\sCommerce
 */
class sCommerce extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sCommerce';
    }
}