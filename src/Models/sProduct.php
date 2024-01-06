<?php namespace Seiger\sCommerce\Models;

use EvolutionCMS\Facades\UrlProcessor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Seiger\sCommerce\Facades\sCommerce;

class sProduct extends Model
{
    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['coverSrc', 'link'];

    /**
     * Availability constants
     */
    const AVAILABILITY_NOT_AVAILABLE = 0;
    const AVAILABILITY_IN_STOCK = 1;
    const AVAILABILITY_ON_ORDER = 2;

    /**
     * Type constants
     */
    const TYPE_SIMPLE = 0;
    const TYPE_OPTIONAL = 1;
    const TYPE_VARIABLE = 2;

    /**
     * Return list of availability codes and labels
     *
     * @return array The array of availability. The key of each element is the constant value, and the value of each element is the corresponding translation string.
     */
    public static function listAvailability(): array
    {
        $list = [];
        $class = new ReflectionClass(__CLASS__);
        foreach ($class->getConstants() as $constant => $value) {
            if (str_starts_with($constant, 'AVAILABILITY_')) {
                $const = strtolower(str_replace('AVAILABILITY_', '', $constant));
                $list[$value] = __('sCommerce::global.'.$const);
            }
        }
        return $list;
    }

    /**
     * Return list of type codes and labels
     *
     * @return array The array of types. The key of each element is the constant value, and the value of each element is the corresponding translation string.
     */
    public static function listType(): array
    {
        $list = [];
        $class = new ReflectionClass(__CLASS__);
        foreach ($class->getConstants() as $constant => $value) {
            if (str_starts_with($constant, 'TYPE_')) {
                $const = strtolower($constant);
                $list[$value] = __('sCommerce::global.'.$const);
            }
        }
        return $list;
    }

    /**
     * Join the language translations table on the query based on the provided locale.
     *
     * @param \Illuminate\Database\Query\Builder $query The query builder instance.
     * @param string $locale The locale to filter the translations by.
     * @return \Illuminate\Database\Query\Builder The modified query builder instance.
     */
    public function scopeLang($query, $locale)
    {
        return $query->leftJoin('s_product_translates', function ($leftJoin) use ($locale) {
            $leftJoin->on('s_products.id', '=', 's_product_translates.product')
                ->where('lang', function ($leftJoin) use ($locale) {
                    $leftJoin->select('lang')
                        ->from('s_product_translates')
                        ->whereRaw(DB::getTablePrefix() . 's_product_translates.product = ' . DB::getTablePrefix() . 's_products.id')
                        ->whereIn('lang', [$locale, 'base'])
                        ->orderByRaw('FIELD(lang, "' . $locale . '", "base")')
                        ->limit(1);
                });
        });
    }

    /**
     * Get the categories associated with the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany The relation representing the product's categories.
     */
    public function categories()
    {
        return $this->belongsToMany(sCategory::class, 's_product_category', 'product', 'category');
    }

    /**
     * Get all related product translations
     *
     * @return Illuminate\Database\Eloquent\Relations\HasMany The relation object for the product translations.
     */
    public function texts()
    {
        return $this->hasMany(sProductTranslate::class, 'product', 'id');
    }

    /**
     * Get the link attribute for the current object
     *
     * Returns the URL of the object based on the configured link rule in the sCommerce module.
     * If the link rule is set to "catalog", the URL is based on the catalog root URL.
     * If the link rule is set to "category", the URL is based on the current category or the catalog root URL.
     * Otherwise, the URL is based on the site start URL.
     *
     * @return string The URL of the object.
     */
    public function getLinkAttribute()
    {
        switch (sCommerce::config('product.link_rule', 'root')) {
            case "catalog" :
                $base_url = UrlProcessor::makeUrl(sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1)));
                break;
            case "category" :
                $category = (int)$this->category ?: sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1));
                $base_url = UrlProcessor::makeUrl($category);
                break;
            default :
                $base_url = UrlProcessor::makeUrl(evo()->getConfig('site_start', 1));
                break;
        }

        return $base_url . $this->alias . evo()->getConfig('friendly_url_suffix', '');
    }
}