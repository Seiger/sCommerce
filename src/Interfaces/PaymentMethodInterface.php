<?php namespace Seiger\sCommerce\Interfaces;

/**
 * Interface PaymentMethodInterface
 *
 * Defines the structure for payment methods in the sCommerce module.
 * This interface ensures that all payment methods follow a consistent
 * structure and provide necessary functionality, such as retrieving
 * localized titles, validating, processing payments, managing modes,
 * and providing available modes.
 */
interface PaymentMethodInterface
{
    /**
     * Get the unique identifier for the payment method.
     *
     * This method returns the ID of the associated payment method from the database.
     * If the payment method is not found, it returns 0 as the default value.
     *
     * @return int The ID of the payment method or 0 if not found.
     */
    public function getId(): int;

    /**
     * Get a unique identifier for the payment instance.
     *
     * This is particularly useful when multiple instances of the same
     * payment class are used with different configurations.
     *
     * @return string The unique identifier.
     */
    public function getIdentifier(): string;

    /**
     * Get the unique name of the payment method.
     *
     * This name is used as an identifier for the payment method
     * in the system.
     *
     * @return string The unique name of the payment method.
     */
    public function getName(): string;

    /**
     * Get the admin display type for the payment method.
     *
     * This provides a string that represents the type of payment method
     * for administrative purposes.
     *
     * @return string The type of the payment method.
     */
    public function getType(): string;

    /**
     * Get the localized title for the payment method.
     *
     * This method retrieves the title of the payment method in the
     * specified language, or defaults to the system's current language.
     *
     * @param string|null $lang The language code (e.g., 'en', 'uk'). Defaults to null.
     * @return string The localized title of the payment method.
     */
    public function getTitle(?string $lang = null): string;

    /**
     * Get the localized description for the payment method.
     *
     * This method retrieves the description of the payment method in the
     * specified language, or defaults to the system's current language.
     *
     * @param string|null $lang The language code (e.g., 'en', 'uk'). Defaults to null.
     * @return string The localized description of the payment method.
     */
    public function getDescription(?string $lang = null): string;

    /**
     * Check if the payment method is active.
     *
     * This method checks the 'active' field of the payment method
     * to determine if the method is available for use.
     *
     * @return bool Returns true if the payment method is active, false otherwise.
     */
    public function isActive(): bool;

    /**
     * Render the payment button for the order.
     *
     * This method generates the HTML code for the payment button. It is expected that each payment method
     * will implement this method to generate a unique payment button for the user, based on the order details.
     * The method can be called from the main application to display the payment button to the user for completing the payment.
     *
     * The method accepts various forms of data input (order ID, order key, or an array of order data) and
     * dynamically handles these inputs to render the appropriate button.
     *
     * @param int|string|array $data The order ID, order key, or an array containing order data.
     *  - If it's an integer, the method assumes it's the order ID.
     *  - If it's a string, the method assumes it's the order key (identifier).
     *  - If it's an array, it assumes it's the complete order data.
     *
     * @return string The HTML for the payment button, or an empty string if the payment method is not found or invalid.
     *
     * @throws \Exception If there is an issue processing the payment button (e.g., missing or invalid payment method).
     */
    public function payButton(int|string|array $data): string;

    /**
     * Validate the payment data submitted by the user.
     *
     * Ensures that the provided payment data meets the required
     * criteria for the payment method.
     *
     * @param array $data The payment data submitted by the user.
     * @return bool True if the data is valid, false otherwise.
     */
    public function validatePayment(array $data): bool;

    /**
     * Process the payment.
     *
     * Handles the payment logic, such as interacting with external gateways or performing local validations.
     * The method should return:
     * - `true` when the payment has been processed (or no additional actions are required);
     * - `false` when the payment failed;
     * - an associative array with provider-specific data (e.g., redirect URL, invoice ID) when additional
     *   action is required on the client side.
     *
     * @param array $data The payment data (order information, user details, etc.).
     * @return array|bool Provider response (see description above).
     */
    public function processPayment(array $data): array|bool;

    /**
     * Retrieve the credentials for this payment method.
     *
     * Fetches the credentials configured for the payment method from the
     * storage and returns them as an array.
     *
     * @return array An array of credentials for the payment method.
     */
    public function initializeCredentials(): self;

    /**
     * Retrieve the settings for this payment method.
     *
     * Fetches the settings configured for the payment method from the
     * storage and returns them as an array.
     *
     * @return array An array of settings for the payment method.
     */
    public function getSettings(): array;

    /**
     * Define the settings fields for this payment method.
     *
     * Specifies the configurable fields for the payment method in the
     * administration panel. These fields allow customization of the
     * payment method's behavior.
     *
     * @return array An array of field definitions.
     */
    public function defineSettings(): array;

    /**
     * Define the credentials fields for this payment method.
     *
     * Specifies the credentials fields for the payment method in the
     * administration panel. These fields allow customization of the
     * payment method's behavior.
     *
     * @return array An array of field definitions.
     */
    public function defineCredentials(): array;

    /**
     * Prepare the settings data for storage.
     *
     * Validates and formats the input data for storage in a JSON-compatible
     * format.
     *
     * @param array $data The input data.
     * @return string A JSON string containing the prepared settings data.
     */
    public function prepareSettings(array $data): string;

    /**
     * Get the mode of the payment system.
     *
     * This method returns the mode of the payment system, such as 'test' for test environments
     * or 'production' for live environments. If the payment system does not use modes,
     * this method should return an empty string.
     *
     * @return string The mode of the payment system (e.g., 'test', 'production').
     */
    public function getMode(): string;

    /**
     * Define the list of available modes for this payment method.
     *
     * This method defines the available modes, such as ['test', 'production'],
     * for the payment system. If the payment system does not support modes,
     * this method can define an empty array.
     *
     * @return array An associative array of available modes with mode as key and description as value.
     */
    public function defineAvailableModes(): array;
}
