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
