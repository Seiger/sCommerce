<?php namespace Seiger\sCommerce;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class sCommerce
{
    /**
     * Module url
     *
     * @return string
     */
    public function moduleUrl(): string
    {
        return 'index.php?a=112&id=' . md5(__('sCommerce::global.title'));
    }
}