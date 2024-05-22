<?php namespace Seiger\sCommerce\Controllers;

use EvolutionCMS\Models\SiteContent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sAttributeValue;
use Seiger\sCommerce\Models\sCategory;
use Seiger\sCommerce\Models\sProduct;

class sCommerceController
{
    protected $categories = [];

    /**
     * Set the products listing in the cache.
     *
     * @return void
     */
    public function setProductsListing(): void
    {
        $productsListing = [];
        $products = sProduct::select('id', 'alias', 'category')->wherePublished(1)->get();
        if ($products) {
            foreach ($products as $product) {
                $link = str_replace(MODX_SITE_URL, '', $product->link);
                $productsListing[trim($link, '/')] = $product->id;
            }
        }
        evo()->clearCache('full');
        Cache::forever('productsListing', $productsListing);
    }

    /**
     * Update database configurations
     *
     * This method updates various database configurations based on the values provided in the request data.
     *
     * @return bool Returns true
     */
    public function updateDBConfigs(): bool
    {
        $prf = 'scom_';
        $tbl = evo()->getDatabase()->getFullTableName('system_settings');

        if (request()->has('basic__catalog_root') && request()->input('basic__catalog_root') != evo()->getConfig($prf . 'catalog_root')) {
            $catalog_root = request()->input('basic__catalog_root');
            evo()->getDatabase()->query("REPLACE INTO {$tbl} (`setting_name`, `setting_value`) VALUES ('{$prf}catalog_root', '{$catalog_root}')");
            evo()->setConfig($prf . 'catalog_root', $catalog_root);
        }
        return true;
    }

    /**
     * Update file configurations
     *
     * This method updates the file configurations based on the provided tabs array.
     * It generates a PHP file with the updated settings and saves it in a specific location.
     *
     * @return bool
     */
    public function updateFileConfigs(): bool
    {
        $filters = ['basic', 'constructor', 'product', 'products'];
        $all = request()->all();

        if (isset($all['main_product_constructors']) && is_array($all['main_product_constructors']) && count($all['main_product_constructors'])) {
            $keys = array_keys($all['main_product_constructors']);
            if (count($keys)) {
                foreach ($all['main_product_constructors']['key'] as $idx => $keyname) {
                    $array = [];
                    $keyname = Str::slug($keyname, '_');
                    foreach ($keys as $key) {
                        if ($key == 'key') {
                            $array[$key] = $keyname;
                        } elseif ($key == 'options') {
                            $oldkey = $all['main_product_constructors']['oldkey'][$idx] ?? '';
                            $array['options'] = [];
                            if (trim($oldkey) && isset($all['main_product_constructors']['options'][$oldkey])) {
                                if (is_array($all['main_product_constructors']['options'][$oldkey]) && count($all['main_product_constructors']['options'][$oldkey])) {
                                    $array['options'] = $all['main_product_constructors']['options'][$oldkey];
                                }
                            }
                        } else {
                            $array[$key] = $all['main_product_constructors'][$key][$idx];
                        }
                    }
                    unset($array['oldkey']);
                    $all['constructor__main_product'][$keyname] = $array;
                }
            }
        }

        ksort($all);
        $config = [];

        // Data array formation
        foreach ($filters as $filter) {
            foreach ($all as $key => $value) {
                if (str_starts_with($key, $filter . '__')) {
                    $key = str_replace($filter . '__', '', $key);
                    if (is_array($value)) {
                        $array = [];
                        foreach ($value as $k => $v) {
                            if (is_array($v)) {
                                foreach ($v as $k1 => $v1) {
                                    if ($this->isInteger($v1)) {
                                        $v1 = intval($v1);
                                    }
                                    $v[$k1] = $v1;
                                }
                            } elseif ($this->isInteger($v)) {
                                $v = intval($v);
                            }
                            $value[$k] = $v;
                        }
                    } elseif ($this->isInteger($value)) {
                        $value = intval($value);
                    }
                    $config[$filter][$key] = $value;
                }
            }
        }

        // Preparation of deadlines with data
        $string = '<?php return ' . $this->dataToString($config) . ';';

        // Save the config
        $handle = fopen(EVO_CORE_PATH . 'custom/config/seiger/settings/sCommerce.php', "w");
        fwrite($handle, $string);
        fclose($handle);

        return true;
    }

    /**
     * Retrieve the default language from the configuration.
     *
     * @return string The default language.
     */
    public function langDefault(): string
    {
        return evo()->getConfig('s_lang_default', 'base');
    }

    /**
     * Retrieve the list of languages configured in the system.
     *
     * @return array The list of languages.
     */
    public function langList(): array
    {
        $lang = evo()->getConfig('s_lang_config', '');
        if (trim($lang)) {
            $lang = explode(',', $lang);
        } else {
            $lang = ['base'];
        }
        return $lang;
    }

    /**
     * Retrieve the list of categories and their respective IDs.
     *
     * @param int $category The ID of the category to retrieve sub-categories for.
     * @return array An associative array where the keys are the category IDs and the values are the category titles.
     */
    public function listCategories(int $category = 0): array
    {
        if ($category == 0) {
            if (evo()->getConfig('check_sMultisite', false)) {
                $category = [];
                $basic = sCommerce::config('basic', ['basic.catalog_root' => evo()->getConfig('site_start', 1)]);
                foreach ($basic as $k => $v) {
                    if (str_starts_with($k, 'catalog_root')) {
                        $category[] = $v;
                    }
                }
            } else {
                $category = sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1));
            }
        }

        if (is_array($category)) {
            foreach ($category as $c) {
                $root = SiteContent::find($c);
                $this->categories[$root->id] = __('sCommerce::global.catalog_root') . ' ('.$root->id.')';
                if ($root->hasChildren()) {
                    foreach ($root->children as $item) {
                        $this->categoryCrumb($item);
                    }
                }
            }
        } else {
            $root = SiteContent::find($category);
            $this->categories[$root->id] = __('sCommerce::global.catalog_root');
            if ($root->hasChildren()) {
                foreach ($root->children as $item) {
                    $this->categoryCrumb($item);
                }
            }
        }
        return $this->categories;
    }

    /**
     * Retrieves a list of all active sub-categories within a specified category.
     *
     * @param int $category The ID of the category to retrieve sub-categories for.
     * @param int $dept The depth of sub-categories to retrieve (default: 10).
     * @return array An array of all active sub-category IDs within the specified category.
     */
    public function listAllActiveSubCategories(int $category, int $dept = 10): array
    {
        return $this->getActiveChildIds($category, $dept);
    }

    /**
     * Retrieves the parent IDs of a category.
     *
     * @param int $category The ID of the category.
     * @return array An array containing the parent IDs of the category.
     */
    public function categoryParentsIds(int $category): array
    {
        $this->categories[] = $category;
        return $this->getParentsIds($category);
    }

    /**
     * Retrieves and assigns the subcategories of a given category recursively.
     *
     * @param object $category A reference to the category object.
     * @return object The modified category object with the "subcategories" property assigned.
     */
    public function listSubCategories(object &$category, int $dept): object
    {
        if ($category->hasChildren() && $dept) {
            $children = $category->children()->active()->get();
            $children->map(function ($item) use ($dept) {
                return $this->listSubCategories($item, $dept--);
            });
            $category->subcategories = $children;
        } else {
            $category->subcategories = sCategory::whereId(0)->get();
        }

        return $category;
    }

    /**
     * Initializes and returns a rich text editor for the specified elements.
     *
     * @param string $ids A comma-separated list of element IDs.
     * @param string $height The height of the editor (default: '500px').
     * @param string $editor The name of the editor to use (default: empty, will retrieve from configuration).
     * @return string The HTML markup of the initialized rich text editor.
     */
    public function textEditor(string $ids, string $height = '500px', string $editor = ''): string
    {
        $theme = null;
        $elements = [];
        $options = [];
        $ids = explode(",", $ids);

        if (!trim($editor)) {
            $editor = evo()->getConfig('which_editor', 'TinyMCE5');
        }

        foreach ($ids as $id) {
            $elements[] = trim($id);
            if ($theme) {
                $options[trim($id)]['theme'] = $theme;
            }
        }

        return implode("", evo()->invokeEvent('OnRichTextEditorInit', [
            'editor' => $editor,
            'elements' => $elements,
            'height' => $height,
            'contentType' => 'htmlmixed',
            'options' => $options
        ]));
    }

    /**
     * Removes a directory and all its contents recursively.
     *
     * @param string $dir The path to the directory to be removed.
     * @return void
     */
    public function removeDirRecursive(string $dir): void
    {
        if ($objs = glob($dir . "/*")) {
            foreach($objs as $obj) {
                if (is_dir($obj)) {
                    $this->removeDirRecursive($obj, $keep_file);
                } else {
                    unlink($obj);
                }
            }
        } else {
            rmdir($dir);
        }
    }

    /**
     * Validate and sanitize a price value.
     *
     * @param mixed $price The price value to be validated.
     * @return float The validated and sanitized price value.
     */
    public function validatePrice(mixed $price): float
    {
        $validPrice = 0.00;
        $price = str_replace(',', '.', $price);

        if (is_int($price) || is_numeric($price)) {
            $price = floatval($price);
            $validPrice = floatval(number_format($price, 2, '.', ''));
        } elseif (is_float($price)) {
            $validPrice = floatval(number_format($price, 2, '.', ''));
        }

        return $validPrice;
    }

    /**
     * Validate an alias and ensure its uniqueness.
     *
     * @param string $string The string to be used for generating the alias.
     * @param int $id The ID of the item for which the alias is being generated.
     * @param string $key The key representing the entity type for which the alias is being generated. (Default: 'product')
     * @return string The valid and unique alias.
     */
    public function validateAlias(string $string, int $id, string $key = 'product'): string
    {
        if (trim($string)) {
            $alias = Str::slug(trim($string), '-');
        } else {
            $alias = $id;
        }

        switch ($key) {
            default :
                $aliases = sProduct::whereNot('id', $id)->get('alias')->pluck('alias')->toArray();
                break;
            case "attribute":
                $aliases = sAttribute::whereNot('id', $id)->get('alias')->pluck('alias')->toArray();
                break;
        }

        if (in_array($alias, $aliases)) {
            $cnt = 1;
            $tempAlias = $alias;
            while (in_array($tempAlias, $aliases)) {
                $tempAlias = $alias . $cnt;
                $cnt++;
            }
            $alias = $tempAlias;
        }

        return $alias;
    }

    /**
     * Validates and returns a unique alias value.
     *
     * @param string $string The string to be converted into an alias.
     * @param int $id The unique identifier.
     * @param int $parent The parent identifier.
     * @param string $key The key to determine the alias value (default: 'attrvalues').
     * @return string The validated and unique alias value.
     */
    public function validateAliasValues(string $string, int $id, int $parent, string $key = 'attrvalues'): string
    {
        if (trim($string)) {
            $alias = Str::slug(trim($string), '-');
        } else {
            $alias = $id;
        }

        switch ($key) {
            default :
                $aliases = sAttributeValue::whereNot('avid', $id)->whereNot('attribute', $parent)->get('alias')->pluck('alias')->toArray();
                break;
        }

        if (in_array($alias, $aliases)) {
            $cnt = 1;
            $tempAlias = $alias;
            while (in_array($tempAlias, $aliases)) {
                $tempAlias = $alias . $cnt;
                $cnt++;
            }
            $alias = $tempAlias;
        }

        return $alias;
    }

    /**
     * Render a view using a template and data.
     *
     * @param string $tpl The template to render.
     * @param array $data The data to pass to the view (optional).
     * @return \View The rendered view.
     */
    public function view(string $tpl, array $data = [])
    {
        return \View::make('sCommerce::'.$tpl, $data);
    }

    /**
     * Generates the breadcrumb for a given category and its children recursively.
     *
     * @param mixed $resource The category resource object.
     * @param string $crumb The existing breadcrumb to append to.
     *
     * @return void
     */
    protected function categoryCrumb($resource, $crumb = ''): void
    {
        $crumb = trim($crumb) ? $crumb . ' > ' . $resource->pagetitle : $resource->pagetitle;
        $this->categories[$resource->id] = $crumb;
        if ($resource->hasChildren()) {
            foreach ($resource->children as $item) {
                $this->categoryCrumb($item, $crumb);
            }
        }
    }

    /**
     * Retrieves the active child IDs of the specified ID(s).
     *
     * @param int|array $id The ID(s) of the parent element(s).
     * @param int $dept The maximum depth to traverse when retrieving child IDs (default: 10).
     * @return array The array of active child IDs.
     */
    protected function getActiveChildIds(int|array $id, int $dept = 10): array
    {
        $id = is_array($id) ? $id : [$id];
        $res = SiteContent::select(['id'])->whereIn('parent', $id)->active()->get()->pluck('id')->toArray();

        if (count($res)) {
            $this->categories = array_merge($this->categories, $res);
            if ($dept) {
                $this->getActiveChildIds($res, $dept--);
            }
        }

        return $this->categories;
    }

    /**
     * Retrieves the IDs of the parent categories for the specified category.
     *
     * @param int $categoryId The ID of the category for which to retrieve parent IDs.
     * @return array An array containing the IDs of the parent categories.
     */
    protected function getParentsIds(int $categoryId): array
    {
        if ($categoryId != evo()->getConfig('catalog_root', evo()->getConfig('site_start', 1))) {
            $category = sCategory::find($categoryId);
            $parent = $category->getParent();
            $this->categories = array_merge($this->categories, [$parent->id ?? 0]);
            if (($parent->id ?? 0) && $categoryId != evo()->getConfig('catalog_root', evo()->getConfig('site_start', 1))) {
                $this->categories = $this->getParentsIds($parent->id);
            }
        }
        return $this->categories;
    }

    /**
     * Check if the given input is an integer.
     *
     * @param mixed $input The input to be checked.
     * @return bool Returns true if the input is an integer, otherwise false.
     */
    protected function isInteger(mixed $input): bool
    {
        return is_scalar($input) && ctype_digit(strval($input));
    }

    /**
     * Convert data to a string representation.
     *
     * @param mixed $data The data to convert.
     * @return string The string representation of the data.
     */
    protected function dataToString(mixed $data): string
    {
        ob_start();
        var_dump($data);
        $data = ob_get_contents();
        ob_end_clean();

        $data = Str::of($data)->replaceMatches('/string\(\d+\) .*/', function ($match) {
            return substr($match[0], (strpos($match[0], ') ') + 2)) . ',';
        })->replaceMatches('/bool\(\w+\)/', function ($match) {
            return str_replace(['bool(', ')'], ['', ','], $match[0]);
        })->replaceMatches('/int\(\d+\)/', function ($match) {
            return str_replace(['int(', ')'], ['', ','], $match[0]);
        })->replaceMatches('/float\(\d+\)/', function ($match) {
            return str_replace(['float(', ')'], ['', ','], $match[0]);
        })->replaceMatches('/array\(\d+\) /', function ($match) {
            return str_replace($match[0], '', $match[0]);
        })->replaceMatches('/=>\n[ \t]{1,}/', function () {
            return ' => ';
        })->replaceMatches('/  /', function () {
            return '    ';
        })->remove('[')->remove(']')->replace('{', '[')->replace('}', '],')->rtrim(",\n");

        return $data;
    }
}
