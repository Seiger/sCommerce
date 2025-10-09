<?php namespace Seiger\sCommerce\Models;

use EvolutionCMS\Facades\UrlProcessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sGallery\sGallery;

/**
 * Class sProduct
 *
 * This class represents a product in the sCommerce application.
 * It extends the base Model class.
 *
 * @package Seiger\sCommerce
 *
 * @method static Builder|sProduct lang(string $locale)
 * @method Builder|sProduct search()
 * @method Builder|sProduct active()
 * @method Builder|sProduct extractConstructor()
 * @property-read string $attrValues The attributes associated with the product.
 * @property-read string $attribute The attribute associated with the product by alias.
 * @property-read string $title The Title of the product.
 * @property-read int $category The Category of the product.
 * @property-read string $link The URL of the product.
 * @property-read string $coverSrc The URL of the cover image source attribute.
 * @property-read string $price The formatted price of the product.
 * @property-read string $specialPrice The formatted special price of the product.
 * @property-read int $reviewsCount The count of reviews for the product.
 */
class sProduct extends Model
{
    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'title',
        'category',
        'link',
        'coverSrc',
        'price',
        'specialPrice',
        'reviewsCount',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'inventory' => 'integer',
        'price_regular' => 'decimal:2',
        'price_special' => 'decimal:2',
        'price_opt_regular' => 'decimal:2',
        'price_opt_special' => 'decimal:2',
        'weight' => 'decimal:4',
        'width' => 'decimal:4',
        'height' => 'decimal:4',
        'length' => 'decimal:4',
        'volume' => 'decimal:4',
    ];

    protected $fillable = ['uuid'];

    /**
     * Availability constants
     */
    const AVAILABILITY_NOT_AVAILABLE = 0;
    const AVAILABILITY_IN_STOCK = 1;
    const AVAILABILITY_ON_ORDER = 2;

    /**
     * Type Product constants
     */
    const MODE_SIMPLE = 0;       // Standard product type without variations. Ideal for straightforward items.
    const MODE_GROUP = 1;        // A collection of related products displayed as a group.
    // const MODE_BUNDLE = 2;       // A package of multiple products sold together at a discounted price.
    // const MODE_VARIABLE = 3;     // Products with variations, such as size or color.
    // const MODE_OPTIONAL = 4;     // Additional items that can complement a purchase (e.g., accessories).
    // const MODE_DOWNLOADABLE = 5; // Digital products, such as software, eBooks, or media files.
    // const MODE_VIRTUAL = 6;      // Non-physical products, including services or licenses.
    // const MODE_SERVICE = 7;      // Offerings like installation, consulting, or maintenance.
    // const MODE_SUBSCRIPTION = 8; // Products with recurring billing (e.g., memberships or digital content).
    // const MODE_PREORDER = 9;     // Products not yet available but open for early purchase.
    // const MODE_CUSTOM = 10;      // Personalized or tailor-made products created upon request.

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
     * Return list of type Product codes and labels
     *
     * @return array The array of types. The key of each element is the constant value, and the value of each element is the corresponding translation string.
     */
    public static function listMode(): array
    {
        $list = [];
        $class = new ReflectionClass(__CLASS__);
        foreach ($class->getConstants() as $constant => $value) {
            if (str_starts_with($constant, 'MODE_')) {
                $const = strtolower($constant);
                $list[$value] = __('sCommerce::global.'.$const);
            }
        }
        return $list;
    }

    /**
     * This method is called when the sProduct class is booted.
     * It adds a global scope to the query that joins the s_product_translates table and selects the product attributes.
     * The join condition is on the product id and the lang column is filtered based on the current locale and 'base'.
     * The lang column is ordered based on the current locale and 'base' to prioritize the current locale.
     * Only one result is selected.
     *
     * @return void
     */
    protected static function booted()
    {
        /*static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string)Str::uuid();
            }
        });*/

        static::addGlobalScope('translate', function (Builder $builder) {
            if (!isset($builder->getQuery()->columns)) {
                $builder->select('*');
            }

            $locale = app()->getLocale();
            foreach ($builder->getQuery()->columns as $key => $column) {
                if (is_string($column) && str_starts_with($column, 'locale.')) {
                    $locale = explode('.', $column)[1];
                    unset($builder->getQuery()->columns[$key]);
                }
            }

            $builder->leftJoin('s_product_translates as spt', function ($leftJoin) use ($builder, $locale) {
                $leftJoin->on('s_products.id', '=', 'spt.product')
                    ->where('spt.lang', function ($leftJoin) use ($builder, $locale) {
                        $leftJoin->select('lang')
                            ->from('s_product_translates as t')
                            ->whereColumn('t.product', 's_products.id')
                            ->whereIn('t.lang', [$locale, 'base'])
                            ->orderByRaw("
                                CASE lang
                                    WHEN ? THEN 0
                                    WHEN 'base' THEN 1
                                    ELSE 2
                                END
                            ", [$locale])
                            ->limit(1);
                    });
            });
        });
    }

    /**
     * Join the language translations table on the query based on the provided locale.
     *
     * @param \Illuminate\Database\Query\Builder $builder Builder The query builder instance.
     * @param string $locale The locale to filter the translations by.
     * @return \Illuminate\Database\Query\Builder The modified query builder instance.
     */
    public static function scopeLang($builder, $locale = '')
    {
        if (!isset($builder->getQuery()->columns)) {
            $builder->select('*');
        }

        if (empty($locale)) {
            $locale = app()->getLocale();
        }

        return $builder->addSelect('locale.' . $locale);
    }

    /**
     * Apply search filters to the query
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder The query builder object
     *
     * @return \Illuminate\Database\Eloquent\Builder The modified query builder object
     */
    public function scopeSearch($builder)
    {
        if (request()->has('search')) {
            if (!isset($builder->getQuery()->columns)) {
                $builder->select('*');
            }

            $fields = collect([
                'sku',
                'spt.pagetitle',
                'spt.longtitle',
                'spt.introtext',
                'spt.content',
            ]);

            $search = Str::of(request('search'))
                ->stripTags()
                ->replaceMatches('/[^\p{L}\p{N}\@\.!#$%&\'*+-\/=?^_`{|}~]/iu', ' ') // allowed symbol in email
                ->replaceMatches('/(\s){2,}/', '$1') // removing extra spaces
                ->trim()->explode(' ')
                ->filter(fn($word) => mb_strlen($word) > 0);

            $select = collect([0]);

            $fields->map(fn($field) => $select->push("(CASE WHEN ".$builder->getGrammar()->wrap($field)." LIKE '%{$search->implode(' ')}%' THEN 10 ELSE 0 END)")); // Generate Exact match points source
            $search->map(fn($word) => $fields->map(fn($field) => $select->push("(CASE WHEN ".$builder->getGrammar()->wrap($field)." LIKE '%{$word}%' THEN 1 ELSE 0 END)"))); // Generate Partial match points source

            $s = $builder->addSelect(DB::Raw('(' . $select->implode(' + ') . ') as points'));
            if (sCommerce::config('basic.search', 'blurred') == 'blurred') {
                $s->when($search->count(), fn($query) => $query->where(fn($query) => $search->map(fn($word) => $fields->map(fn($field) => $query->orWhere($field, 'like', "%{$word}%")))));
            } else {
                $s->when($search->count(), fn($query) => $query->where(fn($query) => $fields->map(fn($field) => $query->orWhere($field, 'like', "%{$search->implode(' ')}%"))));
            }
            return $s->orderByDesc('points');
        }
    }

    /**
     * Apply the active scope to the given query builder.
     *
     * @param \Illuminate\Database\Query\Builder $builder The query builder to apply the scope to.
     * @return \Illuminate\Database\Query\Builder The modified query builder.
     */
    public function scopeActive($builder)
    {
        return $builder->where('s_products.published', '1');
    }

    /**
     * Apply the active scope to the given query builder.
     *
     * @param \Illuminate\Database\Query\Builder $builder The query builder to apply the scope to.
     * @return \Illuminate\Database\Query\Builder The modified query builder.
     */
    public function scopeExtractConstructor($builder)
    {
        if (!isset($builder->getQuery()->columns)) {
            $builder->select('*');
        }

        foreach (sCommerce::config('constructor', []) as $constructor) {
            foreach ($constructor as $field => $item) {
                $builder->addSelect([
                    'constructor_' . $field => sProductTranslate::query()
                        ->select('constructor->' . $field)
                        ->whereLang('base')
                        ->whereColumn('product', 's_products.id')
                        ->take(1)
                ]);
            }
        }

        return $builder;
    }

    /**
     * Get the categories associated with the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany The relation representing the product's categories.
     */
    public function categories()
    {
        return $this->belongsToMany(sCategory::class, 's_product_category', 'product', 'category')->withPivot('position', 'scope');
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
     * Get selected related product translate
     *
     * @return Illuminate\Database\Eloquent\Relations\HasMany The relation object for the product translations.
     */
    public function text($locale = '')
    {
        $locale = trim($locale) ? $locale : config('app.locale');
        return $this->hasOne(sProductTranslate::class, 'product', 'id')->whereIn('lang', [$locale, 'base']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     *
     * Relationship method for retrieving the reviews of a product.
     * Returns a collection of sReview instances associated with the product.
     * The reviews are ordered in descending order by their creation timestamp.
     */
    public function reviews()
    {
        return $this->hasMany(sReview::class, 'product')->orderByDesc('created_at');
    }

    /**
     * Method activeReviews
     *
     * Retrieve the active reviews for a product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     *
     * @see sProduct
     * @see sReview
     * @see sProduct::hasMany
     */
    public function activeReviews()
    {
        return $this->hasMany(sReview::class, 'product')->wherePublished(1)->orderByDesc('created_at');
    }

    /**
     * Get the attributes associated with the product.
     *
     * This method returns a BelongsToMany relationship with the sAttributeValue model.
     * The intermediate table used for the relationship is 's_product_attribute_values'.
     * The foreign key on the s_product_attribute_values table for the attribute model is 'attribute'.
     * The foreign key on the s_product_attribute_values table for the product model is 'product'.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function attrValues()
    {
        return $this->belongsToMany(sAttribute::class, 's_product_attribute_values', 'product', 'attribute')
            ->withPivot('valueid', 'value')
            ->orderBy('position');
    }

    /**
     * Get the attribute associated with the product by alias.
     *
     * @param $alias
     * @return mixed
     */
    public function attribute($alias)
    {
        $attribute = $this->attrValues()->whereAlias($alias)->first();

        if ($attribute) {
            $attribute->title = $attribute->text->pagetitle;
            switch ($attribute->type) {
                case sAttribute::TYPE_ATTR_NUMBER:
                case sAttribute::TYPE_ATTR_CHECKBOX:
                    $value = intval($attribute->pivot->value ?? 0);
                    $attribute->value = $value;
                    $attribute->label = $value;
                    break;
                case sAttribute::TYPE_ATTR_SELECT:
                    $avid = intval($attribute->pivot->valueid ?? 0);
                    $value = $attribute->values()->whereAvid($avid)->first();
                    $attribute->value = $value?->alias ?? '';
                    $attribute->label = $value?->{evo()->getLocale()} ?? $value?->base ?? '';
                    break;
                case sAttribute::TYPE_ATTR_TEXT:
                    $value = json_decode($attribute->pivot->value ?? '', true);
                    $attribute->value = $value[evo()->getLocale()] ?? $value['base'];
                    $attribute->label = $value[evo()->getLocale()] ?? $value['base'];
                    break;
                case sAttribute::TYPE_ATTR_COLOR:
                    $avid = intval($attribute->pivot->valueid ?? 0);
                    $value = $attribute->values()->whereAvid($avid)->first();
                    $attribute->value = $value?->alias ?? '';
                    $attribute->code = $value?->code ?? '';
                    $attribute->label = $value?->{evo()->getLocale()} ?? $value?->base ?? '';
                    break;
                case sAttribute::TYPE_ATTR_CUSTOM:
                    $value = trim($attribute->pivot->value ?? '');
                    $attribute->value = $value;
                    $attribute->label = $value;
                    break;
            }
        }

        return $attribute;
    }

    /**
     * Retrieves the category attribute for the product.
     *
     * @param string|int|null $key The key for the site scope (string) or category ID (int). If null, the default site key is used.
     * @return int|null The category ID of the product or the catalog root ID.
     */
    public function getCategoryAttribute($key = null): int
    {
        // If $key is an integer, it's a category ID - return it directly (optimization)
        if (is_int($key) && $key > 0) {
            return $key;
        }

        if (evo()->getConfig('check_sMultisite', false)) {
            $key = $key ?? evo()->getConfig('site_key', 'default');
            $category = $this->categories()->whereScope('primary_' . $key)->first()->id ?? null;
        } else {
            $category = $this->categories()->whereScope('primary')->first()->id ?? null;
        }
        $cateroot = sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1));
        return $category ?? $cateroot;
    }

    /**
     * Get the title attribute of the sProduct instance.
     *
     * @return string The title attribute value. If the value is not found, an empty string is returned.
     */
    public function getTitleAttribute(): string
    {
        return $this->texts()->whereLang(evo()->getConfig('lang', 'base'))->first()->pagetitle ?? '';
    }

    /**
     * Get the link attribute for the current object
     *
     * Returns the URL of the object based on the configured link rule in the sCommerce module.
     * If the link rule is set to "catalog", the URL is based on the catalog root URL.
     * If the link rule is set to "category", the URL is based on the current category or the catalog root URL.
     * Otherwise, the URL is based on the site start URL.
     *
     * @param string|int $key The site scope (string) or category ID (int) for optimization
     * @return string The URL of the object.
     */
    public function getLinkAttribute($key = ''): string
    {
        switch (sCommerce::config('product.link_rule', 'root')) {
            case "catalog" :
                $category = sCommerce::config('basic.catalog_root' . $key, evo()->getConfig('site_start', 1));
                $base_url = UrlProcessor::makeUrl($category);
                break;
            case "category" :
                $category = intval($this->getCategoryAttribute(trim($key ?? '') ? $key : null) ?: sCommerce::config('basic.catalog_root' . $key, evo()->getConfig('site_start', 1)));
                $base_url = UrlProcessor::makeUrl($category);
                break;
            default :
                $base_url = UrlProcessor::makeUrl(evo()->getConfig('site_start', 1));
                break;
        }

        $base_url = rtrim($base_url, evo()->getConfig('friendly_url_suffix', '')) . '/';
        return $base_url . $this->alias . evo()->getConfig('friendly_url_suffix', '');
    }

    /**
     * Retrieve the source attribute of the cover image.
     *
     * This method checks if the 'cover' property is not empty and the file exists. If it does, it returns the URL of the cover image.
     * If the 'cover' property is empty or the file does not exist, it returns the URL of a default placeholder image.
     *
     * @return string The URL of the cover image source attribute.
     */
    public function getCoverSrcAttribute(): string
    {
        if (!empty($this->cover) && is_file(MODX_BASE_PATH . $this->cover)) {
            $coverSrc = MODX_SITE_URL . $this->cover;
        }  elseif (!empty($this->cover) && sGallery::hasLink($this->cover)) {
            $coverSrc = $this->cover;
        } else {
            $coverSrc = MODX_SITE_URL . 'assets/images/noimage.png';
        }

        return $coverSrc;
    }

    /**
     * Gets the price attribute of the sProduct.
     * Formats the price based on configuration values.
     *
     * @return string The formatted price of the product.
     *
     * @throws ErrorException if configuration values are not set.
     */
    public function getPriceAttribute(): string
    {
        return $this->priceTo(sCommerce::currentCurrency());
    }

    /**
     * Get the numerical price of the product in the current currency.
     *
     * This accessor computes the effective product price as a float, based on
     * the active currency. If a special price is available and lower than
     * the regular price, it takes precedence. Otherwise, the regular price is used.
     *
     * Internally, the method calls `priceToNumber()` which uses the `sCommerce::convertPriceNumber`
     * method to perform the currency conversion.
     *
     * @return float The converted numeric price of the product.
     */
    public function getPriceAsFloatAttribute(): float
    {
        return $this->priceToNumber(sCommerce::currentCurrency());
    }

    /**
     * Convert the price to the specified currency and format it as a string.
     *
     * @param string $currency The target currency.
     * @return string The formatted price.
     */
    public function priceTo($currency): string
    {
        $price = $this->price_special > 0 && $this->price_special < $this->price_regular ? $this->price_special : $this->price_regular ?? 0;
        return sCommerce::convertPrice($price, $this->currency, $currency);
    }

    /**
     * Convert the product regular price to a number in a specified currency.
     *
     * @param string $currency The desired currency to convert to.
     *
     * @return float The converted price in the specified currency.
     */
    public function priceToNumber($currency): float
    {
        $price = $this->price_special > 0 && $this->price_special < $this->price_regular ? $this->price_special : $this->price_regular ?? 0;
        return sCommerce::convertPriceNumber($price, $this->currency, $currency);
    }

    /**
     * Gets the price special attribute of the sProduct.
     * Formats the price based on configuration values.
     *
     * @return string The formatted price of the product.
     *
     * @throws ErrorException if configuration values are not set.
     */
    public function getSpecialPriceAttribute(): string
    {
        return $this->specialPriceTo(sCommerce::currentCurrency());
    }

    /**
     * Convert the price special to the specified currency and format it as a string.
     *
     * @param string $currency The target currency.
     * @return string The formatted price.
     */
    public function specialPriceTo($currency): string
    {
        return sCommerce::convertPrice($this->price_special, $this->currency, $currency);
    }

    /**
     * Convert the product special price to a number in a specified currency.
     *
     * @param string $currency The desired currency to convert to.
     *
     * @return float The converted price in the specified currency.
     */
    public function specialPriceToNumber($currency): float
    {
        return sCommerce::convertPriceNumber($this->price_special, $this->currency, $currency);
    }

    /**
     * Gets the old price attribute of the sProduct.
     * Formats the price based on configuration values.
     *
     * @return string The formatted price of the product.
     *
     * @throws ErrorException if configuration values are not set.
     */
    public function getOldPriceAttribute(): string
    {
        return $this->oldPriceTo(sCommerce::currentCurrency());
    }

    /**
     * Get the numerical old price of the product in the current currency.
     *
     * This accessor computes the effective product old price as a float, based on
     * the active currency. If a special price is available and lower than
     * the regular price, it takes precedence. Otherwise, the regular price is used.
     *
     * Internally, the method calls `priceToNumber()` which uses the `sCommerce::convertPriceNumber`
     * method to perform the currency conversion.
     *
     * @return float The converted numeric price of the product.
     */
    public function getOldPriceAsFloatAttribute(): float
    {
        return $this->oldPriceToNumber(sCommerce::currentCurrency());
    }

    /**
     * Convert the old price to the specified currency and format it as a string.
     *
     * @param string $currency The target currency.
     * @return string The formatted price.
     */
    public function oldPriceTo($currency): string
    {
        $oldPrice = $this->price_special > 0 && $this->price_special < $this->price_regular ? $this->price_regular : $this->price_special ?? 0;
        return sCommerce::convertPrice($oldPrice, $this->currency, $currency);
    }

    /**
     * Convert the product old price to a number in a specified currency.
     *
     * @param string $currency The desired currency to convert to.
     *
     * @return float The converted price in the specified currency.
     */
    public function oldPriceToNumber($currency): float
    {
        $oldPrice = $this->price_special > 0 && $this->price_special < $this->price_regular ? $this->price_regular : $this->price_special ?? 0;
        return sCommerce::convertPriceNumber($oldPrice, $this->currency, $currency);
    }

    /**
     * Gets the reviews count attribute of the sProduct.
     * Returns the total number of reviews associated with the product.
     * Uses eager loading to avoid N+1 queries when possible.
     *
     * @return int The count of reviews for the product.
     */
    public function getReviewsCountAttribute(): int
    {
        // If reviews_count is already loaded via withCount(), use it
        if (isset($this->attributes['reviews_count'])) {
            return (int) $this->attributes['reviews_count'];
        }

        // Otherwise, count the loaded reviews or make a query
        if ($this->relationLoaded('reviews')) {
            return $this->reviews->count();
        }

        // Fallback to database query
        return $this->reviews()->count();
    }
}