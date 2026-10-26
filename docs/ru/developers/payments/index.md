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
     * @return bool
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
     * @return bool|array
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

Начиная с 1.5.0, `$data['checkout_context']` содержит доверенный контекст checkout заказа — из checkout и из платёжных ссылок, которые передают `order`. Используйте учётные данные sandbox, если его `environment` равен `test`, и рабочие учётные данные в остальных случаях; никогда не принимайте решение по текущему пользователю или по данным запроса. См. [Доверенный контекст checkout](../events.md#доверенный-контекст-checkout).

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

Когда менеджер сохраняет способ оплаты, sCommerce вызывает `prepareCredentials(array $data): string` и сохраняет возвращённый JSON. `BasePaymentMethod` уже реализует этот метод и оставляет только поля, объявленные в `defineCredentials()`. Класс, который не наследует `BasePaymentMethod`, тоже должен предоставить этот метод, хотя `PaymentMethodInterface` в 1.x его не объявляет.

Учётные данные хранятся в `s_payment_methods.credentials`. Начиная с 1.5.0, миграция `2026_10_20_000001_rename_payment_method_credentials_column` переименовывает устаревшую колонку, первая буква названия которой была кириллической U+0441. Читайте и записывайте учётные данные через модель с помощью `sPaymentMethod::CREDENTIALS`; модель сопоставляет оба написания с существующей колонкой как до, так и после миграции. Разверните обновлённый код до запуска миграции. Прямой SQL и старый код, обходящие модель, после миграции должны использовать новое название колонки. Если обе колонки уже существуют, миграция копирует значения только в пустые поля `credentials`. Если две колонки содержат разные значения, она оставляет устаревшую колонку и записывает в лог id затронутых способов оплаты, чтобы вы могли разрешить конфликт вручную.

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

Выбранные значения сохраняются списком. Завершите `name` символами `[]`:

```php
[
    'type' => 'multiselect',
    'label' => 'Order statuses',
    'name' => 'statuses[]',
    'value' => $this->getSettings()['statuses'] ?? [],
    'options' => \Seiger\sCommerce\Models\sOrder::listOrderStatuses(),
]
```

## Запись платежей (ledger API)

Каждый платёж заказа — это строка в журнале платежей (`s_order_payments`). `s_orders.payment_status` вычисляется из журнала: никогда не записывайте его напрямую. Записывайте результаты платежей через `Seiger\sCommerce\Payment\PaymentFlow`:

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

`record()` возвращает `LedgerResult` с полями `payment` (строка), `created`, `changed` и `previousStatus`. `recordCaptured()` сохраняет прежнюю сигнатуру и возвращает порядковый номер платежа.

### Статусы

| Статус | Значение |
|---|---|
| `pending` | Платёж начат, результата ещё нет |
| `authorized` | Средства зарезервированы, но не списаны |
| `captured` | Деньги получены |
| `failed`, `rejected`, `expired`, `canceled` | Деньги не получены |
| `refunded`, `partially_refunded` | Для строк `refund`: деньги возвращены. Для строки платежа: устаревшее изменение из менеджера |
| `disputed` | Покупатель оспорил платёж |

### Идемпотентность и переходы

Строки идентифицируются парой `(provider, providerRef)`. Повторный вызов с той же парой безопасен:

- тот же статус ничего не меняет и не вызывает событий;
- разрешённое изменение статуса обновляет существующую строку;
- неразрешённое изменение (например, запоздавший `failed` после `captured`) игнорируется и записывается в лог как предупреждение;
- та же пара с другим заказом, видом (kind), суммой, валютой или родительским платежом возврата выбрасывает `LedgerConflictException`;
- валюта, отличная от валюты заказа, выбрасывает `InvalidArgumentException`.

| Из \ в | authorized | captured | failed, rejected, expired, canceled | refunded, partially_refunded | disputed |
|---|---|---|---|---|---|
| pending | да | да | да | только строки возвратов | нет |
| authorized | – | да | да | нет | нет |
| captured | нет | – | нет | только форма менеджера | да |
| disputed | нет | да (спор выигран) | нет | да (спор проигран) | – |
| failed, rejected, expired, canceled | нет | да (запоздавший успех) | – | нет | нет |

Строки возвратов начинаются со статуса `pending` или `refunded` и никогда не становятся `authorized` или `captured`. `recordCanceled()` и `recordDisputed()` изменяют существующую строку; для неизвестной ссылки (reference) они выбрасывают `OutOfBoundsException`, поэтому верните провайдеру ошибку, после которой он повторит попытку.

Без `providerRef` каждый вызов вставляет новую строку. Используйте это только для ручных записей.

### Выбор `provider` и `providerRef`

- `provider` — ключ в нижнем регистре длиной до 32 символов (`a-z`, `0-9`, `_ . : -`). Используйте отдельный ключ для каждого аккаунта PSP и окружения, например `mypsp-live` и `mypsp-test`, чтобы одинаковые id в тестовом и рабочем окружениях никогда не конфликтовали.
- `providerRef` — id движения денег в PSP: id payment intent, charge или транзакции либо id возврата. Используйте его повторно для каждого обновления статуса этого платежа. Не используйте id события вебхука: один платёж получает несколько событий. При необходимости храните id событий в `metadata`.
- Для возврата передайте `providerRef` исходного платежа как `parentProviderRef`. Он должен принадлежать тому же заказу, валюте и провайдеру.

### Метаданные

`metadata` хранится как JSON-объект размером не более 64 КБ. Ключ `_ledger` зарезервирован: sCommerce хранит в нём родительский платёж возврата, контекст области событий (source, actor, correlation, causation, integration actor) и историю статусов. Существующие ключи сохраняются при изменении статуса.

Задайте в `environment` (`live` или `test`) окружение события PSP. Если ключ указан и отличается от [окружения checkout](../events.md#доверенный-контекст-checkout) заказа, `record()` выбрасывает `InvalidArgumentException`, поэтому тестовый платёж никогда не отметит рабочий заказ оплаченным.

### Статус оплаты заказа

После каждого изменения sCommerce пересчитывает `payment_status` по строкам в валюте заказа и сохраняет заказ через модель, поэтому вызывается `sCommerce.OrderPaymentStatusChanged`:

- полученные деньги учитывают строки платежей в статусах `captured`, `disputed`, `refunded` и `partially_refunded`;
- возвращённые деньги учитывают строки `refund` и устаревшие строки платежей со статусом `refunded`;
- любой оспоренный платёж делает заказ `disputed`;
- строки в другой валюте игнорируются, и об этом один раз записывается в лог.

Платёж, который менеджер отметил как частично возвращённый без записанной суммы возврата, показывает заказ как частично возвращённый, но не меняет итоги.

Когда стоимость заказа меняется после оплаты (менеджер редактирует товары, доплата, взвешенный товар), статус пересчитывается после фиксации изменения: более высокая стоимость делает оплаченный заказ *частично оплаченным*, а более низкая оставляет его *оплаченным* с переплатой, которую показывает панель **Платежи**. Редактирование товаров в менеджере сохраняет стоимость доставки и любые другие суммы, которые оформление заказа добавило сверх товаров. Форма заказа больше не принимает *Частично оплачено* как вариант статуса: вместо этого сумму записывают кнопкой **Записать платеж** (см. ниже).

Журнал вызывает `sCommerce.PaymentRecorded` и `sCommerce.PaymentStatusChanged` (см. [События](../events.md)).

## Вебхуки

### URL обратного вызова

sCommerce принимает callbacks провайдеров на встроенном маршруте:

```php
route('payment.callback', ['method' => 'mypsp']);                                  // /payment-callback/mypsp
route('payment.callback.identified', ['method' => 'mypsp', 'identifier' => 'eu']); // /payment-callback/mypsp/eu
```

Используйте URL с идентификатором, когда один и тот же класс оплаты настроен несколько раз с разными идентификаторами, например для двух аккаунтов. Такой URL попадает только в строку с этим идентификатором. Внутри метода `sCommerce::paymentCallbackUrl($this)` возвращает правильный URL, чтобы показать его в настройках или отправить провайдеру. У маршрутов нет проверки CSRF, поэтому метод должен самостоятельно аутентифицировать каждый запрос.

Отключённые методы всё равно получают callbacks, потому что провайдеры продолжают присылать уведомления о возвратах и спорах по прошлым платежам.

### Реализация `WebhookPaymentMethodInterface`

Реализуйте `Seiger\sCommerce\Interfaces\WebhookPaymentMethodInterface`:

- `matchesWebhook(Request $request): bool` используется, когда URL не указывает метод. Делайте его лёгким и без побочных эффектов.
- `handleWebhook(Request $request): Response` проверяет запрос, записывает результат в журнал и возвращает ответ, который ожидает провайдер.

Обработчик выполняется внутри области событий с source `payment_callback`. Этот полный пример обрабатывает провайдера, который подписывает JSON-тело с помощью HMAC-SHA256:

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

### Идемпотентность и повторные попытки

Провайдеры повторяют callbacks и могут доставлять их не по порядку. Поскольку `record()` идемпотентен по `(provider, providerRef)`, повторный callback ничего не меняет. Возвращайте ответ 2xx для дубликатов и для запросов, которые повторная попытка не исправит. Возвращайте ответ 5xx только для временных проблем; диспетчер возвращает `500 CALLBACK ERROR`, когда обработчик выбрасывает исключение.

Никогда не записывайте `s_orders.payment_status` или другие колонки заказа из вебхука: запишите платёж и позвольте sCommerce вычислить статус заказа.

### Старые обработчики

Классы, которые определяют `handleWebhook()` без параметров или методы сопоставления `canHandleWebhook()`, `matchWebhook()` или `webhookMatches()`, продолжают работать. sCommerce записывает в лог уведомление об устаревании один раз за запрос. Встроенное распознавание подписей Stripe, WayForPay и Portmone остаётся включённым, пока настройка `basic.legacy_webhook_signatures` равна `1` (значение по умолчанию в 1.x); оно будет удалено в 2.0.

### Отслеживание результатов

Используйте `sCommerce.PaymentRecorded`, `sCommerce.PaymentStatusChanged` и `sCommerce.OrderPaymentStatusChanged`, чтобы реагировать на платежи (см. [События](../events.md)).

## Операции с платежами в менеджере

Начиная с 1.5.0, на экране заказа в менеджере отображается панель **Платежи** со строками журнала заказа. Способ оплаты может добавить кнопки **Списать**, **Вернуть** и **Отменить платеж**, реализовав необязательные интерфейсы. Реализуйте только то, что поддерживает провайдер:

| Интерфейс | Метод | Кнопка показывается для |
|---|---|---|
| `CapturablePaymentMethodInterface` | `capture(sOrder, sOrderPayment, ?float $amount, string $idempotencyKey)` | строк `authorized`; `$amount` равен `null` для полной авторизации |
| `RefundablePaymentMethodInterface` | `refund(sOrder, sOrderPayment, float $amount, string $reason, string $idempotencyKey)` | строк `captured`, по которым осталась сумма для возврата |
| `CancelablePaymentMethodInterface` | `cancel(sOrder, sOrderPayment, string $reason, string $idempotencyKey)` | строк `pending` и `authorized` |

Интерфейсы находятся в `Seiger\sCommerce\Interfaces` и расширяют `PaymentMethodInterface`. Каждый метод возвращает `Seiger\sCommerce\Payment\PaymentOperationResult`:

```php
new PaymentOperationResult(
    outcome: 'succeeded',      // succeeded | pending | failed
    providerRef: 're_789',     // provider id of the refund or capture
    message: '',               // safe to show to a manager
    metadata: [],              // stored with the ledger row
);
```

Кнопка появляется, только если метод реализует интерфейс, строка подходит для действия, а у менеджера есть `exec_module` и разрешение `scommerce_payment_capture`, `scommerce_payment_refund` или `scommerce_payment_cancel`. Миграция создаёт эти разрешения и выдаёт их роли администратора. Строки методов без этих интерфейсов показываются только для чтения.

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

Как sCommerce выполняет операцию:

1. Проверяет сессию менеджера, CSRF-токен, разрешения и одноразовый токен формы (nonce), привязанный к заказу, платежу и действию.
2. Под блокировкой заказа проверяет строку и резервирует сумму: возврат не может превышать списанную сумму за вычетом возвратов, которые записаны, ожидают подтверждения или ещё выполняются. Сохраняет операцию в `s_payment_operations` с ключом `{action}/{orderId}/{paymentId}/{nonce}`, который также передаётся как `$idempotencyKey`. Во время вызова провайдера блокировка базы данных не удерживается.
3. Записывает результат через `PaymentFlow`:
   - возврат `succeeded`: строка `refund` со статусом `refunded`, `providerRef` = возвращённый id возврата и `parentProviderRef` = исходный платёж. Успешный возврат обязан вернуть свой id у провайдера;
   - возврат `pending`: строка `refund` со статусом `pending`; позже ваш вебхук записывает `refunded` (или `failed`) с тем же `providerRef`. Без `providerRef` сумма остаётся зарезервированной в операции;
   - списание `succeeded`: та же строка становится `captured`. После частичного списания её сумма становится списанной суммой, а авторизованная сумма сохраняется в `metadata._ledger.authorized_amount`;
   - отмена `succeeded`: строка становится `canceled`;
   - списание или отмена со статусом `pending` и любой результат `failed` не меняют строки журнала.
4. Повторная отправка той же формы возвращает сохранённую операцию и не вызывает провайдера. Тот же nonce с другой суммой или причиной отклоняется как конфликт.

Если вызов провайдера выбрасывает исключение (например, тайм-аут после отправки запроса), операция получает статус `unknown`. sCommerce никогда не повторяет её вслепую и сохраняет резерв суммы. Реализуйте `PaymentOperationLookupInterface::findPaymentOperation(PaymentOperationRequest $request)`, чтобы sCommerce мог запросить провайдера по ключу идемпотентности: верните результат или `null`, если провайдер точно не получил запрос (тогда он отправляется повторно с тем же ключом). Скрытый обработчик sTask **Сверка платежей** (каждые 15 минут) завершает ожидающие операции по журналу и запрашивает неизвестные.

Метод, у которого уже есть собственное надёжное хранилище операций (например, пакет финансовой интеграции), вместо этого реализует `DelegatedPaymentOperationsInterface`. Тогда sCommerce проверяет защиту менеджера и передаёт `PaymentOperationRequest` в `runPaymentOperation()`, не создавая вторую операцию. Реализация должна сама устранять дубликаты по `idempotencyKey`, резервировать сумму, восстанавливать неопределённые результаты и записывать подтверждённые денежные факты через `PaymentFlow`. `supportsPaymentOperation($action)` определяет, какие кнопки показывать.

### Частичное списание (весовые товары)

Для товаров, окончательная цена которых известна только после взвешивания, авторизуйте ориентировочную сумму при оформлении заказа и спишите фактическую. Если `supportsPartialCapture()` возвращает `true`, у кнопки **Списать** появляется поле суммы, заранее заполненное авторизованной суммой, ограниченной текущим остатком к оплате. Сумма должна быть положительной и не больше авторизованной; затем `capture()` получает её как `$amount`, а провайдер освобождает остаток авторизации. Методы, которые возвращают `false`, списывают только полную авторизованную сумму. Если стоимость после взвешивания выше авторизованной суммы, спишите авторизованную сумму, а остаток получите по ссылке на оплату (см. [Графики платежей](#графики-платежей-предоплата-и-финальный-платёж)).

Списание, о котором провайдер сначала сообщил вебхуком, записывается так же: `PaymentFlow::record()` со статусом `captured`, `providerRef` авторизации и меньшей суммой превращает строку `authorized` в частичное списание. Пришедший позже повтор исходной авторизации игнорируется.

### Запись офлайн-платежей

Наличные и банковские переводы не проходят через провайдера, поэтому их записывает менеджер. Для заказа, оплаченного офлайн-способом (реализующим `OfflineSettlementPaymentMethodInterface`, например **Наличные** и **Оплата на счет**), панель показывает:

- **Записать платеж**: полученная сумма (не больше остатка к оплате) и необязательный номер квитанции или примечание. Записывается как строка `captured` с `provider` = имя способа; это `deposit`, пока сумма укладывается в открытую часть предоплаты, иначе `final`;
- **Записать возврат**: деньги, возвращённые клиенту, например после более лёгкого товара или снижения стоимости (не больше суммы, полученной этим способом). Записывается как строка `refund` со статусом `refunded`.

Обоим действиям нужно разрешение `scommerce_payment_record`. Идентификатор записи в журнале формируется из nonce формы, поэтому повторная отправка той же формы ничего нового не записывает, а та же форма с другой суммой отклоняется. Затем офлайн-зачисление записывает только то, что ещё не оплачено.

`sCommerce::supportsIntegration('payment-capabilities')` сообщает о поддержке.

## Графики платежей (предоплата и финальный платёж)

По умолчанию вся стоимость заказа списывается сразу. Слушатель `sCommerce.ResolvePaymentSchedule` может разделить её на части при оформлении заказа (checkout и быстрый заказ) до первого сохранения заказа:

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

| Ключ | Значения |
|---|---|
| `kind` | `deposit` или `final`; виды могут повторяться |
| `amount` | положительное число; сумма всех частей должна равняться стоимости заказа (допуск 0.01) |
| `due` | `checkout`: оплачивается при оформлении заказа; `manual`: оплачивается по ссылке на оплату в любое время; `on_status:<int>`: оплачивается, когда заказ достиг этого статуса |
| `label` | необязательный текст для менеджеров и писем |

sCommerce проверяет график и сохраняет его в `payment_info.schedule` со стабильными id частей. Некорректный график прерывает оформление до сохранения заказа или списания денег; sCommerce никогда не переходит к списанию полной стоимости вместо него.

Сумма к оплате всегда вычисляется на сервере и передаётся в `processPayment()` и `payButton()` как `payment_request`:

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

- При оформлении заказа запрашивается только часть со сроком `checkout`. Если первая открытая часть подлежит оплате позже, `processPayment()` не вызывается.
- `sCheckout::payOrder()` отбрасывает любой `payment_request`, отправленный клиентом. Если запрос содержит `order` (идентификатор заказа) и у заказа есть график, метод подставляет следующую часть к оплате или отвечает, что платить ничего не нужно. Поэтому ссылки на оплату должны передавать `order`.
- `sCommerce::payButton()` передаёт заказ с `payment_request`, когда вызывается с id или идентификатором заказа, и ничего не выводит, если нет части к оплате.
- Полученные, авторизованные и ожидающие платежи распределяются по частям по порядку. Для частично оплаченной части запрашивается только остаток. Возвраты не открывают часть заново: для повторного сбора нужен новый явный запрос.
- Последняя часть поглощает изменения стоимости после оформления заказа: она равна текущей стоимости заказа за вычетом предыдущих частей. Доплата увеличивает её, более низкая стоимость после взвешивания уменьшает, а предыдущие части остаются такими, как согласовано. Панель показывает скорректированную сумму.
- Для заказа без графика, оплачиваемого способом с `InstallmentPaymentMethodInterface`, `payOrder()` и `payButton()` тоже передают `payment_request`: один платёж `final` на неоплаченный остаток (`scheduleItem` `1`, оплата по ссылке на оплату). Поэтому с частично оплаченного заказа никогда повторно не списывается полная стоимость, а для оплаченного заказа или открытой авторизации возвращается ответ, что платить ничего не нужно. Способы без этого маркера работают как раньше.

Способы оплаты должны подтвердить, что они списывают `payment_request.amount`, реализовав маркерный интерфейс `InstallmentPaymentMethodInterface`, и записывать каждый платёж с запрошенным `kind`. Оформление заказа и ссылки на оплату отклоняют заказ с графиком для других способов. Офлайн-способы (наличные, банковский счёт) принимают графики.

`PaymentFlow::summary($order)` возвращает итоги, `outstandingMinor()` (остаток к оплате), `overpaidMinor()` и `nextInstallment`; `PaymentSchedule::nextPayment($order, $summary)` возвращает следующий платёж к запросу как с графиком, так и без него. `sCommerce::supportsIntegration('payment-schedule')` сообщает о поддержке.

## Офлайн-зачисление

Оплату наличными при получении и банковские переводы можно автоматически записывать как оплаченные, когда заказ достигает определённого статуса. В настройках способа **Наличные** или **Оплата на счет** выберите статусы в поле **Отмечать оплаченным при статусе заказа**, например *Доставлено*. По умолчанию поле пустое (функция выключена).

Когда заказ с этим способом оплаты достигает выбранного статуса, sCommerce записывает неоплаченный остаток одним списанием `final`:

- `provider` — имя способа (`cash`, `bank_invoice`), а `providerRef` — `settle/{orderId}/{status}`, поэтому повторное получение статуса никогда не записывает второй платёж;
- `metadata` содержит `source: offline_settlement` и `trigger_status`; события передают source `offline_settlement`;
- записывается только остаток к оплате, поэтому предоплата, внесённая онлайн, учитывается;
- ничего не записывается, если деньги возвращались, есть незавершённая платёжная операция или заказ полностью оплачен;
- частичные платежи, записанные кнопкой **Записать платеж**, учитываются так же.

Тестовые заказы никогда не зачисляются. Зачисление выполняется после фиксации изменения статуса, один раз на бизнес-операцию. Обработчик **Сверка платежей** повторяет пропущенные зачисления, например после ошибки или после статуса, записанного прямым запросом к базе данных. Включайте настройку, только если эти же платежи не записывает другая система (импорт поступлений из ERP или финансовая интеграция).

Сторонние офлайн-способы подключаются, реализуя маркерный интерфейс `OfflineSettlementPaymentMethodInterface` и настройку `settle_on_statuses`; трейт `OfflineSettlementSettings` предоставляет поле настройки. `sCommerce::supportsIntegration('offline-settlement')` сообщает о поддержке.

## Залог за возвратную тару

Начиная с 1.5.0, у товара может быть залог за возвратную тару, например за бутыль для воды. Залог — не скидка и не часть цены товара: он начисляется сверх цены и находится вне НДС. Когда покупатель сдаёт пустую тару в обмен, на обмененные единицы новый залог не начисляется; цена товара не меняется.

| Операция | Сумма |
|---|---|
| Вода 18.9 л, с НДС | 120 |
| Залог за бутыль | 250 |
| К оплате при первой покупке | 370 |
| К оплате при следующей покупке с обменом бутыли | 120 |
| Возврат денег, когда бутыль возвращают насовсем | 250 |

### Настройка

- Включите настройку **Залог за возвратную тару** в **Настройки → Товар** (`product.container_deposit_on`, по умолчанию выключено). Пока она выключена, у товаров нет поля залога, вкладка **Тара** и условия возвратной тары скрыты, залог не начисляется, а `scommerce/container-exchange` отвечает `404`. Возвраты залогов, уже начисленных в заказах, по-прежнему можно оформить из заказа.
- Заполните поле **Залог за тару** в товаре (`s_products.deposit`, в валюте товара). Слушатель `sCommerce.ResolveProductDeposit` может вернуть другую сумму; см. [События](../events.md#scommerceresolveproductdeposit).
- Заполните поле **Условия возвратной тары** в **Настройки → Товар**: формулировку договора, которая определяет тару как возвратную, например пункт вашей публичной оферты. В Украине эта формулировка подтверждает исключение залога из базы налогообложения НДС согласно п. 189.2 НКУ. Она копируется в каждую строку с залогом и в каждый возврат тары, поэтому последующие изменения настройки не меняют существующие записи.

### Оформление заказа и обмен

Покупатель указывает, сколько пустой тары он сдаёт. Отправьте `productId`, `optionId` и `quantity` POST-запросом на `scommerce/container-exchange` (маршрут `sCommerce.containerExchange`) или вызовите `sCart::setContainerExchange($productId, $optionId, $count)`; быстрые заказы принимают `exchange`. Количество ограничивается количеством в строке. sCommerce не ведёт остаток тары по покупателю: если курьер обнаружит другое количество, менеджер меняет **Тара в обмен** в строке заказа, и залог и стоимость заказа пересчитываются.

Строки корзины и строки заказа с залогом хранят:

| Ключ | Значение |
|---|---|
| `deposit_unit` | залог за единицу |
| `exchange_quantity` | единицы, обменянные на пустую тару |
| `deposit_quantity` | единицы с новым залогом |
| `deposit_amount` | `deposit_unit × deposit_quantity` |
| `deposit_returnable` | всегда `true`: тара возвратная |
| `deposit_terms` | условия возвратной тары на момент заказа |
| `deposit_returned_quantity` | тара из этой строки, возвращённая позже |

В мини-корзине есть `totalGoods`, `totalDeposit` и `totalSum` (товары плюс залоги — то, что платит покупатель). `cost` заказа включает залоги. Страница заказа в менеджере и печатный документ показывают их как **В том числе залог за тару**, а печатный документ добавляет условия возвратной тары.

### Возврат тары

Каждый возврат — это строка в `s_container_returns` (`sContainerReturn`): товар, количество, залог за единицу, сумма, валюта, способ расчёта, затронутые заказы, покупатель, менеджер и условия возвратной тары. Повторно отправленная форма записывается один раз.

- **Из заказа:** панель **Платежи** заказа предлагает **Вернуть тару** для строк, залог по которым оплачен. Залог и стоимость заказа уменьшаются, а деньги уходят из его журнала строкой `refund` с провайдером `container_deposit`, поэтому заказ остаётся оплаченным за товары.
- **Без заказа:** вкладка **Тара** модуля записывает возврат для покупателя (имя, телефон или ID учётной записи) и товара с залогом или произвольной суммы залога.
- **Возврат наличными:** деньги выплачиваются. Для возврата без заказа строка `s_container_returns` — единственная запись; журналу платежей заказа нужен заказ.
- **Зачёт в оплату заказа:** укажите ID заказа. Сумма записывается там как платёж `manual` с провайдером `container_deposit`, в пределах остатка к оплате заказа и в его валюте. Возврат из другого заказа перемещает деньги: `refund` в исходном заказе и платёж в целевом.

Для возвратов нужно разрешение `scommerce_payment_record`. `sCommerce::supportsIntegration('container-deposit')` сообщает о поддержке.

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
