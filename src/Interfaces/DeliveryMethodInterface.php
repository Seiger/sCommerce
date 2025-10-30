<?php namespace Seiger\sCommerce\Interfaces;

/**
 * Interface DeliveryMethodInterface
 *
 * Defines the structure for delivery methods in the sCommerce module.
 * This interface ensures consistency across all delivery methods,
 * providing necessary functionalities such as title retrieval, cost calculation,
 * and settings management.
 */
interface DeliveryMethodInterface
{
    /**
     * Get the unique name of the delivery method.
     *
     * This name is used as an identifier for the delivery method in the system.
     *
     * @return string The unique name of the delivery method.
     */
    public function getName(): string;

    /**
     * Get the admin title for the delivery method.
     *
     * This method provides the type or category of the delivery method
     * for administrative purposes.
     *
     * @return string The admin-facing type of the delivery method.
     */
    public function getType(): string;

    /**
     * Get the localized title for the delivery method.
     *
     * Retrieves the title of the delivery method in the specified language.
     * If no language is provided, the system's current language is used.
     *
     * @param string|null $lang The language code (e.g., 'en', 'uk'). Defaults to null.
     * @return string The localized title of the delivery method.
     */
    public function getTitle(?string $lang = null): string;

    /**
     * Get the localized description for the delivery method.
     *
     * Retrieves the description of the delivery method in the specified language.
     * If no language is provided, the system's current language is used.
     *
     * @param string|null $lang The language code (e.g., 'en', 'uk'). Defaults to null.
     * @return string The localized description of the delivery method.
     */
    public function getDescription(?string $lang = null): string;

    /**
     * Get validation rules for the delivery method.
     *
     * This method defines specific validation rules for fields related to the current delivery method.
     * The rules ensure that all required fields are filled and properly formatted.
     *
     * @return array An associative array of validation rules, where the key is the field name,
     *               and the value is the validation rule.
     */
    public function getValidationRules(): array;

    /**
     * Calculate the cost of the delivery method based on the provided data.
     *
     * This method calculates the cost of delivery based on order-related data
     * such as location, weight, or other criteria.
     *
     * @param array $data An array containing order-related data.
     * @return float The calculated delivery cost.
     */
    public function calculateCost(array $data): float;

    /**
     * Define the settings fields for this delivery method.
     *
     * Specifies the configurable fields for the delivery method in the administration panel.
     * These fields allow customization of the delivery method's behavior.
     *
     * @return array An array of field definitions grouped by sections or tabs.
     */
    public function defineFields(): array;

    /**
     * Validate and prepare the settings data for storage.
     *
     * Validates the provided settings data against predefined rules
     * and formats it as a JSON string for database storage.
     *
     * @param array $data The input settings data to process.
     * @return string A JSON string containing the validated and prepared settings data.
     * @throws \Illuminate\Validation\ValidationException If validation rules are not met.
     */
    public function prepareSettings(array $data): string;

    /**
     * Retrieve the settings of the delivery method.
     *
     * Fetches the stored settings for the delivery method and returns them
     * as an associative array. These settings include configurable options
     * like warehouses, addresses, or delivery constraints.
     *
     * @return array An associative array of settings for the delivery method.
     */
    public function getSettings(): array;

    /**
     * Render delivery widget for checkout or other contexts.
     *
     * This method renders the HTML widget for the delivery method, which can be used
     * in various contexts such as checkout pages, order details, admin panel, or email notifications.
     *
     * The widget template is resolved in the following priority order:
     * 1. views/delivery/{name}.blade.php (project customization)
     * 2. core/custom/packages/seiger/scommerce/views/delivery/{name}.blade.php (custom package)
     * 3. core/vendor/seiger/scommerce/views/delivery/{name}.blade.php (vendor default)
     *
     * @param array $data Context data to pass to the widget template (e.g., checkout data, order data).
     * @return string The rendered HTML widget.
     */
    public function renderWidget(array $data): string;
}
