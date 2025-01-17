<?php namespace Seiger\sCommerce\Interfaces;

interface DeliveryMethodInterface
{
    /**
     * Get the unique name of the delivery method.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the admin title for the delivery method.
     *
     * @return string
     */
    public function getAdminTitle(): string;

    /**
     * Get the localized title for the delivery method.
     *
     * @param string|null $lang The language code (e.g., 'en', 'uk'). If null, use the app's current language.
     * @return string
     */
    public function getTitle(?string $lang = null): string;

    /**
     * Get the localized description for the delivery method.
     *
     * @param string|null $lang The language code (e.g., 'en', 'uk'). If null, use the app's current language.
     * @return string
     */
    public function getDescription(?string $lang = null): string;

    /**
     * Calculate the cost of the delivery method based on the provided data.
     *
     * @param array $data Order-related data for cost calculation.
     * @return float
     */
    public function calculateCost(array $data): float;

    /**
     * Define the settings fields for this delivery method.
     *
     * @return array An array of field definitions.
     */
    public function defineFields(): array;

    /**
     * Validate and prepare the settings data for storage.
     *
     * The method ensures that all fields are validated against their respective rules
     * and formats the data as a JSON string suitable for database storage.
     *
     * @param array $data The input data to process.
     * @return string A JSON string of the validated and prepared settings data.
     * @throws \Illuminate\Validation\ValidationException If validation rules are not met.
     */
    public function prepareSettings(array $data): string;

    /**
     * Retrieve the settings of the delivery method.
     *
     * The settings are stored in the database as a JSON string and represent configurable options
     * for the delivery method, such as warehouses, addresses, or delivery limits.
     *
     * @return array An associative array of settings for the delivery method.
     */
    public function getSettings(): array;
}
