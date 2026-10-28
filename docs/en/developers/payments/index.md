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
use Seiger\sCommerce\Payment\PaymentFlow;
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
        $amount = $order->cost ?? 0;
        $currency = $order->currency ?? '';

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
            // processOrder() passes the saved order as an array.
            $order = sOrder::find($data['id'] ?? $data['order_id'] ?? 0);
            
            if (!$order) {
                return [
                    'success' => false,
                    'message' => 'Order not found',
                ];
            }

            // Create Payment Intent
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $this->convertToCents((float) $order->cost),
                'currency' => strtolower($order->currency),
                'payment_method' => $data['payment_method_id'] ?? '',
                'confirm' => true,
                'metadata' => [
                    'order_key' => $order->identifier,
                    'customer_email' => $order->user_info['email'] ?? '',
                ],
                'statement_descriptor' => $this->getSettings()['statement_descriptor'] ?? null,
            ]);

            // Check payment status
            if ($paymentIntent->status === 'succeeded') {
                // Record the capture in the payment ledger; payment_status is derived from it.
                (new PaymentFlow())->recordCaptured(
                    $order,
                    'final',
                    $paymentIntent->amount_received / 100,
                    strtoupper($paymentIntent->currency),
                    'stripe-' . ($paymentIntent->livemode ? 'live' : 'test'),
                    $paymentIntent->id
                );

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

Since 1.5.0, `$data['checkout_context']` carries the order's trusted checkout context, from checkout and from pay links that send `order`. Use sandbox credentials when its `environment` is `test` and live credentials otherwise; never decide from the current user or from request data. See [Trusted checkout context](../events.md#trusted-checkout-context).

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

When the manager saves the method, sCommerce calls `prepareCredentials(array $data): string` and stores the returned JSON. `BasePaymentMethod` already implements it and keeps only the fields declared in `defineCredentials()`. A class that does not extend `BasePaymentMethod` must provide this method too, although `PaymentMethodInterface` does not declare it in 1.x.

Credentials are stored in `s_payment_methods.credentials`. Since 1.5.0 the migration `2026_10_20_000001_rename_payment_method_credentials_column` renames the legacy column whose first letter was Cyrillic U+0441. Read and write credentials through the model with `sPaymentMethod::CREDENTIALS`; the model maps both spellings to the existing column before and after the migration. Deploy the updated code before running the migration. Direct SQL and old code that bypass the model must use the new column name after migration. If both columns already exist, the migration copies values only into empty `credentials` fields. When the two columns hold different values, it keeps the legacy column and logs the affected payment method ids so that you can resolve them manually.

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

### Multiselect Field

The selected values are stored as a list. End the `name` with `[]`:

```php
[
    'type' => 'multiselect',
    'label' => 'Order statuses',
    'name' => 'statuses[]',
    'value' => $this->getSettings()['statuses'] ?? [],
    'options' => \Seiger\sCommerce\Models\sOrder::listOrderStatuses(),
]
```

## Recording payments (ledger API)

Every payment of an order is a row in the payments ledger (`s_order_payments`). `s_orders.payment_status` is derived from the ledger: never write it directly. Record payment results with `Seiger\sCommerce\Payment\PaymentFlow`:

```php
use Seiger\sCommerce\Payment\LedgerEntry;
use Seiger\sCommerce\Payment\PaymentFlow;

$flow = new PaymentFlow();

// Generic form: one call per money movement and status update.
$result = $flow->record($order, new LedgerEntry(
    kind: 'final',               // deposit | final | manual | refund
    status: 'captured',          // see the status table below
    amount: 49.90,               // positive; for refunds the amount returned
    currency: 'EUR',             // must equal $order->currency
    provider: 'mypsp-live',      // namespace of the PSP account and environment
    providerRef: 'pay_123',      // PSP id of this payment, refund or charge
    metadata: ['event_id' => 'evt_456'],
));

// Shortcuts.
$flow->recordAuthorized($order, 'final', 49.90, 'EUR', 'mypsp-live', 'pay_123');
$flow->recordCaptured($order, 'final', 49.90, 'EUR', 'mypsp-live', 'pay_123');
$flow->recordFailed($order, 'final', 49.90, 'EUR', 'mypsp-live', 'pay_123');
$flow->recordCanceled($order, 'mypsp-live', 'pay_123');
$flow->recordRefunded($order, 10.00, 'EUR', 'mypsp-live', 're_789', 'pay_123');
$flow->recordDisputed($order, 'mypsp-live', 'pay_123');

$summary = $flow->summary($order); // totals in minor units, projected payment status
```

`record()` returns a `LedgerResult` with `payment` (the row), `created`, `changed` and `previousStatus`. `recordCaptured()` keeps its old signature and returns the payment sequence number.

### Statuses

| Status | Meaning |
|---|---|
| `pending` | Payment started, no result yet |
| `authorized` | Funds reserved, not captured |
| `captured` | Money received |
| `failed`, `rejected`, `expired`, `canceled` | No money received |
| `refunded`, `partially_refunded` | For `refund` rows: money returned. On a payment row: a legacy manager change |
| `disputed` | The customer disputed the payment |

### Idempotency and transitions

Rows are identified by `(provider, providerRef)`. Repeating a call with the same pair is safe:

- the same status changes nothing and dispatches no event;
- an allowed status change updates the existing row;
- a change that is not allowed (for example a late `failed` after `captured`) is ignored and logged as a warning;
- the same pair with another order, kind, amount, currency or refund parent throws `LedgerConflictException`;
- a currency other than the order currency throws `InvalidArgumentException`.

| From \ to | authorized | captured | failed, rejected, expired, canceled | refunded, partially_refunded | disputed |
|---|---|---|---|---|---|
| pending | yes | yes | yes | refund rows only | no |
| authorized | – | yes | yes | no | no |
| captured | no | – | no | manager form only | yes |
| disputed | no | yes (dispute won) | no | yes (dispute lost) | – |
| failed, rejected, expired, canceled | no | yes (late success) | – | no | no |

Refund rows start as `pending` or `refunded` and never become `authorized` or `captured`. `recordCanceled()` and `recordDisputed()` change an existing row; for an unknown reference they throw `OutOfBoundsException`, so return a retryable error to the provider.

Without `providerRef`, every call inserts a new row. Use that only for manual entries.

### Choosing `provider` and `providerRef`

- `provider` is a lowercase key of up to 32 characters (`a-z`, `0-9`, `_ . : -`). Use one key per PSP account and environment, for example `mypsp-live` and `mypsp-test`, so that identical ids in test and live never collide.
- `providerRef` is the PSP id of the money movement: the payment intent, charge or transaction id, or the refund id. Reuse it for every status update of that payment. Do not use the webhook event id: one payment receives several events. Keep event ids in `metadata` if you need them.
- For a refund, pass the original payment's `providerRef` as `parentProviderRef`. It must belong to the same order, currency and provider.

### Metadata

`metadata` is stored as a JSON object of at most 64 KB. The `_ledger` key is reserved: sCommerce stores the refund parent, the event scope context (source, actor, correlation, causation, integration actor) and the status history there. Existing keys are kept when a status changes.

Set `environment` (`live` or `test`) to the environment of the PSP event. When it is present and differs from the order's [checkout environment](../events.md#trusted-checkout-context), `record()` throws `InvalidArgumentException`, so a test payment never marks a live order paid.

### Order payment status

After each change, sCommerce recalculates `payment_status` from the rows in the order currency and saves the order through the model, so `sCommerce.OrderPaymentStatusChanged` is dispatched:

- captured money counts payment rows in `captured`, `disputed`, `refunded` and `partially_refunded`;
- returned money counts `refund` rows and legacy `refunded` payment rows;
- any disputed payment makes the order `disputed`;
- rows in another currency are ignored and logged once.

A payment that a manager marked as partially refunded, without a recorded returned amount, shows the order as partially refunded but does not change the totals.

When the order cost changes after payment (a manager edits the products, a surcharge, a weighed item), the status is recalculated once the change is committed: a higher cost makes a paid order *partially paid*, and a lower cost leaves it *paid* with an overpayment that the **Payments** panel shows. Editing the products in the manager keeps the delivery cost and any other amount checkout added on top of the products. The order form no longer accepts *Partially paid* as a status choice: the amount is recorded with **Record payment** instead (see below).

The ledger dispatches `sCommerce.PaymentRecorded` and `sCommerce.PaymentStatusChanged` (see [Events](../events.md)).

## Webhooks

### Callback URL

sCommerce receives provider callbacks at a built-in route:

```php
route('payment.callback', ['method' => 'mypsp']);                                  // /payment-callback/mypsp
route('payment.callback.identified', ['method' => 'mypsp', 'identifier' => 'eu']); // /payment-callback/mypsp/eu
```

Use the identified URL when the same payment class is configured several times with different identifiers, for example for two accounts. That URL reaches only the row with this identifier. Inside a method, `sCommerce::paymentCallbackUrl($this)` returns the right URL to show in its settings or send to the provider. The routes have no CSRF check, so the method must authenticate every request itself.

Disabled methods still receive callbacks, because providers keep sending refund and dispute notifications for earlier payments.

### Implementing `WebhookPaymentMethodInterface`

Implement `Seiger\sCommerce\Interfaces\WebhookPaymentMethodInterface`:

- `matchesWebhook(Request $request): bool` is used when the URL does not name the method. Keep it cheap and free of side effects.
- `handleWebhook(Request $request): Response` verifies the request, records the result in the ledger and returns the response the provider expects.

The handler runs inside an event scope with source `payment_callback`. This complete example handles a provider that signs the JSON body with HMAC-SHA256:

```php
<?php namespace EvolutionCMS\Main\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Interfaces\WebhookPaymentMethodInterface;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Payment\BasePaymentMethod;
use Seiger\sCommerce\Payment\LedgerConflictException;
use Seiger\sCommerce\Payment\PaymentFlow;
use Symfony\Component\HttpFoundation\Response;

class ExamplePspPayment extends BasePaymentMethod implements WebhookPaymentMethodInterface
{
    public function getName(): string
    {
        return 'examplepsp';
    }

    public function getType(): string
    {
        return '<b>Example PSP</b> (examplepsp)';
    }

    public function validatePayment(array $data): bool
    {
        return true;
    }

    public function processPayment(array $data): array|bool
    {
        // Create the payment at the provider here and pass $data['identifier'] as order_key metadata.
        return ['success' => true];
    }

    public function payButton(int|string|array $data): string
    {
        return '';
    }

    public function defineCredentials(): array
    {
        return [
            'keys' => [
                'label' => 'Keys',
                'fields' => [
                    'webhook_secret' => [
                        'type' => 'password',
                        'label' => 'Webhook secret',
                        'name' => 'webhook_secret',
                        'value' => $this->credentials['webhook_secret'] ?? '',
                    ],
                ],
            ],
        ];
    }

    public function defineSettings(): array
    {
        return [];
    }

    public function matchesWebhook(Request $request): bool
    {
        return $request->headers->has('X-Example-Signature');
    }

    public function handleWebhook(Request $request): Response
    {
        $secret = (string)($this->credentials['webhook_secret'] ?? '');
        $payload = $request->getContent();
        $signature = (string)$request->header('X-Example-Signature', '');
        if ($secret === '' || !hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
            return response('INVALID SIGNATURE', 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return response('INVALID PAYLOAD', 400);
        }

        // The order key is the random identifier sCommerce issues for payment providers.
        $order = sOrder::query()->where('identifier', (string)($event['order_key'] ?? ''))->first();
        if (!$order) {
            return response('UNKNOWN ORDER', 200); // A retry cannot help.
        }

        $flow = new PaymentFlow();
        $provider = $this->getName() . '-' . (!empty($event['livemode']) ? 'live' : 'test');
        $amount = ((int)($event['amount'] ?? 0)) / 100;
        $currency = strtoupper((string)($event['currency'] ?? ''));
        $paymentId = (string)($event['payment_id'] ?? '');
        $metadata = ['event_id' => (string)($event['id'] ?? '')];

        try {
            match ($event['type'] ?? '') {
                'payment.authorized' => $flow->recordAuthorized($order, 'final', $amount, $currency, $provider, $paymentId, $metadata),
                'payment.succeeded' => $flow->recordCaptured($order, 'final', $amount, $currency, $provider, $paymentId, $metadata),
                'payment.failed' => $flow->recordFailed($order, 'final', $amount, $currency, $provider, $paymentId, $metadata),
                'payment.canceled' => $flow->recordCanceled($order, $provider, $paymentId, $metadata),
                'refund.succeeded' => $flow->recordRefunded($order, $amount, $currency, $provider, (string)$event['refund_id'], $paymentId, $metadata),
                'dispute.created' => $flow->recordDisputed($order, $provider, $paymentId, $metadata),
                default => null,
            };
        } catch (LedgerConflictException|\InvalidArgumentException $e) {
            // Permanent: log for manual review and stop the provider from retrying.
            Log::channel('scommerce')->error('Example PSP webhook rejected', ['event_id' => $metadata['event_id'], 'error' => $e->getMessage()]);
            return response('REJECTED', 200);
        } catch (\OutOfBoundsException) {
            // The payment is not recorded yet: ask the provider to retry later.
            return response('RETRY', 503);
        }

        return response('OK', 200);
    }
}
```

### Idempotency and retries

Providers retry callbacks and may deliver them out of order. Because `record()` is idempotent by `(provider, providerRef)`, a repeated callback changes nothing. Return a 2xx response for duplicates and for requests that a retry cannot fix. Return a 5xx response only for temporary problems; the dispatcher returns `500 CALLBACK ERROR` when the handler throws.

Never write `s_orders.payment_status` or other order columns from a webhook: record the payment and let sCommerce derive the order status.

### Older handlers

Classes that define `handleWebhook()` without parameters, or the matchers `canHandleWebhook()`, `matchWebhook()` or `webhookMatches()`, keep working. sCommerce logs a deprecation notice once per request. The built-in detection of Stripe, WayForPay and Portmone signatures stays enabled while the `basic.legacy_webhook_signatures` setting is `1` (the default in 1.x); it will be removed in 2.0.

### Listening to results

Use `sCommerce.PaymentRecorded`, `sCommerce.PaymentStatusChanged` and `sCommerce.OrderPaymentStatusChanged` to react to payments (see [Events](../events.md)).

## Manager payment operations

Since 1.5.0, the manager order screen shows a **Payments** panel with the ledger rows of the order. A payment method can add **Capture**, **Refund** and **Cancel** buttons by implementing optional interfaces. Implement only what the provider supports:

| Interface | Method | Button shown for |
|---|---|---|
| `CapturablePaymentMethodInterface` | `capture(sOrder, sOrderPayment, ?float $amount, string $idempotencyKey)` | `authorized` rows; `$amount` is `null` for the full authorization |
| `RefundablePaymentMethodInterface` | `refund(sOrder, sOrderPayment, float $amount, string $reason, string $idempotencyKey)` | `captured` rows with money left to refund |
| `CancelablePaymentMethodInterface` | `cancel(sOrder, sOrderPayment, string $reason, string $idempotencyKey)` | `pending` and `authorized` rows |

The interfaces are in `Seiger\sCommerce\Interfaces` and extend `PaymentMethodInterface`. Each method returns a `Seiger\sCommerce\Payment\PaymentOperationResult`:

```php
new PaymentOperationResult(
    outcome: 'succeeded',      // succeeded | pending | failed
    providerRef: 're_789',     // provider id of the refund or capture
    message: '',               // safe to show to a manager
    metadata: [],              // stored with the ledger row
);
```

A button appears only when the method implements the interface, the row is eligible and the manager has `exec_module` plus the permission `scommerce_payment_capture`, `scommerce_payment_refund` or `scommerce_payment_cancel`. A migration creates these permissions and grants them to the administrator role. Rows of methods without the interfaces are shown read-only.

```php
use Seiger\sCommerce\Interfaces\RefundablePaymentMethodInterface;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Payment\BasePaymentMethod;
use Seiger\sCommerce\Payment\PaymentOperationResult;

class MyPspPayment extends BasePaymentMethod implements RefundablePaymentMethodInterface
{
    // getName(), processPayment() and the other methods are omitted.

    public function supportsPartialRefund(): bool
    {
        return true;
    }

    public function refund(sOrder $order, sOrderPayment $payment, float $amount, string $reason, string $idempotencyKey): PaymentOperationResult
    {
        $response = $this->client()->refunds->create([
            'payment' => $payment->provider_ref,
            'amount' => (int)round($amount * 100),
            'reason' => $reason,
        ], ['idempotency_key' => $idempotencyKey]); // Same key, same refund at the provider.

        return match ($response->status) {
            'succeeded' => new PaymentOperationResult('succeeded', $response->id),
            'pending' => new PaymentOperationResult('pending', $response->id),
            default => new PaymentOperationResult('failed', $response->id, 'The provider declined the refund.'),
        };
    }
}
```

How sCommerce runs an operation:

1. It checks the manager session, CSRF token, permissions and a form nonce bound to the order, payment and action.
2. Under the order lock it checks the row and reserves the amount: a refund cannot exceed the captured amount minus refunds that are recorded, pending or still running. It stores the operation in `s_payment_operations` with the key `{action}/{orderId}/{paymentId}/{nonce}`, which is also the `$idempotencyKey`. No database lock is held while the provider is called.
3. It records the result through `PaymentFlow`:
   - refund `succeeded`: a `refund` row with status `refunded`, `providerRef` = the returned refund id and `parentProviderRef` = the original payment. A succeeded refund must return its provider id;
   - refund `pending`: a `refund` row with status `pending`; your webhook later records `refunded` (or `failed`) with the same `providerRef`. Without a `providerRef` the amount stays reserved in the operation;
   - capture `succeeded`: the same row becomes `captured`. After a partial capture its amount becomes the captured amount and the authorized amount is kept in `metadata._ledger.authorized_amount`;
   - cancel `succeeded`: the row becomes `canceled`;
   - a `pending` capture or cancel and every `failed` outcome change no ledger row.
4. Sending the same form again returns the stored operation and does not call the provider. The same nonce with another amount or reason is refused as a conflict.

If the provider call throws (for example a timeout after the request was sent), the operation becomes `unknown`. sCommerce never repeats it blindly and keeps the amount reserved. Implement `PaymentOperationLookupInterface::findPaymentOperation(PaymentOperationRequest $request)` to let sCommerce ask the provider by the idempotency key: return the result, or `null` when the provider has definitely not received the request (it is then sent again with the same key). The hidden sTask worker **Payment reconciliation** (every 15 minutes) completes pending operations from the ledger and looks up unknown ones.

A method that already has its own durable operation store (for example a financial integration package) implements `DelegatedPaymentOperationsInterface` instead. sCommerce then checks the manager guards and passes a `PaymentOperationRequest` to `runPaymentOperation()` without creating a second operation. The implementation must deduplicate by `idempotencyKey`, reserve the amount, recover uncertain results and record confirmed money facts through `PaymentFlow` itself. `supportsPaymentOperation($action)` tells which buttons to show.

### Partial capture (weighed goods)

For goods whose final price is known only after weighing, authorize an estimate at checkout and capture the actual amount. When `supportsPartialCapture()` returns `true`, the **Capture** button gets an amount field, prefilled with the authorization capped at the current balance due. The amount must be positive and not above the authorization; `capture()` then receives it as `$amount`, and the provider releases the rest of the authorization. Methods that return `false` capture only the full authorization. If the weighed cost is higher than the authorization, capture the authorization and collect the rest with a payment link (see [Payment schedules](#payment-schedules-deposit-and-final-payment)).

A capture reported first by the provider's webhook is recorded the same way: `PaymentFlow::record()` with status `captured`, the authorization's `providerRef` and a lower amount turns the `authorized` row into a partial capture. A later replay of the original authorization is ignored.

### Measured goods: authorization ceiling and actual quantities

Goods sold by length or weight may be cut or weighed above the ordered amount, within the line's tolerance (see [Measured quantities](../measured-quantities.md#actual-quantities)). `sOrder::authorizationCeiling()` returns the order cost plus, for every measured line with a tolerance, the line total times the tolerance, rounded half up to cents. A method that authorizes and captures later may authorize up to this ceiling; this is opt-in, and existing methods keep their amounts. When an actual quantity changes the cost, capture the new amount, collect the difference with the final item of the payment schedule, or refund an overpayment.

### Recording offline payments

Cash and bank transfers are not moved by a provider, so the manager records them. For an order paid with an offline method (one implementing `OfflineSettlementPaymentMethodInterface`, such as **Cash** and **Bank invoice**), the panel shows:

- **Record payment**: the amount received (up to the balance due) and an optional receipt number or note. It is recorded as a `captured` row with `provider` = the method name; it is a `deposit` while it fits the open deposit installment, otherwise `final`;
- **Record return**: money given back to the customer, for example after a lighter item or a lowered cost (up to the money this method received). It is recorded as a `refund` row with status `refunded`.

Both need the permission `scommerce_payment_record`. The ledger reference is derived from the form nonce, so sending the same form again records nothing new, and the same form with another amount is refused. Offline settlement then records only what is still unpaid.

`sCommerce::supportsIntegration('payment-capabilities')` reports support.

## Payment schedules (deposit and final payment)

By default the whole order cost is charged at once. A listener of `sCommerce.ResolvePaymentSchedule` can split it into installments when the order is placed (checkout and quick order), before the order is first saved:

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolvePaymentSchedule', function (array $payload) {
    $order = $payload['order']; // unsaved sOrder
    if (!str_starts_with((string)($order->products[0]['sku'] ?? ''), 'SRV-')) {
        return;
    }

    $deposit = round($order->cost * 0.3, 2);
    $payload['schedule'] = [
        ['kind' => 'deposit', 'amount' => $deposit, 'due' => 'checkout', 'label' => 'Booking deposit'],
        ['kind' => 'final', 'amount' => round($order->cost - $deposit, 2), 'due' => 'on_status:8'],
    ];
});
```

| Key | Values |
|---|---|
| `kind` | `deposit` or `final`; repeated kinds are allowed |
| `amount` | positive; all amounts must add up to the order cost (0.01 tolerance) |
| `due` | `checkout`: charged when the order is placed; `manual`: payable by payment link at any time; `on_status:<int>`: payable once the order has reached that status |
| `label` | optional text for managers and e-mails |

sCommerce validates the schedule and stores it in `payment_info.schedule` with stable item ids. An invalid schedule fails the checkout before the order is saved or charged; it never falls back to charging the full cost.

The amount to charge is always computed on the server and passed to `processPayment()` and `payButton()` as `payment_request`:

```php
[
    'kind' => 'deposit',
    'amount' => 30.0,
    'currency' => 'EUR',
    'orderIdentifier' => 'kq3...',   // s_orders.identifier
    'scheduleItem' => '1',
    'key' => 'installment/15/1/3000', // stable per order, item and amount
]
```

- At checkout, only an installment due `checkout` is requested. When the first open installment is due later, `processPayment()` is not called.
- `sCheckout::payOrder()` discards any `payment_request` sent by the client. When the request contains `order` (the order identifier) and the order has a schedule, it sets the next due installment, or answers that nothing is due. Pay links must therefore send `order`.
- `sCommerce::payButton()` passes the order with `payment_request` when it is called with an order id or identifier, and renders nothing when no installment is due.
- Received, authorized and pending payments are allocated to the installments in order. A partially paid installment requests only its rest. Refunds do not reopen an installment: collecting again needs a new explicit request.
- The last installment absorbs cost changes made after checkout: it is the current order cost minus the earlier installments. A surcharge raises it, a lower weighed cost lowers it, and earlier installments stay as agreed. The panel shows the adjusted amount.
- For an order without a schedule paid with an `InstallmentPaymentMethodInterface` method, `payOrder()` and `payButton()` also pass `payment_request`: one `final` payment of the unpaid rest (`scheduleItem` `1`, due by payment link). A partially paid order is therefore never charged its full cost again, and a paid order or an open authorization answers that nothing is due. Methods without the marker behave as before.

Payment methods must declare that they charge `payment_request.amount` by implementing the marker `InstallmentPaymentMethodInterface`, and record each payment with the requested `kind`. Checkout and pay links refuse a scheduled order for other methods. Offline methods (cash, bank invoice) accept schedules.

`PaymentFlow::summary($order)` returns the totals, `outstandingMinor()` (the balance due), `overpaidMinor()` and `nextInstallment`; `PaymentSchedule::nextPayment($order, $summary)` returns the next payment to request with or without a schedule. `sCommerce::supportsIntegration('payment-schedule')` reports support.

## Offline settlement

Cash on delivery and bank transfers can be recorded as paid automatically when the order reaches a status. In the settings of the **Cash** or **Bank invoice** method, select statuses in **Mark as paid on order status**, for example *Delivered*. The setting is empty (off) by default.

When an order with that payment method reaches a selected status, sCommerce records the unpaid balance as one `final` capture:

- `provider` is the method name (`cash`, `bank_invoice`) and `providerRef` is `settle/{orderId}/{status}`, so repeating the status never records a second payment;
- `metadata` contains `source: offline_settlement` and `trigger_status`; the events carry the source `offline_settlement`;
- only the balance due is recorded, so a deposit paid online is respected;
- nothing is recorded when money was refunded, a payment operation is unfinished, or the order is fully paid;
- partial payments recorded with **Record payment** are respected the same way.

Test orders are never settled. Settlement runs after the status change is committed, once per business operation. The **Payment reconciliation** worker retries settlements that were missed, for example after an error or a status written with a direct database query. Enable the setting only when no other system (an ERP receipt import or a financial integration) records the same payments.

Third-party offline methods opt in by implementing the marker `OfflineSettlementPaymentMethodInterface` and a `settle_on_statuses` setting; the `OfflineSettlementSettings` trait provides the settings field. `sCommerce::supportsIntegration('offline-settlement')` reports support.

## Returnable container deposits

Since 1.5.0, a product can carry a returnable container deposit, for example a water bottle. The deposit is not a discount and not part of the goods price: it is charged on top of the price and is outside VAT. When the customer hands over empty containers in exchange, the exchanged units carry no new deposit; the price of the goods does not change.

| Operation | Amount |
|---|---|
| Water 18.9 l, VAT included | 120 |
| Bottle deposit | 250 |
| To pay on the first purchase | 370 |
| To pay on the next purchase with a bottle exchange | 120 |
| Refund when the bottle is returned for good | 250 |

### Setting up

- Switch on **Returnable container deposits** in **Settings → Product** (`product.container_deposit_on`, off by default). While it is off, products have no deposit field, the **Containers** tab and the returnable container terms are hidden, no deposit is charged and `scommerce/container-exchange` answers `404`. Returns of deposits already on orders stay possible from the order.
- Enter **Container deposit** on the product (`s_products.deposit`, in the product currency). A `sCommerce.ResolveProductDeposit` listener can return another amount; see [Events](../events.md#scommerceresolveproductdeposit).
- Enter **Returnable container terms** in **Settings → Product**: the contract wording that defines the containers as returnable, for example the clause of your public offer. In Ukraine this wording supports excluding the deposit from the VAT base under art. 189.2 of the Tax Code. It is copied to every deposit line and every container return, so later changes of the setting do not alter existing records.

### Checkout and exchange

The customer declares how many empty containers they hand over. Such a declaration waives the deposit before anyone has seen the containers, so it needs **Customers declare exchanged containers** (`product.container_exchange_on`, off by default); otherwise the route answers `404`, `setContainerExchange()` fails and declared exchanges are ignored. Post `productId`, `optionId` and `quantity` to `scommerce/container-exchange` (route `sCommerce.containerExchange`) or call `sCart::setContainerExchange($productId, $optionId, $count)`; quick orders accept `exchange`. The count is capped at the line quantity. sCommerce keeps no balance of containers per customer: when the courier finds a different number, the manager changes **Exchanged containers** on the order line, and the deposit and the order cost are recalculated.

Cart lines and order lines with a deposit store:

| Key | Meaning |
|---|---|
| `deposit_unit` | deposit per unit |
| `exchange_quantity` | units exchanged for empty containers |
| `deposit_quantity` | units with a new deposit |
| `deposit_amount` | `deposit_unit × deposit_quantity` |
| `deposit_returnable` | always `true`: the container is returnable |
| `deposit_terms` | the returnable container terms at the time of the order |
| `deposit_returned_quantity` | containers returned from this line later |

The mini-cart has `totalGoods`, `totalDeposit` and `totalSum` (goods plus deposits, what the customer pays). The order `cost` includes the deposits. The manager order page and the print document show them as **Including container deposit**, and the print document adds the returnable container terms.

### Returning containers

Every return is a row in `s_container_returns` (`sContainerReturn`): product, quantity, deposit per unit, amount, currency, settlement, the orders involved, the customer, the manager and the returnable container terms. A resubmitted form records once.

- **From an order:** the order's **Payments** panel offers **Return containers** for lines whose deposit was paid. The order's deposit and cost are lowered, and the money leaves its ledger as a `refund` row with the provider `container_deposit`, so the order stays paid for its goods.
- **Without an order:** the **Containers** tab of the module records a return for a customer (name, phone or account id) and a product with a deposit, or any deposit amount.
- **Cash refund:** the money is paid out. For a return without an order, the `s_container_returns` row is the only record; the order payments ledger needs an order.
- **Credit toward an order:** enter the order id. The amount is recorded there as a `manual` payment with the provider `container_deposit`, up to the order's balance due and in its currency. A return from another order moves the money: a `refund` on the source order and a payment on the target order.

Returns need the `scommerce_payment_record` permission. `sCommerce::supportsIntegration('container-deposit')` reports support.

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

- [Built-in Payment Methods](methods.md) - List of available payment methods
- [Checkout API](../api.md) - Checkout and payment API documentation
- [Order Management](../../admin/index.md) - Working with orders
- [Testing Guide](index.md) - Payment testing best practices
