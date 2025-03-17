<?php namespace Seiger\sCommerce\Payment;

use Seiger\sCommerce\Payment\BasePaymentMethod;

/**
 * Class CashPayment
 *
 * This class represents the "Cash" payment method, allowing users to pay for their orders in cash.
 * It extends the `BasePaymentMethod` class to inherit core functionality and provides specific
 * implementations for the cash payment method.
 */
class CashPayment extends BasePaymentMethod
{
    /**
     * Get the unique name of the payment method.
     *
     * This method returns the unique identifier for the cash payment method.
     *
     * @return string The name of the payment method (e.g., 'cash').
     */
    public function getName(): string
    {
        return 'cash';
    }

    /**
     * Get the admin display title for the cash payment method.
     *
     * This method generates the title to be displayed in the admin panel for the cash payment method.
     * It uses a localized title if available; otherwise, it falls back to a default.
     *
     * @return string The formatted title for the payment method.
     */
    public function getType(): string
    {
        $title = __('sCommerce::global.cash');
        $title = str_contains($title, '::') ? 'Сash' : $title;
        return "<b>" . $title . "</b> (cash)";
    }

    /**
     * Validate the payment data for the cash payment method.
     *
     * Since the cash payment method does not require additional validation, this method always returns true.
     *
     * @param array $data The payment data to validate.
     * @return bool True indicating the payment data is valid.
     */
    public function validatePayment(array $data): bool
    {
        return true; // No additional validation required
    }

    public function defineCredentials(): array
    {
        return [];
    }

    /**
     * Define the fields configuration for the cash payment method.
     *
     * This method defines the fields that can be configured for the cash payment method,
     * such as informational messages or additional notes.
     *
     * @return array The configuration array for the payment method's settings.
     */
    public function defineSettings(): array
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
        ];
    }


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
    public function payButton(int|string|array $data): string
    {
        return '';
    }

    /**
     * Process the cash payment.
     *
     * This method assumes the payment is successful and always returns true.
     * It can be extended to handle specific business logic if required.
     *
     * @param array $data The payment data to process.
     * @return bool True indicating the payment is successful.
     */
    public function processPayment(array $data): bool
    {
        return true; // Assume the payment is successful
    }
}
