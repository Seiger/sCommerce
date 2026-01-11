<?php namespace Seiger\sCommerce\Api\Contracts;

use Seiger\sCommerce\Models\sOrder;

interface OrderUpdateApplierInterface
{
    /**
     * Apply validated canonical update data to the order model.
     *
     * MUST update only vendor schema columns (s_orders as shipped).
     *
     * @param sOrder $order
     * @param array<string,mixed> $data
     */
    public function apply(sOrder $order, array $data): void;
}
