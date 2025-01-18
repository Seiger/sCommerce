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

    /**
     * Define the fields configuration for the cash payment method.
     *
     * This method defines the fields that can be configured for the cash payment method,
     * such as informational messages or additional notes.
     *
     * @return array The configuration array for the payment method's settings.
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
                        'placeholder' => __('sCommerce::global.info_message'),
                    ],
                ],
            ],
        ];
    }
}
