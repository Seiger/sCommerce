<?php
/**
 * Plugin for Seiger Commerce Management Module for Evolution CMS admin panel.
 */

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * Add Menu item
 */
Event::listen('evolution.OnManagerMenuPrerender', function($params) {
    $menu['scommerce'] = [
        'scommerce',
        'main',
        '<i class="' . __('sCommerce::global.icon') . '"></i><span class="menu-item-text">' . __('sCommerce::global.title') . '</span>',
        sCommerce::route('sCommerce.index'),
        __('sCommerce::global.title'),
        "",
        "",
        "main",
        0,
        8,
    ];

    return serialize(array_merge($params['menu'], $menu));
});
