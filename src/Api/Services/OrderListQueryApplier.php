<?php namespace Seiger\sCommerce\Api\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Seiger\sCommerce\Api\Contracts\OrderListQueryApplierInterface;

/**
 * Class OrderListQueryApplier
 *
 * Default no-op implementation.
 * Projects can override this binding to apply custom filters.
 *
 * @package Seiger\sCommerce\Api\Services
 */
final class OrderListQueryApplier implements OrderListQueryApplierInterface
{
    /**
     * {@inheritDoc}
     */
    public function apply(Builder $query, Request $request): Builder
    {
        return $query;
    }
}
