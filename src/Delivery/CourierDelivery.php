<?php namespace Seiger\sCommerce\Delivery;

use Seiger\sCommerce\Interfaces\DeliveryMethodInterface;

class CourierDelivery implements DeliveryMethodInterface
{
    /**
     * Get the unique name of the delivery method.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'courier';
    }

    /**
     * Calculate the cost of delivery based on the order data.
     *
     * @param array $orderData
     * @return float
     */
    public function calculateCost(array $orderData): float
    {
        $baseCost = 70; // Базова вартість доставки кур'єром
        $distanceCost = 0;

        // Приклад: обрахунок додаткової вартості в залежності від міста
        if (isset($orderData['user']['address']['city'])) {
            $city = strtolower($orderData['user']['address']['city']);
            $distanceCost = match ($city) {
                'kyiv' => 0, // Безкоштовно в Києві
                'lviv' => 20, // Додатково у Львові
                'kharkiv' => 30, // Додатково в Харкові
                default => 50, // Додатково для інших міст
            };
        }

        return $baseCost + $distanceCost;
    }

    /**
     * Get additional details or options for the delivery method.
     *
     * @return array
     */
    public function getDetails(): array
    {
        return [
            'description' => 'Courier delivery to the specified address.',
            'note' => 'Delivery time: 2-3 business days.',
        ];
    }
}
