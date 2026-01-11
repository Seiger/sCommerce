<?php namespace Seiger\sCommerce\Api\Contracts;

interface OrderUpdateMapperInterface
{
    /**
     * Map external payload into canonical payload (v1).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function map(array $payload): array;
}
