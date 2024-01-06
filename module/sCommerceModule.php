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
$editor = '';

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

        $data['items'] = sProduct::orderBy($order, $direc)->paginate($perpage);
        break;
    /*
    |--------------------------------------------------------------------------
    | Product
    |--------------------------------------------------------------------------
    */
    case "product":
        $tabs = ['product', 'content'];
        $product = sCommerce::getProduct((int)request()->input('i', 0));
        //dd($product);
        $data['categories'] = [];
        $data['product'] = $product;
        break;
    case "productSave":
        $requestId = (int)request()->input('i', 0);
        $alias = request()->input('alias', 'new-product');
        $product = sCommerce::getProduct($requestId);

        $votes = data_is_json($product->votes ?? '', true);
        $type = $product->type ?: 'simple';
        $cover = sGallery::first('product', $requestId);

        if (empty($alias)) {
            $translate = sProductTranslate::whereProduct($requestId)
                ->whereIn('lang', ['en', $sCommerceController->langDefault()])->orderByRaw('FIELD(lang, "en", "' . $sCommerceController->langDefault() . '")')
                ->first();
            if ($translate) {
                $alias = $translate->pagetitle;
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
        $product->cover = $cover->src ?? '/assets/site/noimage.png';
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
        break;
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
$data['editor'] = $editor;
$data['tabs'] = $tabs;
$data['get'] = $get;
$data['moduleUrl'] = sCommerce::moduleUrl();

echo $sCommerceController->view('index', $data);
