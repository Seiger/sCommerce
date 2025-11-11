---
id: payments
title: Payments
sidebar_position: 13
---

# Creating Custom Payment Methods

This guide explains how to create custom payment method integrations in sCommerce. You'll learn how to extend the base payment functionality to integrate with any payment gateway or implement custom payment logic.

## Overview

sCommerce provides a flexible payment architecture that allows you to:

- Integrate with external payment gateways (Stripe, PayPal, LiqPay, etc.)
- Implement custom payment logic
- Configure payment credentials and settings through the admin panel
- Support multiple payment modes (test/production)
- Handle payment validation and processing
- Render custom payment buttons and forms

## Payment Method Architecture

### Core Components

1. **`PaymentMethodInterface`** - Defines required methods for all payment integrations
2. **`BasePaymentMethod`** - Abstract base class providing common functionality
3. **`sPaymentMethod`** - Database model for storing payment configuration
4. **`sCheckout`** - Service for registering and using payment methods

### Payment Method Lifecycle

```
Registration → Configuration → Validation → Processing → Completion
```

## Creating a Payment Method

### Step 1: Create Payment Method Class

All payment methods must extend `BasePaymentMethod` and implement `PaymentMethodInterface`.

**Example: Simple Cash Payment**

```php
<?php namespace Seiger\sCommerce\Payment;

use Seiger\sCommerce\Payment\BasePaymentMethod;

/**
 * Cash Payment Method
 * 
 * A simple payment method for cash payments (e.g., cash on delivery)
 */
class CashPayment extends BasePaymentMethod
{
    /**
     * Get the unique name of the payment method.
     * 
     * This is used as the identifier in the system.
     * 
     * @return string
     */
    public function getName(): string
    {
        return 'cash';
    }

    /**
     * Get the admin display type for the payment method.
     * 
     * This is shown in the admin panel payment methods list.
     * 
     * @return string
     */
    public function getType(): string
    {
        $title = __('sCommerce::global.cash');
        $title = str_contains($title, '::') ? 'Cash' : $title;
        return "<b>" . $title . "</b> (cash)";
    }

    /**
     * Validate the payment data.
     * 
     * Return true if the payment data is valid.
     * 
     * @param array $data
     * @return bool|array
     */
    public function validatePayment(array $data): bool
    {
        return true; // No validation needed for cash
    }

    /**
     * Define credentials fields for admin panel.
     * 
     * Return an empty array if no credentials are needed.
     * 
     * @return array
     */
    public function defineCredentials(): array
    {
        return [];
    }

    /**
     * Define settings fields for admin panel.
     * 
     * These fields will be displayed in the payment method configuration.
     * 
     * @return array
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
     * Render payment button HTML.
     * 
     * Return HTML for the payment button that will be displayed on the frontend.
     * 
     * @param int|string|array $data Order ID, order key, or order data array
     * @return string
     */
    public function payButton(int|string|array $data): string
    {
        return ''; // No button needed for cash payment
    }

    /**
     * Process the payment.
     * 
     * This method is called when the payment is being processed.
     * Return true for success, false for failure, or an array with additional data (e.g. redirect URL).
     * 
     * @param array $data
     * @return bool
     */
    public function processPayment(array $data): bool|array
    {
        return true; // Assume payment is successful
    }
}
```

### Step 2: Advanced Payment Method with Gateway Integration

**Example: Stripe Payment Integration**

```php
<?php namespace YourNamespace\Payment;

use Seiger\sCommerce\Payment\BasePaymentMethod;
use Stripe\StripeClient;
use Seiger\sCommerce\Models\sOrder;

/**
 * Stripe Payment Gateway Integration
 */
class StripePayment extends BasePaymentMethod
{
    private StripeClient $stripe;

    public function __construct(string $identifier = '')
    {
        parent::__construct($identifier);
        
        // Initialize Stripe client with credentials
        $this->stripe = new StripeClient($this->credentials['secret_key'] ?? '');
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function getType(): string
    {
        return "<b>Stripe</b> (stripe)";
    }

    /**
     * Define credentials that will be stored securely.
     * These are typically API keys and secrets.
     */
    public function defineCredentials(): array
    {
        return [
            'api_keys' => [
                'label' => __('sCommerce::global.api_keys'),
                'fields' => [
                    'publishable_key' => [
                        'type' => 'text',
                        'label' => __('sCommerce::global.publishable_key'),
                        'name' => 'publishable_key',
                        'value' => $this->credentials['publishable_key'] ?? '',
                        'placeholder' => 'pk_test_...',
                    ],
                    'secret_key' => [
                        'type' => 'password',
                        'label' => __('sCommerce::global.secret_key'),
                        'name' => 'secret_key',
                        'value' => $this->credentials['secret_key'] ?? '',
                        'placeholder' => 'sk_test_...',
                    ],
                    'webhook_secret' => [
                        'type' => 'password',
                        'label' => __('sCommerce::global.webhook_secret'),
                        'name' => 'webhook_secret',
                        'value' => $this->credentials['webhook_secret'] ?? '',
                        'placeholder' => 'whsec_...',
                    ],
                ],
            ],
        ];
    }

    /**
     * Define payment method settings.
     * These are configurable options for the payment method.
     */
    public function defineSettings(): array
    {
        return [
            'general' => [
                'label' => __('sCommerce::global.general_settings'),
                'fields' => [
                    'capture_method' => [
                        'type' => 'select',
                        'label' => __('sCommerce::global.capture_method'),
                        'name' => 'capture_method',
                        'value' => $this->getSettings()['capture_method'] ?? 'automatic',
                        'options' => [
                            'automatic' => __('sCommerce::global.automatic'),
                            'manual' => __('sCommerce::global.manual'),
                        ],
                    ],
                    'save_cards' => [
                        'type' => 'checkbox',
                        'label' => __('sCommerce::global.save_cards'),
                        'name' => 'save_cards',
                        'value' => $this->getSettings()['save_cards'] ?? 0,
                    ],
                    'description' => [
                        'type' => 'textarea',
                        'label' => __('sCommerce::global.statement_descriptor'),
                        'name' => 'statement_descriptor',
                        'value' => $this->getSettings()['statement_descriptor'] ?? '',
                        'placeholder' => 'My Shop Purchase',
                    ],
                ],
            ],
        ];
    }

    /**
     * Define available modes for this payment method.
     * Common modes are 'test' and 'production'.
     */
    public function defineAvailableModes(): array
    {
        return [
            'test' => __('sCommerce::global.test_mode'),
            'production' => __('sCommerce::global.production_mode'),
        ];
    }

    public function validatePayment(array $data): bool
    {
        // Validate required fields
        if (empty($data['payment_method_id'])) {
            return false;
        }

        if (empty($data['order_id'])) {
            return false;
        }

        return true;
    }

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

        $publishableKey = $this->credentials['publishable_key'] ?? '';
        $orderId = $order->id ?? 0;
        $amount = $order->total ?? 0;
        $currency = $order->currency ?? 'usd';

        // Return HTML with Stripe Elements integration
        return <<<HTML
<div id="stripe-payment-form-{$orderId}">
    <div id="card-element"></div>
    <div id="card-errors" role="alert"></div>
    <button id="stripe-submit-btn" type="button" class="btn btn-primary">
        Pay {$amount} {$currency}
    </button>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
(function() {
    const stripe = Stripe('{$publishableKey}');
    const elements = stripe.elements();
    const cardElement = elements.create('card');
    cardElement.mount('#card-element');

    document.getElementById('stripe-submit-btn').addEventListener('click', async () => {
        const {paymentMethod, error} = await stripe.createPaymentMethod({
            type: 'card',
            card: cardElement,
        });

        if (error) {
            document.getElementById('card-errors').textContent = error.message;
            return;
        }

        // Submit payment to your server
        const response = await fetch('/checkout/pay/stripe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                payment_method_id: paymentMethod.id,
                order_id: {$orderId}
            })
        });

        const result = await response.json();
        
        if (result.success) {
            window.location.href = result.redirect_url;
        } else {
            document.getElementById('card-errors').textContent = result.message;
        }
    });
})();
</script>
HTML;
    }

    public function processPayment(array $data): array|bool
    {
        try {
            // Load order
            $order = sOrder::find($data['order_id'] ?? 0);
            
            if (!$order) {
                return [
                    'success' => false,
                    'message' => 'Order not found',
                ];
            }

            // Create Payment Intent
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $this->convertToCents($order->total),
                'currency' => strtolower($order->currency ?? 'usd'),
                'payment_method' => $data['payment_method_id'] ?? '',
                'confirm' => true,
                'metadata' => [
                    'order_id' => $order->id,
                    'customer_email' => $order->customer_email ?? '',
                ],
                'statement_descriptor' => $this->getSettings()['statement_descriptor'] ?? null,
            ]);

            // Check payment status
            if ($paymentIntent->status === 'succeeded') {
                // Update order status
                $order->update([
                    'status' => 'paid',
                    'transaction_id' => $paymentIntent->id,
                    'paid_at' => now(),
                ]);

                return [
                    'success' => true,
                    'redirect_url' => route('order.success', ['order' => $order->id]),
                    'transaction_id' => $paymentIntent->id,
                ];
            }

            if ($paymentIntent->status === 'requires_action') {
                return [
                    'success' => false,
                    'requires_action' => true,
                    'client_secret' => $paymentIntent->client_secret,
                ];
            }

            return [
                'success' => false,
                'message' => 'Payment failed',
            ];

        } catch (\Exception $e) {
            \Log::error('Stripe payment error', [
                'error' => $e->getMessage(),
                'order_id' => $data['order_id'] ?? null,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert amount to cents (for Stripe API)
     */
    private function convertToCents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
```

### Step 3: Register Payment Method

Payment methods are automatically discovered and registered when they implement `PaymentMethodInterface` and are stored in the database.

**Service Provider Registration (Optional):**

```php
<?php namespace YourNamespace\Providers;

use Illuminate\Support\ServiceProvider;
use Seiger\sCommerce\Facades\sCheckout;
use YourNamespace\Payment\StripePayment;

class PaymentServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Payment methods are auto-discovered from database
        // No manual registration needed in most cases
        
        // For manual registration:
        // sCheckout::registerPaymentMethod(new StripePayment());
    }
}
```

**Database Registration:**

Payment methods are registered in the `s_payment_methods` table. sCommerce automatically scans for payment classes and allows you to activate them through the admin panel.

## Payment Method Interface Reference

### Required Methods

#### `getName(): string`

Returns the unique identifier for the payment method.

```php
public function getName(): string
{
    return 'stripe'; // Must be unique
}
```

#### `getType(): string`

Returns the admin panel display name.

```php
public function getType(): string
{
    return "<b>Stripe</b> (stripe)";
}
```

#### `getIdentifier(): string`

Returns combined name and identifier (automatically handled by `BasePaymentMethod`).

#### `validatePayment(array $data): bool`

Validates payment data before processing.

```php
public function validatePayment(array $data): bool
{
    return !empty($data['payment_method_id']) && !empty($data['order_id']);
}
```

#### `processPayment(array $data): array|bool`

Processes the payment. Return `true`/`false` or an array with details.

```php
public function processPayment(array $data): array|bool
{
    return [
        'success' => true,
        'transaction_id' => '...',
        'redirect_url' => '...',
    ];
}
```

#### `payButton(int|string|array $data): string`

Renders HTML for the payment button/form.

```php
public function payButton(int|string|array $data): string
{
    return '<button>Pay Now</button>';
}
```

#### `defineCredentials(): array`

Defines credential fields (API keys, secrets) for admin configuration.

```php
public function defineCredentials(): array
{
    return [
        'api_keys' => [
            'label' => 'API Keys',
            'fields' => [
                'api_key' => [
                    'type' => 'password',
                    'label' => 'API Key',
                    'name' => 'api_key',
                    'value' => $this->credentials['api_key'] ?? '',
                ],
            ],
        ],
    ];
}
```

#### `defineSettings(): array`

Defines settings fields for admin configuration.

```php
public function defineSettings(): array
{
    return [
        'options' => [
            'label' => 'Options',
            'fields' => [
                'auto_capture' => [
                    'type' => 'checkbox',
                    'label' => 'Auto Capture',
                    'name' => 'auto_capture',
                    'value' => $this->getSettings()['auto_capture'] ?? 1,
                ],
            ],
        ],
    ];
}
```

#### `defineAvailableModes(): array`

Defines available modes (test/production).

```php
public function defineAvailableModes(): array
{
    return [
        'test' => 'Test Mode',
        'production' => 'Production Mode',
    ];
}
```

## Field Types for Configuration

### Text Field

```php
[
    'type' => 'text',
    'label' => 'Label',
    'name' => 'field_name',
    'value' => $this->settings['field_name'] ?? '',
    'placeholder' => 'Enter value',
]
```

### Password Field

```php
[
    'type' => 'password',
    'label' => 'API Secret',
    'name' => 'api_secret',
    'value' => $this->credentials['api_secret'] ?? '',
]
```

### Textarea Field

```php
[
    'type' => 'textarea',
    'label' => 'Description',
    'name' => 'description',
    'value' => $this->settings['description'] ?? '',
    'rows' => 5,
]
```

### Checkbox Field

```php
[
    'type' => 'checkbox',
    'label' => 'Enable Feature',
    'name' => 'feature_enabled',
    'value' => $this->settings['feature_enabled'] ?? 0,
]
```

### Select Field

```php
[
    'type' => 'select',
    'label' => 'Choose Option',
    'name' => 'option',
    'value' => $this->settings['option'] ?? 'default',
    'options' => [
        'option1' => 'Option 1',
        'option2' => 'Option 2',
    ],
]
```

## Webhook Handling

For payment gateways that use webhooks (Stripe, PayPal, etc.), you'll need to set up webhook endpoints.

### Create Webhook Controller

```php
<?php namespace YourNamespace\Http\Controllers;

use Illuminate\Http\Request;
use Seiger\sCommerce\Models\sOrder;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        
        // Verify webhook signature
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSuccess($event->data->object);
                break;
                
            case 'payment_intent.payment_failed':
                $this->handlePaymentFailure($event->data->object);
                break;
        }

        return response()->json(['status' => 'success']);
    }

    private function handlePaymentSuccess($paymentIntent)
    {
        $orderId = $paymentIntent->metadata->order_id ?? null;
        
        if ($orderId) {
            $order = sOrder::find($orderId);
            $order->update([
                'status' => 'paid',
                'transaction_id' => $paymentIntent->id,
                'paid_at' => now(),
            ]);
        }
    }

    private function handlePaymentFailure($paymentIntent)
    {
        $orderId = $paymentIntent->metadata->order_id ?? null;
        
        if ($orderId) {
            $order = sOrder::find($orderId);
            $order->update([
                'status' => 'payment_failed',
            ]);
        }
    }
}
```

### Register Webhook Route

```php
// routes/web.php
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');
```

## Testing Payment Methods

### Unit Tests

```php
<?php namespace Tests\Unit\Payment;

use Tests\TestCase;
use YourNamespace\Payment\StripePayment;
use Seiger\sCommerce\Models\sOrder;

class StripePaymentTest extends TestCase
{
    public function test_payment_validation()
    {
        $payment = new StripePayment();
        
        $this->assertTrue($payment->validatePayment([
            'payment_method_id' => 'pm_test_123',
            'order_id' => 1,
        ]));
        
        $this->assertFalse($payment->validatePayment([]));
    }

    public function test_payment_processing()
    {
        $order = sOrder::factory()->create();
        $payment = new StripePayment();
        
        $result = $payment->processPayment([
            'payment_method_id' => 'pm_test_123',
            'order_id' => $order->id,
        ]);
        
        $this->assertTrue($result['success'] ?? false);
    }
}
```

## Best Practices

### 1. **Security**
- Store API keys in credentials (encrypted in database)
- Never expose secret keys in frontend code
- Validate webhook signatures
- Use HTTPS for all payment-related requests

### 2. **Error Handling**
- Always catch and log exceptions
- Provide user-friendly error messages
- Return structured error responses

```php
try {
    // Payment processing
} catch (\Exception $e) {
    \Log::error('Payment error', [
        'error' => $e->getMessage(),
        'data' => $data,
    ]);
    
    return [
        'success' => false,
        'message' => 'Payment processing failed. Please try again.',
    ];
}
```

### 3. **Logging**
- Log all payment attempts
- Include order IDs and transaction IDs
- Log webhook events

### 4. **Testing**
- Use test mode for development
- Test with provider's test card numbers
- Test webhook handling
- Test error scenarios

### 5. **Modes**
- Support test and production modes
- Use different API keys for each mode
- Display mode indicator in admin panel

## Common Patterns

### Redirect-Based Payments (PayPal, etc.)

```php
public function payButton(int|string|array $data): string
{
    $order = $this->loadOrder($data);
    $redirectUrl = $this->createPaymentSession($order);
    
    return <<<HTML
<form action="{$redirectUrl}" method="GET">
    <button type="submit">Pay with PayPal</button>
</form>
HTML;
}
```

### Embedded Payment Forms (Stripe, etc.)

```php
public function payButton(int|string|array $data): string
{
    $order = $this->loadOrder($data);
    
    return <<<HTML
<div id="payment-form">
    <!-- Payment form elements -->
    <script>
        // Initialize payment SDK
    </script>
</div>
HTML;
}
```

### Callback-Based Payments

```php
public function processPayment(array $data): array|bool
{
    // Initiate payment
    $response = $this->gateway->createPayment($data);
    
    return [
        'success' => true,
        'requires_action' => true,
        'callback_url' => $response->callbackUrl,
    ];
}
```

## Links

- [Built-in Payment Methods](payments/methods.md) - List of available payment methods
- [Checkout API](api.md) - Checkout and payment API documentation
- [Order Management](orders.md) - Working with orders
- [Testing Guide](testing.md) - Payment testing best practices
