<?php namespace Seiger\sCommerce\Delivery;

use Seiger\sCommerce\Interfaces\DeliveryMethodInterface;
use Seiger\sCommerce\Models\sDeliveryMethod;

class CourierDelivery implements DeliveryMethodInterface
{
    protected sDeliveryMethod $method;
    protected array $settings = [];

    public function __construct()
    {
        $this->method = sDeliveryMethod::where('name', 'courier')->first() ?? new sDeliveryMethod([
            'name' => 'courier',
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
        return 'courier';
    }

    /**
     * Get the admin display title for the CourierDelivery method.
     *
     * This method retrieves the localized title for the "Сourier" delivery method.
     * If the localized string is not found or contains an invalid format,
     * a default title "Сourier" is used. The final title includes formatting
     * for admin display.
     *
     * @return string The formatted title to display in the admin panel.
     */
    public function getAdminTitle(): string
    {
        $title = __('sCommerce::global.courier');
        $title = str_contains($title, '::') ? 'Сourier' : $title;
        return "<b>" . $title . "</b> (courier)";
    }

    /**
     * Get the title for the specified or current language.
     *
     * @param string|null $lang The language code (e.g., 'en', 'uk'). If null, use the app's current language.
     * @return string
     */
    public function getTitle(?string $lang = null): string
    {
        return $this->getLocalizedString($this->method->title ?? '', $lang);
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
        $baseCost = $this->settings['base_cost'] ?? 70;
        $distanceCost = 0;

        if (isset($orderData['user']['address']['city'])) {
            $city = strtolower(trim($orderData['user']['address']['city']));

            $citySettings = collect($this->settings['cities'] ?? [])->firstWhere('city', strtolower($city));
            $distanceCost = $citySettings['extra_cost'] ?? 0;
        }

        return $baseCost + $distanceCost;
    }

    /**
     * Define the fields configuration for the delivery method.
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
                        'placeholder' => __('sCommerce::global.message'),
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
        $preparedData = [];

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

    /**
     * Extract localized string based on the specified or current language.
     *
     * @param string $json
     * @param string|null $lang
     * @return string
     */
    protected function getLocalizedString(string $json, ?string $lang = null): string
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
