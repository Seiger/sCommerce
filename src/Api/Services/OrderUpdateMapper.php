<?php namespace Seiger\sCommerce\Api\Services;

use Seiger\sCommerce\Api\Contracts\OrderUpdateMapperInterface;

final class OrderUpdateMapper implements OrderUpdateMapperInterface
{
    public function map(array $payload): array
    {
        // Minimal normalization for common aliases while keeping the mapper mostly passthrough.
        if (isset($payload['totals']) && is_array($payload['totals'])) {
            if (!array_key_exists('cost', $payload['totals']) && array_key_exists('order_total', $payload['totals'])) {
                $payload['totals']['cost'] = $payload['totals']['order_total'];
            }
        }

        return $payload;
    }
}
