<?php namespace Seiger\sCommerce\Delivery;

use Seiger\sCommerce\Models\sDeliveryMethod;
use Seiger\sCommerce\Interfaces\DeliveryMethodInterface;

class PickupDelivery implements DeliveryMethodInterface
{
    protected sDeliveryMethod $method;
    protected array $settings = [];

    public function __construct()
    {
        $this->method = sDeliveryMethod::where('name', 'pickup')->first() ?? new sDeliveryMethod([
            'name' => 'pickup',
            'class' => static::class,
        ]);
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
     * Get the admin display title for the PickupDelivery method.
     *
     * This method retrieves the localized title for the "Pickup" delivery method.
     * If the localized string is not found or contains an invalid format,
     * a default title "Pickup" is used. The final title includes formatting
     * for admin display.
     *
     * @return string The formatted title to display in the admin panel.
     */
    public function getAdminTitle(): string
    {
        $title = __('sCommerce::global.pickup');
        $title = str_contains($title, '::') ? 'Pickup' : $title;
        return "<b>" . $title . "</b> (pickup)";
    }

    /**
     * Get the title for the specified or current language.
     *
     * @param string|null $lang The language code (e.g., 'en', 'uk'). If null, use the app's current language.
     * @return string
     */
    public function getTitle(?string $lang = null): string
    {
        return $this->getLocalizedString($this->method?->title ?? '', $lang);
    }

    /**
     * Get the description for the specified or current language.
     *
     * @param string|null $lang The language code (e.g., 'en', 'uk'). If null, use the app's current language.
     * @return string
     */
    public function getDescription(?string $lang = null): string
    {
        return $this->getLocalizedString($this->method->description ?? '', $lang);
    }

    /**
     * Retrieve the settings of the delivery method.
     *
     * The settings are stored in the database as a JSON string and represent configurable options
     * for the delivery method, such as warehouses, addresses, or delivery limits.
     *
     * @return array An associative array of settings for the delivery method.
     */
    public function getSettings(): array
    {
        $settings = json_decode($this->method->settings ?? '', true);
        return is_array($settings) ? $settings : [];
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
     * Define the fields configuration for the delivery method.
     *
     * @return array Configuration of fields grouped by sections or tabs.
     */
    public function defineFields(): array
    {
        return [
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
     * This method validates the input data against the defined field rules
     * and prepares the settings array in a JSON-compatible format for storage.
     *
     * @param array $data The input data to validate and prepare.
     * @return string A JSON string of the validated and prepared settings data.
     * @throws ValidationException If validation fails.
     */
    public function prepareSettings(array $data): string
    {
        $fieldNames = $this->extractFieldNames();
        $reservedKeys = ['name', 'title', 'description'];
        $preparedData = [];

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

    /**
     * Extract localized string based on the specified or current language.
     *
     * @param string $json
     * @param string|null $lang
     * @return string
     */
    private function getLocalizedString(string $json, ?string $lang = null): string
    {
        $lang = $lang ?? evo()->getLocale();
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return '';
        }

        return $data[$lang] ?? ($data['en'] ?? reset($data));
    }

    /**
     * Recursively extract all field names from the defineFields configuration.
     *
     * @return array An array of field names.
     */
    private function extractFieldNames(): array
    {
        $fields = $this->defineFields();
        return $this->extractNamesFromFields($fields);
    }

    /**
     * Recursively extract field names from a nested fields configuration.
     *
     * @param array $fields The fields configuration array.
     * @return array An array of field names.
     */
    private function extractNamesFromFields(array $fields): array
    {
        $names = [];

        foreach ($fields as $key => $field) {
            if (isset($field['name'])) {
                $names[] = $field['name'];
            }

            // Recursively check nested fields
            if (isset($field['fields']) && is_array($field['fields'])) {
                $names = array_merge($names, $this->extractNamesFromFields($field['fields']));
            }
        }

        return $names;
    }
}
