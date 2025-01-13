<?php namespace Seiger\sCommerce\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Seiger\sCommerce\Checkout\sCheckout
 */
class sCheckout extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \Seiger\sCommerce\Checkout\sCheckout::class;
    }
}
