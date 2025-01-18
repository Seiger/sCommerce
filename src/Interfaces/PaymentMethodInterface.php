<?php namespace Seiger\sCommerce\Interfaces;

/**
 * Interface PaymentMethodInterface
 *
 * Defines the structure for payment methods in the sCommerce module.
 * This interface ensures that all payment methods follow a consistent
 * structure and provide necessary functionality, such as retrieving
 * localized titles, validating, and processing payments.
 */
interface PaymentMethodInterface
{
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
     * Get a unique identifier for the payment instance.
     *
     * This is particularly useful when multiple instances of the same
     * payment class are used with different configurations.
     *
     * @return string The unique identifier.
     */
    public function getIdentifier(): string;

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
     * Handles the payment logic, such as interacting with external
     * payment gateways or performing local validations.
     *
     * @param array $data The payment data.
     * @return bool True if the payment is successfully processed, false otherwise.
     */
    public function processPayment(array $data): bool;

    /**
     * Define the settings fields for this payment method.
     *
     * Specifies the configurable fields for the payment method in the
     * administration panel. These fields allow customization of the
     * payment method's behavior.
     *
     * @return array An array of field definitions.
     */
    public function defineFields(): array;

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
     * Retrieve the settings for this payment method.
     *
     * Fetches the settings configured for the payment method from the
     * storage and returns them as an array.
     *
     * @return array An array of settings for the payment method.
     */
    public function getSettings(): array;
}
