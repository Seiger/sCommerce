<?php namespace Seiger\sCommerce\Delivery;

use Seiger\sCommerce\Delivery\BaseDeliveryMethod;
use Seiger\sCommerce\Models\sDeliveryMethod;

/**
 * Class CourierDelivery
 *
 * This class implements the "Courier" delivery method. It extends the `BaseDeliveryMethod` and provides
 * specific configurations and behavior for courier-based delivery, such as cost calculation based on city settings.
 *
 * @package Seiger\sCommerce\Delivery
 */
class CourierDelivery extends BaseDeliveryMethod
{
    /**
     * Get the unique name of the delivery method.
     *
     * The name is used as an identifier for this delivery method throughout the system.
     *
     * @return string The unique name of the delivery method.
     */
    public function getName(): string
    {
        return 'courier';
    }

    /**
     * Get the admin display title for the CourierDelivery method.
     *
     * Retrieves the localized title for the delivery method to be displayed in the admin panel.
     * If the title is not found or contains an invalid format, a default title is used.
     *
     * @return string The formatted title for admin display.
     */
    public function getType(): string
    {
        $title = __('sCommerce::global.courier');
        $title = str_contains($title, '::') ? 'Сourier' : $title;
        return "<b>" . $title . "</b> (courier)";
    }

    /**
     * Get validation rules for the delivery method.
     *
     * This method defines specific validation rules for fields related to the current delivery method.
     * The rules ensure that all required fields are filled and properly formatted.
     *
     * @return array An associative array of validation rules, where the key is the field name,
     *               and the value is the validation rule.
     *
     * Example Output:
     * [
     *     'delivery.courier.city' => 'string|max:255',
     *     'delivery.courier.street' => 'string|max:255',
     *     'delivery.courier.build' => 'string|max:10',
     *     'delivery.courier.room' => 'integer|max:10',
     *     'delivery.courier.datetime' => 'string|max:255',
     *     'delivery.courier.date' => 'date_format:Y-m-d H:i',
     *     'delivery.courier.time' => 'date_format:Y-m-d H:i',
     *     'delivery.courier.first_name' => 'string|max:255',
     *     'delivery.courier.middle_name' => 'string|max:255',
     *     'delivery.courier.last_name' => 'string|max:255',
     * ]
     */
    public function getValidationRules(): array
    {
        return [
            'delivery.courier.city' => 'string|max:255',
            'delivery.courier.street' => 'string|max:255',
            'delivery.courier.build' => 'string|max:10',
            'delivery.courier.room' => 'integer|max:100',
            'delivery.courier.datetime' => 'string|max:255',
            'delivery.courier.date' => 'string|max:255',
            'delivery.courier.time' => 'date_format:Y-m-d H:i',
            'delivery.courier.first_name' => 'string|max:255',
            'delivery.courier.middle_name' => 'string|max:255',
            'delivery.courier.last_name' => 'string|max:255',
        ];
    }

    /**
     * Calculate the cost of delivery based on the order data.
     *
     * Calculates the total cost by adding a base cost and an extra cost depending on the city.
     *
     * @param array $orderData The order data, including user and address information.
     * @return float The calculated delivery cost.
     */
    public function calculateCost(array $order): float
    {
        $baseCost = floatval($this->method->cost ?? 0);
        $distanceCost = 0;
        $city = $order['delivery']['courier']['city'] ?? $order['user']['address']['city'] ?? '';

        if (!empty($city)) {
            $city = strtolower(trim($city));
            $citySettings = collect($this->getSettings()['cities'] ?? [])->firstWhere('city', strtolower($city));
            $distanceCost = $citySettings['extra_cost'] ?? 0;
        }

        return $baseCost + $distanceCost;
    }

    /**
     * Define the fields configuration for the delivery method.
     *
     * Specifies the configurable fields for the "Courier" delivery method, such as informational messages
     * and city-specific settings.
     *
     * @return array Configuration of fields grouped by sections or tabs.
     */
    public function defineFields(): array
    {
        return [
            'message' => [
                'label' => __('sCommerce::global.message'),
                'fields' => [
                    'info' => [
                        'type' => 'text',
                        'label' => '',
                        'name' => 'info',
                        'value' => $this->getSettings()['info'] ?? '',
                        'placeholder' => __('sCommerce::global.info_message'),
                    ],
                ],
            ],
            'cities' => [
                'label' => __('sCommerce::global.cities'),
                'fields' => [
                    'cities' => [
                        'type' => 'dynamic',
                        'label' => '',
                        'button_label' => __('global.add'),
                        'values' => $this->getSettings()['cities'] ?? [],
                        'fields' => [
                            'city' => [
                                'type' => 'text',
                                'label' => __('sCommerce::global.city_name'),
                                'name' => 'cities[idx][city]',
                                'placeholder' => __('sCommerce::global.city_name'),
                            ],
                            'extra_cost' => [
                                'type' => 'number',
                                'label' => __('sCommerce::global.extra_cost'),
                                'helptext' => __('sCommerce::global.extra_cost_help'),
                                'name' => 'cities[idx][extra_cost]',
                                'placeholder' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Prepare the settings data for storage.
     *
     * Validates and formats the settings data provided by the admin panel.
     * Converts the settings into a JSON-compatible format for database storage.
     *
     * @param array $data The input data to validate and prepare.
     * @return string A JSON string of the validated and prepared settings data.
     * @throws ValidationException If validation fails.
     */
    public function prepareSettings(array $data): string
    {
        $preparedData = [];
        $fieldNames =  $this->extractFieldNames($this->defineFields());

        foreach ($fieldNames as $fieldName) {
            $key = preg_split('/\]\[|\[|\]/', rtrim($fieldName, ']'))[0];
            if (isset($data[$key])) {
                $preparedData[$key] = $data[$key];
            }
        }

        if (isset($data['cities']) && is_array($data['cities'])) {
            $preparedData['cities'] = array_values($data['cities']);
        }

        return json_encode($preparedData, JSON_UNESCAPED_UNICODE);
    }
}
