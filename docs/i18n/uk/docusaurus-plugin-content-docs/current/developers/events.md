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

## Ціни в кошику

Типово sCommerce використовує історичний роздрібний resolver ціни:

- `price_special` використовується, якщо вона більша за `0` і менша за `price_regular`;
- інакше використовується `price_regular`.

Оптова ціна керується на сервері, а не через дані з frontend-запиту. Це захищає кошик і checkout від
підміни ціни на стороні клієнта.

### Режим ціни в сесії

Використовуйте фасад `sCart`, щоб перемкнути поточну сесію покупця на оптові ціни:

```php
use Seiger\sCommerce\Facades\sCart;

sCart::setPriceMode('wholesale');
```

Повернути сесію до типової роздрібної ціни:

```php
sCart::clearPriceMode();
```

Оптова ціна рахується за тим самим правилом, що й роздрібна:

- `price_opt_special` використовується, якщо вона більша за `0` і менша за `price_opt_regular`;
- інакше використовується `price_opt_regular`.

### sCommerceResolveProductPriceMode

Використовуйте цю подію, щоб змінити режим ціни для конкретного товару поверх режиму із сесії.

```php
use Illuminate\Support\Facades\Event;

Event::listen('evolution.sCommerceResolveProductPriceMode', function(array $payload) {
    $product = $payload['product'];

    if ((int)$product->id === 123) {
        return 'wholesale';
    }

    return null;
});
```

Payload містить:

- `product`: модель поточного товару;
- `optionId`: ID опції в кошику;
- `priceMode`: режим із сесії до товарного override.

### sCommerceResolveProductPrice

Використовуйте цю подію, коли проєкту потрібна повністю кастомна ціна для конкретного товару.

```php
use Illuminate\Support\Facades\Event;

Event::listen('evolution.sCommerceResolveProductPrice', function(array $payload) {
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

Listener може повернути числову ціну або масив із будь-якими з цих ключів:

- `priceMode`
- `price`
- `priceAsFloat`
- `oldPrice`
- `oldPriceAsFloat`

Якщо форматовані `price` або `oldPrice` не передані, sCommerce сформатує їх із числових значень.

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
