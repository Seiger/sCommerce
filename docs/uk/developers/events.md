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

`sPriceResolver` є єдиною точкою визначення ціни для моделі товару, каталогу,
кошика та checkout. Якщо встановлений `sPricing`, він отримує контекст поточного
користувача й повертає застосовну персональну або накопичувальну ціну. Кешована
сторінка залишається нейтральною, а контекстна ціна накладається в межах поточного
запиту, тому її не може отримати інший користувач зі спільного кешу.

Якщо `sPricing` не встановлений або не повернув застосовної ціни, sCommerce
використовує історичний роздрібний resolver:

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

### sCommerce.CheckoutValidationRules

Викликається в `sCheckout::getValidationRules(array $data)` після об’єднання базових правил із правилами вибраного способу доставки.

- `data` — вхідні дані, передані в `getValidationRules()`.
- `rules` — підсумковий масив правил за посиланням. Можна додавати й замінювати ключі або видаляти їх через `unset()`.

Розмістіть слухач у `core/custom/packages/main/plugins/sCommerceEvents.php`, якщо service provider пакета main завантажує цю папку. Змінюйте `rules` безпосередньо: повернений масив не застосовується. Кожен наступний слухач бачить зміни попередніх і може їх перевизначити.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.CheckoutValidationRules', function (array $params) {
    $rules = &$params['rules'];
    $data = $params['data'];

    $rules['user.email'] = 'nullable|email|max:255';
    $rules['user.first_name'] = ['required', 'string', 'max:100'];
    unset($rules['user.middle_name']);
});
```

`setOrderData()` потім залишає лише правила для ключів, наявних у переданих даних. Тому доданий `required` не робить відсутнє поле обов’язковим під час такого часткового оновлення. Видалення правила також виключає поле з цього шляху валідації, а не забезпечує збереження довільних полів. Швидке замовлення має власні правила та не викликає цей хук.

Для цих трьох подій використовуйте точні назви з цієї сторінки, без префікса `evolution.`. Попередні цінові події `evolution.sCommerceResolveProductPriceMode` і `evolution.sCommerceResolveProductPrice` більше не викликаються; оновлюйте проєктні слухачі та sPricing разом із sCommerce.

### sCommerce.ResolveProductPriceMode

Викликається в `sCart` під час додавання товару й отримання мінікошика та в `sCheckout` під час визначення ціни товару для швидкого замовлення. Використовується перша відповідь із непорожнім рядком: `wholesale` і `opt` нормалізуються до `wholesale`, інші рядки — до `auto` (регістр і пробіли по краях ігноруються). Поверніть `null`, щоб залишити режим із сесії, якщо інший слухач не перевизначить його. Подія не змінює сесію.

Використовуйте цю подію, щоб змінити режим ціни для конкретного товару поверх режиму із сесії.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolveProductPriceMode', function(array $payload) {
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

### sCommerce.ResolveProductPrice

Викликається в `sPriceResolver::resolve()` після визначення базової/акційної ціни. Параметри: `product` — об’єкт товару; `optionId` — ID опції, типово `0`; `priceMode` — `auto` або `wholesale`; `currency` — код валюти відображення; `pricing` — початковий масив із ключами `priceMode`, `priceAsFloat`, `oldPriceAsFloat`, `price`, `oldPrice`. Суми потрібно повертати у валюті відображення.

Застосовується перша числова відповідь або масив; відповіді різних слухачів не об’єднуються. Число задає поточну ціну й скидає стару до `0`. Масив замінює лише передані підтримувані ключі. Поверніть `null`, щоб не змінювати ціну й дозволити іншому слухачу надати її. Усі слухачі викликаються до аналізу відповідей. `sPricing` слухає саме цю подію та повертає `priceAsFloat` і `oldPriceAsFloat`; для додаткового постачальника цін враховуйте порядок реєстрації.


Використовуйте цю подію, коли проєкту потрібна повністю кастомна ціна для конкретного товару.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolveProductPrice', function(array $payload) {
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
