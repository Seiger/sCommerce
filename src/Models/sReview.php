<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class sReview
 *
 * @package Seiger\sCommerce
 */
class sReview extends Model
{
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
            $fields = collect(['product', 'name', 'message']);

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
     * Retrieve the related product for this model.
     *
     * @return \Product The related product model.
     */
    public function toProduct()
    {
        return $this->belongsTo(sProduct::class, 'product');
    }

    /**
     * Apply the active scope to the given query builder.
     *
     * @param \Illuminate\Database\Query\Builder $builder The query builder to apply the scope to.
     * @return \Illuminate\Database\Query\Builder The modified query builder.
     */
    public function scopeActive($builder)
    {
        return $builder->where('s_reviews.published', '1');
    }
}