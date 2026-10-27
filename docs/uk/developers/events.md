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

### Ціни з ПДВ або без ПДВ

Починаючи з 1.5.0, вітрина може показувати ціни з ПДВ (брутто) або без ПДВ (нетто). Ціни товарів зберігаються з ПДВ; це стосується лише відображення, а ціна, яка нараховується в кошику та під час оформлення замовлення, завжди дорівнює збереженій ціні.

Налаштування (**Налаштування → Товар**):

- **Ціни без ПДВ** (`product.vat_display_on`, типово вимкнено) вмикає функцію. Поки її вимкнено, наведені нижче налаштування приховано, ціни завжди показуються з ПДВ, ключі для відображення дорівнюють цінам до сплати, а `scommerce/vat-display` відповідає `404`;
- **Ставка ПДВ, %** (`product.vat_rate`, типово `0`): ставка, включена в ціни товарів. За `0` ціна нетто дорівнює ціні брутто;
- **Типові ціни на вітрині** (`product.vat_display`, `gross` або `net`, типово `gross`): типове значення для відвідувачів, які не зробили вибір, наприклад `net` для B2B-магазину. Впливає лише на вітрину;
- **Без ПДВ** у **Представлення списку товарів** (`products.show_field_price_net`, типово увімкнено): колонка списку товарів у менеджері з ціною без ПДВ і включеним у неї роздрібним акцизом (`sProduct::priceNetTo()`), показується, поки увімкнено ціни без ПДВ.

Типові значення для товарів, що продаються на міру, і округлення до кроку за одиницю налаштовуються в окремому блоці **Вимірювані кількості** на тій самій сторінці налаштувань.

Ціна нетто обчислюється як `round(gross / (1 + rate / 100), 2)`. Шаблони читають такі значення:

| Де | Ключі |
|---|---|
| `sProduct` (сторінки товарів) | `$product->displayPrice`, `displayPriceAsFloat`, `displayOldPrice`, `priceNet`, `priceNetAsFloat`, `priceGross`, `priceGrossAsFloat`, `oldPriceNet`, `vatRate`; `$product->vatPricing()` повертає всі ключі одразу |
| `sCommerce::productCards()` (списки товарів) | `displayPrice`, `displayOldPrice`, `priceNet`, `priceGross`, їхні варіанти `AsFloat`, `vatRate`, `vatDisplay` |
| Позиції `sCart::getMiniCart()` | ті самі ключі, а також `sumNet`, `sumNetAsFloat`, `displaySum`, `displaySumAsFloat` |
| Підсумки `sCart::getMiniCart()` | `totalSumNet`, `totalSumNetFormatted`, `totalSumDisplay`, `totalSumDisplayFormatted`, `vatDisplay` |

Наявні ключі `price`, `priceAsFloat`, `sum` і `totalSum` і надалі містять ціни, що нараховуються. Значення для відображення не зберігаються в рядках замовлення.

Перемкнути поточного відвідувача на сервері:

```php
use Seiger\sCommerce\Facades\sCart;

sCart::setVatDisplay('net');   // or 'gross'
sCart::getVatDisplay();        // 'net'
sCart::clearVatDisplay();      // back to the shop default
```

Перемикач на вітрині може надсилати `mode=gross|net` на `scommerce/vat-display` (маршрут `sCommerce.vatDisplay`). У відповіді повертаються `vatDisplay` і оновлений `miniCart`.

#### sCommerce.ResolveProductVatRate

Повертає іншу ставку для товару, наприклад знижену. Payload містить `product`, `optionId` і `rate` (ставку магазину). Використовується перша числова відповідь від `0` до `100`; поверніть `null`, щоб залишити ставку магазину.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolveProductVatRate', function (array $payload) {
    return str_starts_with((string) $payload['product']->sku, 'BOOK-') ? 10 : null;
});
```

#### sCommerce.ResolveVatDisplay

Перевизначає режим відображення, наприклад щоб показувати ціни нетто авторизованим бізнес-клієнтам. Payload містить `mode` (вибір із сесії або типове значення магазину). Використовується перша відповідь `gross` або `net`.

```php
Event::listen('sCommerce.ResolveVatDisplay', function (array $payload) {
    $userId = (int) evo()->getLoginUserID('web');
    return $userId && in_array($userId, config('shop.business_customers', []), true) ? 'net' : null;
});
```

`sCommerce::supportsIntegration('vat-display')` повідомляє про підтримку.

#### Роздрібний акциз

Деякі товари (наприклад, алкоголь, тютюн і пальне) обкладаються акцизним податком з роздрібної торгівлі, який нараховується на ціну з ПДВ і друкується в чеку окремим рядком. Збережена ціна вже містить його, тому ціна до сплати не змінюється.

- **Роздрібний акциз** (`product.retail_excise_on`, типово вимкнено) вмикає функцію. Поки її вимкнено, акциз не обчислюється й не зберігається, а налаштування його ставки приховано.
- **Ставка роздрібного акцизу, %** (`product.retail_excise_rate`, типово `0`) задає ставку для всього магазину. Зазвичай вона залишається `0`, а слухач `sCommerce.ResolveProductRetailExciseRate` повертає ставку для потрібних товарів. Payload містить `product`, `optionId` і `rate`; використовується перша числова відповідь від `0` до `100`.
- Для збереженої ціни P і ставки акцизу r акциз дорівнює `round(P × r / (100 + r), 2)`. Тоді ціна без ПДВ не містить і акцизу: `round((P − excise) / (1 + VAT / 100), 2)`. Наприклад, якщо ПДВ становить 23 %, а акциз 5 %, ціна 129.15 містить акциз 6.15, а ціна без ПДВ дорівнює 100.00.
- Ключі для відображення: `retailExciseRate`, `retailExciseAmount` і `retailExciseAmountAsFloat` у товарів, карток у списках і позицій кошика; `sumRetailExcise` і `sumRetailExciseAsFloat` у позицій кошика; `totalRetailExcise` і `totalRetailExciseFormatted` у мінікошику.
- Рядки замовлення таких товарів зберігають для чека `retail_excise_rate` і `retail_excise_amount` (акциз, включений у суму рядка). Сума перераховується, коли менеджер редагує рядки або зберігає повторне замовлення. Сторінка замовлення в менеджері та друкований документ показують її як **У тому числі роздрібний акциз**.

```php
Event::listen('sCommerce.ResolveProductRetailExciseRate', function (array $payload) {
    return in_array($payload['product']->sku, ['BEER-05', 'WINE-07'], true) ? 5 : null;
});
```

`sCommerce::supportsIntegration('retail-excise')` повідомляє про підтримку.

### sCommerce.ResolveProductDeposit

Повертає заставну вартість зворотної тари за одиницю товару, наприклад заставу за бутель. Викликається лише тоді, коли ввімкнено налаштування **Заставна вартість зворотної тари**. Payload містить `product`, `optionId`, `currency` і `deposit` (значення **Застава за тару** товару, переведене у `currency`). Використовується перша невід’ємна числова відповідь; поверніть `null`, щоб залишити його. Див. [Заставна вартість зворотної тари](payments/index.md#заставна-вартість-зворотної-тари).

```php
Event::listen('sCommerce.ResolveProductDeposit', function (array $payload) {
    return str_starts_with((string) $payload['product']->sku, 'WATER-19') ? 250 : null;
});
```

### sCommerce.CheckoutValidationRules

`getValidationRules(array $data, string $context = 'default')` — єдина точка
формування стандартних правил, правил доставки та плагінів. Звичайне
оформлення перевіряє користувача, доставку й оплату; швидке — кількість,
телефон та email. Вимкнені доставка/оплата не вимагають відповідного методу.
Обидва набори містять правила масиву товарів і цілої додатної кількості.

Laravel Event передає `data`, `rules` за посиланням та `context`
(типово `default`, для швидкого замовлення — `quick`). Змінюйте `rules` безпосередньо; повернення ігнорується.
Наступні слухачі бачать попередні зміни. Базовий набір обирається через switch; інші контексти отримують правила звичайного оформлення та доступні плагінам.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.CheckoutValidationRules', function (array $params): void {
    if ($params['context'] === 'default') $params['rules']['user.email'] = 'nullable|email';
    $params['rules']['products'][] = function ($field, $items, $fail) {
        // Правила проєкту; $fail('Повідомлення') відхиляє оформлення.
    };
});
```

`setOrderData()` зберігає часткову валідацію лише переданих полів.
`processOrder()` перевіряє підготовлені серверні дані тим самим повним набором
правил перед записом і оплатою, зокрема за прямого виклику. Швидке оформлення
викликає валідатор і цю подію один раз після підготовки серверних позицій.
Кількість у введенні та позиціях залишається без приведення до int; перетворення,
сума окремого товару та заставна вартість розраховуються після успішної перевірки.
`products` із запиту ігнорується: використовуються фактичний товар або кошик.
Часткові форми без товарів не запускають правило для products.
Атрибута мінімуму та окремого класу політики проєкту в пакеті немає.

Починаючи з 1.5.0 вітрина перевіряється перед збереженням замовлення:

- `processOrder()` наново розраховує позиції, збережені з моменту `initCheckout()`, за поточними цінами,
  публікацією, залишками та валютою. Якщо щось відрізняється, він зберігає поточні позиції й завершується
  помилкою з `error_code` `cart_changed` і новими `products` та `cost`; покупець підтверджує замовлення ще раз.
  Позиції понад залишок завершуються помилкою `out_of_stock`, зокрема у швидких замовленнях.
- Вартість доставки перераховується під час кожного `setOrderData()` і ще раз у `processOrder()` та
  конвертується з валюти вартості способу у валюту замовлення. Невдала спроба залишає дані
  оформлення без змін, тож повторна спроба ніколи не додає доставку двічі.
- Неопубліковані товари не можна додати до кошика чи замовити. Швидкі замовлення без `productId`
  оформлюють кошик; будь-яке значення, крім цілого числа, вважається невідомим товаром. Штучна кількість
  обмежена 1 000 000, а від'ємна ціна від слухача ніколи не зменшує суму.
- Маршрути `scommerce/process-order` і `scommerce/quick-order` повертають замовлення як
  `sOrder::toCustomerArray()` (без нотаток менеджера, історії та `meta`) і дозволяють `orders.rate_limit`
  замовлень з однієї IP-адреси за 10 хвилин (типово 10, 0 = без обмеження, **Налаштування → Кошик та замовлення**);
  понад це вони відповідають `429` з `error_code` `rate_limited`.

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
- `environment`: `live` або `test` для замовлень [довіреного тестового checkout](#довірений-контекст-checkout);
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

`Seiger\sCommerce\Events\EventScope` повідомляє слухачам, хто вніс зміну. sCommerce використовує такі значення `source`: `storefront`, `storefront_quick`, `storefront_pay`, `payment_callback`, `manager`, `manager_bulk_status`, `manager_bulk_menu`, `manager_repeat`, `manager_payment_action`, `manager_container_return`, `manager_shipment`, `offline_settlement` і `system` (типове значення, коли жодну область не відкрито).

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

`sCommerce::INTEGRATION_EVENTS_VERSION` (зараз `1`) змінюється лише тоді, коли payload змінюється несумісно. `sCommerce::integrationFeatures()` повертає список доступних ключів можливостей, наприклад `line-identity`, `fulfilment-modes`, `line-tax-data` і `checkout-context`.

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

## Подія графіка платежів

### sCommerce.ResolvePaymentSchedule

Викликається один раз для кожного нового замовлення (checkout і швидке замовлення) до його першого збереження. Слухач може розділити вартість замовлення на передоплату та фінальний платіж:

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolvePaymentSchedule', function (array $payload) {
    // $payload['order'] is the unsaved sOrder; $payload['schedule'] is passed by reference.
    $payload['schedule'] = [
        ['kind' => 'deposit', 'amount' => 30, 'due' => 'checkout'],
        ['kind' => 'final', 'amount' => $payload['order']->cost - 30, 'due' => 'manual'],
    ];
});
```

Без слухачів графік лишається порожнім, і повна вартість списується, як і раніше. Формат частин і те, як способи оплати отримують `payment_request`, описано в розділі [Графіки платежів](payments/index.md#графіки-платежів-передоплата-та-фінальний-платіж).

## Рядки замовлення, виконання та податки

Починаючи з 1.5.0, кожне нове замовлення перед першим збереженням проходить ці хуки в такому порядку: `sCommerce.ResolveCheckoutContext`, `sCommerce.ResolveOrderTaxes`, `sCommerce.BeforeOrderCreate`, `sCommerce.ResolvePaymentSchedule`. Після коміту настає `sCommerce.OrderCreated`.

### sCommerce.ResolveLineIdentity

Викликається в `OrderLineIdentity::resolve()` для кожного нового рядка замовлення: рядків кошика, швидкого замовлення одного товару, пошуку товарів у менеджері, а також рядків із менеджера або повторного замовлення, які ще не мають ідентичності. Payload містить `product`, `optionId` (id опції кошика, `0` — опції немає) та `identity`, що передається за посиланням.

Слухачі можуть змінювати лише `sku` (фактичний SKU, наприклад SKU варіанта) і `gtin`. Після цього sCommerce відновлює `line_key`, `product_id`, `option_id` і `product_sku`, тож їх неможливо підмінити. Усі ключі описано в розділі [Знімок рядка замовлення](integration.md#знімок-рядка-замовлення).

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolveLineIdentity', function (array $payload) {
    if ($payload['optionId'] === 3) {
        $payload['identity']['sku'] = $payload['product']->sku . '-RED';
    }
});
```

### sCommerce.ResolveProductFulfilment

Викликається в `sProduct::fulfilment($optionId)`. Payload містить `product`, `optionId` і `fulfilment` (`['shipping' => bool, 'inventory' => bool]`). Типово товари типів **Віртуальний** і **Послуга** не доставляються й не враховуються в залишках; усі інші товари доставляються та враховуються. Увімкніть ці типи в налаштуваннях товарів (**Доступні типи**), щоб їх можна було вибрати в товарі.

Перша відповідь-масив замінює типове значення; ключ, якого в ній немає, зберігає типове значення. Поверніть `null`, щоб залишити типове значення.

```php
Event::listen('sCommerce.ResolveProductFulfilment', function (array $payload) {
    if ($payload['product']->sku === 'GIFT-CARD') {
        return ['shipping' => false];
    }
    return null;
});
```

Результат зберігається в кожному рядку кошика й замовлення як `requires_shipping` і `tracks_inventory`:

- кошик обмежує кількість залишком лише для рядків, що враховуються, тому послугу із залишком `0` можна замовити;
- з увімкненим `basic.deliveries_on` checkout вимагає спосіб доставки, лише якщо хоча б один рядок потребує доставки. Інакше правило `delivery.method` видаляється до `sCommerce.CheckoutValidationRules`, вартість доставки не додається, а замовлення зберігає `delivery_info = {"method": "", "required": false, "cost": 0}`;
- `sCheckout::requiresDelivery()` повідомляє це рішення, а `initCheckout()` повертає його як `requiresDelivery`, тож шаблон може приховати крок доставки.

Рядки замовлень, створених до 1.5.0, не мають прапорців і вважаються такими, що доставляються та враховуються в залишках.

### sCommerce.ResolveOrderTaxes

Викликається один раз для кожного нового замовлення (checkout і швидке замовлення) до його першого збереження. sCommerce ніколи не розраховує податки: їх передає слухач. Payload містить `order` (незбережений `sOrder`), `lines` і `totals` (обидва передаються за посиланням) та `source` (`storefront` або `storefront_quick`).

Слухачі можуть задавати в кожному рядку `lines` такі необов’язкові ключі:

| Ключ | Тип |
|---|---|
| `tax_rate` | float, відсоток |
| `tax_code` | string, до 32 символів |
| `unit_price_net` | float, ціна одиниці без податку |
| `unit_price_gross` | float, ціна одиниці з податком |
| `tax_amount` | float, податок усього рядка |
| `prices_include_tax` | bool |
| `retail_excise_amount` | float, роздрібний акциз, включений у суму рядка |

`totals` зберігається в колонці `s_orders.totals`. Підтримувані ключі: `subtotal_net`, `subtotal_gross`, `shipping_net`, `shipping_gross`, `shipping_tax_rate`, `discount_gross`, `tax_total`, `grand_total`, `retail_excise_total` (float) і `prices_include_tax` (bool). sCommerce задає `retail_excise_amount` у рядках із роздрібним акцизом до цієї події, тому слухачі бачать його й можуть замінити.

Значення неправильного типу, інші ключі, додані рядки та зміни цін або кількостей ігноруються. `cost` замовлення ніколи не змінюється; якщо `totals.grand_total` відрізняється від нього більше ніж на `0.01`, у канал `scommerce` записується попередження. Без слухачів `totals` залишається `NULL`, а рядки не змінюються.

```php
Event::listen('sCommerce.ResolveOrderTaxes', function (array $payload) {
    foreach ($payload['lines'] as &$line) {
        $line['tax_rate'] = 23;
        $line['unit_price_gross'] = (float) $line['priceAsFloat'];
        $line['unit_price_net'] = round($line['unit_price_gross'] / 1.23, 2);
        $line['prices_include_tax'] = true;
    }
    unset($line);
    $payload['totals'] = ['grand_total' => (float) $payload['order']->cost, 'prices_include_tax' => true];
});
```

### sCommerce.BeforeOrderCreate

Викликається безпосередньо перед першим збереженням нового замовлення з checkout або швидкого замовлення. Payload містить `order` (незбережений `sOrder`), `data` (дані checkout без прапорців оточення клієнта) і `source`. Слухачі можуть задавати атрибути замовлення. Поля проєкту, наприклад дату бронювання або адресу виконання робіт, зберігайте в JSON-колонці `s_orders.meta`; сторінка замовлення в менеджері показує `meta` лише для читання.

```php
Event::listen('sCommerce.BeforeOrderCreate', function (array $payload) {
    $meta = $payload['order']->meta ?? [];
    $meta['booking'] = ['date' => $payload['data']['booking_date'] ?? '', 'slot' => '09:00-11:00'];
    $payload['order']->meta = $meta;
});
```

`meta.integration` зарезервовано для контексту checkout (див. нижче); слухачі не можуть його змінити.

## Довірений контекст checkout

Сайт завжди залишається в робочому режимі. Довірений користувач, наприклад обліковий запис QA, може оформлювати тестові замовлення на робочому сайті, поки всі інші відвідувачі продовжують робити реальні покупки. sCommerce визначає оточення кожного нового замовлення на сервері; дані, введені клієнтом, ніколи його не вибирають.

### sCommerce.ResolveCheckoutContext

Викликається checkout на сайті (`processOrder()`) і швидкими замовленнями до перевірки способу оплати, до збереження замовлення та до будь-якого платежу чи листа. Payload містить `data` (дані checkout), `source` і `context`, що передається за посиланням. Типовий контекст: `['environment' => 'live', 'actorId' => <logged-in web or manager user id, 0 for guests>]`.

Слухач, який перевірив права користувача на сервері, може задати:

- `environment`: `live` або `test`;
- `reference`: непрозорий рядок або масив розміром до 2 КБ, наприклад підключення до sandbox, яке слід використати;
- `testRecipients`: адреси e-mail, які отримують листи тестового замовлення;
- `refusal`: повідомлення, яке показується клієнтові. Checkout зупиняється до створення замовлення, платежу та будь-якого листа, наприклад коли тестовий платіж неможливо ізолювати для вибраного платіжного сервісу.

Потім sCommerce перевіряє контекст: будь-яке інше оточення відхиляється, а не вважається робочим, гості не можуть отримати `test`, а `actorId` і `source` завжди беруться із запиту. Ключі, за допомогою яких клієнт міг би заявити оточення (`environment`, `test`, `is_test`, `test_mode`, `sandbox`, `livemode`, `checkout_context`, `meta`, `integration` та їхні форми в camelCase), видаляються з даних до того, як їх побачать слухачі та способи оплати.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolveCheckoutContext', function (array $payload) {
    $userId = $payload['context']['actorId'];
    if ($userId > 0 && in_array($userId, config('shop.test_users', []), true)) {
        $payload['context']['environment'] = 'test';
        $payload['context']['reference'] = ['connection' => 'stripe-sandbox'];
        $payload['context']['testRecipients'] = ['qa@example.com'];
    }
});
```

Визначений контекст зберігається один раз у `s_orders.meta.integration` і більше ніколи не змінюється, хоч би які подальші записи, виходи із системи чи зміни ролей відбувалися. Читайте його через `CheckoutContext::of($order)`; замовлення без збереженого контексту вважаються робочими (`live`).

- Способи оплати отримують його як `checkout_context` у `processPayment()` — з checkout і з платіжних посилань, що передають `order`, незалежно від того, хто відкрив посилання. Вибирайте облікові дані sandbox або робочі облікові дані на його основі, а не за поточним користувачем.
- Тестове замовлення надсилає листи замовлення лише на `testRecipients` (нікому, якщо список порожній) і ніколи — магазину чи клієнтові.
- Тестові замовлення ніколи не зараховуються офлайн-зарахуванням і виключаються з метрик дашборду та списку замовлень. У списку замовлень є посилання **Тестові замовлення** для їх перегляду, а тестове замовлення позначене значком **ТЕСТ**.
- Скоупи `sOrder::live()` і `sOrder::test()` фільтрують запити, а `$order->isTest()` перевіряє окреме замовлення. Події замовлень передають `environment`.
- Запис журналу, у якого `metadata.environment` відрізняється від оточення замовлення, відхиляється (див. [Запис платежів](payments/index.md#метадані)).

Тестовим контекстам потрібна колонка `s_orders.meta`; виконайте міграції sCommerce. Без неї тестові checkout відхиляються, а робочі продовжують працювати. `sCommerce::supportsIntegration('checkout-context')` повідомляє про підтримку.

## Події відправлень

### sCommerce.ShipmentUpdated

Починаючи з 1.5.0, відправлення, яке створено або в якого змінилися статус, номер відстеження чи посилання, перевізник, послуга, позиції або дати, викликає `sCommerce.ShipmentUpdated`. Подія підпорядковується правилам доставки подій замовлень: лише після коміту, з контекстом області подій і ніколи не об’єднується. Повторний запис тих самих даних нічого не викликає.

Payload містить:

- `event` і `shipment` (від’єднана копія `sOrderShipment` лише для читання);
- `shipmentId`, `shipmentUuid`, `sequence`, `orderId`, `orderUuid`, `environment` (`live` або `test`, із замовлення);
- `created` (bool), `oldStatus` (`null` під час створення) і `newStatus`;
- `carrier`, `service`, `trackingNumber`, `trackingUrl`, `items`, `shippedAt`, `deliveredAt` (UTC, наприклад `2026-10-21T08:00:00Z`), `metadata`;
- `fullyShipped` і `fullyDelivered`: чи покриті в цей момент усі товари замовлення, що потребують доставки, надісланими або доставленими посилками;
- `shipmentSource` і `externalRef`: система, що записала відправлення, та його ідентифікатор у ній;
- ключі контексту `source`, `actorId`, `correlationId`, `causationId`, `integrationActor`, `eventId` і `occurredAt`. `source` — це область подій, як і для всіх інших подій; це не `shipmentSource` відправлення.

Подія відправлення викликається перед подіями замовлення, які вона спричиняє, наприклад `sCommerce.OrderStatusChanged` до *Відправлено*. API описано в розділі [Відправлення та відстеження](deliveries.md#відправлення-та-відстеження-api--події). `sCommerce::supportsIntegration('shipments')` повідомляє про підтримку.

## Події залишків

### sCommerce.ResolveProductStock

Починаючи з 1.5.0, кошик запитує в `Seiger\sCommerce\Services\StockResolver::available()`, скільки одиниць товару він може додати. Перемагає перше ціле число, повернене слухачем `sCommerce.ResolveProductStock`; `null` передає відповідь наступному слухачеві. Payload містить `product`, `modificationId` (завжди `0`: id опції кошика не є модифікацією), `quantity` (локальний `s_products.inventory`) і `context` (`optionId` та запитане `quantity`). Прив’язаний провайдер залишків опитується до слухачів, а якщо ніхто не відповів, використовується локальний залишок.

```php
Event::listen('sCommerce.ResolveProductStock', function (array $payload) {
    return $payload['product']->sku === 'GIFT-CARD' ? 1000 : null;
});
```

### Провайдер залишків

Пакет може зробити джерелом залишків зовнішню систему, наприклад ERP, прив’язавши `Seiger\sCommerce\Interfaces\StockProviderInterface` у своєму сервіс-провайдері: `app()->bind(StockProviderInterface::class, ErpStock::class)`. Використовується не більше одного провайдера.

- `available(sProduct $product, int $modificationId = 0, array $context = []): ?int` повертає кількість або `null`, щоб використати слухачів, а потім локальний залишок.
- `commit(sOrder $order): bool` і `release(sOrder $order): bool` викликаються, коли ввімкнено списання залишку за замовленнями (див. [Налаштування](../configuration.md#залишки)). Поверніть `true`, якщо провайдер обробив замовлення; тоді sCommerce не змінює локальні залишки. Залишок замовлення повертає той, хто його списав: замовлення, залишок якого sCommerce списав локально, відновлюється локально без звернення до провайдера.
- Обидва методи можуть виконатися повторно після помилки або збою, тому вони мають бути ідемпотентними в межах замовлення, наприклад із ключем за UUID замовлення.

### Запис залишків

`Seiger\sCommerce\Services\StockWriter::set($productOrId, $quantity, 0, 'erp', $availability)` встановлює залишок і, якщо передано, доступність (`0` немає в наявності, `1` в наявності, `2` під замовлення) одного товару. Варіант GROUP — це окремий рядок товару: передавайте товар варіанта. Ненульовий `modificationId` відхиляється, бо рядки `s_product_modifications` не мають власної ідентичності. Запис, який нічого не змінює, не створює події. Ядро не кешує значення залишків, тому жоден кеш не очищується; слухачі `sCommerce.ProductStockChanged` можуть очищати власні кеші.

### sCommerce.ProductStockChanged

Починаючи з 1.5.0, зміна `s_products.inventory` або `s_products.availability` викликає `sCommerce.ProductStockChanged`, хоч би хто її записав: форма товару, CSV-імпорт, `StockWriter` або списання залишку за замовленням. Подія підпорядковується правилам доставки подій замовлень: лише після коміту, з контекстом області подій. Зміни одного товару в одній області подій об’єднуються від першого старого до останнього нового значення, а об’єднання, яке нічого не змінює, відкидається.

Payload містить `event`, `productId`, `modificationId` (завжди `0`), `sku`, `oldQuantity`, `newQuantity`, `oldAvailability`, `newAvailability`, `stockSource` (хто записав: `manager`, `import`, `order`, джерело, передане в `StockWriter`, або джерело області подій для інших збережень моделі) і ключі контексту `source`, `actorId`, `correlationId`, `causationId`, `integrationActor`, `eventId` і `occurredAt`. Списання та повернення залишків за замовленнями виконуються в області подій із джерелом `inventory`.

Для товарів, що продаються на вагу, за довжиною, об’ємом або кількістю, усі кількості залишку (залишок, події, списання та `available()` провайдера) вказуються в базових одиницях: грамах, міліметрах, мілілітрах або штуках. Див. [Вимірювані кількості](measured-quantities.md#залишки-та-відправлення).

### Залишок відстежує редагування замовлень

Починаючи з 1.5.0, коли менеджер змінює товари замовлення, залишок якого sCommerce списав локально, залишок змінюється в тому самому запиті: `StockCommitter::adjust($order)` списує або повертає різницю для змінених, доданих і видалених рядків — як для поштучних, так і для вимірюваних товарів. Фактичні кількості вимірюваних рядків коригують залишок так само. Якщо для збільшення бракує залишку товару, редагування не зберігається. Замовлення, списані провайдером залишків, локально не коригуються; провайдер отримує `sCommerce.OrderUpdated`. Подальше повернення відновлює рівно скориговані кількості. До 1.5.0 редагування в менеджері не змінювало списаний залишок.

`sCommerce::supportsIntegration('stock-provider')` повідомляє про підтримку.

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
