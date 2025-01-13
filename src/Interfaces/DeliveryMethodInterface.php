<?php namespace Seiger\sCommerce\Interfaces;

interface DeliveryMethodInterface
{
    public function getName(): string;
    public function calculateCost(array $data): float;
    public function getDetails(): array;
}