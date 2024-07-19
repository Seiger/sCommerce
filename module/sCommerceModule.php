<?php
/**
 * E-commerce management module
 */

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sAttributeValue;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sProductTranslate;
use Seiger\sCommerce\Models\sReview;
use Seiger\sGallery\Facades\sGallery;
use Seiger\sGallery\Models\sGalleryField;
use Seiger\sGallery\Models\sGalleryModel;

if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') die("No access");
if (!file_exists(EVO_CORE_PATH . 'custom/config/seiger/settings/sCommerce.php')) {
    evo()->webAlertAndQuit(__('sCommerce::global.finish_configuring'), "index.php?a=2");
}

$sCommerceController = new sCommerceController();
Paginator::defaultView('sCommerce::partials.pagination');
$get = request()->get ?? (sCommerce::config('basic.orders_on', 1) == 1 ? "orders" : "products");
$iUrl = (int)request()->input('i', 0) > 0 ? '&i=' . (int)request()->input('i', 0) : '';
$editor = [];

$tabs = ['products', 'reviews', 'attributes'];
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
        $query = sProduct::lang($sCommerceController->langDefault())->search();

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
        $data['total'] = sProduct::count();
        $data['active'] = sProduct::wherePublished(1)->count();
        $data['disactive'] = $data['total'] - $data['active'];
        break;
    case "product":
        $tabs = ['product'];
        $iUrl = trim($iUrl) ?: '&i=0';
        $requestId = (int)request()->input('i', 0);
        $product = sCommerce::getProduct($requestId);

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

        $data['categories'] = [];
        $data['item'] = $product;

        $tabs[] = 'content';
        break;
    case "productSave":
        $filters = ['constructor'];
        $all = request()->all();
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
            $votes['total'] = 0;
            $votes['1'] = 0;
            $votes['2'] = 0;
            $votes['3'] = 0;
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
        $rating = round((int)$votes['total'] ? $summ / $votes['total'] : 5, 1);

        $product->published = (int)request()->input('published', 0);
        $product->availability = (int)request()->input('availability', 0);
        $product->sku = request()->input('sku', '');
        $product->alias = $sCommerceController->validateAlias($alias, (int)$product->id);
        $product->position = (int)request()->input('position', 0);
        $product->rating = ($rating == 0 ? 5 : $rating);
        $product->quantity = (int)request()->input('quantity', 0);
        $product->price_regular = $sCommerceController->validatePrice(request()->input('price_regular', 0));
        $product->price_special = $sCommerceController->validatePrice(request()->input('price_special', 0));
        $product->price_opt_regular = $sCommerceController->validatePrice(request()->input('price_opt_regular', 0));
        $product->price_opt_special = $sCommerceController->validatePrice(request()->input('price_opt_special', 0));
        $product->currency = request()->input('currency', sCommerce::config('basic.main_currency', 'USD'));
        $product->weight = (float)request()->input('weight', 0);
        $product->cover = str_replace(MODX_SITE_URL, '', $cover->src ?? '/assets/site/noimage.png');
        $product->relevants = json_encode(request()->input('relevants', []));
        $product->similar = json_encode(request()->input('similar', []));
        $product->tmplvars = json_encode(request()->input('tmplvars', []));
        $product->votes = json_encode($votes);
        $product->type = $type;
        $product->save();

        $categories = (array)request()->input('categories', []);
        if (evo()->getConfig('check_sMultisite', false)) {
            foreach(Seiger\sMultisite\Models\sMultisite::all() as $domain) {
                $parent = (int)request()->input('parent_' . $domain->key, 0);
                if ($parent > 0) {
                    $categories[$parent] = ['scope' => 'primary_' . $domain->key];
                }
            }
        } else {
            $categories[(int)request()->input('parent', sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1)))] = ['scope' => 'primary'];
        }
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

        $sCommerceController->setProductsListing();
        $back = str_replace('&i=0', '&i=' . $product->id, (request()->back ?? '&get=product'));
        evo()->invokeEvent('OnAfterProductSave', compact('product', 'text'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "productDelete":
        $product = sCommerce::getProduct((int)request()->input('i', 0));

        if ($product && isset($product->id) && (int)$product->id > 0) {
            $sCommerceController->removeDirRecursive(MODX_BASE_PATH . 'assets/sgallery/product/' . $product->id);
            $galleries = sGallery::all('product', $product->id);
            if ($galleries->count()) {
                foreach ($galleries as $gallery) {
                    if ($gallery) {
                        sGalleryField::where('key', $gallery->id)->delete();
                        $gallery->delete();
                    }
                }
            }

            $product->categories()->sync([]);
            $product->texts()->delete();
            $product->delete();
        }

        $sCommerceController->setProductsListing();
        $back = '&get=products';
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "prodattributes":
        $tabs = ['product', 'prodattributes', 'content'];
        $iUrl = trim($iUrl) ?: '&i=0';
        $requestId = (int)request()->input('i', 0);
        $product = sCommerce::getProduct($requestId);

        $categoryParentsIds = [0];
        if ($product->categories) {
            foreach ($product->categories as $category) {
                $categoryParentsIds = array_merge($categoryParentsIds, $sCommerceController->categoryParentsIds($category->id));
            }
        }

        $attributes = sAttribute::lang($sCommerceController->langDefault())->whereHas('categories', function ($q) use ($categoryParentsIds) {
            $q->whereIn('category', $categoryParentsIds);
        })->orderBy('position')->get();

        $attrValues = $product->attrValues->mapWithKeys(function ($value) {
            return [$value->id => $value];
        })->all();

        $attributes->mapWithKeys(function ($attribute) use ($attrValues) {
            $attribute->value = $attrValues[$attribute->id]->pivot->value ?? '';
            return $attribute;
        });

        $data['product'] = $product;
        $data['attributes'] = $attributes;
        break;
    case "prodattributesSave":
        $filters = ['attribute'];
        $all = request()->all();
        $requestId = (int)request()->input('i', 0);
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
                                case sAttribute::TYPE_ATTR_TEXT : // 5
                                    if (is_array($value) && count($value)) {
                                        $vals = [];
                                        foreach ($value as $k => $v) {
                                            if (trim($v)) {
                                                $vals[$k] = trim($v);
                                            }
                                        }
                                        if (count($vals)) {
                                            $product->attrValues()->attach($key, ['valueid' => 0, 'value' => json_encode($vals)]);
                                        }
                                    }
                                    break;
                                case sAttribute::TYPE_ATTR_CUSTOM : // 15
                                    if (is_array($value) && count($value)) {
                                        $product->attrValues()->attach($key, ['valueid' => 0, 'value' => json_encode($value)]);
                                    }
                                    break;
                            }
                        }
                    }
                }
            }
        }

        $back = str_replace('&i=0', '&i=' . $product->id, (request()->back ?? '&get=prodattributes'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "content":
        $tabs = ['product'];
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
                if (is_file(dirname($field).'/template.blade.php')) {
                    $template = basename(dirname($field));
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
                    $chunks[] = view($templates[$key] . '.template', compact(['i', 'id', 'value']))->render();
                    $richtexts[$key][] = $i;
                    if (isset($value['richtext']) && is_array($value['richtext']) && count($value['richtext'])) {
                        foreach (range(1, count($value['richtext'])) as $rnum) {
                            $richtexts[$key][] = $i . $rnum;
                        }
                    }
                }
            }
        }

        foreach ($richtexts as $key => $items) {
            if (!count($items)) {
                $items[] = 1;
            }

            foreach ($items as $item) {
                $editor[] = $key . $item;
            }
        }

        if (sCommerce::config('product.visual_editor_introtext', 0) == 1) {
            $editor[] = 'introtext';
        }

        $product = sCommerce::getProduct($content->product ?? 0);
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

        $data['item'] = $content;
        $data['buttons'] = $buttons;
        $data['elements'] = $elements;
        $data['chunks'] = $chunks;

        $tabs[] = 'content';
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
    | Attributes
    |--------------------------------------------------------------------------
    */
    case "attributes":
        $perpage = Cookie::get('scom_attributes_page_items', 50);
        $order = request()->input('order', 'id');
        $direc = request()->input('direc', 'desc');
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
        break;
    case "attribute":
        $iUrl = trim($iUrl) ?: '&i=0';
        $requestId = (int)request()->input('i', 0);
        $attribute = sCommerce::getAttribute($requestId);

        $tabs = ['attribute'];
        if (in_array($attribute->type, [
            sAttribute::TYPE_ATTR_SELECT,
            sAttribute::TYPE_ATTR_MULTISELECT,
        ])) {
            $tabs[] = 'attrvalues';
        }

        $data['item'] = $attribute;
        $data['categories'] = $attribute->categories->pluck('id')->toArray();
        $data['texts'] = $attribute->texts->mapWithKeys(function ($item) {
            return [$item->lang => $item];
        })->all();
        break;
    case "attrvalues":
        $iUrl = trim($iUrl) ?: '&i=0';
        $requestId = (int)request()->input('i', 0);
        $attribute = sCommerce::getAttribute($requestId);

        $tabs = ['attribute'];
        if (in_array($attribute->type, [
            sAttribute::TYPE_ATTR_SELECT,
            sAttribute::TYPE_ATTR_MULTISELECT,
        ])) {
            $tabs[] = 'attrvalues';
        }

        $data['item'] = $attribute;
        $data['values'] = $attribute->values;
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

        $back = str_replace('&i=0', '&i=' . $attribute->id, (request()->back ?? '&get=attribute'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "attributeDelete":
        $attribute = sCommerce::getAttribute((int)request()->input('i', 0));

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
                /** @TODO multilang $columns */
                $columns = sAttributeValue::describe();
                dd(!$columns->has('eng'));
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
        break;
    case "review":
        $tabs = ['review'];
        $iUrl = trim($iUrl) ?: '&i=0';
        $requestId = (int)request()->input('i', 0);
        $review = sReview::find($requestId);
        $data['item'] = $review;
        break;
    case "reviewSave":
        $all = request()->all();
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

        $review->product = (int)($all['product'] ?? 0);
        $review->rating = (int)($all['rating'] ?? 5);
        $review->name = $all['name'] ?? '';
        $review->message = $all['message'] ?? '';
        $review->published = (int)($all['published'] ?? 0);
        $review->created_at = $all['created_at'];
        $review->save();

        $back = str_replace('&i=0', '&i=' . $review->id, (request()->back ?? '&get=review'));
        return header('Location: ' . sCommerce::moduleUrl() . $back);
    case "reviewDelete":
        $requestId = (int)request()->input('i', 0);
        $review = sReview::find($requestId);

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
    | Settings
    |--------------------------------------------------------------------------
    */
    case "settings":
        if (!evo()->hasPermission('settings')) {
            $back = request()->back ?? '&get=orders';
            return header('Location: ' . sCommerce::moduleUrl() . $back);
        }

        $data['mainProductConstructors'] = [];
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
$data['editor'] = count($editor) ? $sCommerceController->textEditor(implode(',', $editor)) : '';
$data['tabs'] = $tabs;
$data['get'] = $get;
$data['iUrl'] = $iUrl;
$data['moduleUrl'] = sCommerce::moduleUrl();

echo $sCommerceController->view('index', $data);
