<?php namespace Seiger\sCommerce\Interfaces;

interface PaymentMethodInterface
{
    public function getName(): string;
    public function processPayment(array $data): bool;
}