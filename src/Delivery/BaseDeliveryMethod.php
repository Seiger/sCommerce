<?php namespace Seiger\sCommerce\Delivery;

use Seiger\sCommerce\Interfaces\DeliveryMethodInterface;
use Seiger\sCommerce\Models\sDeliveryMethod;

/**
 * Class BaseDeliveryMethod
 *
 * This abstract class provides a base implementation for delivery methods. It implements common
 * functionality shared across all delivery methods, such as retrieving settings, titles, and descriptions,
 * as well as handling localized strings and field extraction for settings configuration.
 *
 * @package Seiger\sCommerce\Delivery
 */
abstract class BaseDeliveryMethod implements DeliveryMethodInterface
{
    /**
     * @var sDeliveryMethod The delivery method instance loaded from the database or initialized with default values.
     */
    protected sDeliveryMethod $method;

    /**
     * BaseDeliveryMethod constructor.
     *
     * This constructor initializes the delivery method by loading its configuration from the database
     * or creating a new instance with default values.
     */
    public function __construct()
    {
        $this->method = sDeliveryMethod::where('name', $this->getName())->first() ?? new sDeliveryMethod([
            'name' => $this->getName(),
            'class' => static::class,
        ]);
    }

    /**
     * Get the localized title for the delivery method.
     *
     * @param string|null $lang The language code (e.g., 'en', 'uk'). If null, the app's current language is used.
     * @return string The localized title for the delivery method.
     */
    public function getTitle(?string $lang = null): string
    {
        return $this->getLocalizedString($this->method->title ?? '', $lang);
    }

    /**
     * Get the localized description for the delivery method.
     *
     * @param string|null $lang The language code (e.g., 'en', 'uk'). If null, the app's current language is used.
     * @return string The localized description for the delivery method.
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
     * Extract a localized string based on the specified or current language.
     *
     * @param string $json The JSON-encoded string containing translations.
     * @param string|null $lang The language code (e.g., 'en', 'uk'). If null, the app's current language is used.
     * @return string The localized string or an empty string if no valid translation is found.
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
     * Recursively extract field names from a nested fields configuration.
     *
     * This method processes the fields configuration to extract all defined field names,
     * including nested fields.
     *
     * @param array $fields The fields configuration array.
     * @return array An array of field names.
     */
    protected function extractFieldNames(array $fields): array
    {
        $names = [];

        foreach ($fields as $key => $field) {
            if (isset($field['name'])) {
                $names[] = $field['name'];
            }

            if (isset($field['fields']) && is_array($field['fields'])) {
                $names = array_merge($names, $this->extractFieldNames($field['fields']));
            }
        }

        return $names;
    }
}