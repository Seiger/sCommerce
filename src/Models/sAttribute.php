<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Class sAttribute
 * This class represents an attribute in the system.
 *
 * @package Seiger\sCommerce
 *
 * @method static Builder|sAttribute lang(string $locale)
 * @method Builder|sAttribute search()
 */
class sAttribute extends Model
{
    /**
     * Type of input the attribute constants
     */
    const TYPE_ATTR_NUMBER = 0;
    //const TYPE_ATTR_CHECKBOX = 1;
    //const TYPE_ATTR_RADIO = 2;
    const TYPE_ATTR_SELECT = 3;
    const TYPE_ATTR_MULTISELECT = 4;
    const TYPE_ATTR_TEXT = 5;
    //const TYPE_ATTR_TEXTAREA = 6;
    //const TYPE_ATTR_COLOR = 7;
    //const TYPE_ATTR_DATE = 8;
    //const TYPE_ATTR_DATETIME = 9;
    //const TYPE_ATTR_IMAGE = 10;
    //const TYPE_ATTR_FILE = 11;

    /**
     * Return list of type of input the attribute codes and labels
     *
     * @return array The array of types. The key of each element is the constant value, and the value of each element is the corresponding translation string.
     */
    public static function listType(): array
    {
        $list = [];
        $class = new ReflectionClass(__CLASS__);
        foreach ($class->getConstants() as $constant => $value) {
            if (str_starts_with($constant, 'TYPE_ATTR_')) {
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
    public static function scopeLang($query, string $locale)
    {
        return $query->leftJoin('s_attribute_translates', function ($leftJoin) use ($locale) {
            $leftJoin->on('s_attributes.id', '=', 's_attribute_translates.attribute')
                ->where('lang', function ($leftJoin) use ($locale) {
                    $leftJoin->select('lang')
                        ->from('s_attribute_translates')
                        ->whereRaw(DB::getTablePrefix() . 's_attribute_translates.attribute = ' . DB::getTablePrefix() . 's_attributes.id')
                        ->whereIn('lang', [$locale, 'base'])
                        ->orderByRaw('FIELD(lang, "' . $locale . '", "base")')
                        ->limit(1);
                });
        });
    }

    /**
     * Apply search filters to the query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The query builder object
     *
     * @return \Illuminate\Database\Eloquent\Builder The modified query builder object
     */
    public function scopeSearch($query)
    {
        if (request()->has('search')) {
            $fields = collect(['pagetitle', 'longtitle', 'introtext', 'content']);

            $search = Str::of(request('search'))
                ->stripTags()
                ->replaceMatches('/[^\p{L}\p{N}\@\.!#$%&\'*+-\/=?^_`{|}~]/iu', ' ') // allowed symbol in email
                ->replaceMatches('/(\s){2,}/', '$1') // removing extra spaces
                ->trim()->explode(' ')
                ->filter(fn($word) => mb_strlen($word) > 2);

            $select = collect([0]);

            $search->map(fn($word) => $fields->map(fn($field) => $select->push("(CASE WHEN \"{$field}\" LIKE '%{$word}%' THEN 1 ELSE 0 END)"))); // Generate points source

            return $query->addSelect('*', DB::Raw('(' . $select->implode(' + ') . ') as points'))
                ->when($search->count(), fn($query) => $query->where(fn($query) => $search->map(fn($word) => $fields->map(fn($field) => $query->orWhere($field, 'like', "%{$word}%")))))
                ->orderByDesc('points');
        }
    }

    /**
     * Get the categories associated with the attribute.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany The relation representing the product's categories.
     */
    public function categories()
    {
        return $this->belongsToMany(sCategory::class, 's_attribute_category', 'attribute', 'category');
    }

    /**
     * Retrieve the translations associated with this attribute.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function texts()
    {
        return $this->hasMany(sAttributeTranslate::class, 'attribute', 'id');
    }

    /**
     * Retrieve the translations associated with this attribute.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function values()
    {
        return $this->hasMany(sAttributeValue::class, 'attribute', 'id')->orderBy('position');
    }
}