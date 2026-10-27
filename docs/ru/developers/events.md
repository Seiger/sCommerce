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

`sPriceResolver` — единая точка определения цены для модели товара, витрины,
корзины и checkout. Если установлен `sPricing`, он получает контекст текущего
пользователя и может вернуть персональную или накопительную цену. Кэшированная
страница остаётся нейтральной, а контекстная цена применяется в запросе, поэтому
общий кэш не передаёт цену одного клиента другому.

Если `sPricing` недоступен или не вернул применимую цену, sCommerce использует
исторический розничный resolver цены:

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

### Цены с НДС или без НДС

Начиная с 1.5.0, витрина может показывать цены с НДС (брутто) или без НДС (нетто). Цены товаров хранятся с НДС; это касается только отображения, а цена, которая начисляется в корзине и при оформлении заказа, всегда равна сохранённой цене.

Настройки (**Настройки → Товар**):

- **Цены без НДС** (`product.vat_display_on`, по умолчанию выключено) включает функцию. Пока она выключена, настройки ниже скрыты, цены всегда показываются с НДС, ключи для отображения равны ценам к оплате, а `scommerce/vat-display` отвечает `404`;
- **Ставка НДС, %** (`product.vat_rate`, по умолчанию `0`): ставка, включённая в цены товаров. При `0` цена нетто равна цене брутто;
- **Цены на витрине по умолчанию** (`product.vat_display`, `gross` или `net`, по умолчанию `gross`): значение по умолчанию для посетителей, которые не сделали выбор, например `net` для B2B-магазина. Влияет только на витрину;
- **Без НДС** в **Представление списка товаров** (`products.show_field_price_net`, по умолчанию включено): колонка списка товаров в менеджере с ценой без НДС и включённым в неё розничным акцизом (`sProduct::priceNetTo()`), показывается, пока включены цены без НДС.

Значения по умолчанию для товаров, продаваемых по мере, и округление до шага за единицу настраиваются в отдельном блоке **Измеряемые количества** на той же странице настроек.

Цена нетто рассчитывается как `round(gross / (1 + rate / 100), 2)`. Шаблоны читают такие значения:

| Где | Ключи |
|---|---|
| `sProduct` (страницы товаров) | `$product->displayPrice`, `displayPriceAsFloat`, `displayOldPrice`, `priceNet`, `priceNetAsFloat`, `priceGross`, `priceGrossAsFloat`, `oldPriceNet`, `vatRate`; `$product->vatPricing()` возвращает все ключи сразу |
| `sCommerce::productCards()` (списки товаров) | `displayPrice`, `displayOldPrice`, `priceNet`, `priceGross`, их варианты `AsFloat`, `vatRate`, `vatDisplay` |
| Позиции `sCart::getMiniCart()` | те же ключи, а также `sumNet`, `sumNetAsFloat`, `displaySum`, `displaySumAsFloat` |
| Итоги `sCart::getMiniCart()` | `totalSumNet`, `totalSumNetFormatted`, `totalSumDisplay`, `totalSumDisplayFormatted`, `vatDisplay` |

Существующие ключи `price`, `priceAsFloat`, `sum` и `totalSum` по-прежнему содержат начисляемые цены. Значения для отображения не сохраняются в строках заказа.

Переключить текущего посетителя на сервере:

```php
use Seiger\sCommerce\Facades\sCart;

sCart::setVatDisplay('net');   // or 'gross'
sCart::getVatDisplay();        // 'net'
sCart::clearVatDisplay();      // back to the shop default
```

Переключатель на витрине может отправлять `mode=gross|net` на `scommerce/vat-display` (маршрут `sCommerce.vatDisplay`). В ответе возвращаются `vatDisplay` и обновлённый `miniCart`.

#### sCommerce.ResolveProductVatRate

Возвращает другую ставку для товара, например пониженную. Payload содержит `product`, `optionId` и `rate` (ставку магазина). Используется первый числовой ответ от `0` до `100`; верните `null`, чтобы оставить ставку магазина.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolveProductVatRate', function (array $payload) {
    return str_starts_with((string) $payload['product']->sku, 'BOOK-') ? 10 : null;
});
```

#### sCommerce.ResolveVatDisplay

Переопределяет режим отображения, например чтобы показывать цены нетто авторизованным бизнес-клиентам. Payload содержит `mode` (выбор из сессии или значение магазина по умолчанию). Используется первый ответ `gross` или `net`.

```php
Event::listen('sCommerce.ResolveVatDisplay', function (array $payload) {
    $userId = (int) evo()->getLoginUserID('web');
    return $userId && in_array($userId, config('shop.business_customers', []), true) ? 'net' : null;
});
```

`sCommerce::supportsIntegration('vat-display')` сообщает о поддержке.

#### Розничный акциз

Некоторые товары (например, алкоголь, табак и топливо) облагаются розничным акцизом, который начисляется на цену с НДС и печатается в чеке отдельной строкой. Сохранённая цена уже включает его, поэтому цена к оплате не меняется.

- **Розничный акциз** (`product.retail_excise_on`, по умолчанию выключено) включает функцию. Пока она выключена, акциз не рассчитывается и не сохраняется, а настройка его ставки скрыта.
- **Ставка розничного акциза, %** (`product.retail_excise_rate`, по умолчанию `0`) задаёт ставку для всего магазина. Обычно она остаётся `0`, а слушатель `sCommerce.ResolveProductRetailExciseRate` возвращает ставку для нужных товаров. Payload содержит `product`, `optionId` и `rate`; используется первый числовой ответ от `0` до `100`.
- Для сохранённой цены P и ставки акциза r акциз равен `round(P × r / (100 + r), 2)`. Тогда цена без НДС не включает и акциз: `round((P − excise) / (1 + VAT / 100), 2)`. Например, при НДС 23 % и акцизе 5 % цена 129.15 содержит акциз 6.15, а цена без НДС равна 100.00.
- Ключи для отображения: `retailExciseRate`, `retailExciseAmount` и `retailExciseAmountAsFloat` у товаров, карточек в списках и позиций корзины; `sumRetailExcise` и `sumRetailExciseAsFloat` у позиций корзины; `totalRetailExcise` и `totalRetailExciseFormatted` в мини-корзине.
- Строки заказа таких товаров сохраняют для чека `retail_excise_rate` и `retail_excise_amount` (акциз, включённый в сумму строки). Сумма пересчитывается, когда менеджер редактирует строки или сохраняет повторный заказ. Страница заказа в менеджере и печатный документ показывают её как **В том числе розничный акциз**.

```php
Event::listen('sCommerce.ResolveProductRetailExciseRate', function (array $payload) {
    return in_array($payload['product']->sku, ['BEER-05', 'WINE-07'], true) ? 5 : null;
});
```

`sCommerce::supportsIntegration('retail-excise')` сообщает о поддержке.

### sCommerce.ResolveProductDeposit

Возвращает залог за возвратную тару за единицу товара, например залог за бутыль. Вызывается только при включённой настройке **Залог за возвратную тару**. Payload содержит `product`, `optionId`, `currency` и `deposit` (значение **Залог за тару** товара, пересчитанное в `currency`). Используется первый неотрицательный числовой ответ; верните `null`, чтобы оставить его. См. [Залог за возвратную тару](payments/index.md#залог-за-возвратную-тару).

```php
Event::listen('sCommerce.ResolveProductDeposit', function (array $payload) {
    return str_starts_with((string) $payload['product']->sku, 'WATER-19') ? 250 : null;
});
```

### sCommerce.CheckoutValidationRules

`getValidationRules(array $data, string $context = 'default')` — единая точка
формирования стандартных правил, правил доставки и плагинов. Обычное
оформление проверяет пользователя, доставку и оплату; быстрое — количество,
телефон и email. Отключённые доставка/оплата не требуют соответствующего метода.
Оба набора содержат правила массива товаров и целого положительного количества.

Laravel Event передаёт `data`, `rules` по ссылке и `context`
(по умолчанию `default`, для быстрого заказа — `quick`). Изменяйте `rules` непосредственно; результат возврата
игнорируется. Следующие слушатели видят предыдущие изменения. Базовый набор выбирается через switch; другие контексты получают правила обычного оформления и доступны плагинам.

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.CheckoutValidationRules', function (array $params): void {
    if ($params['context'] === 'default') $params['rules']['user.email'] = 'nullable|email';
    $params['rules']['products'][] = function ($field, $items, $fail) {
        // Правила проекта; $fail('Сообщение') отклоняет оформление.
    };
});
```

`setOrderData()` сохраняет частичную проверку только переданных полей.
`processOrder()` проверяет подготовленные серверные данные тем же полным
набором правил до записи и оплаты, в том числе при прямом вызове. Быстрое
оформление вызывает валидатор и это событие один раз после подготовки серверных
позиций. Количество во вводе и позициях сохраняется без приведения к int;
преобразование, сумма отдельного товара и залог рассчитываются после проверки. `products` из запроса игнорируется: используются фактический товар или
корзина. Частичная форма без товаров не запускает правило products.
Атрибута минимума и отдельного класса политики проекта в пакете нет.

Начиная с 1.5.0 витрина проверяется перед сохранением заказа:

- `processOrder()` заново рассчитывает позиции, сохранённые с момента `initCheckout()`, по текущим ценам,
  публикации, остаткам и валюте. Если что-то отличается, он сохраняет текущие позиции и завершается ошибкой
  с `error_code` `cart_changed` и новыми `products` и `cost`; покупатель подтверждает заказ ещё раз.
  Позиции сверх остатка завершаются ошибкой `out_of_stock`, в том числе в быстрых заказах.
- Стоимость доставки пересчитывается при каждом `setOrderData()` и ещё раз в `processOrder()` и
  конвертируется из валюты стоимости способа в валюту заказа. Неудачная попытка оставляет данные
  оформления без изменений, поэтому повторная попытка никогда не добавляет доставку дважды.
- Неопубликованные товары нельзя добавить в корзину или заказать. Быстрые заказы без `productId`
  оформляют корзину; любое значение, кроме целого числа, считается неизвестным товаром. Штучное количество
  ограничено 1 000 000, а отрицательная цена от слушателя никогда не уменьшает сумму.
- Маршруты `scommerce/process-order` и `scommerce/quick-order` возвращают заказ как
  `sOrder::toCustomerArray()` (без заметок менеджера, истории и `meta`) и допускают `orders.rate_limit`
  заказов с одного IP-адреса за 10 минут (по умолчанию 10, 0 = без ограничения, **Настройки → Корзина и заказы**);
  сверх этого они отвечают `429` с `error_code` `rate_limited`.

Для этих трёх событий используйте точные имена с этой страницы, без префикса `evolution.`. Предыдущие ценовые события `evolution.sCommerceResolveProductPriceMode` и `evolution.sCommerceResolveProductPrice` больше не вызываются; обновляйте слушатели проекта и sPricing вместе с sCommerce.

### sCommerce.ResolveProductPriceMode

Вызывается в `sCart` при добавлении товара и получении мини-корзины, а также в `sCheckout` при определении цены товара для быстрого заказа. Используется первый непустой строковый ответ: `wholesale` и `opt` нормализуются в `wholesale`, остальные строки — в `auto` (регистр и пробелы по краям игнорируются). Верните `null`, чтобы сохранить режим сессии, если другой слушатель его не переопределит. Событие не изменяет сессию.

Используйте это событие, чтобы изменить режим цены для конкретного товара поверх режима из сессии.

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

Payload содержит:

- `product`: модель текущего товара;
- `optionId`: ID опции в корзине;
- `priceMode`: режим из сессии до товарного override.

### sCommerce.ResolveProductPrice

Вызывается в `sPriceResolver::resolve()` после определения базовой/акционной цены. Параметры: `product` — объект товара; `optionId` — ID опции, по умолчанию `0`; `priceMode` — `auto` или `wholesale`; `currency` — код валюты отображения; `pricing` — исходный массив с ключами `priceMode`, `priceAsFloat`, `oldPriceAsFloat`, `price`, `oldPrice`. Суммы нужно возвращать в валюте отображения.

Применяется первый числовой ответ или массив; ответы разных слушателей не объединяются. Число задаёт текущую цену и сбрасывает старую в `0`. Массив заменяет только переданные поддерживаемые ключи. Верните `null`, чтобы не менять цену и позволить другому слушателю задать её. Все слушатели вызываются до анализа ответов. `sPricing` слушает это событие и возвращает `priceAsFloat` и `oldPriceAsFloat`; учитывайте порядок регистрации дополнительных поставщиков цен.


Используйте это событие, когда проекту нужна полностью кастомная цена для конкретного товара.

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

## Доменные события заказов

Начиная с 1.5.0, sCommerce вызывает типизированные события заказов из модели `sOrder`. Они охватывают checkout на сайте, быстрые заказы, страницы оплаты и callbacks, форму заказа в менеджере, массовые действия, повторные заказы и любой другой код, который сохраняет заказы через Eloquent. В новом коде используйте их вместо `evolution.sCommerceAfterOrderSave`.

Как и ценовые события, эти имена не имеют префикса `evolution.`. Каждый слушатель получает один ассоциативный массив.

| Событие | Когда вызывается | Дополнительные ключи |
|---|---|---|
| `sCommerce.OrderCreated` | Заказ создан | нет |
| `sCommerce.OrderUpdated` | Изменены колонки заказа | `changes` |
| `sCommerce.OrderStatusChanged` | Изменён `status` | `oldStatus`, `newStatus`, `oldStatusKey`, `newStatusKey` |
| `sCommerce.OrderPaymentStatusChanged` | Изменён `payment_status` | `oldPaymentStatus`, `newPaymentStatus`, `oldPaymentStatusKey`, `newPaymentStatusKey` |
| `sCommerce.OrderCancelled` | `status` стал `ORDER_STATUS_CANCELED` | `previousStatus` |
| `sCommerce.OrderDeleted` | Заказ мягко удалён (soft delete) или удалён окончательно (force delete) | `forced` (`false` для soft delete) |

Каждый payload содержит:

- `event`: объект события (`Seiger\sCommerce\Events\Order*`);
- `order`: отсоединённая копия `sOrder` закоммиченной строки, сделанная в момент фиксации изменения. Используйте её только для чтения данных. Не сохраняйте её, а когда нужно текущее состояние заказа, перечитайте его;
- `orderId`, `orderUuid`, `reference`, `status`, `paymentStatus`, `cost`, `currency`, `isQuick`, `siteKey` (`domain` заказа);
- `environment`: `live` или `test` для заказов [доверенного тестового checkout](#доверенный-контекст-checkout);
- `source`, `actorId`, `correlationId`, `causationId`, `integrationActor`: контекст изменения (см. ниже);
- `eventId` (UUID v4) и `occurredAt` (UTC, например `2026-10-20T08:15:30.123Z`).

`changes` сопоставляет каждую изменённую колонку с `['old' => ..., 'new' => ...]`; JSON-колонки декодируются. `updated_at` никогда не включается. Ключи статусов — это непереведённые машинные имена, полученные из констант, например `canceled`, `ready_for_shipment`, `paid` или `authorized`. Получить их можно через `sOrder::orderStatusKey()` и `sOrder::paymentStatusKey()`. Изменение статуса оплаты — это изменение проекции заказа, а не доказательство того, что деньги списаны.

Новые заказы получают UUID в `s_orders.uuid`, если эта колонка существует. Для существующих заказов значения задним числом не заполняются, поэтому у них может остаться `NULL`. Локальный `id` заказа остаётся стабильным ключом для интеграций; `uuid` и `reference` — дополнительные идентификаторы.

### Правила доставки событий

- События вызываются только после коммита транзакции базы данных. Откаченные изменения никогда не порождают событий.
- Внутри области событий (event scope) события буферизуются до завершения самой внешней области. Затем они объединяются для каждого заказа: `OrderCreated` поглощает последующие обновления и содержит итоговый снимок. Несколько событий `OrderUpdated`, `OrderStatusChanged` или `OrderPaymentStatusChanged` становятся одним событием — от первого старого значения до последнего нового. Значение, вернувшееся к исходному, отбрасывается. `OrderCancelled` и `OrderDeleted` никогда не объединяются.
- Для каждого заказа события вызываются в таком порядке: создание, обновление, изменение статуса, изменение статуса оплаты, отмена, удаление. Заказы сохраняют порядок, в котором они были впервые изменены.
- Исключение в слушателе записывается в лог-канал `scommerce` и не превращает успешный checkout или действие в менеджере в ошибку. Последующие события всё равно вызываются. Другие слушатели события, на котором произошло исключение, после него не вызываются.
- Не возвращайте `false` из слушателя: Laravel прекращает вызов остальных слушателей этого события.
- Прямые запросы `DB::table('s_orders')->update()` обходят события модели.
- Вызов происходит в том же PHP-процессе и не является надёжным (durable) outbox. Если интеграция не должна терять изменения, ей нужно собственное надёжное хранилище и периодическая сверка.

### Область событий

`Seiger\sCommerce\Events\EventScope` сообщает слушателям, кто внёс изменение. sCommerce использует такие значения `source`: `storefront`, `storefront_quick`, `storefront_pay`, `payment_callback`, `manager`, `manager_bulk_status`, `manager_bulk_menu`, `manager_repeat`, `manager_payment_action`, `manager_container_return`, `manager_shipment`, `offline_settlement` и `system` (значение по умолчанию, когда ни одна область не открыта).

Код, который записывает заказы от имени интеграции, должен открывать собственную область. Тогда слушатели смогут игнорировать изменения, вызванные их собственными записями:

```php
use Seiger\sCommerce\Events\EventScope;

EventScope::run('erp', 0, function () use ($order) {
    $order->status = \Seiger\sCommerce\Models\sOrder::ORDER_STATUS_SHIPPED;
    $order->save();
}, correlationId: $flowId, causationId: $remoteEventId, integrationActor: 'erp-sync');
```

`correlationId` и `causationId` — непрозрачные строки длиной до 64 символов. Вложенные области наследуют actor, correlation, causation и integration actor, если они не указаны. `EventScope::run()` всегда закрывает область; если вы используете `EventScope::begin()`, вызывайте `end()` в блоке `finally`.

### Пример слушателя

Разместите слушатели в `core/custom/packages/main/plugins/sCommerceEvents.php`, если service provider пакета main загружает эту папку:

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

### Определение поддержки

Проверяйте наличие возможности, а не сравнивайте версии пакета:

```php
use Seiger\sCommerce\sCommerce;

$supported = class_exists(sCommerce::class)
    && defined(sCommerce::class . '::INTEGRATION_EVENTS_VERSION')
    && sCommerce::supportsIntegration('order-events');
```

`sCommerce::INTEGRATION_EVENTS_VERSION` (сейчас `1`) меняется только тогда, когда payload изменяется несовместимо. `sCommerce::integrationFeatures()` возвращает список доступных ключей возможностей, например `line-identity`, `fulfilment-modes`, `line-tax-data` и `checkout-context`.

## События журнала платежей

Начиная с 1.5.0, каждая запись в журнал платежей (`s_order_payments`) вызывает событие, независимо от того, какой код её выполнил. Эти события подчиняются тем же правилам доставки, что и события заказов: только после коммита, никогда для откаченных записей и с тем же контекстом области событий. События платежей никогда не объединяются.

| Событие | Когда вызывается | Дополнительные ключи |
|---|---|---|
| `sCommerce.PaymentRecorded` | Создана строка журнала | нет |
| `sCommerce.PaymentStatusChanged` | Изменён `status` строки журнала | `oldStatus`, `newStatus` |

Каждый payload содержит:

- `event` и `payment` (отсоединённая копия `sOrderPayment` только для чтения);
- `paymentId`, `orderId`, `orderUuid`, `sequence`, `kind`, `status`, `provider`, `providerRef`, `parentProviderRef`, `metadata`;
- `amount` и `amountMinor` (целое число в минорных единицах, например в центах), а также `currency`;
- ключи контекста `source`, `actorId`, `correlationId`, `causationId`, `integrationActor`, `eventId` и `occurredAt`.

Если изменение статуса также меняет статус оплаты заказа, после него вызывается `sCommerce.OrderPaymentStatusChanged`. `sCommerce::supportsIntegration('payment-ledger-api')` сообщает о поддержке. API, который записывает журнал, описан в разделе [Запись платежей](payments/index.md#запись-платежей-ledger-api).

## Событие графика платежей

### sCommerce.ResolvePaymentSchedule

Вызывается один раз для каждого нового заказа (checkout и быстрый заказ) до его первого сохранения. Слушатель может разделить стоимость заказа на предоплату и финальный платёж:

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

Без слушателей график остаётся пустым, и полная стоимость списывается, как и раньше. Формат частей и то, как способы оплаты получают `payment_request`, описаны в разделе [Графики платежей](payments/index.md#графики-платежей-предоплата-и-финальный-платёж).

## Строки заказа, исполнение и налоги

Начиная с 1.5.0, каждый новый заказ перед первым сохранением проходит эти хуки в таком порядке: `sCommerce.ResolveCheckoutContext`, `sCommerce.ResolveOrderTaxes`, `sCommerce.BeforeOrderCreate`, `sCommerce.ResolvePaymentSchedule`. После коммита следует `sCommerce.OrderCreated`.

### sCommerce.ResolveLineIdentity

Вызывается в `OrderLineIdentity::resolve()` для каждой новой строки заказа: строк корзины, быстрого заказа одного товара, поиска товаров в менеджере, а также строк из менеджера или повторного заказа, у которых ещё нет идентичности. Payload содержит `product`, `optionId` (id опции корзины, `0` — опции нет) и `identity`, передаваемый по ссылке.

Слушатели могут изменять только `sku` (фактический SKU, например SKU варианта) и `gtin`. После этого sCommerce восстанавливает `line_key`, `product_id`, `option_id` и `product_sku`, поэтому их нельзя подменить. Все ключи описаны в разделе [Снимок строки заказа](integration.md#снимок-строки-заказа).

```php
use Illuminate\Support\Facades\Event;

Event::listen('sCommerce.ResolveLineIdentity', function (array $payload) {
    if ($payload['optionId'] === 3) {
        $payload['identity']['sku'] = $payload['product']->sku . '-RED';
    }
});
```

### sCommerce.ResolveProductFulfilment

Вызывается в `sProduct::fulfilment($optionId)`. Payload содержит `product`, `optionId` и `fulfilment` (`['shipping' => bool, 'inventory' => bool]`). По умолчанию товары типов **Виртуальный** и **Услуга** не доставляются и не учитываются в остатках; все остальные товары доставляются и учитываются. Включите эти типы в настройках товаров (**Доступные типы**), чтобы их можно было выбрать в товаре.

Первый ответ-массив заменяет значение по умолчанию; ключ, которого в нём нет, сохраняет значение по умолчанию. Верните `null`, чтобы оставить значение по умолчанию.

```php
Event::listen('sCommerce.ResolveProductFulfilment', function (array $payload) {
    if ($payload['product']->sku === 'GIFT-CARD') {
        return ['shipping' => false];
    }
    return null;
});
```

Результат сохраняется в каждой строке корзины и заказа как `requires_shipping` и `tracks_inventory`:

- корзина ограничивает количество остатком только для учитываемых строк, поэтому услугу с остатком `0` можно заказать;
- при включённом `basic.deliveries_on` checkout требует способ доставки, только если хотя бы одна строка требует доставки. Иначе правило `delivery.method` удаляется до `sCommerce.CheckoutValidationRules`, стоимость доставки не добавляется, а заказ сохраняет `delivery_info = {"method": "", "required": false, "cost": 0}`;
- `sCheckout::requiresDelivery()` сообщает это решение, а `initCheckout()` возвращает его как `requiresDelivery`, поэтому шаблон может скрыть шаг доставки.

Строки заказов, созданных до 1.5.0, не имеют флагов и считаются доставляемыми и учитываемыми в остатках.

### sCommerce.ResolveOrderTaxes

Вызывается один раз для каждого нового заказа (checkout и быстрый заказ) до его первого сохранения. sCommerce никогда не рассчитывает налоги: их передаёт слушатель. Payload содержит `order` (несохранённый `sOrder`), `lines` и `totals` (оба передаются по ссылке) и `source` (`storefront` или `storefront_quick`).

Слушатели могут задавать в каждой строке `lines` такие необязательные ключи:

| Ключ | Тип |
|---|---|
| `tax_rate` | float, процент |
| `tax_code` | string, до 32 символов |
| `unit_price_net` | float, цена единицы без налога |
| `unit_price_gross` | float, цена единицы с налогом |
| `tax_amount` | float, налог всей строки |
| `prices_include_tax` | bool |
| `retail_excise_amount` | float, розничный акциз, включённый в сумму строки |

`totals` сохраняется в колонке `s_orders.totals`. Поддерживаемые ключи: `subtotal_net`, `subtotal_gross`, `shipping_net`, `shipping_gross`, `shipping_tax_rate`, `discount_gross`, `tax_total`, `grand_total`, `retail_excise_total` (float) и `prices_include_tax` (bool). sCommerce задаёт `retail_excise_amount` в строках с розничным акцизом до этого события, поэтому слушатели видят его и могут заменить.

Значения неверного типа, другие ключи, добавленные строки и изменения цен или количеств игнорируются. `cost` заказа никогда не меняется; если `totals.grand_total` отличается от него больше чем на `0.01`, в канал `scommerce` записывается предупреждение. Без слушателей `totals` остаётся `NULL`, а строки не меняются.

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

Вызывается непосредственно перед первым сохранением нового заказа из checkout или быстрого заказа. Payload содержит `order` (несохранённый `sOrder`), `data` (данные checkout без флагов окружения клиента) и `source`. Слушатели могут задавать атрибуты заказа. Поля проекта, например дату бронирования или адрес выполнения работ, храните в JSON-колонке `s_orders.meta`; страница заказа в менеджере показывает `meta` только для чтения.

```php
Event::listen('sCommerce.BeforeOrderCreate', function (array $payload) {
    $meta = $payload['order']->meta ?? [];
    $meta['booking'] = ['date' => $payload['data']['booking_date'] ?? '', 'slot' => '09:00-11:00'];
    $payload['order']->meta = $meta;
});
```

`meta.integration` зарезервирован для контекста checkout (см. ниже); слушатели не могут его изменить.

## Доверенный контекст checkout

Сайт всегда остаётся в рабочем режиме. Доверенный пользователь, например учётная запись QA, может оформлять тестовые заказы на рабочем сайте, пока все остальные посетители продолжают делать реальные покупки. sCommerce определяет окружение каждого нового заказа на сервере; данные, введённые клиентом, никогда его не выбирают.

### sCommerce.ResolveCheckoutContext

Вызывается checkout на сайте (`processOrder()`) и быстрыми заказами до проверки способа оплаты, до сохранения заказа и до любого платежа или письма. Payload содержит `data` (данные checkout), `source` и `context`, передаваемый по ссылке. Контекст по умолчанию: `['environment' => 'live', 'actorId' => <logged-in web or manager user id, 0 for guests>]`.

Слушатель, который проверил права пользователя на сервере, может задать:

- `environment`: `live` или `test`;
- `reference`: непрозрачная строка или массив размером до 2 КБ, например подключение к sandbox, которое нужно использовать;
- `testRecipients`: адреса e-mail, которые получают письма тестового заказа;
- `refusal`: сообщение, которое показывается клиенту. Checkout останавливается до создания заказа, платежа и любого письма, например когда тестовый платёж невозможно изолировать для выбранного платёжного сервиса.

Затем sCommerce проверяет контекст: любое другое окружение отклоняется, а не считается рабочим, гости не могут получить `test`, а `actorId` и `source` всегда берутся из запроса. Ключи, с помощью которых клиент мог бы заявить окружение (`environment`, `test`, `is_test`, `test_mode`, `sandbox`, `livemode`, `checkout_context`, `meta`, `integration` и их формы в camelCase), удаляются из данных до того, как их увидят слушатели и способы оплаты.

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

Определённый контекст сохраняется один раз в `s_orders.meta.integration` и больше никогда не меняется, какие бы последующие записи, выходы из системы или смены ролей ни происходили. Читайте его через `CheckoutContext::of($order)`; заказы без сохранённого контекста считаются рабочими (`live`).

- Способы оплаты получают его как `checkout_context` в `processPayment()` — из checkout и из платёжных ссылок, которые передают `order`, независимо от того, кто открыл ссылку. Выбирайте учётные данные sandbox или рабочие учётные данные на его основе, а не по текущему пользователю.
- Тестовый заказ отправляет письма заказа только на `testRecipients` (никому, если список пуст) и никогда — магазину или клиенту.
- Тестовые заказы никогда не зачисляются офлайн-зачислением и исключаются из метрик дашборда и списка заказов. В списке заказов есть ссылка **Тестовые заказы** для их просмотра, а тестовый заказ отмечен значком **ТЕСТ**.
- Скоупы `sOrder::live()` и `sOrder::test()` фильтруют запросы, а `$order->isTest()` проверяет отдельный заказ. События заказов передают `environment`.
- Запись журнала, у которой `metadata.environment` отличается от окружения заказа, отклоняется (см. [Запись платежей](payments/index.md#метаданные)).

Тестовым контекстам нужна колонка `s_orders.meta`; выполните миграции sCommerce. Без неё тестовые checkout отклоняются, а рабочие продолжают работать. `sCommerce::supportsIntegration('checkout-context')` сообщает о поддержке.

## События отправлений

### sCommerce.ShipmentUpdated

Начиная с 1.5.0, отправление, которое создано или у которого изменились статус, трек-номер или ссылка, перевозчик, услуга, позиции или даты, вызывает `sCommerce.ShipmentUpdated`. Событие подчиняется правилам доставки событий заказов: только после коммита, с контекстом области событий и никогда не объединяется. Повторная запись тех же данных ничего не вызывает.

Payload содержит:

- `event` и `shipment` (отсоединённая копия `sOrderShipment` только для чтения);
- `shipmentId`, `shipmentUuid`, `sequence`, `orderId`, `orderUuid`, `environment` (`live` или `test`, из заказа);
- `created` (bool), `oldStatus` (`null` при создании) и `newStatus`;
- `carrier`, `service`, `trackingNumber`, `trackingUrl`, `items`, `shippedAt`, `deliveredAt` (UTC, например `2026-10-21T08:00:00Z`), `metadata`;
- `fullyShipped` и `fullyDelivered`: покрыты ли в этот момент все товары заказа, требующие доставки, отправленными или доставленными посылками;
- `shipmentSource` и `externalRef`: система, записавшая отправление, и его идентификатор в ней;
- ключи контекста `source`, `actorId`, `correlationId`, `causationId`, `integrationActor`, `eventId` и `occurredAt`. `source` — это область событий, как и для всех остальных событий; это не `shipmentSource` отправления.

Событие отправления вызывается перед событиями заказа, которые оно порождает, например `sCommerce.OrderStatusChanged` в *Отправлено*. API описан в разделе [Отправления и отслеживание](deliveries.md#отправления-и-отслеживание-api--события). `sCommerce::supportsIntegration('shipments')` сообщает о поддержке.

## События остатков

### sCommerce.ResolveProductStock

Начиная с 1.5.0, корзина спрашивает у `Seiger\sCommerce\Services\StockResolver::available()`, сколько единиц товара она может добавить. Побеждает первое целое число, возвращённое слушателем `sCommerce.ResolveProductStock`; `null` передаёт ответ следующему слушателю. Payload содержит `product`, `modificationId` (всегда `0`: id опции корзины не является модификацией), `quantity` (локальный `s_products.inventory`) и `context` (`optionId` и запрошенное `quantity`). Привязанный провайдер остатков опрашивается до слушателей, а если никто не ответил, используется локальный остаток.

```php
Event::listen('sCommerce.ResolveProductStock', function (array $payload) {
    return $payload['product']->sku === 'GIFT-CARD' ? 1000 : null;
});
```

### Провайдер остатков

Пакет может сделать источником остатков внешнюю систему, например ERP, привязав `Seiger\sCommerce\Interfaces\StockProviderInterface` в своём сервис-провайдере: `app()->bind(StockProviderInterface::class, ErpStock::class)`. Используется не более одного провайдера.

- `available(sProduct $product, int $modificationId = 0, array $context = []): ?int` возвращает количество или `null`, чтобы использовать слушателей, а затем локальный остаток.
- `commit(sOrder $order): bool` и `release(sOrder $order): bool` вызываются, когда включено списание остатка по заказам (см. [Настройка](../configuration.md#остатки)). Верните `true`, если провайдер обработал заказ; тогда sCommerce не меняет локальные остатки. Остаток заказа возвращает тот, кто его списал: заказ, остаток которого sCommerce списал локально, восстанавливается локально без обращения к провайдеру.
- Оба метода могут выполниться повторно после ошибки или сбоя, поэтому они должны быть идемпотентными в пределах заказа, например с ключом по UUID заказа.

### Запись остатков

`Seiger\sCommerce\Services\StockWriter::set($productOrId, $quantity, 0, 'erp', $availability)` задаёт остаток и, если передана, доступность (`0` нет в наличии, `1` в наличии, `2` под заказ) одного товара. Вариант GROUP — это отдельная строка товара: передавайте товар варианта. Ненулевой `modificationId` отклоняется, потому что у строк `s_product_modifications` нет собственной идентичности. Запись, которая ничего не меняет, не создаёт события. Ядро не кеширует значения остатков, поэтому никакой кеш не очищается; слушатели `sCommerce.ProductStockChanged` могут очищать собственные кеши.

### sCommerce.ProductStockChanged

Начиная с 1.5.0, изменение `s_products.inventory` или `s_products.availability` вызывает `sCommerce.ProductStockChanged`, кто бы его ни записал: форма товара, CSV-импорт, `StockWriter` или списание остатка по заказу. Событие подчиняется правилам доставки событий заказов: только после коммита, с контекстом области событий. Изменения одного товара в одной области событий объединяются от первого старого до последнего нового значения, а объединение, которое ничего не меняет, отбрасывается.

Payload содержит `event`, `productId`, `modificationId` (всегда `0`), `sku`, `oldQuantity`, `newQuantity`, `oldAvailability`, `newAvailability`, `stockSource` (кто записал: `manager`, `import`, `order`, источник, переданный в `StockWriter`, или источник области событий для других сохранений модели) и ключи контекста `source`, `actorId`, `correlationId`, `causationId`, `integrationActor`, `eventId` и `occurredAt`. Списания и возвраты остатков по заказам выполняются в области событий с источником `inventory`.

Для товаров, продаваемых по весу, длине, объёму или количеству, все количества остатка (остаток, события, списания и `available()` провайдера) указываются в базовых единицах: граммах, миллиметрах, миллилитрах или штуках. См. [Измеряемые количества](measured-quantities.md#остатки-и-отправления).

### Остаток следует за редактированием заказов

Начиная с 1.5.0, когда менеджер меняет товары заказа, остаток которого sCommerce списал локально, остаток меняется в том же запросе: `StockCommitter::adjust($order)` списывает или возвращает разницу для изменённых, добавленных и удалённых строк — как для поштучных, так и для измеряемых товаров. Фактические количества измеряемых строк корректируют остаток так же. Если для увеличения не хватает остатка товара, редактирование не сохраняется. Заказы, списанные провайдером остатков, локально не корректируются; провайдер получает `sCommerce.OrderUpdated`. Последующий возврат восстанавливает ровно скорректированные количества. До 1.5.0 редактирование в менеджере не меняло списанный остаток.

`sCommerce::supportsIntegration('stock-provider')` сообщает о поддержке.

## Разделение строк корзины

### sCommerce.SplitCartLine

Начиная с 1.5.0, слушатель может продать одну позицию корзины (товар с опцией и количеством) несколькими строками заказа с разными ценами. В самом sCommerce нет партий остатков; это событие позволяет пакету партий или адаптеру ERP применять собственные правила. Типичные случаи:

- Подакцизные товары с ценой, зафиксированной для партии: единицы более старой партии продаются по её цене, прежде чем открывается следующая партия.
- Скоропортящиеся товары: единицы партии, у которой близки дата и время окончания срока годности, продаются первыми по уценённой цене, остальные единицы той же позиции — по обычной цене.
- Товары, которые должны быть из одной партии, например плитка одного тона: слушатель указывает партию в строке.

Корзина, checkout и быстрый заказ одного товара вызывают событие для каждой позиции после определения её цены. Payload содержит `product`, `optionId`, `quantity` (базовые единицы для товаров, продаваемых по мере), `currency` и `pricing` (определённая цена позиции, см. [sCommerce.ResolveProductPrice](#scommerceresolveproductprice)). Решает первый слушатель, который вернул массив; верните `null`, чтобы оставить позицию одной строкой.

Ответ — это список частей, количества которых в сумме дают количество позиции:

| Ключ | Обязательный | Значение |
|---|---|---|
| `key` | да | Уникален в пределах позиции: от 1 до 40 букв, цифр, `.`, `_` или `-`, например `lot-11` |
| `quantity` | да | Положительное целое число |
| `priceAsFloat` | нет | Цена за единицу в `currency`; без неё часть получает цену позиции |
| `oldPriceAsFloat` | нет | Зачёркнутая цена уценки; только вместе с `priceAsFloat`, по умолчанию отсутствует |
| `label` | нет | Текст до 255 символов для покупателя и менеджера, например `Best before 27.10 18:00` |
| `allocation` | нет | Массив, кодируемый в JSON, который сохраняется в строке заказа как есть, например id партий и количества |

Любой другой ответ, например количества с другой суммой, повторяющийся ключ или отрицательная цена, выбрасывает `UnexpectedValueException`, поэтому ошибка слушателя никогда не приводит к продаже по неверной цене.

```php
use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Cart\CartLineParts;

Event::listen(CartLineParts::EVENT, function (array $payload) {
    $lots = MyLots::sellable($payload['product']->id); // oldest first, each with id, quantity, price, markdown
    if ($lots === []) {
        return null;
    }
    $parts = [];
    $left = $payload['quantity'];
    foreach ($lots as $lot) {
        $units = min($left, $lot->quantity);
        if ($units > 0) {
            $parts[] = array_filter([
                'key' => 'lot-' . $lot->id,
                'quantity' => $units,
                'priceAsFloat' => $lot->markdown ?? $lot->price,
                'oldPriceAsFloat' => $lot->markdown !== null ? $payload['pricing']['priceAsFloat'] : null,
                'label' => $lot->markdown !== null ? 'Best before ' . $lot->expiresAt->format('d.m H:i') : null,
                'allocation' => ['lots' => [['id' => $lot->id, 'quantity' => $units]]],
            ], static fn ($value) => $value !== null);
            $left -= $units;
        }
    }

    return $parts;
});
```

Каждая часть становится одной строкой корзины и заказа:

- Её `line_key` — `{productId}:{optionId}:{key}`, например `5:0:lot-11`.
- Её цена, сумма, НДС и ключи залога рассчитываются для части. Заявленные обмены тары заполняют части по порядку.
- Она содержит `part_key`, `part_label` (если задан) и `allocation` (если задан). Письма о заказе, экран заказа в менеджере и форма печати показывают `part_label` под названием товара.
- Только в корзине `cart_quantity` — это количество всей позиции. Элемент изменения количества разделённой строки должен отправлять это количество позиции, потому что `sCart::addProduct()` задаёт количество позиции, а не одной части. `cart_quantity` не сохраняется в заказах.

Checkout повторно рассчитывает цены своих строк перед сохранением заказа. Если разделение тем временем изменилось, например партия распродана, ключи строк или цены отличаются, и покупатель получает `cart_changed` с новыми строками и итогом.

Слушатель также должен согласовать остальные правила партий:

- **Остаток.** Сделайте так, чтобы `sCommerce.ResolveProductStock` или провайдер остатков возвращал то, что можно продать.
- **Цена в каталоге.** Сделайте так, чтобы `sCommerce.ResolveProductPrice` возвращал цену первой единицы для карточек товаров.
- **Списание остатка.** Остаток партий списывает `StockProviderInterface`, который читает `allocation` из строк заказа. sCommerce не интерпретирует распределения. Его локальное списание остатка, как и раньше, учитывает части одного товара вместе.

Ключи частей остаются на сервере: когда менеджер сохраняет заказ, строки сохраняют `part_key`, `part_label` и `allocation`, записанные под их `line_key`, а у строк, добавленных в менеджере, их нет. Менеджер, который меняет количество разделённой строки, меняет строку, а не её распределение; провайдер остатков получает `sCommerce.OrderUpdated`. Повторные заказы сохраняют записанные цены и метки, но отбрасывают `allocation`.

`sCommerce::supportsIntegration('line-parts')` сообщает о поддержке.

## Дополнительные события менеджера и frontend

События менеджера используют префикс evolution. События цен имеют точные имена sCommerce.* без префикса. Структура данных frontend callbacks зависит от операции.

- `evolution.sCommerceManagerTabsEvent`
- `evolution.sCommerceManagerSettingsSaveEvent`
- `evolution.sCommerceAfterProductContentSave`
- `evolution.sCommerceAfterOrderSave`: устаревшее событие, только для менеджера. Вызывается после успешного сохранения в форме заказа менеджера, массового изменения статуса, массового «отметить как оплаченный» и повторных заказов; сохранённый заказ передаётся как `item`. Для заказов с сайта не вызывается; используйте доменные события заказов выше.

```javascript
sCommerce.onAddedToCart = (data) => console.log(data);
sCommerce.onRemovedFromCart = (data) => console.log(data);
sCommerce.onUpdatedCart = (data) => console.log(data);
sCommerce.onFastOrder = (data) => console.log(data);
sCommerce.onWishlist = (data) => console.log(data);
```
