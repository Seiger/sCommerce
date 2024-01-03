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

/**
 * Add icon to tree
 */
Event::listen('evolution.OnManagerNodePrerender', function($params) {
    if (evo()->getConfig('scom_catalog_root', 0) > 1) {
        switch ($params['ph']['id']) {
            case evo()->getConfig('scom_catalog_root') :
                $params['ph']['icon'] = '<i class="' . __('sCommerce::global.icon') . '"></i>';
                $params['ph']['icon_folder_open'] = "<i class='" . __('sCommerce::global.icon') . "'></i>";
                $params['ph']['icon_folder_close'] = "<i class='" . __('sCommerce::global.icon') . "'></i>";
                break;
        }

        return serialize($params['ph']);
    }
});
