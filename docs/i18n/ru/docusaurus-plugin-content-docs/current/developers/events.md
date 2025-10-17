---
id: events
title: События
sidebar_position: 9
---

События Evo предоставляют простую реализацию шаблона наблюдателя, позволяющую вам подписываться и прослушивать
различные события, которые происходят в вашем приложении. Используя события, удобно управлять
дополнительными параметрами sCommerce. Ниже приведен список зарезервированных событий.

## Расширение возможностей управления интерфейсом

### sCommerceManagerAddTabEvent

```php
Event::listen('evolution.sCommerceManagerAddTabEvent', function($params) {
    dd($params);
});
```

## Манипуляции с товарами

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

## События корзины

### sCommerceAfterAddToCart

```php
Event::listen('evolution.sCommerceAfterAddToCart', function($params) {
    // $params содержит информацию о добавленном товаре
    dd($params);
});
```

### sCommerceAfterRemoveFromCart

```php
Event::listen('evolution.sCommerceAfterRemoveFromCart', function($params) {
    // $params содержит информацию об удаленном товаре
    dd($params);
});
```

## События заказов

### sCommerceAfterOrderCreate

```php
Event::listen('evolution.sCommerceAfterOrderCreate', function($params) {
    // $params содержит информацию о созданном заказе
    dd($params);
});
```

### sCommerceAfterOrderUpdate

```php
Event::listen('evolution.sCommerceAfterOrderUpdate', function($params) {
    // $params содержит информацию об обновленном заказе
    dd($params);
});
```

## Пример использования

### Создание плагина для обработки событий:

```php
<?php
// Файл: core/custom/packages/main/plugins/sCommerceEvents.php

use Illuminate\Support\Facades\Event;

// Обработка сохранения товара
Event::listen('evolution.sCommerceAfterProductSave', function($params) {
    $product = $params['product'] ?? null;
    if ($product) {
        // Логирование сохранения товара
        Log::info('Товар сохранен', ['product_id' => $product->id]);
        
        // Дополнительная обработка (например, обновление поискового индекса)
        // SearchIndex::update($product);
    }
});

// Обработка добавления товара в корзину
Event::listen('evolution.sCommerceAfterAddToCart', function($params) {
    $productId = $params['product_id'] ?? null;
    $quantity = $params['quantity'] ?? 1;
    
    if ($productId) {
        // Отправка аналитики
        Analytics::track('add_to_cart', [
            'product_id' => $productId,
            'quantity' => $quantity
        ]);
    }
});
```

### Регистрация плагина:

Добавьте файл в список плагинов в конфигурации Evolution CMS или зарегистрируйте его в `core/custom/config/app.php`:

```php
'plugins' => [
    'sCommerceEvents' => MODX_BASE_PATH . 'core/custom/packages/main/plugins/sCommerceEvents.php',
],
```
