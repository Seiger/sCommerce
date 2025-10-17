---
id: events
title: Події
sidebar_position: 9
---

Події Evo надають просту реалізацію шаблону спостерігача, що дозволяє вам підписуватися та прослуховувати
різні події, які відбуваються у вашому додатку. Використовуючи події, зручно керувати
додатковими параметрами sCommerce. Нижче наведено список зарезервованих подій.

## Розширення можливостей управління інтерфейсом

### sCommerceManagerAddTabEvent

```php
Event::listen('evolution.sCommerceManagerAddTabEvent', function($params) {
    dd($params);
});
```

## Маніпуляції з товарами

### sCommerceAfterProductSave

```php
Event::listen('evolution.sCommerceAfterProductSave', function($params) {
    dd($params);
});
```

### sCommerceAfterProductDuplicate

```php
Event::listen('evolution.sCommerceAfterProductDuplicate', function($params) {
    dd($params);
});
```

### sCommerceAfterProductDelete

```php
Event::listen('evolution.sCommerceAfterProductDelete', function($params) {
    dd($params);
});
```

## Події кошика

### sCommerceAfterAddToCart

```php
Event::listen('evolution.sCommerceAfterAddToCart', function($params) {
    // $params містить інформацію про доданий товар
    dd($params);
});
```

### sCommerceAfterRemoveFromCart

```php
Event::listen('evolution.sCommerceAfterRemoveFromCart', function($params) {
    // $params містить інформацію про видалений товар
    dd($params);
});
```

## Події замовлень

### sCommerceAfterOrderCreate

```php
Event::listen('evolution.sCommerceAfterOrderCreate', function($params) {
    // $params містить інформацію про створене замовлення
    dd($params);
});
```

### sCommerceAfterOrderUpdate

```php
Event::listen('evolution.sCommerceAfterOrderUpdate', function($params) {
    // $params містить інформацію про оновлене замовлення
    dd($params);
});
```

## Приклад використання

### Створення плагіну для обробки подій:

```php
<?php
// Файл: core/custom/packages/main/plugins/sCommerceEvents.php

use Illuminate\Support\Facades\Event;

// Обробка збереження товару
Event::listen('evolution.sCommerceAfterProductSave', function($params) {
    $product = $params['product'] ?? null;
    if ($product) {
        // Логування збереження товару
        Log::info('Товар збережено', ['product_id' => $product->id]);
        
        // Додаткова обробка (наприклад, оновлення індексу пошуку)
        // SearchIndex::update($product);
    }
});

// Обробка додавання товару в кошик
Event::listen('evolution.sCommerceAfterAddToCart', function($params) {
    $productId = $params['product_id'] ?? null;
    $quantity = $params['quantity'] ?? 1;
    
    if ($productId) {
        // Відправка аналітики
        Analytics::track('add_to_cart', [
            'product_id' => $productId,
            'quantity' => $quantity
        ]);
    }
});
```

### Реєстрація плагіну:

Додайте файл до списку плагінів у конфігурації Evolution CMS або зареєструйте його в `core/custom/config/app.php`:

```php
'plugins' => [
    'sCommerceEvents' => MODX_BASE_PATH . 'core/custom/packages/main/plugins/sCommerceEvents.php',
],
```
