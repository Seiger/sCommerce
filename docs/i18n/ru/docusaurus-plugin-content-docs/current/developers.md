---
id: developers
title: Руководство разработчика
sidebar_position: 3
---

# Руководство разработчика

Это руководство охватывает расширенную конфигурацию, настройку и разработку с sCommerce.

## Обзор архитектуры

sCommerce следует модульной архитектуре с чётким разделением ответственности:

```
sCommerce/
├── src/
│   ├── Models/           # Eloquent модели
│   ├── Controllers/      # HTTP контроллеры
│   ├── Services/         # Бизнес-логика
│   ├── Facades/          # Фасады сервисов
│   ├── Http/            # Маршруты и middleware
│   └── Integration/      # Внешние интеграции
├── views/               # Blade шаблоны
├── assets/              # CSS, JS, изображения
└── database/            # Миграции и сидеры
```

## Модели

### Модель sProduct

Основная модель товара с отношениями и атрибутами:

```php
use Seiger\sCommerce\Models\sProduct;

// Создание товара
$product = sProduct::create([
    'name' => 'Название товара',
    'alias' => 'nazvanie-tovara',
    'price_regular' => 999.99,
    'category' => 1,
    'published' => 1
]);

// Отношения
$product->category;           // BelongsTo sCategory
$product->images;             // HasMany sProductImage
$product->attributes;         // HasMany sProductAttribute
$product->translates;         // HasMany sProductTranslate
$product->reviews;            // HasMany sProductReview

// Scope запросы
sProduct::published();        // Только опубликованные товары
sProduct::inStock();          // Только товары в наличии
sProduct::byCategory(1);      // Товары определённой категории

// Атрибуты
$product->link;               // URL товара
$product->reviewsCount;       // Количество отзывов
$product->averageRating;      // Средний рейтинг
```

### Модель sCategory

Управление категориями с иерархической структурой:

```php
use Seiger\sCommerce\Models\sCategory;

// Создание категории
$category = sCategory::create([
    'pagetitle' => 'Электроника',
    'alias' => 'elektronika',
    'parent' => 0,
    'published' => 1
]);

// Отношения
$category->children;          // HasMany sCategory (подкатегории)
$category->parent;            // BelongsTo sCategory
$category->products;          // HasMany sProduct
```

### Модель sOrder

Управление и обработка заказов:

```php
use Seiger\sCommerce\Models\sOrder;

// Создание заказа
$order = sOrder::create([
    'customer_id' => 1,
    'status' => 'pending',
    'total' => 1999.98,
    'currency' => 'RUB'
]);
```

## Сервисы

### Сервис sCommerce

Основной сервисный класс для операций электронной коммерции:

```php
use Seiger\sCommerce\Facades\sCommerce;

// Операции с товарами
$products = sCommerce::getProducts($filters);
$product = sCommerce::getProduct($id);

// Операции с категориями
$categories = sCommerce::getCategories();
$category = sCommerce::getCategory($id);

// Операции с корзиной
sCommerce::addToCart($productId, $quantity);
sCommerce::removeFromCart($itemId);
$cart = sCommerce::getCart();

// Операции с заказами
$order = sCommerce::createOrder($data);
```

## Разработка API

### REST API эндпоинты

sCommerce предоставляет комплексный REST API:

```php
// API товаров
GET    /api/products              # Список товаров
GET    /api/products/{id}         # Детали товара
POST   /api/products              # Создание товара
PUT    /api/products/{id}         # Обновление товара
DELETE /api/products/{id}         # Удаление товара

// API категорий
GET    /api/categories            # Список категорий
GET    /api/categories/{id}       # Детали категории

// API корзины
GET    /api/cart                  # Получить корзину
POST   /api/cart/items            # Добавить товар
DELETE /api/cart/items/{id}       # Удалить товар

// API заказов
GET    /api/orders                # Список заказов
POST   /api/orders                # Создать заказ
```

## Пользовательские интеграции

### Интеграция платёжных шлюзов

Создание собственного платёжного шлюза:

```php
<?php namespace App\Payments;

use Seiger\sCommerce\Contracts\PaymentGatewayInterface;

class CustomPaymentGateway implements PaymentGatewayInterface
{
    public function processPayment(array $data): array
    {
        // Логика обработки платежа
        $result = $this->callPaymentAPI($data);
        
        return [
            'success' => $result['status'] === 'success',
            'transaction_id' => $result['transaction_id'],
            'message' => $result['message']
        ];
    }
}
```

### Интеграция провайдеров доставки

Создание собственного провайдера доставки:

```php
<?php namespace App\Shipping;

use Seiger\sCommerce\Contracts\ShippingProviderInterface;

class CustomShippingProvider implements ShippingProviderInterface
{
    public function calculateShipping(array $data): array
    {
        // Расчёт стоимости доставки
        $cost = $this->calculateCost($data);
        
        return [
            'success' => true,
            'cost' => $cost,
            'delivery_time' => '3-5 рабочих дней'
        ];
    }
}
```

## Система событий

sCommerce предоставляет систему событий для расширения функциональности:

```php
use Seiger\sCommerce\Events\ProductCreated;
use Seiger\sCommerce\Events\OrderCreated;

// Прослушивание событий
Event::listen(ProductCreated::class, function ($event) {
    // Отправить уведомление
    // Обновить индекс поиска
});

Event::listen(OrderCreated::class, function ($event) {
    // Отправить email подтверждение
    // Обновить инвентарь
});
```

## Оптимизация производительности

### Оптимизация базы данных

```php
// Используйте eager loading для избежания N+1 запросов
$products = sProduct::with(['category', 'images', 'attributes'])
    ->published()
    ->get();

// Используйте индексы базы данных
Schema::table('s_products', function (Blueprint $table) {
    $table->index(['published', 'category']);
    $table->index('alias');
});
```

### Кеширование

```php
use Illuminate\Support\Facades\Cache;

// Кеширование данных товаров
$products = Cache::remember('products.category.1', 3600, function () {
    return sProduct::published()
        ->byCategory(1)
        ->get();
});
```

## Лучшие практики

1. **Всегда используйте транзакции** для критических операций
2. **Валидируйте входные данные** перед обработкой
3. **Используйте надлежащую обработку ошибок** и логирование
4. **Оптимизируйте запросы к базе данных** и используйте индексы
5. **Тестируйте тщательно** перед развёртыванием
6. **Используйте кеширование** для часто используемых данных
7. **Следите за производительностью** и метриками ошибок
8. **Держите зависимости обновлёнными** для безопасности
9. **Документируйте ваши настройки** для дальнейшего обслуживания
10. **Используйте контроль версий** для всех изменений
