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
use Seiger\sCommerce\Payment\PaymentFlow;
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
        $amount = $order->cost ?? 0;
        $currency = $order->currency ?? '';

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
            // processOrder() передає збережене замовлення як масив.
            $order = sOrder::find($data['id'] ?? $data['order_id'] ?? 0);
            
            if (!$order) {
                return [
                    'success' => false,
                    'message' => 'Замовлення не знайдено',
                ];
            }

            // Створити Payment Intent
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

            // Перевірити статус платежу
            if ($paymentIntent->status === 'succeeded') {
                // Записуємо списання в журнал платежів; payment_status обчислюється з нього.
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

Коли менеджер зберігає спосіб оплати, sCommerce викликає `prepareCredentials(array $data): string` і зберігає повернений JSON. `BasePaymentMethod` уже реалізує цей метод і залишає лише поля, оголошені в `defineCredentials()`. Клас, який не наслідує `BasePaymentMethod`, також має надати цей метод, хоча `PaymentMethodInterface` у 1.x його не оголошує.

Облікові дані зберігаються в `s_payment_methods.credentials`. Починаючи з 1.5.0, міграція `2026_10_20_000001_rename_payment_method_credentials_column` перейменовує застарілу колонку, перша літера назви якої була кириличною U+0441. Читайте й записуйте облікові дані через модель за допомогою `sPaymentMethod::CREDENTIALS`; модель зіставляє обидва написання з наявною колонкою як до, так і після міграції. Розгорніть оновлений код до запуску міграції. Прямий SQL і старий код, що обходять модель, після міграції мають використовувати нову назву колонки. Якщо обидві колонки вже існують, міграція копіює значення лише в порожні поля `credentials`. Якщо дві колонки містять різні значення, вона залишає застарілу колонку та записує в лог id відповідних способів оплати, щоб ви могли розв’язати конфлікт вручну.

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

## Запис платежів (ledger API)

Кожен платіж замовлення — це рядок у журналі платежів (`s_order_payments`). `s_orders.payment_status` обчислюється з журналу: ніколи не записуйте його напряму. Записуйте результати платежів через `Seiger\sCommerce\Payment\PaymentFlow`:

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

`record()` повертає `LedgerResult` з полями `payment` (рядок), `created`, `changed` і `previousStatus`. `recordCaptured()` зберігає стару сигнатуру й повертає порядковий номер платежу.

### Статуси

| Статус | Значення |
|---|---|
| `pending` | Платіж розпочато, результату ще немає |
| `authorized` | Кошти зарезервовано, але не списано |
| `captured` | Кошти отримано |
| `failed`, `rejected`, `expired`, `canceled` | Кошти не отримано |
| `refunded`, `partially_refunded` | Для рядків `refund`: кошти повернено. Для рядка платежу: застаріла зміна з менеджера |
| `disputed` | Покупець оскаржив платіж |

### Ідемпотентність і переходи

Рядки ідентифікуються парою `(provider, providerRef)`. Повторний виклик із тією самою парою безпечний:

- той самий статус нічого не змінює й не викликає подій;
- дозволена зміна статусу оновлює наявний рядок;
- недозволена зміна (наприклад, запізнілий `failed` після `captured`) ігнорується й записується в лог як попередження;
- та сама пара з іншим замовленням, видом (kind), сумою, валютою або батьківським платежем повернення викидає `LedgerConflictException`;
- валюта, відмінна від валюти замовлення, викидає `InvalidArgumentException`.

| З \ у | authorized | captured | failed, rejected, expired, canceled | refunded, partially_refunded | disputed |
|---|---|---|---|---|---|
| pending | так | так | так | лише рядки повернень | ні |
| authorized | – | так | так | ні | ні |
| captured | ні | – | ні | лише форма менеджера | так |
| disputed | ні | так (спір виграно) | ні | так (спір програно) | – |
| failed, rejected, expired, canceled | ні | так (запізнілий успіх) | – | ні | ні |

Рядки повернень починаються зі статусу `pending` або `refunded` і ніколи не стають `authorized` чи `captured`. `recordCanceled()` і `recordDisputed()` змінюють наявний рядок; для невідомого посилання (reference) вони викидають `OutOfBoundsException`, тож поверніть провайдеру помилку, після якої він повторить спробу.

Без `providerRef` кожен виклик вставляє новий рядок. Використовуйте це лише для ручних записів.

### Вибір `provider` і `providerRef`

- `provider` — ключ у нижньому регістрі довжиною до 32 символів (`a-z`, `0-9`, `_ . : -`). Використовуйте окремий ключ для кожного облікового запису PSP і середовища, наприклад `mypsp-live` і `mypsp-test`, щоб однакові id у тестовому та робочому середовищах ніколи не конфліктували.
- `providerRef` — id руху коштів у PSP: id payment intent, charge чи транзакції або id повернення. Використовуйте його повторно для кожного оновлення статусу цього платежу. Не використовуйте id події вебхука: один платіж отримує кілька подій. За потреби зберігайте id подій у `metadata`.
- Для повернення передайте `providerRef` початкового платежу як `parentProviderRef`. Він має належати тому самому замовленню, валюті та провайдеру.

### Метадані

`metadata` зберігається як JSON-об'єкт розміром до 64 КБ. Ключ `_ledger` зарезервовано: sCommerce зберігає в ньому батьківський платіж повернення, контекст області подій (source, actor, correlation, causation, integration actor) та історію статусів. Наявні ключі зберігаються під час зміни статусу.

### Статус оплати замовлення

Після кожної зміни sCommerce перераховує `payment_status` за рядками у валюті замовлення та зберігає замовлення через модель, тому викликається `sCommerce.OrderPaymentStatusChanged`:

- отримані кошти враховують рядки платежів у статусах `captured`, `disputed`, `refunded` і `partially_refunded`;
- повернені кошти враховують рядки `refund` і застарілі рядки платежів зі статусом `refunded`;
- будь-який оскаржений платіж робить замовлення `disputed`;
- рядки в іншій валюті ігноруються, і про це один раз записується в лог.

Платіж, який менеджер позначив як частково повернений без записаної суми повернення, показує замовлення як частково повернене, але не змінює підсумки.

Журнал викликає `sCommerce.PaymentRecorded` і `sCommerce.PaymentStatusChanged` (див. [Події](../events.md)).

## Вебхуки

### URL зворотного виклику

sCommerce приймає callbacks провайдерів на вбудованому маршруті:

```php
route('payment.callback', ['method' => 'mypsp']);                                  // /payment-callback/mypsp
route('payment.callback.identified', ['method' => 'mypsp', 'identifier' => 'eu']); // /payment-callback/mypsp/eu
```

Використовуйте URL з ідентифікатором, коли той самий клас оплати налаштовано кілька разів з різними ідентифікаторами, наприклад для двох облікових записів. Такий URL потрапляє лише до рядка з цим ідентифікатором. Усередині методу `sCommerce::paymentCallbackUrl($this)` повертає правильний URL, щоб показати його в налаштуваннях або надіслати провайдеру. Маршрути не мають перевірки CSRF, тому метод має самостійно автентифікувати кожен запит.

Вимкнені методи все одно отримують callbacks, бо провайдери продовжують надсилати сповіщення про повернення та спори щодо попередніх платежів.

### Реалізація `WebhookPaymentMethodInterface`

Реалізуйте `Seiger\sCommerce\Interfaces\WebhookPaymentMethodInterface`:

- `matchesWebhook(Request $request): bool` використовується, коли URL не називає метод. Робіть його легким і без побічних ефектів.
- `handleWebhook(Request $request): Response` перевіряє запит, записує результат у журнал і повертає відповідь, яку очікує провайдер.

Обробник виконується всередині області подій із source `payment_callback`. Цей повний приклад обробляє провайдера, який підписує JSON-тіло за допомогою HMAC-SHA256:

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

### Ідемпотентність і повторні спроби

Провайдери повторюють callbacks і можуть доставляти їх не по порядку. Оскільки `record()` ідемпотентний за `(provider, providerRef)`, повторний callback нічого не змінює. Повертайте відповідь 2xx для дублікатів і для запитів, які повторна спроба не виправить. Повертайте відповідь 5xx лише для тимчасових проблем; диспетчер повертає `500 CALLBACK ERROR`, коли обробник викидає виняток.

Ніколи не записуйте `s_orders.payment_status` чи інші колонки замовлення з вебхука: запишіть платіж і дозвольте sCommerce обчислити статус замовлення.

### Старіші обробники

Класи, що визначають `handleWebhook()` без параметрів або методи зіставлення `canHandleWebhook()`, `matchWebhook()` чи `webhookMatches()`, продовжують працювати. sCommerce записує в лог повідомлення про застарілість один раз на запит. Вбудоване розпізнавання підписів Stripe, WayForPay і Portmone залишається увімкненим, поки налаштування `basic.legacy_webhook_signatures` дорівнює `1` (типове значення в 1.x); його буде вилучено у 2.0.

### Відстеження результатів

Використовуйте `sCommerce.PaymentRecorded`, `sCommerce.PaymentStatusChanged` і `sCommerce.OrderPaymentStatusChanged`, щоб реагувати на платежі (див. [Події](../events.md)).

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

- [Вбудовані способи оплати](methods.md) - Список доступних способів оплати
- [API оформлення замовлення](../api.md) - Документація API оформлення та платежів
- [Управління замовленнями](../../admin/index.md) - Робота із замовленнями
- [Посібник з тестування](index.md) - Найкращі практики тестування платежів
