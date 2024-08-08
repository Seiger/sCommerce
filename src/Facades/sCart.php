<?php namespace Seiger\sCommerce\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Seiger\sCommerce\sCart addItem($item)
 * @method static \Seiger\sCommerce\sCart removeItem($itemId)
 * @method static \Seiger\sCommerce\sCart clear()
 * @method static \Seiger\sCommerce\sCart getItems()
 * @method static \Seiger\sCommerce\sCart getTotal()
 * @method static \Seiger\sCommerce\sCart getItemCount()
 * @method static \Seiger\sCommerce\sCart updateItemQuantity($itemId, $quantity)
 * @method static \Seiger\sCommerce\sCart getInstance()
 */
class sCart extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sCart';
    }
}
