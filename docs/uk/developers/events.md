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

## Доменні події замовлень

Починаючи з 1.5.0, sCommerce викликає типізовані події замовлень із моделі `sOrder`. Вони охоплюють checkout на сайті, швидкі замовлення, сторінки оплати та callbacks, форму замовлення в менеджері, масові дії, повторні замовлення та будь-який інший код, що зберігає замовлення через Eloquent. У новому коді використовуйте їх замість `evolution.sCommerceAfterOrderSave`.

Як і цінові події, ці назви не мають префікса `evolution.`. Кожен слухач отримує один асоціативний масив.

| Подія | Коли викликається | Додаткові ключі |
|---|---|---|
| `sCommerce.OrderCreated` | Замовлення створено | немає |
| `sCommerce.OrderUpdated` | Змінено колонки замовлення | `changes` |
| `sCommerce.OrderStatusChanged` | Змінено `status` | `oldStatus`, `newStatus`, `oldStatusKey`, `newStatusKey` |
| `sCommerce.OrderPaymentStatusChanged` | Змінено `payment_status` | `oldPaymentStatus`, `newPaymentStatus`, `oldPaymentStatusKey`, `newPaymentStatusKey` |
| `sCommerce.OrderCancelled` | `status` став `ORDER_STATUS_CANCELED` | `previousStatus` |
| `sCommerce.OrderDeleted` | Замовлення м’яко видалено (soft delete) або видалено остаточно (force delete) | `forced` (`false` для soft delete) |

Кожен payload містить:

- `event`: об’єкт події (`Seiger\sCommerce\Events\Order*`);
- `order`: від’єднана копія `sOrder` закоміченого рядка, зроблена в момент фіксації зміни. Використовуйте її лише для читання даних. Не зберігайте її, а коли потрібен поточний стан замовлення, перечитайте його;
- `orderId`, `orderUuid`, `reference`, `status`, `paymentStatus`, `cost`, `currency`, `isQuick`, `siteKey` (`domain` замовлення);
- `source`, `actorId`, `correlationId`, `causationId`, `integrationActor`: контекст зміни (див. нижче);
- `eventId` (UUID v4) і `occurredAt` (UTC, наприклад `2026-10-20T08:15:30.123Z`).

`changes` зіставляє кожну змінену колонку з `['old' => ..., 'new' => ...]`; JSON-колонки декодуються. `updated_at` ніколи не включається. Ключі статусів — це неперекладені машинні імена, отримані з констант, наприклад `canceled`, `ready_for_shipment`, `paid` або `authorized`. Отримати їх можна через `sOrder::orderStatusKey()` і `sOrder::paymentStatusKey()`. Зміна статусу оплати — це зміна проєкції замовлення, а не доказ того, що кошти списано.

Нові замовлення отримують UUID у `s_orders.uuid`, якщо ця колонка існує. Для наявних замовлень значення заднім числом не заповнюються, тож вони можуть залишатися з `NULL`. Локальний `id` замовлення залишається стабільним ключем для інтеграцій; `uuid` і `reference` — додаткові ідентифікатори.

### Правила доставки подій

- Події викликаються лише після коміту транзакції бази даних. Відкочені зміни ніколи не породжують подій.
- Усередині області подій (event scope) події буферизуються до завершення найзовнішнішої області. Потім вони об’єднуються для кожного замовлення: `OrderCreated` поглинає подальші оновлення й містить фінальний знімок. Кілька подій `OrderUpdated`, `OrderStatusChanged` або `OrderPaymentStatusChanged` стають однією подією — від першого старого значення до останнього нового. Значення, яке повернулося до початкового, відкидається. `OrderCancelled` і `OrderDeleted` ніколи не об’єднуються.
- Для кожного замовлення події викликаються в такому порядку: створення, оновлення, зміна статусу, зміна статусу оплати, скасування, видалення. Замовлення зберігають порядок, у якому їх було вперше змінено.
- Виняток у слухачі записується в лог-канал `scommerce` і не перетворює успішний checkout або дію в менеджері на помилку. Наступні події все одно викликаються. Інші слухачі події, на якій стався виняток, після нього не викликаються.
- Не повертайте `false` зі слухача: Laravel припиняє виклик решти слухачів цієї події.
- Прямі запити `DB::table('s_orders')->update()` обходять події моделі.
- Виклик відбувається в тому самому PHP-процесі й не є надійним (durable) outbox. Якщо інтеграція не повинна втрачати зміни, їй потрібне власне надійне сховище та періодична звірка.

### Область подій

`Seiger\sCommerce\Events\EventScope` повідомляє слухачам, хто вніс зміну. sCommerce використовує такі значення `source`: `storefront`, `storefront_quick`, `storefront_pay`, `payment_callback`, `manager`, `manager_bulk_status`, `manager_bulk_menu`, `manager_repeat` і `system` (типове значення, коли жодну область не відкрито).

Код, який записує замовлення від імені інтеграції, має відкривати власну область. Тоді слухачі зможуть ігнорувати зміни, спричинені їхніми власними записами:

```php
use Seiger\sCommerce\Events\EventScope;

EventScope::run('erp', 0, function () use ($order) {
    $order->status = \Seiger\sCommerce\Models\sOrder::ORDER_STATUS_SHIPPED;
    $order->save();
}, correlationId: $flowId, causationId: $remoteEventId, integrationActor: 'erp-sync');
```

`correlationId` і `causationId` — непрозорі рядки довжиною до 64 символів. Вкладені області успадковують actor, correlation, causation та integration actor, якщо їх не вказано. `EventScope::run()` завжди закриває область; якщо ви використовуєте `EventScope::begin()`, викликайте `end()` у блоці `finally`.

### Приклад слухача

Розмістіть слухачі в `core/custom/packages/main/plugins/sCommerceEvents.php`, якщо service provider пакета main завантажує цю папку:

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.OrderStatusChanged', function (array $payload) {
    if ($payload['integrationActor'] === 'erp-sync') {
        return; // Ignore the echo of our own write.
    }

    if ($payload['newStatusKey'] === 'canceled') {
        // Notify an external system about $payload['orderId'] / $payload['orderUuid'].
    }
});
```

### Визначення підтримки

Перевіряйте наявність можливості, а не порівнюйте версії пакета:

```php
use Seiger\sCommerce\sCommerce;

$supported = class_exists(sCommerce::class)
    && defined(sCommerce::class . '::INTEGRATION_EVENTS_VERSION')
    && sCommerce::supportsIntegration('order-events');
```

`sCommerce::INTEGRATION_EVENTS_VERSION` (зараз `1`) змінюється лише тоді, коли payload змінюється несумісно. `sCommerce::integrationFeatures()` повертає список доступних ключів можливостей.

## Події журналу платежів

Починаючи з 1.5.0, кожен запис у журнал платежів (`s_order_payments`) викликає подію, незалежно від того, який код його виконав. Ці події підпорядковуються тим самим правилам доставки, що й події замовлень: лише після коміту, ніколи для відкочених записів і з тим самим контекстом області подій. Події платежів ніколи не об’єднуються.

| Подія | Коли викликається | Додаткові ключі |
|---|---|---|
| `sCommerce.PaymentRecorded` | Створено рядок журналу | немає |
| `sCommerce.PaymentStatusChanged` | Змінено `status` рядка журналу | `oldStatus`, `newStatus` |

Кожен payload містить:

- `event` і `payment` (від’єднана копія `sOrderPayment` лише для читання);
- `paymentId`, `orderId`, `orderUuid`, `sequence`, `kind`, `status`, `provider`, `providerRef`, `parentProviderRef`, `metadata`;
- `amount` і `amountMinor` (ціле число в мінорних одиницях, наприклад у центах), а також `currency`;
- ключі контексту `source`, `actorId`, `correlationId`, `causationId`, `integrationActor`, `eventId` і `occurredAt`.

Якщо зміна статусу також змінює статус оплати замовлення, після неї викликається `sCommerce.OrderPaymentStatusChanged`. `sCommerce::supportsIntegration('payment-ledger-api')` повідомляє про підтримку. API, що записує журнал, описано в розділі [Запис платежів](payments/index.md#запис-платежів-ledger-api).

## Додаткові події менеджера та frontend

Події менеджера слухають із префіксом evolution. Події ціноутворення мають точні імена sCommerce.* без цього префікса. Для frontend використовуйте наведені callbacks; структура відповіді залежить від операції.

- `evolution.sCommerceManagerTabsEvent`
- `evolution.sCommerceManagerSettingsSaveEvent`
- `evolution.sCommerceAfterProductContentSave`
- `evolution.sCommerceAfterOrderSave`: застаріла подія, лише для менеджера. Викликається після успішного збереження у формі замовлення менеджера, масової зміни статусу, масового «позначити як оплачене» та повторних замовлень; збережене замовлення передається як `item`. Для замовлень із сайту не викликається; використовуйте доменні події замовлень вище.

```javascript
sCommerce.onAddedToCart = (data) => console.log(data);
sCommerce.onRemovedFromCart = (data) => console.log(data);
sCommerce.onUpdatedCart = (data) => console.log(data);
sCommerce.onFastOrder = (data) => console.log(data);
sCommerce.onWishlist = (data) => console.log(data);
```
