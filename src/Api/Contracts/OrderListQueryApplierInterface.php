<?php namespace Seiger\sCommerce\Api\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Interface OrderListQueryApplierInterface
 *
 * Applies request-driven filters to the orders list query.
 * This extension point allows projects to add custom filters
 * without forking vendor controllers or models.
 *
 * @package Seiger\sCommerce\Api\Contracts
 */
interface OrderListQueryApplierInterface
{
    /**
     * Apply filters to the orders query.
     *
     * @param Builder $query
     * @param Request $request
     * @return Builder
     */
    public function apply(Builder $query, Request $request): Builder;
}
