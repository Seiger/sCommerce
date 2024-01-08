<?php
/**
 * E-commerce management module
 */

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cookie;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sProductTranslate;
use Seiger\sGallery\Facades\sGallery;

if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') die("No access");
if (!file_exists(EVO_CORE_PATH . 'custom/config/seiger/settings/sCommerce.php')) {
    evo()->webAlertAndQuit(__('sCommerce::global.finish_configuring'), "index.php?a=2");
}

$sCommerceController = new sCommerceController();
Paginator::defaultView('sCommerce::partials.pagination');
$get = request()->get ?? (sCommerce::config('basic.orders_on', 1) == 1 ? "orders" : "products");
$iUrl = (int)request()->input('i', 0) > 0 ? '&i=' . (int)request()->input('i', 0) : '';
$editor = [];

$tabs = ['products'];
if (evo()->hasPermission('settings')) {
    $tabs[] = 'settings';
}

switch ($get) {
    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */
    default:
    case "orders":
        break;
    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */
    case "products":
        $perpage = Cookie::get('scom_products_page_items', 50);
        $order = request()->input('order', 'id');
        $direc = request()->input('direc', 'desc');

        $data['items'] = sProduct::lang($sCommerceController->langDefault())->orderBy($order, $direc)->paginate($perpage);
        $data['total'] = sProduct::count();
        $data['active'] = sProduct::wherePublished(1)->count();
        $data['disactive'] = $data['total'] - $data['active'];
        break;
    /*
    |--------------------------------------------------------------------------
    | Product
    |--------------------------------------------------------------------------
    */
    case "product":
        $tabs = ['product', 'content'];
        $iUrl = trim($iUrl) ?: '&i=0';
        $product = sCommerce::getProduct((int)request()->input('i', 0));
        $data['categories'] = [];
        $data['item'] = $product;
        break;
    case "productSave":
        $requestId = (int)request()->input('i', 0);
        $alias = request()->input('alias', 'new-product');
        $product = sCommerce::getProduct($requestId);

        $votes = data_is_json($product->votes ?? '', true);
        $type = $product->type ?: 'simple';
        $cover = sGallery::first('product', $requestId);

        if (empty($alias) || str_starts_with($alias, 'new-product')) {
            $translate = sProductTranslate::whereProduct($requestId)
                ->whereIn('lang', ['en', $sCommerceController->langDefault()])->orderByRaw('FIELD(lang, "en", "' . $sCommerceController->langDefault() . '")')
                ->first();
            if ($translate) {
                $alias = trim($translate->pagetitle) ?: 'new-product';
            } else {
                $alias = 'new-product';
            }
        }

        if (!$votes) {
            $votes = [];
            $votes['total'] = 1;
            $votes['1'] = 0;
            $votes['2'] = 0;
            $votes['3'] = 0;
            $votes['3'] = 0;
            $votes['4'] = 0;
            $votes['5'] = 1;
        }

        $product->published = (int)request()->input('published', 0);
        $product->availability = (int)request()->input('availability', 0);
        $product->category = (int)request()->input('parent', sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1)));
        $product->sku = request()->input('sku', '');
        $product->alias = $sCommerceController->validateAlias($alias, (int)$product->id);
        $product->position = (int)request()->input('position', 0);
        $product->quantity = (int)request()->input('quantity', 0);
        $product->price_regular = $sCommerceController->validatePrice(request()->input('price_regular', 0));
        $product->price_special = $sCommerceController->validatePrice(request()->input('price_special', 0));
        $product->price_opt_regular = $sCommerceController->validatePrice(request()->input('price_opt_regular', 0));
        $product->price_opt_special = $sCommerceController->validatePrice(request()->input('price_opt_special', 0));
        $product->weight = (float)request()->input('weight', 0);
        $product->cover = str_replace(MODX_SITE_URL, '', $cover->src ?? '/assets/site/noimage.png');
        $product->relevants = json_encode(request()->input('relevants', []));
        $product->similar = json_encode(request()->input('similar', []));
        $product->tmplvars = json_encode(request()->input('tmplvars', []));
        $product->votes = json_encode($votes);
        $product->type = $type;
        $product->save();

        $product->categories()->sync((array)request()->input('categories', []));

        if (!$product->texts->count()) {
            $product->texts()->create(['lang' => $sCommerceController->langDefault()]);
        }

        $sCommerceController->setProductsListing();
        $back = str_replace('&i=0', '&i=' . $product->id, (request()->back ?? '&get=product'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "productDelete":
        $product = sCommerce::getProduct((int)request()->input('i', 0));

        if ($product) {
            $sCommerceController->removeDirRecursive(MODX_BASE_PATH . 'assets/sgallery/product/' . $product->id);
            
            $product->categories()->sync([]);
            $product->texts()->delete();
            $product->delete();
        }

        $sCommerceController->setProductsListing();
        $back = '&get=products';
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "content":
        $tabs = ['product', 'content'];
        $iUrl = trim($iUrl) ?: '&i=0';
        $requestId = (int)request()->input('i', 0);
        $requestLang = request()->input('lang');
        $content = sProductTranslate::whereProduct($requestId)->whereLang($requestLang)->firstOrNew();
        $content->id = $content->product ?? $requestId;

        $buttons = [];
        $elements = [];
        $templates = [];
        $richtexts = [];
        $fields = glob(MODX_BASE_PATH . 'assets/modules/scommerce/builder/*/config.php');
        View::getFinder()->setPaths([MODX_BASE_PATH . 'assets/modules/scommerce/builder']);

        if (count($fields)) {
            foreach ($fields as $idx => $field) {
                $template = str_replace('config.php', 'template.blade.php', $field);

                if (is_file($template)) {
                    $template = basename(dirname($template));
                    $field = require $field;

                    if ((int)$field['active']) {
                        $id = $field['id'];
                        $templates[$id] = $template;
                        $order = ($field['order'] ?? ($idx + 25));
                        while (isset($buttons[$order])) {
                            $order++;
                        }
                        $buttons[$order] = $sCommerceController->view('partials.addBlockButton', compact(['id', 'field']))->render();
                        $elements[] = view($template . '.template', compact(['id']))->render();
                        if (strtolower($field['type']) == 'richtext') {
                            $richtexts[$id] = [];
                        }
                    }
                }
            }
        }
        ksort($buttons);

        $chunks = [];
        $builder = data_is_json($content->builder ?? '', true);
        if (is_array($builder) && count($builder)) {
            foreach ($builder as $i => $item) {
                $key = array_key_first($item);
                if (isset($templates[$key])) {
                    $id = $key . $i;
                    $value = $item[$key];
                    $chunks[] = view($template . '.template', compact(['id', 'value']))->render();
                    $richtexts[$key][] = $i;
                }
            }
        }

        foreach ($richtexts as $key => $items) {
            if(count($items)) {
                $start = last($items);
            } else {
                $start = 1;
            }

            foreach (range($start, 10) as $y) {
                $items[] = $y;
            }

            foreach ($items as $item) {
                $editor[] = $key . $item;
            }
        }

        $data['item'] = $content;
        $data['buttons'] = $buttons;
        $data['elements'] = $elements;
        $data['chunks'] = $chunks;
        break;
    case "contentSave":
        $requestId = (int)request()->input('i', 0);
        $requestLang = request()->input('lang');
        $contentField = '';
        $renders = [];
        $fields = glob(MODX_BASE_PATH . 'assets/modules/scommerce/builder/*/config.php');
        View::getFinder()->setPaths([MODX_BASE_PATH . 'assets/modules/scommerce/builder']);
        
        if (count($fields)) {
            foreach ($fields as $field) {
                $render = str_replace('config.php', 'render.blade.php', $field);
                if (is_file($render)) {
                    $render = basename(dirname($render));
                    $field = require $field;
                    $id = $field['id'];
                    $renders[$id] = $render;
                }
            }
        }

        $contentBuilder = request()->input('builder', '');
        if (is_array($contentBuilder) && count($contentBuilder)) {
            foreach ($contentBuilder as $position => $item) {
                $id = array_key_first($item);
                if (isset($renders[$id])) {
                    $value = $item[$id];
                    $contentField .= view($renders[$id] . '.render', compact(['id', 'value']))->render();
                }
            }
        }
        $contentField = str_replace([chr(9), chr(10), chr(13), '  '], '', $contentField);

        $content = sProductTranslate::whereProduct($requestId)->whereLang($requestLang)->firstOrNew();
        $content->pagetitle = request()->input('pagetitle', '');
        $content->longtitle = request()->input('longtitle', '');
        $content->introtext = request()->input('introtext', '');
        $content->content = $contentField;
        $content->seotitle = request()->input('seotitle', '');
        $content->seodescription = request()->input('seodescription', '');
        $content->seorobots = request()->input('seorobots', '');
        $content->builder = json_encode(array_values(request()->input('builder', [])));
        $content->constructor = json_encode(request()->input('constructor', []));
        if (($content->product ?? 0) == 0) {
            $product = sCommerce::getProduct($requestId);
            if (!$product->id) {
                $product->alias = $sCommerceController->validateAlias(trim($content->pagetitle) ?: 'new-product', $requestId);
                $product->save();
            }
            $content->product = $product->id;
        }
        if (!$content->tid) {
            $content->lang = $requestLang;
        }
        $content->save();

        $sCommerceController->setProductsListing();
        $back = str_replace('&i=0', '&i=' . $content->product, (request()->back ?? '&get=product'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    case "settings":
        if (!evo()->hasPermission('settings')) {
            $back = request()->back ?? '&get=orders';
            return header('Location: ' . sCommerce::moduleUrl() . $back);
        }
        break;
    case "settingsSave":
        $sCommerceController->updateDBConfigs();
        $sCommerceController->updateFileConfigs();
        evo()->clearCache('full');

        session()->flash('success', __('sCommerce::global.settings_save_success'));
        $back = request()->back ?? '&get=settings';
        return header('Location: ' . sCommerce::moduleUrl() . $back);
}

$data['sCommerceController'] = $sCommerceController;
$data['editor'] = $sCommerceController->textEditor(implode(',', $editor));
$data['tabs'] = $tabs;
$data['get'] = $get;
$data['iUrl'] = $iUrl;
$data['moduleUrl'] = sCommerce::moduleUrl();

echo $sCommerceController->view('index', $data);
