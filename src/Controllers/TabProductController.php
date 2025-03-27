<?php namespace Seiger\sCommerce\Controllers;

use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sProductTranslate;
use View;

class TabProductController
{
    public function content(int $requestId = 0, string $requestLang = 'base')
    {
        $tabs = ['product'];
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
                if (is_file(dirname($field) . '/template.blade.php')) {
                    $template = basename(dirname($field));
                    $field = require $field;

                    if ((int)$field['active']) {
                        $id = $field['id'];
                        $templates[$id] = $template;
                        $order = ($field['order'] ?? ($idx + 25));
                        while (isset($buttons[$order])) {
                            $order++;
                        }
                        $buttons[$order] = (new sCommerceController())->view('partials.addBlockButton', compact(['id', 'field']))->render();
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

        if ($product && (int)$product?->type > 0) {
            if (in_array($product->type, [
                sProduct::TYPE_GROUP,
            ])) {
                $tabs[] = 'modifications';
            }
        }

        $categoryParentsIds = [0];
        if ($product->categories) {
            foreach ($product->categories as $category) {
                $categoryParentsIds = array_merge($categoryParentsIds, (new sCommerceController())->categoryParentsIds($category->id));
            }
        }
        $attributes = sAttribute::whereHas('categories', function ($q) use ($categoryParentsIds) {
            $q->whereIn('category', $categoryParentsIds);
        })->get();
        if ($attributes->count()) {
            $tabs[] = 'prodattributes';
        }

        $data['product'] = $product;
        $data['item'] = $content;
        $data['buttons'] = $buttons;
        $data['elements'] = $elements;
        $data['chunks'] = $chunks;

        $tabs[] = 'content';

        $_SESSION['itemaction'] = 'Editing a Product content' . ($requestLang != 'base' ? $requestLang : '');
        $_SESSION['itemname'] = $product->title;

        return compact(['tabs', 'editor', 'data']);
    }
}