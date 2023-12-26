<?php
/**
 * Plugin for Seiger Commerce Management Module for Evolution CMS admin panel.
 */

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Facades\sCommerce;

/**
 * Add Menu item
 */
Event::listen('evolution.OnManagerMenuPrerender', function($params) {
    if (evo()->getConfig('scom_in_main_menu', 0) == 1) {
        $menu['scommerce'] = [
            'scommerce',
            'main',
            '<i class="' . __('sCommerce::global.icon') . '"></i><span class="menu-item-text">' . __('sCommerce::global.title') . '</span>',
            sCommerce::moduleUrl(),
            __('sCommerce::global.title'),
            "",
            "",
            "main",
            0,
            evo()->getConfig('scom_main_menu_order', 11),
        ];

        return serialize(array_merge($params['menu'], $menu));
    }
});
