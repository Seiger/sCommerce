<?php namespace Seiger\sCommerce\Payment;

use Seiger\sCommerce\Interfaces\PaymentMethodInterface;
use Seiger\sCommerce\Models\sPaymentMethod;

/**
 * Class BasePaymentMethod
 *
 * This abstract class provides a base implementation for payment methods.
 * It implements common functionality such as localization, settings management,
 * and dynamic identifier handling.
 */
abstract class BasePaymentMethod implements PaymentMethodInterface
{
    /**
     * @var sPaymentMethod The associated payment method data from the database.
     */
    protected sPaymentMethod $method;
    public $credentials = [];

    /**
     * BasePaymentMethod constructor.
     *
     * Initializes the payment method by attempting to retrieve it from the database
     * or creating a new instance if it does not exist.
     *
     * @param string $identifier An optional unique identifier for the payment method.
     */
    public function __construct(string $identifier = '')
    {
        $this->method = sPaymentMethod::whereName($this->getName())
            ->whereIdentifier($identifier)
            ->first() ?? new sPaymentMethod([
            'name' => $this->getName(),
            'class' => static::class,
            'identifier' => $identifier,
        ]);
        $this->initializeCredentials();
    }

    /**
     * Get a unique identifier for the payment method.
     *
     * Combines the method's name and identifier to generate a unique key.
     *
     * @return string The unique identifier for the payment method.
     */
    public function getIdentifier(): string
    {
        return $this->getName() . $this->method->identifier;
    }

    /**
     * Get the localized title of the payment method.
     *
     * @param string|null $lang The language code (e.g., 'en', 'uk'). If null, uses the current system language.
     * @return string The localized title.
     */
    public function getTitle(?string $lang = null): string
    {
        return $this->getLocalizedString($this->method->title ?? '', $lang);
    }

    /**
     * Get the localized description of the payment method.
     *
     * @param string|null $lang The language code (e.g., 'en', 'uk'). If null, uses the current system language.
     * @return string The localized description.
     */
    public function getDescription(?string $lang = null): string
    {
        return $this->getLocalizedString($this->method->description ?? '', $lang);
    }

    /**
     * Retrieve the stored credentials securely.
     */
    public function initializeCredentials(): self
    {
        $defines = $this->defineCredentials();
        if (!empty($defines)) {
            if (is_array($defines) && count($defines) > 0) {
                $fields = [];
                foreach ($defines as $define) {
                    if (isset($define['fields'])) {
                        $fields = array_merge($fields, array_keys($define['fields']));
                    }
                }

                if (count($fields) > 0) {
                    $credentials = json_decode($this->method->сredentials ?? '', true);
                    unset($this->method->сredentials);

                    foreach ($fields as $key) {
                        $this->credentials[$key] = isset($credentials[$key]) ? $credentials[$key] : null;
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Retrieve the settings for the payment method.
     *
     * @return array An associative array of settings for the payment method.
     */
    public function getSettings(): array
    {
        $settings = json_decode($this->method->settings ?? '', true);
        return is_array($settings) ? $settings : [];
    }

    /**
     * Extract a localized string based on the specified or current language.
     *
     * @param string $json A JSON-encoded string of translations.
     * @param string|null $lang The language code. If null, uses the current system language.
     * @return string The localized string.
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
     * Prepare the credentials data for storage.
     *
     * Validates and formats the credentials data for the payment method based on its fields configuration.
     *
     * @param array $data The credentials data to process.
     * @return string A JSON-encoded string of the validated credentials data.
     */
    public function prepareCredentials(array $data): string
    {
        $preparedData = [];
        $fieldNames =  $this->extractFieldNames($this->defineCredentials());

        foreach ($fieldNames as $fieldName) {
            $key = preg_split('/\]\[|\[|\]/', rtrim($fieldName, ']'))[0];
            if (isset($data[$key])) {
                $preparedData[$key] = $data[$key];
            }
        }

        return json_encode($preparedData, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Prepare the settings data for storage.
     *
     * Validates and formats the settings data for the payment method based on its fields configuration.
     *
     * @param array $data The settings data to process.
     * @return string A JSON-encoded string of the validated settings data.
     */
    public function prepareSettings(array $data): string
    {
        $preparedData = [];
        $fieldNames =  $this->extractFieldNames($this->defineSettings());

        foreach ($fieldNames as $fieldName) {
            $key = preg_split('/\]\[|\[|\]/', rtrim($fieldName, ']'))[0];
            if (isset($data[$key])) {
                $preparedData[$key] = $data[$key];
            }
        }

        return json_encode($preparedData, JSON_UNESCAPED_UNICODE);
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
    private function extractFieldNames(array $fields): array
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
