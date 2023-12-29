<?php namespace Seiger\sCommerce\Controllers;

use Seiger\sCommerce\Facades\sCommerce;

class sCommerceController
{
    /**
     * Show tab page with sOffer files
     *
     * @return View
     */
    public function index(): View
    {
        return $this->view('index');
    }

    /**
     * Save management of basic functionality section
     *
     * @return bool
     */
    public function saveBasicConfigs(): bool
    {
        $prf = 'scom_';
        $tbl = evo()->getDatabase()->getFullTableName('system_settings');
        if (request()->has('in_main_menu') && request()->in_main_menu != evo()->getConfig($prf . 'in_main_menu')) {
            $in_main_menu = request()->in_main_menu;
            evo()->getDatabase()->query("REPLACE INTO {$tbl} (`setting_name`, `setting_value`) VALUES ('{$prf}in_main_menu', '{$in_main_menu}')");
            evo()->setConfig($prf . 'in_main_menu', $in_main_menu);
        }
        if (request()->has('main_menu_order') && request()->main_menu_order != evo()->getConfig($prf . 'main_menu_order')) {
            $main_menu_order = request()->main_menu_order;
            evo()->getDatabase()->query("REPLACE INTO {$tbl} (`setting_name`, `setting_value`) VALUES ('{$prf}main_menu_order', '{$main_menu_order}')");
            evo()->setConfig($prf . 'main_menu_order', $main_menu_order);
        }
        return true;
    }

    /**
     * Default language
     *
     * @return string
     */
    public function langDefault(): string
    {
        return evo()->getConfig('s_lang_default', 'base');
    }

    /**
     * Languages list
     *
     * @return array
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
     * Price validation
     *
     * @param mixed $price
     * @return float
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
     * Alias validation
     *
     * @param $data
     * @param string $table
     * @return string
     */
    public function validateAlias($string = '', $id = 0, $key = 'article'): string
    {
        if (trim($string)) {
            $alias = Str::slug(trim($string), '-');
        } else {
            $alias = $id;
        }

        switch ($key) {
            default :
                $aliases = sArticle::where('s_articles.id', '<>', $id)->get('alias')->pluck('alias')->toArray();
                break;
            case "feature" :
                $aliases = sArticlesFeature::where('s_articles_features.fid', '<>', $id)->get('alias')->pluck('alias')->toArray();
                break;
            case "tag" :
                $aliases = sArticlesTag::where('s_articles_tags.tagid', '<>', $id)->get('alias')->pluck('alias')->toArray();
                break;
            case "author" :
                $aliases = sArticlesAuthor::where('s_articles_authors.autid', '<>', $id)->get('alias')->pluck('alias')->toArray();
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
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'title.required' => 'A title is required',
            'body.required' => 'A message is required',
        ];
    }

    /**
     * Display render
     *
     * @param string $tpl
     * @param array $data
     * @return bool
     */
    public function view(string $tpl, array $data = [])
    {
        return \View::make('sCommerce::'.$tpl, $data);
    }
}
