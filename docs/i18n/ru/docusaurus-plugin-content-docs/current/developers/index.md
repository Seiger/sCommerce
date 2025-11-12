---
id: developers
title: Руководство разработчика
sidebar_position: 4
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
$product = sCommerce::getProductByAlias($alias);
sCommerce::applyFilters([
    'bicycle-category' => ['grevel'],
], $categoryId);
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

### Принудительные фильтры

```php
use Seiger\sCommerce\sFilter;

sFilter::force([
    'sex' => ['unisex'],
], $categoryId);

// ... вывод каталога ...

sFilter::release();
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

## Интеграция фронтенда

### Конвенция Data-атрибутов

sCommerce использует последовательную конвенцию именования для фронтенд data-атрибутов для обработки взаимодействия пользователей. Все атрибуты следуют шаблону `data-sc-{action}`.

#### Атрибуты действий

- `data-sc-buy` — Добавить товар в корзину
- `data-sc-remove` — Удалить товар из корзины
- `data-sc-wishlist` — Добавить/удалить товар в/из списка желаемого
- `data-sc-compare` — Добавить товар к сравнению
- `data-sc-fast-buy` — Быстрая покупка в один клик
- `data-sc-increment` — Увеличить количество на 1
- `data-sc-decrement` — Уменьшить количество на 1

#### Атрибуты параметров

- `data-sc-quantity` — Количество товара
- `data-sc-price` — Цена (для клиентских расчётов)
- `data-sc-variant` — ID варианта/модификации

### API событий

sCommerce предоставляет простую систему событий для обработки действий с корзиной. Используйте callbacks для реагирования на действия пользователя:

```javascript
// Прослушивание события добавления в корзину
sCommerce.onAddedToCart = (data) => {
    console.log('Товар добавлен:', data.product);
    // Обновить мини-корзину
    document.querySelector('.mini-cart-count').textContent = data.miniCart.count;
    document.querySelector('.mini-cart-total').textContent = data.miniCart.total;
};

// Прослушивание события удаления из корзины
sCommerce.onRemovedFromCart = (data) => {
    console.log('Товар удалён:', data.product);
    // Обновить мини-корзину
    document.querySelector('.mini-cart-count').textContent = data.miniCart.count;
};

// Прослушивание события обновления количества
sCommerce.onUpdatedCart = (data) => {
    console.log('Количество обновлено:', data);
};

// Прослушивание события быстрого заказа
sCommerce.onFastOrder = (data) => {
    if (data.success) {
        alert('Спасибо! Мы свяжемся с вами в ближайшее время.');
    }
};
```

#### Доступные события

- `sCommerce.onAddedToCart` — Срабатывает при добавлении товара в корзину
- `sCommerce.onRemovedFromCart` — Срабатывает при удалении товара из корзины
- `sCommerce.onUpdatedCart` — Срабатывает при обновлении количества в корзине
- `sCommerce.onFastOrder` — Срабатывает при отправке быстрого заказа

#### Структура данных события

Все события получают объект `data` со следующей структурой:

```javascript
{
    success: true,
    product: {
        id: 123,
        title: "Название товара",
        price: "99.99",
        // ... другие данные товара
    },
    miniCart: {
        count: 3,
        total: "299.97",
        // ... другие данные корзины
    }
}
```

### Примеры использования

#### Карточка товара (Каталог)

```html
<div class="product-card">
    <button data-sc-wishlist="{{$product->id}}">♥</button>
    
    <input type="number" class="qty-input" value="1" min="1" max="{{$product->inventory}}">
    
    <button data-sc-buy="{{$product->id}}">
        @lang('Купить')
    </button>
    
    <button data-sc-fast-buy="{{$product->id}}">
        @lang('Купить в 1 клик')
    </button>
</div>
```

#### Корзина покупок

```html
<div class="cart-item" data-item-id="{{$product->id}}">
    <svg data-sc-remove="{{$product->id}}">...</svg>
    
    <div class="quantity-control">
        <button data-sc-decrement="{{$product->id}}">-</button>
        <input type="number" class="qty-input" value="{{$quantity}}" data-sc-quantity="{{$product->id}}">
        <button data-sc-increment="{{$product->id}}">+</button>
    </div>
</div>
```

### Устаревший API

:::warning Устарело
Следующие подходы устарели и будут удалены в версии 1.5. Пожалуйста, мигрируйте на новый API событий.
:::

#### Старый CustomEvent API (Устарело)

```javascript
// ❌ Устарело - Будет удалено в v1.5
document.addEventListener('sCommerceAddedToCart', (event) => {
    const data = event.detail;
    console.log(data);
});
```

**Миграция:** Используйте `sCommerce.onAddedToCart = (data) => {}` вместо этого.

#### Старые названия атрибутов (Устарело)

- ❌ `data-s-buy` → ✅ Используйте `data-sc-buy`
- ❌ `data-s-fast-buy` → ✅ Используйте `data-sc-fast-buy`
- ❌ `data-s-remove` → ✅ Используйте `data-sc-remove`
- ❌ `data-s-quantity` → ✅ Используйте `data-sc-quantity`

### Преимущества

- ✅ **W3C Валидный** — Все атрибуты соответствуют стандартам HTML5
- ✅ **Простой API** — Легко использовать callbacks: `sCommerce.onEventName = (data) => {}`
- ✅ **Последовательный** — Единая конвенция именования во всём пакете
- ✅ **Читабельный** — Легко понять и поддерживать
- ✅ **Расширяемый** — Просто добавлять новые действия
- ✅ **Dataset API** — Автоматически конвертируется в camelCase в JavaScript (`dataset.scBuy`)
- ✅ **Без зависимостей** — Чистый vanilla JavaScript

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
