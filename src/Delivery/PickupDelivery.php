<?php namespace Seiger\sCommerce\Delivery;

use Seiger\sCommerce\Models\sDeliveryMethod;
use Seiger\sCommerce\Interfaces\DeliveryMethodInterface;

class PickupDelivery implements DeliveryMethodInterface
{
    protected sDeliveryMethod $method;

    public function __construct()
    {
        $this->method = sDeliveryMethod::where('name', $this->getName())->firstOrFail();
    }

    /**
     * Get the unique name of the delivery method.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'pickup';
    }

    /**
     * Calculate the cost of delivery based on the order data.
     *
     * @param array $orderData
     * @return float
     */
    public function calculateCost(array $orderData): float
    {
        return 0.0;
    }

    /**
     * Get additional details or options for the delivery method.
     *
     * @return array
     */
    public function getDetails(): array
    {
        /*return [
            'description' => __('sCommerce::delivery.pickup_description'),
            'locations' => $this->getPickupLocations(),
        ];*/
        return [
            'description' => $this->method->description,
            'title' => $this->method->title,
            'locations' => $this->getPickupLocations(),
            'position' => $this->method->position,
        ];
    }

    /**
     * Get the available pickup locations.
     *
     * @return array
     */
    protected function getPickupLocations(): array
    {
        return [
            ['id' => 1, 'address' => '123 Main St, Kyiv', 'working_hours' => '9:00 - 18:00'],
            ['id' => 2, 'address' => '456 High St, Lviv', 'working_hours' => '10:00 - 20:00'],
        ];
    }
}
