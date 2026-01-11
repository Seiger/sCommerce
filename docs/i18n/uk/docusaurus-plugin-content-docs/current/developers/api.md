---
id: api
title: API інтеграція (sApi)
sidebar_position: 7
---

## Стандартна інтеграція

sCommerce сам по собі не реєструє роути `/{base}/{version}/*`. API-ендпоінти з’являються лише якщо встановлено `seiger/sapi`, який знаходить провайдери роутів через Composer-метадані (`extra.sapi.route_providers`).

### Orders API роути

Пакет надає endpoint `orders` через:

- Route provider: `Seiger\sCommerce\Api\Routes\OrdersRouteProvider`
- Роути:
  - `GET  /{base}/{version}/orders`
  - `PUT  /{base}/{version}/orders/{order_id}`

Точні `{base}` та `{version}` визначаються конфігурацією/дискавері sApi.

### Update flow (PUT /orders/{order_id})

`Seiger\sCommerce\Api\Controllers\OrdersController@update` зроблено навмисно “тонким”:

1) Знайти `sOrder` по id або повернути `404`.
2) Зарезолвити 3 сервіси з контейнера:
   - `OrderUpdateMapperInterface`
   - `OrderUpdateValidatorInterface`
   - `OrderUpdateApplierInterface`
3) Запустити пайплайн оновлення:
   - `$mapper->map($payload)` → canonical array
   - `$validator->validate($canonical)` → `422` при помилках валідації
   - `DB::transaction(fn() => $applier->apply($order, $validated) + $order->save())`
4) Повернути `ApiResponse::success($order, '')`.

Vendor-дефолти реєструються в `Seiger\sCommerce\sCommerceServiceProvider` лише якщо:

- встановлено sApi (перевірка `interface_exists(\Seiger\sApi\Contracts\RouteProviderInterface::class)`), і
- інтерфейс ще не `bound()` (щоб проектні overrides мали пріоритет).

## Кастомна інтеграція (якщо стандартний контракт не підходить)

Підтримуються 2 рівні кастомізації: **override логіки (рекомендується)** та **override роутів**.

### 1) Override мапінгу/валідації/apply (рекомендується)

Залишаємо vendor роути + контролер, але приймаємо проектний формат payload через DI overrides.

Створіть проектні реалізації:

- Mapper (зовнішній payload → canonical): `implements OrderUpdateMapperInterface`
- Validator (canonical → нормалізовані дані): `implements OrderUpdateValidatorInterface`
- Applier (нормалізовані дані → зміни `sOrder`): `implements OrderUpdateApplierInterface`

Та зареєструйте bindings у custom ServiceProvider:

```php
$this->app->bind(\Seiger\sCommerce\Api\Contracts\OrderUpdateMapperInterface::class, \Project\Api\OrderUpdateMapper::class);
$this->app->bind(\Seiger\sCommerce\Api\Contracts\OrderUpdateValidatorInterface::class, \Project\Api\OrderUpdateValidator::class);
$this->app->bind(\Seiger\sCommerce\Api\Contracts\OrderUpdateApplierInterface::class, \Project\Api\OrderUpdateApplier::class);
```

Підключіть ServiceProvider через `core/custom/config/app/providers/*.php` (ім’я файла керує порядком завантаження).

### 2) Override route provider (повна заміна endpoint)

Якщо стандартний endpoint/контролер не підходить, реалізуйте свій `RouteProviderInterface` і зареєструйте його в `core/custom/composer.json` у `extra.sapi.route_providers` для того ж `{version}/{endpoint}`. sApi discovery надає пріоритет custom descriptor над vendor.

