<?php namespace Seiger\sCommerce\Facades;

use Illuminate\Support\Facades\Facade;

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