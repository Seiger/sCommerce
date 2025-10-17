<?php namespace Seiger\sCommerce\Payment;

use Seiger\sCommerce\Payment\BasePaymentMethod;
use Seiger\sCommerce\Models\sOrder;

/**
 * Class BankInvoicePayment
 *
 * This class represents the "Bank Invoice" payment method, allowing customers to pay via bank transfer
 * using an invoice. It extends the `BasePaymentMethod` class to inherit core functionality and provides
 * specific implementations for the bank invoice payment method.
 */
class BankInvoicePayment extends BasePaymentMethod
{
    /**
     * Get the unique name of the payment method.
     *
     * This method returns the unique identifier for the bank invoice payment method.
     *
     * @return string The name of the payment method (e.g., 'bank_invoice').
     */
    public function getName(): string
    {
        return 'bank_invoice';
    }

    /**
     * Get the admin display title for the bank invoice payment method.
     *
     * This method generates the title to be displayed in the admin panel for the bank invoice payment method.
     * It uses a localized title if available; otherwise, it falls back to a default.
     *
     * @return string The formatted title for the payment method.
     */
    public function getType(): string
    {
        $title = __('sCommerce::global.bank_invoice');
        $title = str_contains($title, '::') ? 'Bank Invoice' : $title;
        return "<b>" . $title . "</b> (bank_invoice)";
    }

    /**
     * Validate the payment data for the bank invoice payment method.
     *
     * Since the bank invoice payment method does not require additional validation at the time of order creation,
     * this method always returns true. Validation will occur when the payment is actually received.
     *
     * @param array $data The payment data to validate.
     * @return bool True indicating the payment data is valid.
     */
    public function validatePayment(array $data): bool
    {
        return true; // No additional validation required at checkout
    }

    /**
     * Define credentials fields for the bank invoice payment method.
     *
     * This method defines the bank account credentials that will be displayed to customers
     * and used for generating invoices.
     *
     * @return array The configuration array for the payment method's credentials.
     */
    public function defineCredentials(): array
    {
        return [];
    }

    /**
     * Define the fields configuration for the bank invoice payment method.
     *
     * This method defines the fields that can be configured for the bank invoice payment method,
     * such as payment terms, processing days, and informational messages.
     *
     * @return array The configuration array for the payment method's settings.
     */
    public function defineSettings(): array
    {
        return [
            'bank_details' => [
                'label' => __('sCommerce::global.bank_details'),
                'fields' => [
                    'account_holder' => [
                        'type' => 'text',
                        'label' => __('sCommerce::global.account_holder'),
                        'name' => 'account_holder',
                        'value' => $this->credentials['account_holder'] ?? '',
                        'placeholder' => __('sCommerce::global.account_holder_placeholder'),
                    ],
                    'bank_name' => [
                        'type' => 'text',
                        'label' => __('sCommerce::global.bank_name'),
                        'name' => 'bank_name',
                        'value' => $this->credentials['bank_name'] ?? '',
                        'placeholder' => __('sCommerce::global.bank_name_placeholder'),
                    ],
                    'account_number' => [
                        'type' => 'text',
                        'label' => __('sCommerce::global.account_number'),
                        'name' => 'account_number',
                        'value' => $this->credentials['account_number'] ?? '',
                        'placeholder' => 'UA123456789012345678901234567',
                    ],
                    'bank_code' => [
                        'type' => 'text',
                        'label' => __('sCommerce::global.bank_code'),
                        'name' => 'bank_code',
                        'value' => $this->credentials['bank_code'] ?? '',
                        'placeholder' => __('sCommerce::global.bank_code_placeholder'),
                    ],
                    'tax_id' => [
                        'type' => 'text',
                        'label' => __('sCommerce::global.tax_id'),
                        'name' => 'tax_id',
                        'value' => $this->credentials['tax_id'] ?? '',
                        'placeholder' => __('sCommerce::global.tax_id_placeholder'),
                    ],
                ],
            ],
            'general' => [
                'label' => __('sCommerce::global.general_settings'),
                'fields' => [
                    'payment_terms' => [
                        'type' => 'number',
                        'label' => __('sCommerce::global.payment_terms_days'),
                        'name' => 'payment_terms',
                        'value' => $this->getSettings()['payment_terms'] ?? 7,
                        'placeholder' => '7',
                        'min' => 1,
                        'max' => 30,
                    ],
                ],
                'label' => __('sCommerce::global.message'),
                'fields' => [
                    'info' => [
                        'type' => 'textarea',
                        'label' => __('sCommerce::global.payment_instructions'),
                        'name' => 'info',
                        'value' => $this->getSettings()['info'] ?? '',
                        'placeholder' => __('sCommerce::global.bank_invoice_info_placeholder'),
                        'rows' => 5,
                    ],
                ],
            ],
        ];
    }

    /**
     * Render the payment button for the order.
     *
     * Template resolution order:
     * 1. Custom template: assets/modules/scommerce/payment/bank_invoice.blade.php
     * 2. Default template: core/vendor/seiger/scommerce/payment/bank_invoice.blade.php
     * 
     * To customize the template, create a copy at:
     * assets/modules/scommerce/payment/bank_invoice.blade.php
     *
     * Available template variables (see Blade comments in template file):
     * - $title, $account_holder, $bank_name, $account_number, $bank_code
     * - $tax_id, $amount, $currency, $order_id, $payment_terms, $info
     *
     * @param int|string|array $data The order ID, order key, or an array containing order data.
     * @return string The HTML for the payment instructions.
     */
    public function payButton(int|string|array $data): string
    {
        // Load order data
        if (is_int($data)) {
            $order = sOrder::find($data);
        } elseif (is_string($data)) {
            $order = sOrder::whereKey($data)->first();
        } else {
            $order = (object) $data;
        }

        if (!$order) {
            return '';
        }

        // Prepare template variables
        $viewData = [
            'title' => $this->getTitle(),
            'account_holder' => $this->credentials['account_holder'] ?? '',
            'bank_name' => $this->credentials['bank_name'] ?? '',
            'account_number' => $this->credentials['account_number'] ?? '',
            'bank_code' => $this->credentials['bank_code'] ?? '',
            'tax_id' => $this->credentials['tax_id'] ?? '',
            'amount' => $order->total ?? 0,
            'currency' => $order->currency ?? 'UAH',
            'order_id' => $order->id ?? 0,
            'payment_terms' => $this->getSettings()['payment_terms'] ?? 7,
            'info' => $this->getSettings()['info'] ?? '',
        ];

        // Check if custom Blade template exists
        $customTemplatePath = EVO_BASE_PATH . 'assets/modules/scommerce/payment/bank_invoice.blade.php';
        $defaultTemplatePath = __DIR__ . '/../payment/bank_invoice.blade.php';
        
        if (file_exists($customTemplatePath)) {
            // Render custom Blade template
            return $this->renderBladeTemplate($customTemplatePath, $viewData);
        }

        // Use default template from core
        return $this->renderBladeTemplate($defaultTemplatePath, $viewData);
    }

    /**
     * Render Blade template.
     *
     * @param string $templatePath Path to the Blade template file
     * @param array $data Data to pass to the template
     * @return string Rendered HTML
     */
    private function renderBladeTemplate(string $templatePath, array $data): string
    {
        try {
            // Use Laravel Blade to render the template
            return view()->file($templatePath, $data)->render();
        } catch (\Exception $e) {
            Log::channel('scommerce')->error('Bank Invoice Payment: Failed to render Blade template', [
                'template' => $templatePath,
                'error' => $e->getMessage()
            ]);
            
            // Return empty string if template rendering fails
            return '';
        }
    }
    
    /**
     * Process the bank invoice payment.
     *
     * This method marks the order as pending payment confirmation.
     * Actual payment verification will be done manually by the administrator.
     *
     * @param array $data The payment data to process.
     * @return bool True indicating the order was created successfully.
     */
    public function processPayment(array $data): bool
    {
        // Bank invoice payments require manual confirmation
        // The order status will be updated to 'awaiting_payment' automatically
        return true;
    }
}
