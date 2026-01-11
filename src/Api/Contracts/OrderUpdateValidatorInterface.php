<?php namespace Seiger\sCommerce\Api\Contracts;

interface OrderUpdateValidatorInterface
{
    /**
     * Validate canonical payload and return normalized data for applier.
     *
     * @param array<string,mixed> $payload
     * @return array{ok:bool,data:array<string,mixed>,errors:array<string,mixed>}
     */
    public function validate(array $payload): array;
}
