---
id: payments
title: Платежі
sidebar_position: 13
---

# Створення власних способів оплати

Цей посібник пояснює, як створити власні інтеграції способів оплати в sCommerce. Ви дізнаєтесь, як розширити базову функціональність платежів для інтеграції з будь-яким платіжним шлюзом або реалізації власної логіки оплати.

## Огляд

sCommerce надає гнучку архітектуру платежів, яка дозволяє:

- Інтегруватись з зовнішніми платіжними шлюзами (Stripe, PayPal, LiqPay тощо)
- Реалізовувати власну логіку оплати
- Налаштовувати облікові дані та параметри через панель адміністратора
- Підтримувати кілька режимів роботи (тест/виробництво)
- Обробляти валідацію та обробку платежів
- Відображати власні кнопки та форми оплати

## Архітектура способів оплати

### Основні компоненти

1. **`PaymentMethodInterface`** - Визначає обов'язкові методи для всіх платіжних інтеграцій
2. **`BasePaymentMethod`** - Абстрактний базовий клас, що надає загальну функціональність
3. **`sPaymentMethod`** - Модель бази даних для зберігання конфігурації платежів
4. **`sCheckout`** - Сервіс для реєстрації та використання способів оплати

### Життєвий цикл способу оплати

```
Реєстрація → Налаштування → Валідація → Обробка → Завершення
```

## Створення способу оплати

### Крок 1: Створення класу способу оплати

Всі способи оплати повинні розширювати `BasePaymentMethod` та реалізовувати `PaymentMethodInterface`.

**Приклад: Проста оплата готівкою**

```php
<?php namespace Seiger\sCommerce\Payment;

use Seiger\sCommerce\Payment\BasePaymentMethod;

/**
 * Спосіб оплати готівкою
 * 
 * Простий спосіб оплати для платежів готівкою (наприклад, накладений платіж)
 */
class CashPayment extends BasePaymentMethod
{
    /**
     * Отримати унікальну назву способу оплати.
     * 
     * Використовується як ідентифікатор у системі.
     * 
     * @return string
     */
    public function getName(): string
    {
        return 'cash';
    }

    /**
     * Отримати тип відображення для панелі адміністратора.
     * 
     * Показується в списку способів оплати в панелі адміністратора.
     * 
     * @return string
     */
    public function getType(): string
    {
        $title = __('sCommerce::global.cash');
        $title = str_contains($title, '::') ? 'Готівка' : $title;
        return "<b>" . $title . "</b> (cash)";
    }

    /**
     * Валідувати дані платежу.
     * 
     * Повернути true, якщо дані платежу валідні.
     * 
     * @param array $data
     * @return bool
     */
    public function validatePayment(array $data): bool
    {
        return true; // Валідація не потрібна для готівки
    }

    /**
     * Визначити поля облікових даних для панелі адміністратора.
     * 
     * Повернути порожній масив, якщо облікові дані не потрібні.
     * 
     * @return array
     */
    public function defineCredentials(): array
    {
        return [];
    }

    /**
     * Визначити поля налаштувань для панелі адміністратора.
     * 
     * Ці поля будуть відображатися в конфігурації способу оплати.
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
     * Відобразити HTML кнопки оплати.
     * 
     * Повернути HTML для кнопки оплати, яка буде відображатися на фронтенді.
     * 
     * @param int|string|array $data ID замовлення, ключ замовлення або масив даних замовлення
     * @return string
     */
    public function payButton(int|string|array $data): string
    {
        return ''; // Кнопка не потрібна для оплати готівкою
    }

    /**
     * Обробити платіж.
     * 
     * Цей метод викликається під час обробки платежу.
     * Поверніть true для успіху, false для помилки або масив з додатковими даними (наприклад, redirect URL).
     * 
     * @param array $data
     * @return bool|array
     */
    public function processPayment(array $data): bool|array
    {
        return true; // Припустимо, що платіж успішний
    }
}
```

### Крок 2: Розширений спосіб оплати з інтеграцією шлюзу

**Приклад: Інтеграція платіжного шлюзу Stripe**

```php
<?php namespace YourNamespace\Payment;

use Seiger\sCommerce\Payment\BasePaymentMethod;
use Stripe\StripeClient;
use Seiger\sCommerce\Models\sOrder;

/**
 * Інтеграція платіжного шлюзу Stripe
 */
class StripePayment extends BasePaymentMethod
{
    private StripeClient $stripe;

    public function __construct(string $identifier = '')
    {
        parent::__construct($identifier);
        
        // Ініціалізувати клієнт Stripe з обліковими даними
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
     * Визначити облікові дані, які будуть зберігатися безпечно.
     * Зазвичай це API ключі та секрети.
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
     * Визначити налаштування способу оплати.
     * Це налаштовувані опції для способу оплати.
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
                        'placeholder' => 'Покупка в моєму магазині',
                    ],
                ],
            ],
        ];
    }

    /**
     * Визначити доступні режими для цього способу оплати.
     * Загальні режими - 'test' та 'production'.
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
        // Валідувати обов'язкові поля
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
        // Завантажити дані замовлення
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
        $currency = $order->currency ?? 'uah';

        // Повернути HTML з інтеграцією Stripe Elements
        return <<<HTML
<div id="stripe-payment-form-{$orderId}">
    <div id="card-element"></div>
    <div id="card-errors" role="alert"></div>
    <button id="stripe-submit-btn" type="button" class="btn btn-primary">
        Сплатити {$amount} {$currency}
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

        // Надіслати платіж на ваш сервер
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
            // Завантажити замовлення
            $order = sOrder::find($data['order_id'] ?? 0);
            
            if (!$order) {
                return [
                    'success' => false,
                    'message' => 'Замовлення не знайдено',
                ];
            }

            // Створити Payment Intent
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $this->convertToCents($order->total),
                'currency' => strtolower($order->currency ?? 'uah'),
                'payment_method' => $data['payment_method_id'] ?? '',
                'confirm' => true,
                'metadata' => [
                    'order_id' => $order->id,
                    'customer_email' => $order->customer_email ?? '',
                ],
                'statement_descriptor' => $this->getSettings()['statement_descriptor'] ?? null,
            ]);

            // Перевірити статус платежу
            if ($paymentIntent->status === 'succeeded') {
                // Оновити статус замовлення
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
                'message' => 'Платіж не вдався',
            ];

        } catch (\Exception $e) {
            \Log::error('Помилка платежу Stripe', [
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
     * Конвертувати суму в копійки (для Stripe API)
     */
    private function convertToCents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
```

### Крок 3: Реєстрація способу оплати

Способи оплати автоматично виявляються та реєструються, коли вони реалізують `PaymentMethodInterface` та зберігаються в базі даних.

**Реєстрація в Service Provider (опціонально):**

```php
<?php namespace YourNamespace\Providers;

use Illuminate\Support\ServiceProvider;
use Seiger\sCommerce\Facades\sCheckout;
use YourNamespace\Payment\StripePayment;

class PaymentServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Способи оплати автоматично виявляються з бази даних
        // Ручна реєстрація не потрібна в більшості випадків
        
        // Для ручної реєстрації:
        // sCheckout::registerPaymentMethod(new StripePayment());
    }
}
```

**Реєстрація в базі даних:**

Способи оплати реєструються в таблиці `s_payment_methods`. sCommerce автоматично сканує класи платежів та дозволяє активувати їх через панель адміністратора.

## Довідка інтерфейсу способу оплати

### Обов'язкові методи

#### `getName(): string`

Повертає унікальний ідентифікатор способу оплати.

```php
public function getName(): string
{
    return 'stripe'; // Має бути унікальним
}
```

#### `getType(): string`

Повертає назву відображення в панелі адміністратора.

```php
public function getType(): string
{
    return "<b>Stripe</b> (stripe)";
}
```

#### `getIdentifier(): string`

Повертає комбіновану назву та ідентифікатор (автоматично обробляється `BasePaymentMethod`).

#### `validatePayment(array $data): bool`

Валідує дані платежу перед обробкою.

```php
public function validatePayment(array $data): bool
{
    return !empty($data['payment_method_id']) && !empty($data['order_id']);
}
```

#### `processPayment(array $data): array|bool`

Обробляє платіж. Поверніть `true`/`false` або масив з деталями.

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

Відображає HTML для кнопки/форми оплати.

```php
public function payButton(int|string|array $data): string
{
    return '<button>Сплатити зараз</button>';
}
```

#### `defineCredentials(): array`

Визначає поля облікових даних (API ключі, секрети) для налаштування в адмінці.

```php
public function defineCredentials(): array
{
    return [
        'api_keys' => [
            'label' => 'API Ключі',
            'fields' => [
                'api_key' => [
                    'type' => 'password',
                    'label' => 'API Ключ',
                    'name' => 'api_key',
                    'value' => $this->credentials['api_key'] ?? '',
                ],
            ],
        ],
    ];
}
```

#### `defineSettings(): array`

Визначає поля налаштувань для конфігурації в адмінці.

```php
public function defineSettings(): array
{
    return [
        'options' => [
            'label' => 'Опції',
            'fields' => [
                'auto_capture' => [
                    'type' => 'checkbox',
                    'label' => 'Автозахоплення',
                    'name' => 'auto_capture',
                    'value' => $this->getSettings()['auto_capture'] ?? 1,
                ],
            ],
        ],
    ];
}
```

#### `defineAvailableModes(): array`

Визначає доступні режими (тест/виробництво).

```php
public function defineAvailableModes(): array
{
    return [
        'test' => 'Тестовий режим',
        'production' => 'Режим виробництва',
    ];
}
```

## Типи полів для конфігурації

### Текстове поле

```php
[
    'type' => 'text',
    'label' => 'Мітка',
    'name' => 'field_name',
    'value' => $this->settings['field_name'] ?? '',
    'placeholder' => 'Введіть значення',
]
```

### Поле пароля

```php
[
    'type' => 'password',
    'label' => 'API Секрет',
    'name' => 'api_secret',
    'value' => $this->credentials['api_secret'] ?? '',
]
```

### Текстова область

```php
[
    'type' => 'textarea',
    'label' => 'Опис',
    'name' => 'description',
    'value' => $this->settings['description'] ?? '',
    'rows' => 5,
]
```

### Прапорець

```php
[
    'type' => 'checkbox',
    'label' => 'Увімкнути функцію',
    'name' => 'feature_enabled',
    'value' => $this->settings['feature_enabled'] ?? 0,
]
```

### Випадаючий список

```php
[
    'type' => 'select',
    'label' => 'Виберіть опцію',
    'name' => 'option',
    'value' => $this->settings['option'] ?? 'default',
    'options' => [
        'option1' => 'Опція 1',
        'option2' => 'Опція 2',
    ],
]
```

## Обробка вебхуків

Для платіжних шлюзів, що використовують вебхуки (Stripe, PayPal тощо), вам потрібно налаштувати кінцеві точки вебхуків.

### Створення контролера вебхуків

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
        
        // Перевірити підпис вебхука
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            return response()->json(['error' => 'Невірний підпис'], 400);
        }

        // Обробити подію
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

### Реєстрація маршруту вебхука

```php
// routes/web.php
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');
```

## Тестування способів оплати

### Модульні тести

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

## Найкращі практики

### 1. **Безпека**
- Зберігайте API ключі в credentials (зашифровані в базі даних)
- Ніколи не розкривайте секретні ключі у фронтенд-коді
- Валідуйте підписи вебхуків
- Використовуйте HTTPS для всіх запитів, пов'язаних з платежами

### 2. **Обробка помилок**
- Завжди перехоплюйте та логуйте винятки
- Надавайте зрозумілі користувачеві повідомлення про помилки
- Повертайте структуровані відповіді з помилками

```php
try {
    // Обробка платежу
} catch (\Exception $e) {
    \Log::error('Помилка платежу', [
        'error' => $e->getMessage(),
        'data' => $data,
    ]);
    
    return [
        'success' => false,
        'message' => 'Обробка платежу не вдалася. Спробуйте ще раз.',
    ];
}
```

### 3. **Логування**
- Логуйте всі спроби платежів
- Включайте ID замовлень та ID транзакцій
- Логуйте події вебхуків

### 4. **Тестування**
- Використовуйте тестовий режим для розробки
- Тестуйте з тестовими номерами карток провайдера
- Тестуйте обробку вебхуків
- Тестуйте сценарії помилок

### 5. **Режими**
- Підтримуйте тестовий та виробничий режими
- Використовуйте різні API ключі для кожного режиму
- Відображайте індикатор режиму в панелі адміністратора

## Загальні патерни

### Платежі з перенаправленням (PayPal тощо)

```php
public function payButton(int|string|array $data): string
{
    $order = $this->loadOrder($data);
    $redirectUrl = $this->createPaymentSession($order);
    
    return <<<HTML
<form action="{$redirectUrl}" method="GET">
    <button type="submit">Сплатити через PayPal</button>
</form>
HTML;
}
```

### Вбудовані платіжні форми (Stripe тощо)

```php
public function payButton(int|string|array $data): string
{
    $order = $this->loadOrder($data);
    
    return <<<HTML
<div id="payment-form">
    <!-- Елементи платіжної форми -->
    <script>
        // Ініціалізувати SDK платежу
    </script>
</div>
HTML;
}
```

### Платежі на основі зворотного виклику

```php
public function processPayment(array $data): array|bool
{
    // Ініціювати платіж
    $response = $this->gateway->createPayment($data);
    
    return [
        'success' => true,
        'requires_action' => true,
        'callback_url' => $response->callbackUrl,
    ];
}
```

## Посилання

- [Вбудовані способи оплати](payments/methods.md) - Список доступних способів оплати
- [API оформлення замовлення](api.md) - Документація API оформлення та платежів
- [Управління замовленнями](orders.md) - Робота із замовленнями
- [Посібник з тестування](testing.md) - Найкращі практики тестування платежів
