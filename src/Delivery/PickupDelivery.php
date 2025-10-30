<?php namespace Seiger\sCommerce\Delivery;

use Seiger\sCommerce\Delivery\BaseDeliveryMethod;
use Seiger\sCommerce\Models\sDeliveryMethod;

/**
 * Class PickupDelivery
 *
 * This class implements the "Pickup" delivery method. It extends the `BaseDeliveryMethod` and provides
 * specific configurations and behavior for self-pickup delivery, such as configurable warehouse locations.
 *
 * @package Seiger\sCommerce\Delivery
 */
class PickupDelivery extends BaseDeliveryMethod
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
        return 'pickup';
    }

    /**
     * Get the admin display title for the PickupDelivery method.
     *
     * Retrieves the localized title for the delivery method to be displayed in the admin panel.
     * If the title is not found or contains an invalid format, a default title is used.
     *
     * @return string The formatted title for admin display.
     */
    public function getType(): string
    {
        $title = __('sCommerce::global.pickup');
        $title = str_contains($title, '::') ? 'Pickup' : $title;
        return "<b>" . $title . "</b> (pickup)";
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
     *     'delivery.address' => 'string|max:255',
     * ]
     */
    public function getValidationRules(): array
    {
        return [
            'delivery.pickup' => 'string|max:255',
        ];
    }

    /**
     * Calculate the cost of delivery based on the order data.
     *
     * The pickup delivery method has no additional cost, so this method always returns 0.
     *
     * @param array $orderData The order data, including user and address information.
     * @return float The calculated delivery cost, which is always 0 for pickup.
     */
    public function calculateCost(array $orderData): float
    {
        return 0.0;
    }

    /**
     * Define the fields configuration for the delivery method.
     *
     * Specifies the configurable fields for the "Pickup" delivery method, such as warehouse locations.
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
                        'type' => 'textarea',
                        'label' => __('sCommerce::global.info_message'),
                        'name' => 'info',
                        'value' => $this->getSettings()['info'] ?? '',
                        'placeholder' => __('sCommerce::global.delivery_info_placeholder'),
                        'helptext' => __('sCommerce::global.delivery_info_helptext'),
                    ],
                ],
            ],
            'locations' => [
                'label' => __('sCommerce::global.pickup_locations'),
                'fields' => [
                    'warehouses' => [
                        'type' => 'dynamic',
                        'label' => '',
                        'values' => $this->getSettings()['locations'] ?? [],
                        'button_label' => __('global.add'),
                        'fields' => [
                            'address' => [
                                'type' => 'text',
                                'label' => '',
                                'name' => 'locations[idx][address]',
                                'placeholder' => __('sCommerce::global.address'),
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

        if (isset($data['locations']) && is_array($data['locations'])) {
            $preparedData['locations'] = array_values($data['locations']);
        }

        return json_encode($preparedData, JSON_UNESCAPED_UNICODE);
    }
}
