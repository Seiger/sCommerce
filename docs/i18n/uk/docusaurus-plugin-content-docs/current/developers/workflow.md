---
id: workflow
title: Флоу розробки
sidebar_position: 1
---

# Флоу розробки з sCommerce

Цей детальний посібник проведе вас через повний процес створення інтернет-магазину з використанням sCommerce та Evolution CMS.

## Огляд

Флоу розробки складається з кількох ключових етапів:

1. **Планування та налаштування** - Планування проекту та налаштування середовища
2. **Дизайн та архітектура** - UI/UX дизайн та архітектура системи
3. **Backend розробка** - Реалізація основного функціоналу
4. **Frontend розробка** - Користувацький інтерфейс та шаблони
5. **Інтеграція та тестування** - Платіжні системи та тестування
6. **Розгортання та запуск** - Розгортання в продакшені та запуск
7. **Підтримка та оптимізація** - Поточне обслуговування та покращення

---

## Етап 1: Планування та налаштування

### 1.1 Вимоги проекту

**Визначте потреби вашого інтернет-магазину:**

- **Каталог товарів** - Кількість товарів, категорій, варіантів
- **Управління користувачами** - Реєстрація, профілі, групи клієнтів
- **Обробка замовлень** - Кошик, оформлення, управління замовленнями
- **Способи оплати** - Кредитні картки, банківські перекази, цифрові гаманці
- **Доставка** - Зони доставки, тарифи, відстеження
- **Податки** - Податкові ставки, регіони, розрахунки
- **Багатомовність** - Підтримувані мови та регіони
- **SEO** - Структура URL, мета-теги, карта сайту

### 1.2 Налаштування середовища

**Передумови:**
```bash
# Системні вимоги
- Evolution CMS 3.7+
- PHP 8.3+
- MySQL 8.0+ / MariaDB 10.5+ / PostgreSQL 10+ / SQLite 3.25+
- Composer 2.2+
- Node.js 18+ (для frontend інструментів збірки)
```

**Встановлення:**
```bash
# 1. Встановити Evolution CMS
composer create-project evolution-cms/evolution my-ecommerce-site

# 2. Встановити sCommerce
cd core
composer update
php artisan package:installrequire seiger/scommerce "*"

# 3. Опублікувати ресурси
php artisan vendor:publish --tag=scommerce

# 4. Запустити міграції
php artisan migrate

# 5. Очистити кеш
php artisan cache:clear
```

### 1.3 Структура проекту

**Рекомендована структура директорій:**
```
my-ecommerce-site/
├── core/                           # Ядро Evolution CMS
│   ├── vendor/seiger/scommerce/    # Пакет sCommerce
│   └── custom/                     # Користувацький код
├── assets/                         # Статичні ресурси
│   ├── css/                        # Стилі
│   ├── js/                         # JavaScript
│   ├── images/                     # Зображення
│   └── modules/scommerce/          # Користувацькі ресурси sCommerce
├── views/                          # Frontend шаблони
│   ├── layout.blade.php            # Основний макет
│   ├── home.blade.php              # Головна сторінка
│   ├── catalog.blade.php           # Каталог товарів
│   ├── product.blade.php           # Деталі товару
│   ├── cart.blade.php              # Кошик покупок
│   └── checkout.blade.php          # Процес оформлення
└── manager/                        # Адміністративний інтерфейс
```

---

## Етап 2: Дизайн та архітектура

### 2.1 UI/UX дизайн

**Ключові сторінки для дизайну:**

1. **Головна сторінка** - Герой-секція, рекомендовані товари, категорії
2. **Каталог товарів** - Сітка товарів, фільтри, пагінація
3. **Деталі товару** - Зображення, описи, варіанти, відгуки
4. **Кошик покупок** - Товари в кошику, кількості, підсумки
5. **Оформлення замовлення** - Інформація клієнта, доставка, оплата
6. **Обліковий запис користувача** - Профіль, замовлення, адреси
7. **Адміністративна панель** - Замовлення, товари, клієнти

**Врахування дизайну:**
- Mobile-first адаптивний дизайн
- Швидкі часи завантаження
- Інтуїтивна навігація
- Чіткі кнопки дій
- Доступний дизайн (WCAG 2.1)

### 2.2 Архітектура бази даних

**Основні таблиці (автоматично створюються sCommerce):**

```sql
-- Товари
s_products (id, name, alias, description, price_regular, price_special, ...)
s_product_images (id, product_id, image, alt, sort)
s_product_attributes (id, product_id, attribute, value, price_modifier)

-- Категорії
s_categories (id, name, alias, description, parent_id, ...)
s_product_category (product_id, category_id, position, scope)

-- Замовлення
s_orders (id, customer_id, status, total, currency, ...)
s_order_items (id, order_id, product_id, quantity, price, total)

-- Клієнти
s_customers (id, user_id, first_name, last_name, email, ...)
s_addresses (id, customer_id, type, name, address, city, ...)
```

---

## Етап 3: Backend розробка

### 3.1 Управління товарами

**Створення категорій товарів:**

```php
use Seiger\sCommerce\Models\sCategory;

// Створити основні категорії
$electronics = sCategory::create([
    'name' => 'Електроніка',
    'alias' => 'electronics',
    'description' => 'Електронні пристрої та аксесуари',
    'published' => 1,
    'position' => 1
]);

// Створити підкатегорії
$smartphones = sCategory::create([
    'name' => 'Смартфони',
    'alias' => 'smartphones',
    'description' => 'Мобільні телефони та аксесуари',
    'parent_id' => $electronics->id,
    'published' => 1,
    'position' => 1
]);
```

**Додавання товарів:**

```php
use Seiger\sCommerce\Models\sProduct;

$product = sProduct::create([
    'name' => 'iPhone 15 Pro',
    'alias' => 'iphone-15-pro',
    'description' => 'Останній iPhone з передовими функціями',
    'short_description' => 'Преміум смартфон з Pro камерою',
    'price_regular' => 999.00,
    'price_special' => 899.00,
    'category' => $smartphones->id,
    'sku' => 'IPH15-PRO-128',
    'in_stock' => 50,
    'published' => 1
]);

// Додати зображення товару
$product->images()->create([
    'image' => 'iphone-15-pro-main.jpg',
    'alt' => 'iPhone 15 Pro Вид спереду',
    'sort' => 1
]);

// Додати атрибути товару
$product->attributes()->create([
    'attribute' => 'color',
    'value' => 'Космічний чорний',
    'price_modifier' => 0
]);
```

### 3.2 Обробка замовлень

**Робочий процес створення замовлення:**

```php
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderItem;

// Створити замовлення
$order = sOrder::create([
    'customer_id' => $customer->id,
    'status' => 'pending',
    'total' => 899.00,
    'currency' => 'USD',
    'shipping_address' => [
        'name' => 'Іван Іванов',
        'address' => 'вул. Головна, 123',
        'city' => 'Київ',
        'state' => 'Київська область',
        'zip' => '01001',
        'country' => 'UA'
    ],
    'billing_address' => [
        'name' => 'Іван Іванов',
        'address' => 'вул. Головна, 123',
        'city' => 'Київ',
        'state' => 'Київська область',
        'zip' => '01001',
        'country' => 'UA'
    ]
]);

// Додати товари замовлення
$order->items()->create([
    'product_id' => $product->id,
    'quantity' => 1,
    'price' => 899.00,
    'total' => 899.00
]);

// Обробити оплату
$paymentResult = $order->processPayment([
    'method' => 'credit_card',
    'transaction_id' => 'txn_123456',
    'status' => 'completed'
]);

if ($paymentResult) {
    $order->update(['status' => 'processing']);
}
```

---

## Етап 4: Frontend розробка

### 4.1 Структура шаблонів

**Основний макет (`views/layout.blade.php`):**

```blade
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Мій інтернет-магазин')</title>
    <meta name="description" content="@yield('description', 'Онлайн магазин з якісними товарами')">
    
    <!-- CSS -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
    <!-- Заголовок -->
    <header class="header">
        @include('partials.header')
    </header>

    <!-- Основний контент -->
    <main class="main">
        @yield('content')
    </main>

    <!-- Підвал -->
    <footer class="footer">
        @include('partials.footer')
    </footer>

    <!-- JavaScript -->
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
```

### 4.2 Кошик покупок

**Шаблон кошика (`views/cart.blade.php`):**

```blade
@extends('layout')

@section('title', 'Кошик покупок')
@section('description', 'Перегляньте товари в кошику та перейдіть до оформлення')

@section('content')
<div class="cart-page">
    <div class="container">
        <h1>Кошик покупок</h1>
        
        @if($cart->items->count() > 0)
            <div class="cart-items">
                @foreach($cart->items as $item)
                    <div class="cart-item" data-item-id="{{ $item->id }}">
                        <div class="product-info">
                            <img src="{{ $item->product->images->first()->image ?? '/images/no-image.png' }}" 
                                 alt="{{ $item->product->name }}" 
                                 class="product-image">
                            
                            <div class="product-details">
                                <h3>{{ $item->product->name }}</h3>
                                <p class="product-sku">Артикул: {{ $item->product->sku }}</p>
                                
                                @if($item->product->attributes->count() > 0)
                                    <div class="product-attributes">
                                        @foreach($item->product->attributes as $attr)
                                            <span class="attribute">
                                                {{ $attr->attribute }}: {{ $attr->value }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        <div class="quantity-controls">
                            <button class="btn btn-sm quantity-decrease" data-item-id="{{ $item->id }}">-</button>
                            <input type="number" 
                                   class="quantity-input" 
                                   value="{{ $item->quantity }}" 
                                   min="1" 
                                   max="{{ $item->product->in_stock }}"
                                   data-item-id="{{ $item->id }}">
                            <button class="btn btn-sm quantity-increase" data-item-id="{{ $item->id }}">+</button>
                        </div>
                        
                        <div class="item-price">
                            ${{ number_format($item->total, 2) }}
                        </div>
                        
                        <button class="btn btn-danger remove-item" data-item-id="{{ $item->id }}">
                            Видалити
                        </button>
                    </div>
                @endforeach
            </div>
            
            <div class="cart-summary">
                <div class="subtotal">
                    <span>Підсумок:</span>
                    <span>${{ number_format($cart->subtotal, 2) }}</span>
                </div>
                
                <div class="tax">
                    <span>Податок:</span>
                    <span>${{ number_format($cart->tax, 2) }}</span>
                </div>
                
                <div class="shipping">
                    <span>Доставка:</span>
                    <span>${{ number_format($cart->shipping, 2) }}</span>
                </div>
                
                <div class="total">
                    <span>Всього:</span>
                    <span>${{ number_format($cart->total, 2) }}</span>
                </div>
                
                <a href="/checkout" class="btn btn-primary btn-lg checkout-btn">
                    Перейти до оформлення
                </a>
            </div>
        @else
            <div class="empty-cart">
                <h2>Ваш кошик порожній</h2>
                <p>Додайте товари, щоб почати!</p>
                <a href="/catalog" class="btn btn-primary">Продовжити покупки</a>
            </div>
        @endif
    </div>
</div>
@endsection
```

---

## Етап 5: Інтеграція та тестування

### 5.1 Інтеграція платежів

**Інтеграція платіжного шлюзу:**

```php
// app/Services/PaymentService.php
namespace App\Services;

use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sPayment;

class PaymentService
{
    public function processPayment(sOrder $order, array $paymentData): array
    {
        $paymentMethod = $order->paymentMethod;
        
        switch ($paymentMethod->identifier) {
            case 'stripe':
                return $this->processStripePayment($order, $paymentData);
                
            case 'paypal':
                return $this->processPayPalPayment($order, $paymentData);
                
            case 'bank_invoice':
                return $this->processBankInvoicePayment($order, $paymentData);
                
            default:
                throw new \Exception('Непідтримуваний спосіб оплати');
        }
    }
    
    private function processStripePayment(sOrder $order, array $paymentData): array
    {
        // Логіка інтеграції Stripe
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        
        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $order->total * 100, // Конвертувати в копійки
                'currency' => strtolower($order->currency),
                'metadata' => [
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id
                ]
            ]);
            
            return [
                'success' => true,
                'transaction_id' => $intent->id,
                'status' => 'completed'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
```

---

## Етап 6: Розгортання та запуск

### 6.1 Налаштування продакшену

**Конфігурація середовища:**

```bash
# .env.production
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourstore.com

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=yourstore_production
DB_USERNAME=your-db-user
DB_PASSWORD=your-secure-password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourstore.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls

STRIPE_KEY=pk_live_your_stripe_key
STRIPE_SECRET=sk_live_your_stripe_secret
```

### 6.2 Оптимізація продуктивності

**Конфігурація кешування:**

```php
// config/cache.php
return [
    'default' => env('CACHE_DRIVER', 'redis'),
    
    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
        ],
        
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],
    ],
    
    'prefix' => env('CACHE_PREFIX', 'scommerce'),
];
```

---

## Етап 7: Підтримка та оптимізація

### 7.1 Моніторинг

**Моніторинг додатка:**

```php
// app/Services/MonitoringService.php
namespace App\Services;

use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Models\sOrder;

class MonitoringService
{
    public function logOrderMetrics()
    {
        $today = now()->startOfDay();
        $yesterday = $today->copy()->subDay();
        
        $todayOrders = sOrder::where('created_at', '>=', $today)->count();
        $yesterdayOrders = sOrder::whereBetween('created_at', [$yesterday, $today])->count();
        
        Log::info('Щоденні метрики замовлень', [
            'today_orders' => $todayOrders,
            'yesterday_orders' => $yesterdayOrders,
            'growth_rate' => $yesterdayOrders > 0 ? (($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100 : 0
        ]);
    }
    
    public function checkLowStock()
    {
        $lowStockProducts = sProduct::where('in_stock', '<=', 10)
            ->where('published', 1)
            ->get();
            
        if ($lowStockProducts->count() > 0) {
            Log::warning('Попередження про низькі запаси', [
                'products' => $lowStockProducts->pluck('name', 'id')->toArray()
            ]);
        }
    }
}
```

---

## Найкращі практики

### Організація коду

1. **Дотримуйтесь стандартів кодування PSR-12**
2. **Використовуйте зрозумілі назви змінних та функцій**
3. **Пишіть комплексні тести**
4. **Документуйте свій код**
5. **Ефективно використовуйте систему контролю версій**

### Продуктивність

1. **Реалізуйте стратегії кешування**
2. **Оптимізуйте запити до бази даних**
3. **Використовуйте CDN для статичних ресурсів**
4. **Мінімізуйте HTTP запити**
5. **Стискайте зображення та ресурси**

### Безпека

1. **Валідуйте всі користувацькі входи**
2. **Використовуйте HTTPS всюди**
3. **Реалізуйте належну аутентифікацію**
4. **Оновлюйте залежності**
5. **Регулярні аудити безпеки**

### Користувацький досвід

1. **Mobile-first дизайн**
2. **Швидкі часи завантаження**
3. **Інтуїтивна навігація**
4. **Чіткі повідомлення про помилки**
5. **Доступний дизайн**

---

## Висновок

Цей флоу розробки надає комплексний посібник для створення інтернет-магазинів з sCommerce. Дотримуючись цих етапів та найкращих практик, ви можете створювати надійні, масштабовані та підтримувані онлайн-магазини, які забезпечують відмінний користувацький досвід та сприяють зростанню бізнесу.

Пам'ятайте, що потрібно адаптувати цей флоу до специфічних вимог вашого проекту та завжди пріоритизувати безпеку, продуктивність та користувацький досвід у процесі розробки.
