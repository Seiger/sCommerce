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

## Цены в корзине

По умолчанию sCommerce использует исторический розничный resolver цены:

- `price_special` используется, если она больше `0` и меньше `price_regular`;
- иначе используется `price_regular`.

Оптовая цена управляется на сервере, а не через данные frontend-запроса. Это защищает корзину и checkout
от подмены цены на стороне клиента.

### Режим цены в сессии

Используйте фасад `sCart`, чтобы переключить текущую сессию покупателя на оптовые цены:

```php
use Seiger\sCommerce\Facades\sCart;

sCart::setPriceMode('wholesale');
```

Вернуть сессию к стандартной розничной цене:

```php
sCart::clearPriceMode();
```

Оптовая цена считается по тому же правилу, что и розничная:

- `price_opt_special` используется, если она больше `0` и меньше `price_opt_regular`;
- иначе используется `price_opt_regular`.

### sCommerce.cart.resolveProductPriceMode

Используйте это событие, чтобы изменить режим цены для конкретного товара поверх режима из сессии.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.cart.resolveProductPriceMode', function(array $payload) {
    $product = $payload['product'];

    if ((int)$product->id === 123) {
        return 'wholesale';
    }

    return null;
});
```

Payload содержит:

- `product`: модель текущего товара;
- `optionId`: ID опции в корзине;
- `priceMode`: режим из сессии до товарного override.

### sCommerce.cart.resolveProductPrice

Используйте это событие, когда проекту нужна полностью кастомная цена для конкретного товара.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.cart.resolveProductPrice', function(array $payload) {
    $product = $payload['product'];

    if ((int)$product->id === 123) {
        return [
            'priceAsFloat' => 77.50,
            'oldPriceAsFloat' => 100.00,
        ];
    }

    return null;
});
```

Listener может вернуть числовую цену или массив с любыми из этих ключей:

- `priceMode`
- `price`
- `priceAsFloat`
- `oldPrice`
- `oldPriceAsFloat`

Если форматированные `price` или `oldPrice` не переданы, sCommerce сформатирует их из числовых значений.

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
