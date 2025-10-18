---
id: developers
title: Посібник розробника
sidebar_position: 4
---

# Посібник розробника

Цей посібник охоплює розширену конфігурацію, налаштування та розробку з sCommerce.

## Огляд архітектури

sCommerce слідує модульній архітектурі з чітким розділенням відповідальності:

```
sCommerce/
├── src/
│   ├── Models/           # Eloquent моделі
│   ├── Controllers/      # HTTP контролери
│   ├── Services/         # Бізнес-логіка
│   ├── Facades/          # Фасади сервісів
│   ├── Http/            # Маршрути та middleware
│   └── Integration/      # Зовнішні інтеграції
├── views/               # Blade шаблони
├── assets/              # CSS, JS, зображення
└── database/            # Міграції та сідери
```

## Моделі

### Модель sProduct

Основна модель товару з відносинами та атрибутами:

```php
use Seiger\sCommerce\Models\sProduct;

// Створення товару
$product = sProduct::create([
    'name' => 'Назва товару',
    'alias' => 'nazva-tovaru',
    'price_regular' => 999.99,
    'category' => 1,
    'published' => 1
]);

// Відносини
$product->category;           // BelongsTo sCategory
$product->images;             // HasMany sProductImage
$product->attributes;         // HasMany sProductAttribute
$product->translates;         // HasMany sProductTranslate
$product->reviews;            // HasMany sProductReview

// Scope запити
sProduct::published();        // Тільки опубліковані товари
sProduct::inStock();          // Тільки товари в наявності
sProduct::byCategory(1);      // Товари певної категорії

// Атрибути
$product->link;               // URL товару
$product->reviewsCount;       // Кількість відгуків
$product->averageRating;      // Середній рейтинг
```

### Модель sCategory

Управління категоріями з ієрархічною структурою:

```php
use Seiger\sCommerce\Models\sCategory;

// Створення категорії
$category = sCategory::create([
    'pagetitle' => 'Електроніка',
    'alias' => 'elektronika',
    'parent' => 0,
    'published' => 1
]);

// Відносини
$category->children;          // HasMany sCategory (підкатегорії)
$category->parent;            // BelongsTo sCategory
$category->products;          // HasMany sProduct
```

### Модель sOrder

Управління та обробка замовлень:

```php
use Seiger\sCommerce\Models\sOrder;

// Створення замовлення
$order = sOrder::create([
    'customer_id' => 1,
    'status' => 'pending',
    'total' => 1999.98,
    'currency' => 'UAH'
]);
```

## Сервіси

### Сервіс sCommerce

Основний сервісний клас для операцій електронної комерції:

```php
use Seiger\sCommerce\Facades\sCommerce;

// Операції з товарами
$products = sCommerce::getProducts($filters);
$product = sCommerce::getProduct($id);

// Операції з категоріями
$categories = sCommerce::getCategories();
$category = sCommerce::getCategory($id);

// Операції з кошиком
sCommerce::addToCart($productId, $quantity);
sCommerce::removeFromCart($itemId);
$cart = sCommerce::getCart();

// Операції із замовленнями
$order = sCommerce::createOrder($data);
```

## Розробка API

### REST API ендпоінти

sCommerce надає комплексний REST API:

```php
// API товарів
GET    /api/products              # Список товарів
GET    /api/products/{id}         # Деталі товару
POST   /api/products              # Створення товару
PUT    /api/products/{id}         # Оновлення товару
DELETE /api/products/{id}         # Видалення товару

// API категорій
GET    /api/categories            # Список категорій
GET    /api/categories/{id}       # Деталі категорії

// API кошика
GET    /api/cart                  # Отримати кошик
POST   /api/cart/items            # Додати товар
DELETE /api/cart/items/{id}       # Видалити товар

// API замовлень
GET    /api/orders                # Список замовлень
POST   /api/orders                # Створити замовлення
```

## Користувацькі інтеграції

### Інтеграція платіжних шлюзів

Створення власного платіжного шлюзу:

```php
<?php namespace App\Payments;

use Seiger\sCommerce\Contracts\PaymentGatewayInterface;

class CustomPaymentGateway implements PaymentGatewayInterface
{
    public function processPayment(array $data): array
    {
        // Логіка обробки платежу
        $result = $this->callPaymentAPI($data);
        
        return [
            'success' => $result['status'] === 'success',
            'transaction_id' => $result['transaction_id'],
            'message' => $result['message']
        ];
    }
}
```

### Інтеграція провайдерів доставки

Створення власного провайдера доставки:

```php
<?php namespace App\Shipping;

use Seiger\sCommerce\Contracts\ShippingProviderInterface;

class CustomShippingProvider implements ShippingProviderInterface
{
    public function calculateShipping(array $data): array
    {
        // Розрахунок вартості доставки
        $cost = $this->calculateCost($data);
        
        return [
            'success' => true,
            'cost' => $cost,
            'delivery_time' => '3-5 робочих днів'
        ];
    }
}
```

## Система подій

sCommerce надає систему подій для розширення функціональності:

```php
use Seiger\sCommerce\Events\ProductCreated;
use Seiger\sCommerce\Events\OrderCreated;

// Прослуховування подій
Event::listen(ProductCreated::class, function ($event) {
    // Надіслати сповіщення
    // Оновити індекс пошуку
});

Event::listen(OrderCreated::class, function ($event) {
    // Надіслати email підтвердження
    // Оновити інвентар
});
```

## Оптимізація продуктивності

### Оптимізація бази даних

```php
// Використовуйте eager loading для уникнення N+1 запитів
$products = sProduct::with(['category', 'images', 'attributes'])
    ->published()
    ->get();

// Використовуйте індекси бази даних
Schema::table('s_products', function (Blueprint $table) {
    $table->index(['published', 'category']);
    $table->index('alias');
});
```

### Кешування

```php
use Illuminate\Support\Facades\Cache;

// Кешування даних товарів
$products = Cache::remember('products.category.1', 3600, function () {
    return sProduct::published()
        ->byCategory(1)
        ->get();
});
```

## Інтеграція фронтенду

### Конвенція Data-атрибутів

sCommerce використовує послідовну конвенцію іменування для фронтенд data-атрибутів для обробки взаємодії користувачів. Всі атрибути слідують шаблону `data-sc-{action}`.

#### Атрибути дій

- `data-sc-buy` — Додати товар у кошик
- `data-sc-remove` — Видалити товар з кошика
- `data-sc-wishlist` — Додати/видалити товар до/з списку бажаного
- `data-sc-compare` — Додати товар до порівняння
- `data-sc-fast-buy` — Швидка покупка в один клік
- `data-sc-increment` — Збільшити кількість на 1
- `data-sc-decrement` — Зменшити кількість на 1

#### Атрибути параметрів

- `data-sc-quantity` — Кількість товару
- `data-sc-price` — Ціна (для клієнтських розрахунків)
- `data-sc-variant` — ID варіанту/модифікації

### API подій

sCommerce надає просту систему подій для обробки дій з кошиком. Використовуйте callbacks для реагування на дії користувача:

```javascript
// Прослуховування події додавання в кошик
sCommerce.onAddedToCart = (data) => {
    console.log('Товар додано:', data.product);
    // Оновити міні-кошик
    document.querySelector('.mini-cart-count').textContent = data.miniCart.count;
    document.querySelector('.mini-cart-total').textContent = data.miniCart.total;
};

// Прослуховування події видалення з кошика
sCommerce.onRemovedFromCart = (data) => {
    console.log('Товар видалено:', data.product);
    // Оновити міні-кошик
    document.querySelector('.mini-cart-count').textContent = data.miniCart.count;
};

// Прослуховування події оновлення кількості
sCommerce.onUpdatedCart = (data) => {
    console.log('Кількість оновлено:', data);
};

// Прослуховування події швидкого замовлення
sCommerce.onFastOrder = (data) => {
    if (data.success) {
        alert('Дякуємо! Ми зв\'яжемось з вами найближчим часом.');
    }
};
```

#### Доступні події

- `sCommerce.onAddedToCart` — Спрацьовує при додаванні товару в кошик
- `sCommerce.onRemovedFromCart` — Спрацьовує при видаленні товару з кошика
- `sCommerce.onUpdatedCart` — Спрацьовує при оновленні кількості в кошику
- `sCommerce.onFastOrder` — Спрацьовує при відправці швидкого замовлення

#### Структура даних події

Всі події отримують об'єкт `data` з наступною структурою:

```javascript
{
    success: true,
    product: {
        id: 123,
        title: "Назва товару",
        price: "99.99",
        // ... інші дані товару
    },
    miniCart: {
        count: 3,
        total: "299.97",
        // ... інші дані кошика
    }
}
```

### Приклади використання

#### Картка товару (Каталог)

```html
<div class="product-card">
    <button data-sc-wishlist="{{$product->id}}">♥</button>
    
    <input type="number" class="qty-input" value="1" min="1" max="{{$product->inventory}}">
    
    <button data-sc-buy="{{$product->id}}">
        @lang('Купити')
    </button>
    
    <button data-sc-fast-buy="{{$product->id}}">
        @lang('Купити в 1 клік')
    </button>
</div>
```

#### Кошик покупок

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

### Застарілий API

:::warning Застаріло
Наступні підходи застаріли і будуть видалені у версії 1.5. Будь ласка, мігруйте на новий API подій.
:::

#### Старий CustomEvent API (Застаріло)

```javascript
// ❌ Застаріло - Буде видалено у v1.5
document.addEventListener('sCommerceAddedToCart', (event) => {
    const data = event.detail;
    console.log(data);
});
```

**Міграція:** Використовуйте `sCommerce.onAddedToCart = (data) => {}` замість цього.

#### Старі назви атрибутів (Застаріло)

- ❌ `data-s-buy` → ✅ Використовуйте `data-sc-buy`
- ❌ `data-s-fast-buy` → ✅ Використовуйте `data-sc-fast-buy`
- ❌ `data-s-remove` → ✅ Використовуйте `data-sc-remove`
- ❌ `data-s-quantity` → ✅ Використовуйте `data-sc-quantity`

### Переваги

- ✅ **W3C Валідний** — Усі атрибути відповідають стандартам HTML5
- ✅ **Простий API** — Легко використовувати callbacks: `sCommerce.onEventName = (data) => {}`
- ✅ **Послідовний** — Єдина конвенція іменування в усьому пакеті
- ✅ **Читабельний** — Легко зрозуміти та підтримувати
- ✅ **Розширюваний** — Просто додавати нові дії
- ✅ **Dataset API** — Автоматично конвертується в camelCase в JavaScript (`dataset.scBuy`)
- ✅ **Без залежностей** — Чистий vanilla JavaScript

## Найкращі практики

1. **Завжди використовуйте транзакції** для критичних операцій
2. **Валідуйте вхідні дані** перед обробкою
3. **Використовуйте належну обробку помилок** та логування
4. **Оптимізуйте запити до бази даних** та використовуйте індекси
5. **Тестуйте ретельно** перед розгортанням
6. **Використовуйте кешування** для часто використовуваних даних
7. **Слідкуйте за продуктивністю** та метриками помилок
8. **Тримайте залежності оновленими** для безпеки
9. **Документуйте ваші налаштування** для подальшого обслуговування
10. **Використовуйте контроль версій** для всіх змін
