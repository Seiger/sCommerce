<?php
/**
 * E-commerce management module sCommerce
 */

use EvolutionCMS\Facades\ManagerTheme;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Controllers\TabProductController;
use Seiger\sCommerce\Facades\sCheckout;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Interfaces\DeliveryMethodInterface;
use Seiger\sCommerce\Interfaces\PaymentMethodInterface;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sAttributeValue;
use Seiger\sCommerce\Models\sCategory;
use Seiger\sCommerce\Models\sDeliveryMethod;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sPaymentMethod;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sProductTranslate;
use Seiger\sCommerce\Models\sReview;
use Seiger\sGallery\Facades\sGallery;
use Seiger\sGallery\Models\sGalleryField;
use Seiger\sGallery\Models\sGalleryModel;
use Seiger\sTask\Facades\sTask;
use Seiger\sTask\Models\sWorker;

if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') die("No access");
if (!file_exists(EVO_CORE_PATH . 'custom/config/seiger/settings/sCommerce.php')) {
    evo()->webAlertAndQuit(__('sCommerce::global.finish_configuring'), "index.php?a=2");
}

$sCommerceController = new sCommerceController();
Paginator::defaultView('sCommerce::partials.pagination');
$get = request()->get ?? (sCommerce::config('basic.orders_on', 1) == 1 ? "orders" : "products");
$iUrl = (int)request()->input('i', 0) > 0 ? '&i=' . (int)request()->input('i', 0) : '';
$pUrl = (int)request()->input('page', 1) > 1 ? '&page=' . (int)request()->input('page', 1) : '';
$editor = [];
$tabs = [];

if (sCommerce::config('basic.orders_on', 1) == 1) {
    $tabs[] = 'orders';
}
$tabs = array_merge($tabs, ['products', 'reviews', 'attributes']);
if (sCommerce::config('basic.integrations_on', 1) == 1) {
    $tabs[] = 'integrations';
}
if (count(sCommerce::config('basic.available_currencies', [])) > 1 && trim(sCommerce::config('basic.main_currency', ''))) {
    $tabs[] = 'currencies';
}
if (sCommerce::config('basic.payments_on', 1) == 1) {
    $tabs[] = 'payments';
}
if (sCommerce::config('basic.deliveries_on', 1) == 1) {
    $tabs[] = 'deliveries';
}
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
        if (is_array($handlers = evo()->invokeEvent('sCommerceManagerAddTabEvent'))) {
            foreach ($handlers as $handler) {
                if (trim($handler['handler']) && is_file($handler['handler'])) {
                    include_once $handler['handler'];
                }
            }
        }
    case "orders":
        $perpage = Cookie::get('scom_orders_page_items', 50);
        $dbStatuses = array_flip(sOrder::select('status')->distinct()->pluck('status')->toArray());
        $status = request()->input('status', 0);
        $status = isset($dbStatuses[$status]) ? $status : 0;
        $order = request()->input('order', 'id');
        $direc = request()->input('direc', 'desc');

        $query = sOrder::query()->select('*');
        $query->orderBy($order, $direc);

        $unprocessedes = [
            sOrder::ORDER_STATUS_NEW,
            sOrder::ORDER_STATUS_FAILED,
        ];
        $workings = [
            sOrder::ORDER_STATUS_PROCESSING,
            sOrder::ORDER_STATUS_CONFIRMED,
            sOrder::ORDER_STATUS_PACKING,
            sOrder::ORDER_STATUS_READY_FOR_SHIPMENT,
            sOrder::ORDER_STATUS_SHIPPED,
            sOrder::ORDER_STATUS_DELIVERED,
            sOrder::ORDER_STATUS_ON_HOLD,
            sOrder::ORDER_STATUS_RETURN_REQUESTED,
        ];
        $completeds = [
            sOrder::ORDER_STATUS_DELETED,
            sOrder::ORDER_STATUS_COMPLETED,
            sOrder::ORDER_STATUS_CANCELED,
            sOrder::ORDER_STATUS_RETURNED,
        ];

        $data['items'] = $query->paginate($perpage);
        $data['unprocessedes'] = $unprocessedes;
        $data['workings'] = $workings;
        $data['completeds'] = $completeds;
        $data['status'] = $status;
        $data['statuses'] = array_intersect_key(sOrder::listOrderStatuses(), $dbStatuses);
        $data['total'] = sOrder::count();
        $data['unprocessed'] = sOrder::whereIn('status', $unprocessedes)->count();
        $data['working'] = sOrder::whereIn('status', $workings)->count();
        $data['completed'] = sOrder::whereIn('status', $completeds)->count();
        $_SESSION['itemaction'] = 'Viewing a list of orders';
        $_SESSION['itemname'] = __('sCommerce::global.title');
        break;
    case "order":
        $tabs = ['order'];
        $iUrl = trim($iUrl) ?: '&i=0';
        $requestId = (int)request()->input('i', 0);
        $item = sOrder::find($requestId);

        $unprocessedes = [
            sOrder::ORDER_STATUS_NEW,
            sOrder::ORDER_STATUS_FAILED,
        ];
        $workings = [
            sOrder::ORDER_STATUS_PROCESSING,
            sOrder::ORDER_STATUS_CONFIRMED,
            sOrder::ORDER_STATUS_PACKING,
            sOrder::ORDER_STATUS_READY_FOR_SHIPMENT,
            sOrder::ORDER_STATUS_SHIPPED,
            sOrder::ORDER_STATUS_DELIVERED,
            sOrder::ORDER_STATUS_ON_HOLD,
            sOrder::ORDER_STATUS_RETURN_REQUESTED,
        ];
        $completeds = [
            sOrder::ORDER_STATUS_DELETED,
            sOrder::ORDER_STATUS_COMPLETED,
            sOrder::ORDER_STATUS_CANCELED,
            sOrder::ORDER_STATUS_RETURNED,
        ];

        $data['item'] = $item;
        $data['unprocessedes'] = $unprocessedes;
        $data['workings'] = $workings;
        $data['completeds'] = $completeds;
        $data['payment'] = isset($item->payment_info['method']) && trim($item->payment_info['method']) ? sCheckout::getPayment($item->payment_info['method']) : false;
        $data['delivery'] = isset($item->delivery_info['method']) && trim($item->delivery_info['method']) ? sCheckout::getDelivery($item->delivery_info['method']) : false;
        $_SESSION['itemaction'] = 'Editing a Order of #' . $item->id;
        $_SESSION['itemname'] = __('sCommerce::global.title');
        break;
    case "orderSave":
        $requestId = request()->integer('i');
        $item = sOrder::find($requestId);

        if ($item) {
            $history = $item->history;

            $payment_status = request()->integer('payment_status', $item->payment_status);
            if ($payment_status != $item->payment_status) {
                $item->payment_status = $payment_status;
                $history[] = [
                    'payment_status' => $payment_status,
                    'timestamp' => now()->toDateTimeString(),
                    'user_id' => (int)evo()->getLoginUserID('mgr'),
                ];
            }

            $status = request()->integer('status', $item->status);
            if ($status != $item->status) {
                $item->status = $status;
                $history[] = [
                    'status' => $status,
                    'timestamp' => now()->toDateTimeString(),
                    'user_id' => (int)evo()->getLoginUserID('mgr'),
                ];
            }

            if (request()->string('note')->isNotEmpty()) {
                $manager_notes = $item->manager_notes;
                $manager_notes[] = [
                    'comment' => request()->string('note')->value(),
                    'timestamp' => now()->toDateTimeString(),
                    'user_id' => (int)evo()->getLoginUserID('mgr'),
                ];
                $item->manager_notes = $manager_notes;
            }

            $item->history = $history;
            $item->update();
        }

        $_SESSION['itemaction'] = 'Saving a Order of #' . $item->id;
        $_SESSION['itemname'] = __('sCommerce::global.title');
        $back = str_replace('&i=0', '&i=' . $item->id, (request()->back ?? '&get=orders'));
        evo()->invokeEvent('sCommerceAfterOrderSave', compact('item'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */
    case "products":
        $perpage = Cookie::get('scom_per_page', 50);
        $cat = request()->input('cat', 0);
        $allCats = DB::table('s_product_category')->groupBy('category')->get()->pluck('category')->toArray();
        if (!evo()->getConfig('check_sMultisite', false)) {
            $allCats = array_merge([sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1))], $allCats);
        }
        $cat = in_array($cat, $allCats) ? $cat : 0;

        if ($cat > 0) {
            $order = request()->input('order', 'position');
            $direc = request()->input('direc', 'asc');
        } else {
            $order = request()->input('order', 'id');
            $direc = request()->input('direc', 'desc');
        }

        $query = sProduct::lang($sCommerceController->langDefault())
            ->search()
            ->extractConstructor()
            ->addSelect(['position' =>
                DB::table('s_product_category')
                    ->select('position')
                    ->where('s_product_category.category', $cat)
                    ->orWhere('s_product_category.scope', 'primary')
                    ->whereColumn('s_product_category.product', 's_products.id')
                    ->limit(1)
            ]);

        if ($cat > 0) {
            $query->whereHas('categories', function ($q) use ($cat) {
                $q->where('category', $cat);
            });
        }

        switch ($order) {
            case "category":
                $query->addSelect(
                    DB::Raw(
                        '(select `' . DB::getTablePrefix() . 'site_content`.`pagetitle` 
                        from `' . DB::getTablePrefix() . 'site_content` 
                        where `' . DB::getTablePrefix() . 'site_content`.`id` = (
                            select `' . DB::getTablePrefix() . 's_product_category`.`category` 
                            from `' . DB::getTablePrefix() . 's_product_category`
                            where `' . DB::getTablePrefix() . 's_product_category`.`product` = `' . DB::getTablePrefix() . 's_products`.`id`
                            limit 1)
                        ) as cat'
                    )
                );
                $query->orderBy('cat', $direc);
                break;
            case str_starts_with($order, 'a.'):
                $order = ltrim($order, 'a.');
                $hasLang = Schema::hasColumn('s_attribute_values', ManagerTheme::getLang());
                $query->addSelect(['sort' =>
                    DB::table('s_attribute_values')
                        ->select(DB::raw(
                            $hasLang
                                ? "CASE WHEN " . ManagerTheme::getLang() . " IS NOT NULL AND " . ManagerTheme::getLang() . " != '' THEN " . ManagerTheme::getLang() . " ELSE base END as value"
                                : "base as value"
                        ))
                        ->where('s_attribute_values.avid', function ($q) use ($order) {
                            $q->select('valueid')
                                ->from('s_product_attribute_values')
                                ->where('s_product_attribute_values.attribute', function ($q) use ($order) {
                                    $q->select('id')
                                        ->from('s_attributes')
                                        ->where('s_attributes.alias', $order);
                                })
                                ->whereColumn('s_product_attribute_values.product', 's_products.id')
                                ->limit(1);
                        })
                        ->union(DB::table('s_product_attribute_values')
                            ->select('value')
                            ->where('s_product_attribute_values.attribute', function ($q) use ($order) {
                                $q->select('id')
                                    ->from('s_attributes')
                                    ->where('s_attributes.alias', $order);
                            })
                            ->whereColumn('s_product_attribute_values.product', 's_products.id')
                            ->limit(1)
                        )
                        ->limit(1)
                ]);
                $query->orderBy('sort', $direc);
                break;
            default:
                $query->orderBy($order, $direc);
                break;
        }

        $items = $query->paginate($perpage);
        $resources = Cache::rememberForever(
            'sCommerceProductsResourcesManager',
            fn () => SiteContent::query()
                ->select('id', 'pagetitle')
                ->whereIn('id', $allCats)
                ->orderBy('pagetitle')
                ->pluck('pagetitle', 'id')
                ->all()
        );

        $domains = null;
        if (evo()->getConfig('check_sMultisite', false)) {
            $domains = \Seiger\sMultisite\Models\sMultisite::all();
        }

        $listCategories = Cache::rememberForever(
            'sCommerceListCategoriesManager',
            function () use ($domains, $resources, $sCommerceController) {
                if ($domains && count($domains)) {
                    $out = [];
                    foreach ($domains as $domain) {
                        $root = sCommerce::config('basic.catalog_root' . $domain->key, $domain->site_start);
                        $res = $sCommerceController->listCategories($root);
                        foreach ($res as $key => $value) {
                            if (isset($resources[$key])) {
                                $out[$domain->key][$key] = $value;
                            }
                        }
                    }
                    return $out;
                }

                $root = sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1));
                return $sCommerceController->listCategories($root);
            }
        );

        $data['items'] = $items;
        $data['total'] = sProduct::count();
        $data['active'] = sProduct::wherePublished(1)->count();
        $data['disactive'] = $data['total'] - $data['active'];
        $data['domains'] = $domains;
        $data['resources'] = $resources;
        $data['listCategories'] = $listCategories;
        $data['cat'] = $cat;
        $_SESSION['itemaction'] = 'Viewing a list of products';
        $_SESSION['itemname'] = __('sCommerce::global.title');
        break;
    case "sortproducts":
        $tabs = ['sortproducts'];
        $cat = request()->input('cat', 0);
        $catName = sCategory::find($cat)->pagetitle ?? __('sCommerce::global.title');
        $query = sProduct::lang($sCommerceController->langDefault());

        if (count(sCommerce::config('products.additional_fields', []))) {
            foreach (sCommerce::config('products.additional_fields', []) as $field) {
                $query->addSelect(
                    DB::Raw(
                        '(select `' . DB::getTablePrefix() . 's_product_translates`.`constructor` ->> "$.'.$field.'"
                        from `' . DB::getTablePrefix() . 's_product_translates` 
                        where `' . DB::getTablePrefix() . 's_product_translates`.`product` = `' . DB::getTablePrefix() . 's_products`.`id`
                        and `' . DB::getTablePrefix() . 's_product_translates`.`lang` = "base"
                        ) as ' . $field
                    )
                );
            }
        }

        $query->addSelect(['position' =>
            DB::table('s_product_category')
                ->select('position')
                ->where('s_product_category.category', $cat)
                ->whereColumn('s_product_category.product', 's_products.id')
        ])->whereHas('categories', function ($q) use ($cat) {$q->where('category', $cat);})
            ->orderBy('position');

        $data['cat'] = $cat;
        $data['catName'] = $catName;
        $data['items'] = $query->get();
        $_SESSION['itemaction'] = 'Sorting a list of products by Category ' . $catName;
        $_SESSION['itemname'] = __('sCommerce::global.title');
        break;
    case "sortproductssave":
        $cat = request()->input('cat', 0);
        $catName = sCategory::find($cat)->pagetitle ?? __('sCommerce::global.title');
        $products = request()->get('products', []);

        foreach ($products as $position => $product) {
            DB::table('s_product_category')
                ->where('category', $cat)
                ->where('product', $product)
                ->update(['position' => $position]);
        }

        $_SESSION['itemaction'] = 'Save Sorting products in Category ' . $catName;
        $_SESSION['itemname'] = __('sCommerce::global.title');
        $back = '&get=products&cat='.$cat;
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    /*
    |--------------------------------------------------------------------------
    | Product
    |--------------------------------------------------------------------------
    */
    case "product":
        $tabs = ['product'];
        $iUrl = trim($iUrl) ?: '&i=0';
        $requestId = (int)request()->input('i', 0);
        $product = sCommerce::getProduct($requestId);

        if ($product && (int)$product?->mode > 0) {
            if (in_array($product->mode, [
                sProduct::MODE_GROUP,
            ])) {
                $tabs[] = 'modifications';
            }
        }

        $categoryParentsIds = [0];
        if ($product->categories) {
            foreach ($product->categories as $category) {
                $categoryParentsIds = array_merge($categoryParentsIds, $sCommerceController->categoryParentsIds($category->id));
            }
        }

        $attributes = sAttribute::whereHas('categories', function ($q) use ($categoryParentsIds) {
            $q->whereIn('category', $categoryParentsIds);
        })->get();
        if ($attributes->count()) {
            $tabs[] = 'prodattributes';
        }

        $data['categories'] = $product->categories->pluck('id')->toArray();
        $data['item'] = $product;

        $tabs[] = 'content';

        if ($requestId > 0) {
            $_SESSION['itemaction'] = 'Editing Product';
            $_SESSION['itemname'] = $product->title;
        } else {
            $_SESSION['itemaction'] = 'Creating a Product';
            $_SESSION['itemname'] = __('sCommerce::global.title');
        }
        break;
    case "productForView":
        $requestId = (int)request()->input('i', 0);
        $view = request()->input('view');
        $parameters = json_decode(request()->string('parameters', ''), true) ?: [];
        $data['success'] = 0;

        if ($requestId && $view) {
            $product = sCommerce::getProduct($requestId);
            if ($product) {
                $data['success'] = 1;
                $data['view'] = $sCommerceController->view($view, ['item' => $product, 'parameters' => $parameters])->render();
            }
        }

        die(json_encode($data));
    case "productSave":
        $filters = ['constructor'];
        $all = request()->all();
        $requestId = (int)request()->input('i', 0);
        $alias = request()->input('alias', 'new-product');
        $product = sCommerce::getProduct($requestId);
        $prodCats = $product->categories->mapWithKeys(function ($value) {return [$value->id => $value->pivot->position];})->all();

        // Get categories with primary scope before saving
        $catsBefore = $product->categories()
            ->wherePivot('scope', 'LIKE', 'primary%')
            ->pluck('s_product_category.category')
            ->toArray();

        $votes = data_is_json($product->votes ?? '', true);
        $cover = sGallery::first('product', $requestId);

        if (empty($alias) || str_starts_with($alias, 'new-product')) {
            $translate = sProductTranslate::whereProduct($requestId)
                ->whereIn('lang', ['en', $sCommerceController->langDefault()])
                ->orderByRaw("CASE lang WHEN 'en' THEN 0 WHEN ? THEN 1 ELSE 2 END", [$sCommerceController->langDefault()])
                ->first();
            if ($translate) {
                $alias = trim($translate->pagetitle) ?: 'new-product';
            } else {
                $alias = 'new-product';
            }
        }

        if (!$votes) {
            $votes = [];
            $votes['total'] = 0;
            $votes['1'] = 0;
            $votes['2'] = 0;
            $votes['3'] = 0;
            $votes['4'] = 0;
            $votes['5'] = 0;
        }

        $summ = 0;
        foreach ($votes as $key => $value) {
            if ((int)$key > 0) {
                $summ += (int)$key * (int)$value;
            }
        }
        $rating = (int)$votes['total'] > 0 ? round($summ / ($votes['total'] ?? 1), 1) : 0;

        $product->published = (int)request()->input('published', 0);
        $product->availability = (int)request()->input('availability', 0);
        $product->sku = request()->input('sku', '');
        $product->alias = $sCommerceController->validateAlias($alias, (int)$product->id);
        $product->rating = ($rating == 0 ? 5 : $rating);
        if (sCommerce::config('product.inventory_on', 0) == 2) {
            $product->inventory = (int)request()->input('inventory', 0);
        }
        $product->price_regular = $sCommerceController->validatePrice(request()->input('price_regular', 0));
        $product->price_special = $sCommerceController->validatePrice(request()->input('price_special', 0));
        $product->price_opt_regular = $sCommerceController->validatePrice(request()->input('price_opt_regular', 0));
        $product->price_opt_special = $sCommerceController->validatePrice(request()->input('price_opt_special', 0));
        $product->currency = request()->input('currency', sCommerce::config('basic.main_currency', 'USD'));
        $product->weight = $sCommerceController->validateNumber(request()->input('weight', 0));
        $product->width = $sCommerceController->validateNumber(request()->input('width', 0));
        $product->height = $sCommerceController->validateNumber(request()->input('height', 0));
        $product->length = $sCommerceController->validateNumber(request()->input('length', 0));
        $product->volume = $sCommerceController->validateNumber(request()->input('volume', 0));
        $product->cover = str_replace(EVO_SITE_URL, '', $cover->src ?? '/assets/site/noimage.png');
        $product->relevants = json_encode(request()->input('relevants', []), JSON_UNESCAPED_UNICODE);
        $product->similar = json_encode(request()->input('similar', []), JSON_UNESCAPED_UNICODE);
        $product->tmplvars = json_encode(request()->input('tmplvars', []), JSON_UNESCAPED_UNICODE);
        $product->votes = json_encode($votes, JSON_UNESCAPED_UNICODE);
        $product->mode = request()->integer('mode');
        $product->save();

        $inputCats = (array)request()->input('categories', []);
        $categories = [];
        foreach ($inputCats as $cat) {
            $categories[$cat] = ['position' => ($prodCats[$cat] ?? 0)];
        }
        if (evo()->getConfig('check_sMultisite', false)) {
            foreach(Seiger\sMultisite\Models\sMultisite::all() as $domain) {
                $parent = (int)request()->input('parent_' . $domain->key, 0);
                if ($parent > 0) {
                    $categories[$parent] = ['scope' => 'primary_' . $domain->key, 'position' => ($prodCats[$parent] ?? 0)];
                }
            }
        } else {
            $parent = (int)request()->input('parent', sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1)));
            $categories[$parent] = ['scope' => 'primary', 'position' => ($prodCats[$parent] ?? 0)];
        }
        $product->categories()->sync([]);
        $product->categories()->sync($categories);

        if (!$product->texts->count()) {
            $product->texts()->create(['lang' => $sCommerceController->langDefault()]);
        }

        $constructorInput = [];
        foreach ($filters as $filter) {
            foreach ($all as $key => $value) {
                if (str_starts_with($key, $filter . '__')) {
                    $key = str_replace($filter . '__', '', $key);
                    $constructorInput[$key] = $value;
                }
            }
        }

        $text = null;
        foreach ($product->texts as $text) {
            $constructor = data_is_json($text->constructor, true);
            if (is_array($constructor)) {
                $constructor = array_merge($constructor, $constructorInput);
            } else {
                $constructor = $constructorInput;
            }
            $text->constructor = json_encode($constructor);
            $text->update();
        }

        // Get categories with primary scope after saving
        $catsAfter = $product->categories()
            ->wherePivot('scope', 'LIKE', 'primary%')
            ->pluck('s_product_category.category')
            ->toArray();

        // Check if primary categories were changed
        $primaryCategoriesChanged = count(array_diff($catsBefore, $catsAfter)) > 0 || count(array_diff($catsAfter, $catsBefore)) > 0;

        if (isset($product->getChanges()['alias']) || $primaryCategoriesChanged) {
            // Primary categories or alias changed - trigger cache rebuild
            sTask::create('s_products_listing_cache', 'make');
        }

        $_SESSION['itemaction'] = 'Saving Product';
        $_SESSION['itemname'] = $product->title;
        $back = str_replace('&i=0', '&i=' . $product->id, (request()->back ?? '&get=product'));
        evo()->invokeEvent('sCommerceAfterProductSave', compact('product', 'text'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "productDuplicate":
        $requestId = (int)request()->input('i', 0);
        $product = sCommerce::getProduct($requestId);

        if ($requestId > 0 && $product && isset($product->id) && (int)$product->id > 0) {
            // Product
            $newProduct = sCommerce::getProduct(0);
            $newProduct->published = 0;
            $newProduct->availability = $product->availability;
            $newProduct->sku = $product->sku;
            $newProduct->alias = $sCommerceController->validateAlias($product->alias . '-duplicate', 0);
            $newProduct->rating = $product->rating;
            $newProduct->inventory = $product->inventory;
            $newProduct->price_regular = $product->price_regular;
            $newProduct->price_special = $product->price_special;
            $newProduct->price_opt_regular = $product->price_opt_regular;
            $newProduct->price_opt_special = $product->price_opt_special;
            $newProduct->currency = $product->currency;
            $newProduct->weight = $product->weight;
            $newProduct->width = $product->width;
            $newProduct->height = $product->height;
            $newProduct->length = $product->length;
            $newProduct->volume = $product->volume;
            $newProduct->cover = $product->cover;
            $newProduct->relevants = $product->relevants;
            $newProduct->similar = $product->similar;
            $newProduct->tmplvars = $product->tmplvars;
            $newProduct->votes = $product->votes;
            $newProduct->mode = $product->mode;
            $newProduct->additional = $product->additional;
            $newProduct->representation = $product->representation;
            $newProduct->save();

            // Categories
            $categories = $product->categories()->withPivot('scope')->get();
            foreach ($categories as $category) {
                $newProduct->categories()->attach($category->id, ['scope' => $category->pivot->scope]);
            }

            // Attribures
            if ($product->attrValues->count()) {
                foreach ($product->attrValues as $attrValue) {
                    $newProduct->attrValues()->attach($attrValue->id, ['valueid' => $attrValue->pivot->valueid, 'value' => $attrValue->pivot->value]);
                }
            }

            // Texts
            foreach ($product->texts as $text) {
                $array = $text->toArray();
                unset($array['tid'], $array['product']);
                $array['pagetitle'] = $array['pagetitle'] . ' - Duplicate';
                $newProduct->texts()->create($array);
                $newProduct->texts()->update($array);
            }

            // Images
            $galleryDirectory = MODX_BASE_PATH . 'assets/sgallery/product/' . $product->id;
            if (is_dir($galleryDirectory)) {
                $sCommerceController->copyDirRecursive(
                    $galleryDirectory,
                    MODX_BASE_PATH . 'assets/sgallery/product/' . $newProduct->id,
                );

                $galleries = sGallery::collections()->documentId($product->id)->itemType('product')->get();
                if ($galleries->count()) {
                    foreach ($galleries as $gallery) {
                        if ($gallery) {
                            $gArr = $gallery->toArray();
                            $thisFile = sGalleryModel::whereParent($newProduct->id)
                                ->whereBlock($gArr['block'])
                                ->whereItemType($gArr['type'])
                                ->whereFile($gArr['file'])
                                ->firstOrCreate();
                            $thisFile->parent = $newProduct->id;
                            $thisFile->block = $gArr['block'];
                            $thisFile->position = $gArr['position'];
                            $thisFile->file = $gArr['file'];
                            $thisFile->type = $gArr['type'];
                            $thisFile->item_type = $gArr['item_type'];
                            $thisFile->update();

                            $fields = sGalleryField::where('key', $gallery->id)->get();
                            foreach ($fields as $field) {
                                $fArr = $field->toArray();
                                $fTranslate = new sGalleryField();
                                $fTranslate->key = $thisFile->id;
                                $fTranslate->lang = $fArr['lang'];
                                $fTranslate->alt = $fArr['alt'];
                                $fTranslate->title = $fArr['title'];
                                $fTranslate->description = $fArr['description'];
                                $fTranslate->link_text = $fArr['link_text'];
                                $fTranslate->link = $fArr['link'];
                                $fTranslate->save();
                            }
                        }
                    }
                }
            }
        }

        $_SESSION['itemaction'] = 'Duplicate Product';
        $_SESSION['itemname'] = $product->title;
        $back = '&get=product&i=' . $newProduct->id;
        evo()->invokeEvent('sCommerceAfterProductDuplicate', ['oldProduct' => $product, 'newProduct' => sCommerce::getProduct($newProduct->id)]);
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "productDelete":
        $product = sCommerce::getProduct((int)request()->input('i', 0));

        if ($product && isset($product->id) && (int)$product->id > 0) {
            $_SESSION['itemaction'] = 'Deleting Product';
            $_SESSION['itemname'] = $product->title;

            $galleryDirectory = MODX_BASE_PATH . 'assets/sgallery/product/' . $product->id;
            if (file_exists($galleryDirectory)) {
                $sCommerceController->removeDirRecursive(MODX_BASE_PATH . 'assets/sgallery/product/' . $product->id);
                $galleries = sGallery::collections()->documentId($product->id)->itemType('product')->get();
                if ($galleries->count()) {
                    foreach ($galleries as $gallery) {
                        if ($gallery) {
                            sGalleryField::where('key', $gallery->id)->delete();
                            $gallery->delete();
                        }
                    }
                }
            }

            $product->categories()->sync([]);
            $product->texts()->delete();
            $product->delete();
        }

        sTask::create('s_products_listing_cache', 'make');
        $back = '&get=products';
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "modifications":
        $tabs = ['product', 'modifications'];
        $iUrl = trim($iUrl) ?: '&i=0';
        $requestId = request()->integer('i');
        $product = sCommerce::getProduct($requestId);

        $categoryParentsIds = [0];
        if ($product->categories) {
            foreach ($product->categories as $category) {
                $categoryParentsIds = array_merge($categoryParentsIds, $sCommerceController->categoryParentsIds($category->id));
            }
        }

        $listAttributes = sAttribute::lang($sCommerceController->langDefault())
            ->whereHas('categories', function ($q) use ($categoryParentsIds) {
                $q->whereIn('category', $categoryParentsIds);
            })
            ->whereIn('type', [sAttribute::TYPE_ATTR_SELECT, sAttribute::TYPE_ATTR_COLOR])
            ->orderBy('position')
            ->get();

        if ($listAttributes->count()) {
            $tabs[] = 'prodattributes';
            $attrValues = [];

            foreach ($product->attrValues as $value) {
                if ($value->type == sAttribute::TYPE_ATTR_MULTISELECT) {
                    $attrValues[$value->id][] = $value;
                } else {
                    $attrValues[$value->id] = $value;
                }
            }

            $listAttributes->mapWithKeys(function ($attribute) use ($attrValues) {
                if (isset($attrValues[$attribute->id])) {
                    if (is_array($attrValues[$attribute->id])) {
                        $value = [];
                        foreach ($attrValues[$attribute->id] as $attrValue) {
                            if ($attrValue->type == sAttribute::TYPE_ATTR_MULTISELECT) {
                                $value[] = intval($attrValue->pivot->valueid);
                            }
                        }
                        $attribute->value = $value;
                    } else {
                        $attribute->value = $attrValues[$attribute->id]->pivot->value ?? '';
                    }
                } else {
                    $attribute->value = '';
                }
                return $attribute;
            });
        }

        //
        $modificationsQuery = DB::table('s_product_modifications')->select('mods', 'parameters')->where('product', $product->id)->first();
        $modificationsIds = [$product->id];
        if ($modificationsQuery) {
            $modificationsIds = json_decode($modificationsQuery->mods ?? '', true) ?: [];
            $parameters = json_decode($modificationsQuery->parameters ?? '', true) ?: [];
            if (is_array($modificationsIds) && count($modificationsIds)) {
                $modificationsIds = array_flip($modificationsIds);
                unset($modificationsIds[$product->id]);
                $modificationsIds = array_flip($modificationsIds);
                if (count($modificationsIds)) {
                    $modifications = sCommerce::getProducts($modificationsIds);
                }
            }
        }

        //

        $search = Str::of($product->title)
            ->replaceMatches('/[^\p{L}\p{N}\@\.!#$%&\'*+-\/=?^_`{|}~]/iu', ' ') // allowed symbol in email
            ->replaceMatches('/(\s){2,}/', '$1') // removing extra spaces
            ->trim()->explode(' ')
            ->filter(fn($word) => mb_strlen($word) > 2);

        $select = collect([]);
        $search->map(fn($word) => $select->push("(CASE WHEN " . sProduct::getGrammar()->wrap('pagetitle') . " LIKE '%{$word}%' THEN 1 ELSE 0 END)"));

        $productIds = sProduct::whereIn('mode', [sProduct::MODE_GROUP])->whereNotIn('product', array_merge($modificationsIds, [$product->id]))
            ->addSelect('product', DB::Raw('(' . $select->implode(' + ') . ') as points'))
            ->orderByDesc('points')
            ->get()->pluck('product')->toArray();
        $products = sCommerce::getProducts($productIds)?->items();
        //

        $tabs[] = 'content';
        $data['product'] = $product;
        $data['listAttributes'] = $listAttributes;
        $data['products'] = $products;
        $data['modifications'] = $modifications ?? collect([]);
        $data['parameters'] = $parameters ?? [];

        if ($requestId > 0) {
            $_SESSION['itemaction'] = 'Editing Product';
            $_SESSION['itemname'] = $product->title;
        } else {
            $_SESSION['itemaction'] = 'Creating a Product';
            $_SESSION['itemname'] = __('sCommerce::global.title');
        }
        break;
    case "modificationsSave":
        $requestId = request()->integer('i');
        $product = sCommerce::getProduct($requestId);

        if ($product) {
            if ($product->mode == sProduct::MODE_GROUP) {
                $modificationsQuery = DB::table('s_product_modifications')->select('mods')->where('product', $product->id)->first();
                if ($modificationsQuery) {
                    $modificationsIds = json_decode($modificationsQuery->mods ?? '', true) ?: [0];
                    if (is_array($modificationsIds) && count($modificationsIds)) {
                        DB::table('s_product_modifications')->whereIn('product', $modificationsIds)->delete();
                    }
                }

                $modifications = request()->input('modifications', []);
                $parameters = request()->input('parameters', []);

                if (is_array($modifications) && count($modifications)) {
                    foreach ($modifications as $modification) {
                        DB::table('s_product_modifications')->insert([
                            'product' => $modification,
                            'mode' => sProduct::MODE_GROUP,
                            'mods' => json_encode($modifications, JSON_UNESCAPED_UNICODE),
                            'parameters' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                }
            }
        }

        $_SESSION['itemaction'] = 'Saving a Product modifications';
        $_SESSION['itemname'] = $product->title ?? 'No title';
        $back = str_replace('&i=0', '&i=' . ($product->id ?? 0), (request()->back ?? '&get=modifications'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "prodattributes":
        $tabs = ['product'];
        $iUrl = trim($iUrl) ?: '&i=0';
        $requestId = (int)request()->input('i', 0);
        $product = sCommerce::getProduct($requestId);

        if ($product && (int)$product?->mode > 0) {
            if (in_array($product->mode, [
                sProduct::MODE_GROUP,
            ])) {
                $tabs[] = 'modifications';
            }
        }

        $tabs[] = 'prodattributes';
        $tabs[] = 'content';

        $categoryParentsIds = [0];
        if ($product->categories) {
            foreach ($product->categories as $category) {
                $categoryParentsIds = array_merge($categoryParentsIds, $sCommerceController->categoryParentsIds($category->id));
            }
        }

        $attributes = sAttribute::lang($sCommerceController->langDefault())->whereHas('categories', function ($q) use ($categoryParentsIds) {
            $q->whereIn('category', $categoryParentsIds);
        })->orderBy('position')->get();

        $attrValues = [];
        foreach ($product->attrValues as $value) {
            if ($value->type == sAttribute::TYPE_ATTR_MULTISELECT) {
                $attrValues[$value->id][] = $value;
            } else {
                $attrValues[$value->id] = $value;
            }
        }

        $attributes->mapWithKeys(function ($attribute) use ($attrValues) {
            if (isset($attrValues[$attribute->id])) {
                if (is_array($attrValues[$attribute->id])) {
                    $value = [];
                    foreach ($attrValues[$attribute->id] as $attrValue) {
                        if ($attrValue->type == sAttribute::TYPE_ATTR_MULTISELECT) {
                            $value[] = intval($attrValue->pivot->valueid);
                        }
                    }
                    $attribute->value = $value;
                } else {
                    $attribute->value = $attrValues[$attribute->id]->pivot->value ?? '';
                }
            } else {
                $attribute->value = '';
            }
            return $attribute;
        });

        $data['product'] = $product;
        $data['attributes'] = $attributes;
        $_SESSION['itemaction'] = 'Editing a Product attributes';
        $_SESSION['itemname'] = $product->title;
        break;
    case "prodattributesSave":
        $filters = ['attribute'];
        $all = request()->all();
        $requestId = request()->integer('i');
        $product = sCommerce::getProduct($requestId);

        if ($product) {
            $product->attrValues()->detach();

            $categoryParentsIds = [0];
            if ($product->categories) {
                foreach ($product->categories as $category) {
                    $categoryParentsIds = array_merge($categoryParentsIds, $sCommerceController->categoryParentsIds($category->id));
                }
            }

            $attributes = sAttribute::lang($sCommerceController->langDefault())->whereHas('categories', function ($q) use ($categoryParentsIds) {
                $q->whereIn('category', $categoryParentsIds);
            })->get();

            foreach ($filters as $filter) {
                foreach ($all as $key => $value) {
                    if (str_starts_with($key, $filter . '__')) {
                        $key = str_replace($filter . '__', '', $key);
                        $attribute = $attributes->where('id', $key)->first();
                        if ($attribute) {
                            switch ($attribute->type) {
                                case sAttribute::TYPE_ATTR_NUMBER : // 0
                                    if (trim($value)) {
                                        if (is_float($value)) {
                                            $value = floatval(str_replace(',', '.', $value));
                                        } else {
                                            $value = intval($value);
                                        }
                                        $product->attrValues()->attach($key, ['valueid' => 0, 'value' => $value]);
                                    }
                                    break;
                                case sAttribute::TYPE_ATTR_CHECKBOX : // 1
                                case sAttribute::TYPE_ATTR_PRICE_RANGE : // 16
                                    if (trim($value)) {
                                        $value = intval($value);
                                        $product->attrValues()->attach($key, ['valueid' => 0, 'value' => $value]);
                                    }
                                    break;
                                case sAttribute::TYPE_ATTR_SELECT : // 3
                                case sAttribute::TYPE_ATTR_COLOR : // 8
                                    if (trim($value)) {
                                        $valueId = intval($value);
                                        $product->attrValues()->attach($key, ['valueid' => $valueId, 'value' => $value]);
                                    }
                                    break;
                                case sAttribute::TYPE_ATTR_MULTISELECT : // 4
                                    if (is_array($value) && count($value)) {
                                        foreach ($value as $k => $v) {
                                            if (trim($v)) {
                                                $vId = intval($v);
                                                $product->attrValues()->attach($key, ['valueid' => $vId, 'value' => $v]);
                                            }
                                        }
                                    }
                                    break;
                                case sAttribute::TYPE_ATTR_TEXT : // 5
                                    if (is_array($value) && count($value)) {
                                        $vals = [];
                                        foreach ($value as $k => $v) {
                                            if (trim($v)) {
                                                $vals[$k] = trim($v);
                                            }
                                        }
                                    } elseif (is_string($value) && trim($value)) {
                                        $vals['base'] = trim($value);
                                    }

                                    if (isset($vals) && count($vals)) {
                                        $product->attrValues()->attach($key, ['valueid' => 0, 'value' => json_encode($vals, JSON_UNESCAPED_UNICODE)]);
                                    }
                                    break;
                                case sAttribute::TYPE_ATTR_CUSTOM : // 15
                                    if (is_array($value) && count($value)) {
                                        $product->attrValues()->attach($key, ['valueid' => 0, 'value' => json_encode($value, JSON_UNESCAPED_UNICODE)]);
                                    } elseif (is_string($value) && trim($value)) {
                                        $product->attrValues()->attach($attribute->id, ['valueid' => 0, 'value' => trim($value)]);
                                    }
                                    break;
                            }
                        }
                    }
                }
            }
        }

        $_SESSION['itemaction'] = 'Saving a Product attributes';
        $_SESSION['itemname'] = $product->title ?? 'No title';
        $back = str_replace('&i=0', '&i=' . ($product->id ?? 0), (request()->back ?? '&get=prodattributes'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "content":
        $requestId = (int)request()->input('i', 0);
        $requestLang = request()->input('lang', 'base');
        $iUrl = trim($iUrl) ?: '&i=0';
        $result = (new TabProductController())->content($requestId, $requestLang);

        $tabs = $result['tabs'] ?? [];
        $editor = $result['editor'] ?? [];
        $data = $result['data'] ?? [];
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

        $product = sCommerce::getProduct($requestId);
        $content = sProductTranslate::whereProduct($requestId)->whereLang($requestLang)->firstOrNew();
        $content->pagetitle = request()->input('pagetitle', '');
        $content->longtitle = request()->input('longtitle', '');
        $content->introtext = request()->input('introtext', '');
        $content->content = $contentField;
        $content->builder = json_encode(array_values(request()->input('builder', [])), JSON_UNESCAPED_UNICODE);
        $content->constructor = json_encode(request()->input('constructor', []), JSON_UNESCAPED_UNICODE);
        if (($content->product ?? 0) == 0) {
            if (!$product->id) {
                $product->alias = $sCommerceController->validateAlias(trim($content->pagetitle) ?: 'new-product', $requestId);
                $product->save();
                sTask::create('s_products_listing_cache', 'make');
            }
            $content->product = $product->id;
        }
        if (!$content->tid) {
            $content->lang = $requestLang;
        }
        $content->save();

        $_SESSION['itemaction'] = 'Saving a Product content' . ($requestLang != 'base' ? $requestLang : '');
        $_SESSION['itemname'] = $product->title;
        $back = str_replace('&i=0', '&i=' . $content->product, (request()->back ?? '&get=product'));
        evo()->invokeEvent('sCommerceAfterProductContentSave', compact('product', 'content'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    /*
    |--------------------------------------------------------------------------
    | Attributes
    |--------------------------------------------------------------------------
    */
    case "attributes":
        $perpage = Cookie::get('scom_attributes_page_items', 50);
        $order = request()->input('order', 'position');
        $direc = request()->input('direc', 'asc');
        $query = sAttribute::lang($sCommerceController->langDefault())->search();

        switch ($order) {
            case "category":
                $query->addSelect(
                    '*',
                    DB::Raw('(select `' . DB::getTablePrefix() . 'site_content`.`pagetitle` from `' . DB::getTablePrefix() . 'site_content` where `' . DB::getTablePrefix() . 'site_content`.`id` = `' . DB::getTablePrefix() . 's_products`.`category`) as cat')
                );
                $query->orderBy('cat', $direc);
                break;
            default :
                $query->orderBy($order, $direc);
                break;
        }

        $data['items'] = $query->paginate($perpage);
        $_SESSION['itemaction'] = 'Viewing a list of attributes';
        $_SESSION['itemname'] = __('sCommerce::global.title');
        break;
    case "attribute":
        $iUrl = trim($iUrl) ?: '&i=0';
        $requestId = (int)request()->input('i', 0);
        $attribute = sCommerce::getAttribute($requestId);

        $tabs = ['attribute'];
        if (in_array($attribute->type, [
            sAttribute::TYPE_ATTR_SELECT,
            sAttribute::TYPE_ATTR_MULTISELECT,
            sAttribute::TYPE_ATTR_COLOR,
        ])) {
            $tabs[] = 'attrvalues';
        }

        $data['item'] = $attribute;
        $data['categories'] = $attribute->categories->pluck('id')->toArray();
        $data['texts'] = $attribute->texts->mapWithKeys(function ($item) {
            return [$item->lang => $item];
        })->all();

        if ($requestId > 0) {
            $_SESSION['itemaction'] = 'Editing Attribute';
            $_SESSION['itemname'] = $data['texts'][$sCommerceController->langDefault()]?->pagetitle;
        } else {
            $_SESSION['itemaction'] = 'Creating a Attribute';
            $_SESSION['itemname'] = __('sCommerce::global.title');
        }
        break;
    case "attrvalues":
        $iUrl = trim($iUrl) ?: '&i=0';
        $requestId = (int)request()->input('i', 0);
        $attribute = sCommerce::getAttribute($requestId);

        $tabs = ['attribute'];
        if (in_array($attribute->type, [
            sAttribute::TYPE_ATTR_SELECT,
            sAttribute::TYPE_ATTR_MULTISELECT,
            sAttribute::TYPE_ATTR_COLOR,
        ])) {
            $tabs[] = 'attrvalues';
        }

        $data['item'] = $attribute;
        $data['values'] = $attribute->values;

        $_SESSION['itemaction'] = 'Viewing a list of attribute values';
        $_SESSION['itemname'] = $attribute->texts()->whereLang($sCommerceController->langDefault())->first()?->pagetitle ?? '';
        break;
    case "attributeSave":
        $requestId = (int)request()->input('i', 0);
        $alias = request()->input('alias', 'new-attribute');
        $attribute = sCommerce::getAttribute($requestId);

        if (empty($alias) || str_starts_with($alias, 'new-attribute')) {
            if (request()->has('texts.en') && trim(request()->string('texts.en.pagetitle')->value())) {
                $alias = trim(request()->string('texts.en.pagetitle')->value()) ?: 'new-attribute';
            } elseif (request()->has('texts.'.$sCommerceController->langDefault()) && trim(request()->string('texts.'.$sCommerceController->langDefault().'.pagetitle')->value())) {
                $alias = trim(request()->string('texts.'.$sCommerceController->langDefault().'.pagetitle')->value()) ?: 'new-attribute';
            } else {
                $alias = 'new-attribute';
            }
        }

        $attribute->published = (int)request()->input('published', 0);
        $attribute->asfilter = (int)request()->input('asfilter', 0);
        $attribute->position = (int)request()->input('position', 0);
        $attribute->type = (int)request()->input('type', 0);
        $attribute->alias = $sCommerceController->validateAlias($alias, (int)$attribute->id, 'attribute');
        $attribute->helptext = request()->string('helptext')->trim()->value();
        $attribute->save();

        $attribute->categories()->sync((array)request()->input('categories', []));

        if (request()->has('texts') && is_array(request()->input('texts', [])) && count(request()->input('texts', []))) {
            foreach (request()->input('texts', []) as $lang => $texts) {
                if (is_array($texts) && count($texts)) {
                    $text = $attribute->texts()->whereLang($lang)->first();
                    if (!$text) {
                        $attribute->texts()->create(['lang' => $lang]);
                        $text = $attribute->texts()->whereLang($lang)->first();
                    }

                    foreach ($texts as $field => $value) {
                        $text->{$field} = $value;
                    }

                    $text->update();
                }
            }
        }

        $_SESSION['itemaction'] = 'Saving a Attribute';
        $_SESSION['itemname'] = $attribute->texts()->whereLang($sCommerceController->langDefault())->first()?->pagetitle ?? '';
        $back = str_replace('&i=0', '&i=' . $attribute->id, (request()->back ?? '&get=attribute'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "attributeDelete":
        $attribute = sCommerce::getAttribute((int)request()->input('i', 0));
        $_SESSION['itemaction'] = 'Deleting Attribute';
        $_SESSION['itemname'] = $attribute->texts()->whereLang($sCommerceController->langDefault())->first()?->pagetitle ?? '';

        if ($attribute) {
            $attribute->categories()->sync([]);
            $attribute->texts()->delete();
            $attribute->values()->delete();
            $attribute->delete();
        }

        $back = '&get=attributes';
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "attrvaluesSave":
        $attribute = sCommerce::getAttribute((int)request()->input('i', 0));
        $requestValues = request()->input('values', []);
        $currentValues = $attribute->values->mapWithKeys(function ($item) {return [$item->alias => $item];})->all();
        $values = [];

        if ($attribute) {
            $langs = $sCommerceController->langList();
            $columns = sAttributeValue::describe()->forget(['created_at', 'updated_at']);
            if ($langs != ['base']) {
                foreach ($langs as $lang) {
                    if (!$columns->has($lang)) {
                        Schema::table(sAttributeValue::query()->from, function (Blueprint $table) use ($lang) {
                            $table->tinyText($lang)->after('base')->default('')->comment(strtoupper($lang) . 'translate for Title');
                        });
                    }
                }
            }

            if (count($requestValues)) {
                $keys = array_keys($requestValues);
                if (count($keys)) {
                    foreach ($requestValues['avid'] as $idx => $avid) {
                        $array = [];
                        foreach ($keys as $key) {
                            $array[$key] = $requestValues[$key][$idx];
                        }
                        if (trim($array['alias'])) {
                            $array['alias'] = $sCommerceController->validateAliasValues($array['alias'], (int)$array['avid'], (int)$attribute->id);
                        } elseif (isset($array['en'])) {
                            $array['alias'] = $sCommerceController->validateAliasValues($array['en'], (int)$array['avid'], (int)$attribute->id);
                        } else {
                            $array['alias'] = $sCommerceController->validateAliasValues($array[$sCommerceController->langDefault()], (int)$array['avid'], (int)$attribute->id);
                        }
                        $array['position'] = $idx;
                        if ($sCommerceController->langDefault() != 'base') {
                            $array['base'] = $array[$sCommerceController->langDefault()];
                        }
                        $values[$array['alias']] = $array;
                    }
                }

                $willDelete = array_diff_key($currentValues, $values);
                if (count($willDelete)) {
                    foreach ($willDelete as $item) {
                        $item->delete();
                    }
                }

                $willCreate = array_diff_key($values, $currentValues);
                if (count($willCreate)) {
                    foreach ($willCreate as $item) {
                        $attribute->values()->create(['alias' => $item['alias']]);
                    }
                }

                $willUpdate = $attribute->values()->get();
                foreach ($willUpdate as $item) {
                    if (isset($values[$item->alias]) && is_array($values[$item->alias])) {
                        foreach ($values[$item->alias] as $key => $value) {
                            if ($key != 'avid' && $columns->has($key)) {
                                $item->{$key} = $value; // @TODO add validation here with $columns
                            }
                        }
                        $item->update();
                    }
                }
            }
        }

        $_SESSION['itemaction'] = 'Saving Attribute values';
        $_SESSION['itemname'] = $attribute->texts()->whereLang($sCommerceController->langDefault())->first()?->pagetitle ?? '';
        $back = str_replace('&i=0', '&i=' . $attribute->id, (request()->back ?? '&get=attrvalues'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    */
    case "reviews":
        $perpage = Cookie::get('scom_reviews_page_items', 50);
        $order = request()->input('order', 'id');
        $direc = request()->input('direc', 'desc');
        $query = sReview::query()->search();

        switch ($order) {
            case "category":
                $query->addSelect(
                    '*',
                    DB::Raw(
                        '(select `' . DB::getTablePrefix() . 'site_content`.`pagetitle` 
                        from `' . DB::getTablePrefix() . 'site_content` 
                        where `' . DB::getTablePrefix() . 'site_content`.`id` = (
                            select `' . DB::getTablePrefix() . 's_product_category`.`category` 
                            from `' . DB::getTablePrefix() . 's_product_category`
                            where `' . DB::getTablePrefix() . 's_product_category`.`product` = `' . DB::getTablePrefix() . 's_products`.`id`
                            limit 1)
                        ) as cat'
                    )
                );
                $query->orderBy('cat', $direc);
                break;
            default :
                $query->orderBy($order, $direc);
                break;
        }

        $data['items'] = $query->paginate($perpage);
        $data['total'] = sReview::count();
        $data['active'] = sReview::wherePublished(1)->count();
        $data['disactive'] = $data['total'] - $data['active'];
        $_SESSION['itemaction'] = 'Viewing a list of reviews';
        $_SESSION['itemname'] = __('sCommerce::global.title');
        break;
    case "review":
        $tabs = ['review'];
        $iUrl = trim($iUrl) ?: '&i=0';
        $requestId = (int)request()->input('i', 0);
        $review = sReview::find($requestId);
        $data['item'] = $review;

        if ($requestId > 0) {
            $_SESSION['itemaction'] = 'Editing Review';
            $_SESSION['itemname'] = $review->name . (($review->toProduct ?? false) ? ' for ' . $review->toProduct->title : '');
        } else {
            $_SESSION['itemaction'] = 'Creating a Review';
            $_SESSION['itemname'] = __('sCommerce::global.title');
        }
        break;
    case "reviewSave":
        $all = request()->all();
        unset($all['a'], $all['id']);
        $requestId = (int)request()->input('i', 0);
        $review = sReview::find($requestId);

        if (!$review) {
            $review = new sReview();
        }

        if ($review->product && $review->published) {
            $product = sProduct::find($review->product);
            if ($product) {
                $votes = data_is_json($product->votes ?? '', true);
                if ($votes && isset($votes[$review->rating])) {
                    if ($votes[$review->rating] > 0) {
                        $votes[$review->rating] = $votes[$review->rating] - 1;
                        $votes['total'] = $votes['total'] > 0 ? $votes['total'] - 1 : 0;
                    }

                    $summ = 0;
                    foreach ($votes as $key => $value) {
                        if ((int)$key > 0) {
                            $summ += (int)$key * (int)$value;
                        }
                    }

                    $product->rating = round((int)$votes['total'] ? $summ / $votes['total'] : 5, 1);
                    $product->votes = json_encode($votes);
                    $product->update();
                }
            }
        }

        if (isset($all['product']) && (int)$all['product'] && isset($all['rating']) && (int)$all['rating'] && isset($all['published']) && (int)$all['published']) {
            $product = sProduct::find($all['product']);
            if ($product) {
                $votes = data_is_json($product->votes ?? '', true);

                if (!$votes) {
                    if (!$votes) {
                        $votes = [];
                        $votes['total'] = 0;
                        $votes['1'] = 0;
                        $votes['2'] = 0;
                        $votes['3'] = 0;
                        $votes['3'] = 0;
                        $votes['4'] = 0;
                        $votes['5'] = 0;
                    }
                }

                $votes[$all['rating']] = ($votes[$all['rating']] ?? 0) + 1;
                $votes['total'] = ($votes['total'] ?? 0) + 1;

                $summ = 0;
                foreach ($votes as $key => $value) {
                    if ((int)$key > 0) {
                        $summ += (int)$key * (int)$value;
                    }
                }

                $product->rating = round((int)$votes['total'] ? $summ / $votes['total'] : 5, 1);
                $product->votes = json_encode($votes);
                $product->update();
            }
        }

        $columns = Schema::getColumnListing('s_reviews');
        $columns = array_intersect($columns, array_keys($all));

        if ($columns) {
            foreach ($columns as $column) {
                $review->{$column} = $all[$column] ?? '';
            }
            $review->save();
        }
        /*$review->product = (int)($all['product'] ?? 0);
        $review->rating = (int)($all['rating'] ?? 5);
        $review->name = $all['name'] ?? '';
        $review->message = $all['message'] ?? '';
        $review->published = (int)($all['published'] ?? 0);
        $review->created_at = $all['created_at'];
        $review->save();*/

        $_SESSION['itemaction'] = 'Saving Review';
        $_SESSION['itemname'] = $review->name . (($review->toProduct ?? false) ? ' for ' . $review->toProduct->title : '');
        $back = str_replace('&i=0', '&i=' . $review->id, (request()->back ?? '&get=review'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "reviewDelete":
        $requestId = (int)request()->input('i', 0);
        $review = sReview::find($requestId);
        $_SESSION['itemaction'] = 'Deleting Review';
        $_SESSION['itemname'] = $review->name . (($review->toProduct ?? false) ? ' for ' . $review->toProduct->title : '');

        if ($review) {
            if ($review->product && $review->published) {
                $product = sProduct::find($review->product);
                if ($product) {
                    $votes = data_is_json($product->votes ?? '', true);
                    if ($votes && isset($votes[$review->rating])) {
                        if ($votes[$review->rating] > 0) {
                            $votes[$review->rating] = $votes[$review->rating] - 1;
                            $votes['total'] = $votes['total'] > 0 ? $votes['total'] - 1 : 0;
                        }

                        $summ = 0;
                        foreach ($votes as $key => $value) {
                            if ((int)$key > 0) {
                                $summ += (int)$key * (int)$value;
                            }
                        }

                        $product->rating = round((int)$votes['total'] ? $summ / $votes['total'] : 5, 1);
                        $product->votes = json_encode($votes);
                        $product->update();
                    }
                }
            }
            $review->delete();
        }

        $back = request()->back ?? '&get=reviews';
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    */
    case "integrations":
        $perpage = Cookie::get('scom_per_page', 50);

        $query = sWorker::query()->visible()->byScope('sCommerce');
        if (!evo()->hasPermission('stask')) {
            $query->active();
        }
        $query->ordered();

        $data['items'] = $query->paginate($perpage);

        $_SESSION['itemaction'] = 'Viewing a list of integrations';
        $_SESSION['itemname'] = __('sCommerce::global.title');
        break;
    /*
    |--------------------------------------------------------------------------
    | Currencies
    |--------------------------------------------------------------------------
    */
    case "currencies":
        $_SESSION['itemaction'] = 'Editing Currencies';
        break;
    case "currenciesSave":
        $sCommerceController->updateCurrenciesConfigs();
        evo()->clearCache('full');

        $_SESSION['itemaction'] = 'Saving Currencies';
        session()->flash('success', __('sCommerce::global.settings_save_success'));
        $back = request()->back ?? '&get=currencies';
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    /*
    |--------------------------------------------------------------------------
    | Deliveries
    |--------------------------------------------------------------------------
    */
    case "deliveries":
        $perpage = Cookie::get('scom_deliveries_page_items', 50);
        $order = request()->input('order', 'position');
        $direc = request()->input('direc', 'asc');

        $newMethods = [];
        $allMethods = sDeliveryMethod::all()->pluck('class')->toArray();

        $classMap = require base_path('vendor/composer/autoload_classmap.php');
        $exdMaps = require base_path('vendor/seiger/scommerce/config/excluded_namespaces.php');

        foreach ($classMap as $className => $path) {
            foreach ($exdMaps as $exdMap) {
                if (str_starts_with($className, $exdMap)) {
                    continue 2;
                }
            }

            try {
                if (class_exists($className)) {
                    $reflection = new ReflectionClass($className);

                    if ($reflection->implementsInterface(DeliveryMethodInterface::class) && !$reflection->isAbstract()) {
                        $newMethods[] = $className;
                    }
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        $needMethods = array_diff($newMethods, $allMethods);

        if (!empty($needMethods)) {
            foreach ($needMethods as $methodClass) {
                try {
                    $instance = new $methodClass();
                    $name = $instance->getName();
                    $title = ['base' => $instance->getTitle()];
                    $description = ['base' => ''];

                    sDeliveryMethod::create([
                        'name' => $name,
                        'class' => $methodClass,
                        'active' => false,
                        'position' => sDeliveryMethod::max('position') + 1,
                        'title' => json_encode($title, JSON_UNESCAPED_UNICODE),
                        'description' => json_encode($description, JSON_UNESCAPED_UNICODE),
                    ]);
                } catch (Throwable $e) {
                    Log::channel('scommerce')->error("Failed to register delivery method: {$methodClass}", ['error' => $e->getMessage()]);
                }
            }
        }

        $query = sDeliveryMethod::query()->orderBy($order, $direc);
        $items = $query->paginate($perpage);

        $items->getCollection()->transform(function ($method) use ($sCommerceController) {
            $className = $method->class;
            if (class_exists($className)) {
                $instance = new $className();
                $method->type = ucfirst(str_replace('_', ' ', $method->name));
                $method->title = '';
                $method->description = '';
                if (method_exists($instance, 'getType')) {
                    $method->type = $instance->getType();
                }
                if (method_exists($instance, 'getTitle')) {
                    $method->title = $instance->getTitle($sCommerceController->langDefault());
                }
                if (method_exists($instance, 'getDescription')) {
                    $method->description = $instance->getDescription($sCommerceController->langDefault());
                }
                return $method;
            }
        });

        $data['items'] = $items;
        $data['order'] = $order;

        $_SESSION['itemaction'] = 'Viewing a list of deliveries';
        $_SESSION['itemname'] = __('sCommerce::global.title');
        break;
    case "delivery":
        $tabs = ['delivery'];
        $requestId = request()->integer('i');
        $item = sDeliveryMethod::find($requestId);

        if ($requestId > 0 && $item) {
            $className = $item->class;
            if (class_exists($className)) {
                $instance = new $className();
                $item->instance = $instance;
                $item->type = ucfirst(str_replace('_', ' ', $item->name));
                if (method_exists($instance, 'getType')) {
                    $item->type = $instance->getType();
                }
            }

            $data['item'] = $item;

            $_SESSION['itemaction'] = 'Editing Delivery';
            $_SESSION['itemname'] = $item->title;
        } else {
            return header('Location: ' . sCommerce::moduleUrl() . (request()->back ?? '&get=deliveries'));
        }
        break;
    case "deliverySave":
        $requestId = request()->integer('i');
        $item = sDeliveryMethod::find($requestId);

        if ($requestId > 0 && $item) {
            $className = $item->class;
            if (class_exists($className)) {
                $instance = new $className();
                $admintitle = ucfirst(str_replace('_', ' ', $item->name));
                if (method_exists($instance, 'getAdminTitle')) {
                    $admintitle = $instance->getAdminTitle();
                }

                $item->active = request()->boolean('active');
                $item->position = request()->integer('position');
                $item->currency = request()->input('currency', sCommerce::config('basic.main_currency', 'USD'));
                $item->cost = request()->float('cost');
                $item->title = json_encode(request()->input('title', []), JSON_UNESCAPED_UNICODE);
                $item->description = json_encode(request()->input('description', []), JSON_UNESCAPED_UNICODE);
                $item->settings = $instance->prepareSettings(request()->all());
                $item->update();
            }
        }

        $_SESSION['itemaction'] = 'Saving Delivery';
        $_SESSION['itemname'] = $admintitle ?? '';
        $back = str_replace('&i=0', '&i=' . $item->id, (request()->back ?? '&get=deliveries'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */
    case "payments":
        $perpage = Cookie::get('scom_payments_page_items', 50);
        $order = request()->input('order', 'position');
        $direc = request()->input('direc', 'asc');

        $newMethods = [];
        $allMethods = sPaymentMethod::all()->pluck('class')->toArray();

        $classMap = require base_path('vendor/composer/autoload_classmap.php');
        $exdMaps = require base_path('vendor/seiger/scommerce/config/excluded_namespaces.php');

        foreach ($classMap as $className => $path) {
            foreach ($exdMaps as $exdMap) {
                if (str_starts_with($className, $exdMap)) {
                    continue 2;
                }
            }

            try {
                if (class_exists($className)) {
                    $reflection = new ReflectionClass($className);

                    if ($reflection->implementsInterface(PaymentMethodInterface::class) && !$reflection->isAbstract()) {
                        $newMethods[] = $className;
                    }
                }
            } catch (Throwable $e) {
                dump($e->getMessage());
                continue;
            }
        }

        $needMethods = array_diff($newMethods, $allMethods);

        if (!empty($needMethods)) {
            foreach ($needMethods as $methodClass) {
                try {
                    $instance = new $methodClass();
                    $name = $instance->getName();
                    $title = ['base' => ucfirst(str_replace('_', ' ', $instance->getName()))];
                    $description = ['base' => ''];

                    sPaymentMethod::create([
                        'name' => $name,
                        'class' => $methodClass,
                        'identifier' => '',
                        'active' => false,
                        'position' => sPaymentMethod::max('position') + 1,
                        'title' => json_encode($title, JSON_UNESCAPED_UNICODE),
                        'description' => json_encode($description, JSON_UNESCAPED_UNICODE),
                    ]);
                } catch (Throwable $e) {
                    Log::channel('scommerce')->error("Failed to register payment method: {$methodClass}", ['error' => $e->getMessage()]);
                }
            }
        }

        $query = sPaymentMethod::query()->orderBy($order, $direc);
        $items = $query->paginate($perpage);

        $items->getCollection()->transform(function ($method) use ($sCommerceController) {
            $className = $method->class;
            if (class_exists($className)) {
                $instance = new $className($method->identifier);
                $method->type = $instance->getType();
                $method->title = $instance->getTitle($sCommerceController->langDefault());
                $method->description = $instance->getDescription($sCommerceController->langDefault());
                return $method;
            }
        });

        $data['items'] = $items;
        $data['order'] = $order;

        $_SESSION['itemaction'] = 'Viewing a list of payments';
        $_SESSION['itemname'] = __('sCommerce::global.title');
        break;
    case "payment":
        $tabs = ['payment'];
        $requestId = request()->integer('i');
        $item = sPaymentMethod::find($requestId);

        if ($requestId > 0 && $item) {
            $className = $item->class;
            if (class_exists($className)) {
                $item->instance = new $className($item->identifier);
                $item->type = ucfirst(str_replace('_', ' ', $item->name));
                if (method_exists($item->instance, 'getType')) {
                    $item->type = $item->instance->getType();
                }
            }

            $data['item'] = $item;

            $_SESSION['itemaction'] = 'Editing Payment';
            $_SESSION['itemname'] = $item->title;
        } else {
            return header('Location: ' . sCommerce::moduleUrl() . (request()->back ?? '&get=payments'));
        }
        break;
    case "paymentSave":
        $requestId = request()->integer('i');
        $item = sPaymentMethod::find($requestId);

        if ($requestId > 0 && $item) {
            $className = $item->class;
            if (class_exists($className)) {
                $instance = new $className();
                $admintitle = ucfirst(str_replace('_', ' ', $item->name));
                if (method_exists($instance, 'getAdminTitle')) {
                    $admintitle = $instance->getAdminTitle();
                }

                $item->active = request()->boolean('active');
                $item->position = request()->integer('position');
                $item->title = json_encode(request()->input('title', []), JSON_UNESCAPED_UNICODE);
                $item->description = json_encode(request()->input('description', []), JSON_UNESCAPED_UNICODE);
                $item->сredentials = $instance->prepareCredentials(request()->all());
                $item->settings = $instance->prepareSettings(request()->all());
                $item->mode = request()->string('mode', '')->trim();
                $item->update();
            }
        }

        $_SESSION['itemaction'] = 'Saving Payment';
        $_SESSION['itemname'] = $admintitle ?? '';
        $back = str_replace('&i=0', '&i=' . $item->id, (request()->back ?? '&get=payments'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "paymentCheck":
        $requestId = request()->integer('i');
        $item = sPaymentMethod::find($requestId);

        $response = ['success' => false, 'message' => 'Payment not found'];
        if ($requestId > 0 && $item) {
            $className = $item->class;
            if (class_exists($className)) {
                try {
                    $instance = new $className($item->identifier);
                    if (method_exists($instance, 'checkConnection')) {
                        $response = (array)$instance->checkConnection($item);
                    } else {
                        $response = ['success' => false, 'message' => 'checkConnection() is not implemented'];
                    }
                } catch (Throwable $e) {
                    $response = ['success' => false, 'message' => $e->getMessage()];
                }
            } else {
                $response = ['success' => false, 'message' => 'Class not found'];
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    case "getCurrencyItem":
        $data['success'] = 0;
        $item = request()->string('item')->trim()->value();
        if ($item) {
            $currency = sCommerce::getCurrencies([$item]);
            if ($currency) {
                $data['success'] = 1;
                $data['view'] = $sCommerceController->view('partials.settingsCurrencyItem', $currency->first())->render();
            }
        }
        die(json_encode($data));
    case "settings":
        if (!evo()->hasPermission('settings')) {
            $back = request()->back ?? '&get=orders';
            return header('Location: ' . sCommerce::moduleUrl() . $back);
        }

        $views = [];
        foreach (File::allFiles(View::getFinder()->getPaths()) as $file) {
            if (Str::startsWith($file->getRelativePath(), 'notifications/email')) {
                if (Str::endsWith($file, '.blade.php') && !Str::endsWith($file, 'layout.blade.php')) {
                    $views[] = $file->getRelativePathname();
                }
            }
        }

        $data['emailNotifications'] = $views;
        $data['mainProductConstructors'] = [];
        $_SESSION['itemaction'] = 'Editing Settings';
        break;
    case "settingsSave":
        $sCommerceController->updateDBConfigs();
        $sCommerceController->updateFileConfigs();
        evo()->clearCache('full');

        $_SESSION['itemaction'] = 'Saving Settings';
        session()->flash('success', __('sCommerce::global.settings_save_success'));
        $back = request()->back ?? '&get=settings';
        return header('Location: ' . sCommerce::moduleUrl() . $back);
}

$data['sCommerceController'] = $sCommerceController;
$data['editor'] = count($editor) ? $sCommerceController->textEditor(implode(',', $editor)) : '';
$data['tabs'] = $tabs;
$data['get'] = $get;
$data['iUrl'] = $iUrl;
$data['pUrl'] = $pUrl;
$data['moduleUrl'] = sCommerce::moduleUrl();

echo $sCommerceController->view('index', $data);
