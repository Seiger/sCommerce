<?php
/**
 * Plugin for Seiger Commerce Management Module for Evolution CMS admin panel.
 */

use EvolutionCMS\Models\SiteTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Facades\sCommerce;

/**
 * Catch the Product by alias
 */
Event::listen('evolution.OnPageNotFound', function($params) {
    $goTo = false;
    $aliasArr = request()->segments();
    if ($aliasArr[0] == evo()->getConfig('lang', 'base')) {
        unset($aliasArr[0]);
    }
    $alias = implode('/', $aliasArr);
    $goTo = Arr::exists(sCommerce::documentListing(), $alias);
    if (!$goTo && evo()->getLoginUserID('mgr')) {
        $alias = Arr::last($aliasArr);
        $product = sCommerce::getProductByAlias($alias ?? '');
        if ($product && isset($product->id) && (int)$product->id > 0) {
            $goTo = true;
        }
    }
    if ($goTo) {
        evo()->sendForward(evo()->getConfig('site_start', 1));
        exit();
    }
});

/**
 * Get document fields and add to array of resource fields
 */
Event::listen('evolution.OnBeforeLoadDocumentObject', function($params) {
    $aliasArr = request()->segments();
    if (isset($aliasArr[0]) && $aliasArr[0] == evo()->getConfig('lang', 'base')) {
        unset($aliasArr[0]);
    }

    $alias = implode('/', $aliasArr);
    $document = sCommerce::documentListing()[$alias] ?? false;
    if (!$document && evo()->getLoginUserID('mgr')) {
        $alias = Arr::last($aliasArr);
        $product = sCommerce::getProductByAlias($alias ?? '');
        if ($product && isset($product->id) && (int)$product->id > 0) {
            $document = (int)$product->id;
        }
    }

    if ($document) {
        $product = sCommerce::getProduct($document, evo()->getConfig('lang', 'base'));
        $product->constructor = data_is_json($product->constructor, true);
        $product->tmplvars = data_is_json($product->tmplvars, true);

        if ($product->tmplvars && count($product->tmplvars)) {
            foreach ($product->tmplvars as $name => $value) {
                if (isset($params['documentObject'][$name]) && is_array($params['documentObject'][$name])) {
                    $params['documentObject'][$name][1] = $value;
                }
            }
        }

        $template = SiteTemplate::whereTemplatealias('s_commerce_product')->first();
        $product->template = $template->id ?? 0;
        $product->hide_from_tree = false;
        $product->content_dispo = false;
        $product->deleted = 0;
        $product->cacheable = 1;

        if (sCommerce::config('product.views_on', 1) == 1) {
            if (!in_array($product->id, $_SESSION['s_commerce_product_views'] ?? [])) {
                $product->increment('views');
                $_SESSION['s_commerce_product_views'][] = $product->id;
            }
        }

        unset($product->tmplvars);
        return $params['documentObject'] = Arr::dot($product->toArray());
    }
});

/**
 * Add Menu item
 */
Event::listen('evolution.OnManagerMenuPrerender', function($params) {
    if (sCommerce::config('basic.in_main_menu', 0) == 1) {
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
            sCommerce::config('basic.main_menu_order', 11),
        ];

        return serialize(array_merge($params['menu'], $menu));
    }
});

/**
 * Add icon to tree
 */
Event::listen('evolution.OnManagerNodePrerender', function($params) {
    if (sCommerce::config('basic.catalog_root', 0) > 1) {
        switch ($params['ph']['id']) {
            case sCommerce::config('basic.catalog_root') :
                $params['ph']['icon'] = '<i class="' . __('sCommerce::global.icon') . '"></i>';
                $params['ph']['icon_folder_open'] = "<i class='" . __('sCommerce::global.icon') . "'></i>";
                $params['ph']['icon_folder_close'] = "<i class='" . __('sCommerce::global.icon') . "'></i>";
                break;
        }

        return serialize($params['ph']);
    }
});
