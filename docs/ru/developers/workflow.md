---
id: workflow
title: Флоу разработки
sidebar_position: 1
---

# Флоу разработки с sCommerce

Это подробное руководство проведет вас через полный процесс создания интернет-магазина с использованием sCommerce и Evolution CMS.

## Обзор

Флоу разработки состоит из нескольких ключевых этапов:

1. **Планирование и настройка** - Планирование проекта и настройка окружения
2. **Дизайн и архитектура** - UI/UX дизайн и архитектура системы
3. **Backend разработка** - Реализация основного функционала
4. **Frontend разработка** - Пользовательский интерфейс и шаблоны
5. **Интеграция и тестирование** - Платежные системы и тестирование
6. **Развертывание и запуск** - Развертывание в продакшене и запуск
7. **Поддержка и оптимизация** - Текущее обслуживание и улучшения

---

## Этап 1: Планирование и настройка

### 1.1 Требования проекта

**Определите потребности вашего интернет-магазина:**

- **Каталог товаров** - Количество товаров, категорий, вариантов
- **Управление пользователями** - Регистрация, профили, группы клиентов
- **Обработка заказов** - Корзина, оформление, управление заказами
- **Способы оплаты** - Кредитные карты, банковские переводы, цифровые кошельки
- **Доставка** - Зоны доставки, тарифы, отслеживание
- **Налоги** - Налоговые ставки, регионы, расчеты
- **Многоязычность** - Поддерживаемые языки и регионы
- **SEO** - Структура URL, мета-теги, карта сайта

### 1.2 Настройка окружения

**Предварительные требования:**
```bash
# Системные требования
- Evolution CMS 3.7+
- PHP 8.3+
- MySQL 8.0+ / MariaDB 10.5+ / PostgreSQL 10+ / SQLite 3.25+
- Composer 2.2+
- Node.js 18+ (для frontend инструментов сборки)
```

**Установка:**
```bash
# 1. Установить Evolution CMS
composer create-project evolution-cms/evolution my-ecommerce-site

# 2. Установить sCommerce
cd core
composer update
php artisan package:installrequire seiger/scommerce "*"

# 3. Опубликовать ресурсы
php artisan vendor:publish --tag=scommerce

# 4. Запустить миграции
php artisan migrate

# 5. Очистить кеш
php artisan cache:clear
```

### 1.3 Структура проекта

**Рекомендуемая структура директорий:**
```
my-ecommerce-site/
├── core/                           # Ядро Evolution CMS
│   ├── vendor/seiger/scommerce/    # Пакет sCommerce
│   └── custom/                     # Пользовательский код
├── assets/                         # Статические ресурсы
│   ├── css/                        # Стили
│   ├── js/                         # JavaScript
│   ├── images/                     # Изображения
│   └── modules/scommerce/          # Пользовательские ресурсы sCommerce
├── views/                          # Frontend шаблоны
│   ├── layout.blade.php            # Основной макет
│   ├── home.blade.php              # Главная страница
│   ├── catalog.blade.php           # Каталог товаров
│   ├── product.blade.php           # Детали товара
│   ├── cart.blade.php              # Корзина покупок
│   └── checkout.blade.php          # Процесс оформления
└── manager/                        # Административный интерфейс
```

---

## Этап 2: Дизайн и архитектура

### 2.1 UI/UX дизайн

**Ключевые страницы для дизайна:**

1. **Главная страница** - Герой-секция, рекомендуемые товары, категории
2. **Каталог товаров** - Сетка товаров, фильтры, пагинация
3. **Детали товара** - Изображения, описания, варианты, отзывы
4. **Корзина покупок** - Товары в корзине, количества, итоги
5. **Оформление заказа** - Информация клиента, доставка, оплата
6. **Аккаунт пользователя** - Профиль, заказы, адреса
7. **Административная панель** - Заказы, товары, клиенты

**Соображения дизайна:**
- Mobile-first адаптивный дизайн
- Быстрые времена загрузки
- Интуитивная навигация
- Четкие кнопки действий
- Доступный дизайн (WCAG 2.1)

### 2.2 Архитектура базы данных

**Основные таблицы (автоматически создаются sCommerce):**

```sql
-- Товары
s_products (id, name, alias, description, price_regular, price_special, ...)
s_product_images (id, product_id, image, alt, sort)
s_product_attributes (id, product_id, attribute, value, price_modifier)

-- Категории
s_categories (id, name, alias, description, parent_id, ...)
s_product_category (product_id, category_id, position, scope)

-- Заказы
s_orders (id, customer_id, status, total, currency, ...)
s_order_items (id, order_id, product_id, quantity, price, total)

-- Клиенты
s_customers (id, user_id, first_name, last_name, email, ...)
s_addresses (id, customer_id, type, name, address, city, ...)
```

---

## Этап 3: Backend разработка

### 3.1 Управление товарами

**Создание категорий товаров:**

```php
use Seiger\sCommerce\Models\sCategory;

// Создать основные категории
$electronics = sCategory::create([
    'name' => 'Электроника',
    'alias' => 'electronics',
    'description' => 'Электронные устройства и аксессуары',
    'published' => 1,
    'position' => 1
]);

// Создать подкатегории
$smartphones = sCategory::create([
    'name' => 'Смартфоны',
    'alias' => 'smartphones',
    'description' => 'Мобильные телефоны и аксессуары',
    'parent_id' => $electronics->id,
    'published' => 1,
    'position' => 1
]);
```

**Добавление товаров:**

```php
use Seiger\sCommerce\Models\sProduct;

$product = sProduct::create([
    'name' => 'iPhone 15 Pro',
    'alias' => 'iphone-15-pro',
    'description' => 'Последний iPhone с передовыми функциями',
    'short_description' => 'Премиум смартфон с Pro камерой',
    'price_regular' => 999.00,
    'price_special' => 899.00,
    'category' => $smartphones->id,
    'sku' => 'IPH15-PRO-128',
    'in_stock' => 50,
    'published' => 1
]);

// Добавить изображения товара
$product->images()->create([
    'image' => 'iphone-15-pro-main.jpg',
    'alt' => 'iPhone 15 Pro Вид спереди',
    'sort' => 1
]);

// Добавить атрибуты товара
$product->attributes()->create([
    'attribute' => 'color',
    'value' => 'Космический черный',
    'price_modifier' => 0
]);
```

---

## Этап 4: Frontend разработка

### 4.1 Структура шаблонов

**Основной макет (`views/layout.blade.php`):**

```blade
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Мой интернет-магазин')</title>
    <meta name="description" content="@yield('description', 'Онлайн магазин с качественными товарами')">
    
    <!-- CSS -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
    <!-- Заголовок -->
    <header class="header">
        @include('partials.header')
    </header>

    <!-- Основной контент -->
    <main class="main">
        @yield('content')
    </main>

    <!-- Подвал -->
    <footer class="footer">
        @include('partials.footer')
    </footer>

    <!-- JavaScript -->
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
```

### 4.2 Корзина покупок

**Шаблон корзины (`views/cart.blade.php`):**

```blade
@extends('layout')

@section('title', 'Корзина покупок')
@section('description', 'Просмотрите товары в корзине и перейдите к оформлению')

@section('content')
<div class="cart-page">
    <div class="container">
        <h1>Корзина покупок</h1>
        
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
                            Удалить
                        </button>
                    </div>
                @endforeach
            </div>
            
            <div class="cart-summary">
                <div class="subtotal">
                    <span>Подытог:</span>
                    <span>${{ number_format($cart->subtotal, 2) }}</span>
                </div>
                
                <div class="tax">
                    <span>Налог:</span>
                    <span>${{ number_format($cart->tax, 2) }}</span>
                </div>
                
                <div class="shipping">
                    <span>Доставка:</span>
                    <span>${{ number_format($cart->shipping, 2) }}</span>
                </div>
                
                <div class="total">
                    <span>Итого:</span>
                    <span>${{ number_format($cart->total, 2) }}</span>
                </div>
                
                <a href="/checkout" class="btn btn-primary btn-lg checkout-btn">
                    Перейти к оформлению
                </a>
            </div>
        @else
            <div class="empty-cart">
                <h2>Ваша корзина пуста</h2>
                <p>Добавьте товары, чтобы начать!</p>
                <a href="/catalog" class="btn btn-primary">Продолжить покупки</a>
            </div>
        @endif
    </div>
</div>
@endsection
```

---

## Этап 5: Интеграция и тестирование

### 5.1 Интеграция платежей

**Интеграция платежного шлюза:**

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
                throw new \Exception('Неподдерживаемый способ оплаты');
        }
    }
    
    private function processStripePayment(sOrder $order, array $paymentData): array
    {
        // Логика интеграции Stripe
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        
        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $order->total * 100, // Конвертировать в копейки
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

## Этап 6: Развертывание и запуск

### 6.1 Настройка продакшена

**Конфигурация окружения:**

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

---

## Этап 7: Поддержка и оптимизация

### 7.1 Мониторинг

**Мониторинг приложения:**

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
        
        Log::info('Ежедневные метрики заказов', [
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
            Log::warning('Предупреждение о низких запасах', [
                'products' => $lowStockProducts->pluck('name', 'id')->toArray()
            ]);
        }
    }
}
```

---

## Лучшие практики

### Организация кода

1. **Следуйте стандартам кодирования PSR-12**
2. **Используйте осмысленные имена переменных и функций**
3. **Пишите комплексные тесты**
4. **Документируйте свой код**
5. **Эффективно используйте систему контроля версий**

### Производительность

1. **Реализуйте стратегии кеширования**
2. **Оптимизируйте запросы к базе данных**
3. **Используйте CDN для статических ресурсов**
4. **Минимизируйте HTTP запросы**
5. **Сжимайте изображения и ресурсы**

### Безопасность

1. **Валидируйте все пользовательские вводы**
2. **Используйте HTTPS везде**
3. **Реализуйте надлежащую аутентификацию**
4. **Обновляйте зависимости**
5. **Регулярные аудиты безопасности**

### Пользовательский опыт

1. **Mobile-first дизайн**
2. **Быстрые времена загрузки**
3. **Интуитивная навигация**
4. **Четкие сообщения об ошибках**
5. **Доступный дизайн**

---

## Заключение

Этот флоу разработки предоставляет комплексное руководство для создания интернет-магазинов с sCommerce. Следуя этим этапам и лучшим практикам, вы можете создавать надежные, масштабируемые и поддерживаемые онлайн-магазины, которые обеспечивают отличный пользовательский опыт и способствуют росту бизнеса.

Помните, что нужно адаптировать этот флоу к специфическим требованиям вашего проекта и всегда приоритизировать безопасность, производительность и пользовательский опыт в процессе разработки.
