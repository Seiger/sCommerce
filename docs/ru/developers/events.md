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

### sCommerce.CheckoutValidationRules

Вызывается в `sCheckout::getValidationRules(array $data)` после объединения базовых правил с правилами выбранного способа доставки.

- `data` — входные данные, переданные в `getValidationRules()`.
- `rules` — итоговый массив правил по ссылке. Можно добавлять и заменять ключи или удалять их через `unset()`.

Разместите слушатель в `core/custom/packages/main/plugins/sCommerceEvents.php`, если service provider пакета main загружает эту папку. Изменяйте `rules` напрямую: возвращённый массив не применяется. Каждый следующий слушатель видит изменения предыдущих и может их переопределить.

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

`setOrderData()` затем оставляет только правила для ключей, присутствующих в переданных данных. Поэтому добавление `required` не делает отсутствующее поле обязательным при таком частичном обновлении. Удаление правила также исключает поле из этого пути валидации, а не обеспечивает сохранение произвольных полей. Быстрый заказ использует собственные правила и не вызывает этот хук.

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

`Seiger\sCommerce\Events\EventScope` сообщает слушателям, кто внёс изменение. sCommerce использует такие значения `source`: `storefront`, `storefront_quick`, `storefront_pay`, `payment_callback`, `manager`, `manager_bulk_status`, `manager_bulk_menu`, `manager_repeat`, `manager_payment_action`, `offline_settlement` и `system` (значение по умолчанию, когда ни одна область не открыта).

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

`sCommerce::INTEGRATION_EVENTS_VERSION` (сейчас `1`) меняется только тогда, когда payload изменяется несовместимо. `sCommerce::integrationFeatures()` возвращает список доступных ключей возможностей.

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
