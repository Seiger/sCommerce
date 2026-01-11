---
id: api
title: API интеграция (sApi)
sidebar_position: 7
---

## Стандартная интеграция

sCommerce сам по себе не регистрирует роуты `/{base}/{version}/*`. API-эндпоинты появляются только если установлен `seiger/sapi`, который находит провайдеры роутов через Composer-метаданные (`extra.sapi.route_providers`).

### Orders API роуты

Пакет предоставляет endpoint `orders` через:

- Route provider: `Seiger\sCommerce\Api\Routes\OrdersRouteProvider`
- Роуты:
  - `GET  /{base}/{version}/orders`
  - `PUT  /{base}/{version}/orders/{order_id}`

Точные `{base}` и `{version}` определяются конфигурацией/дискавери sApi.

### Update flow (PUT /orders/{order_id})

`Seiger\sCommerce\Api\Controllers\OrdersController@update` сделан намеренно “тонким”:

1) Найти `sOrder` по id или вернуть `404`.
2) Зарезолвить 3 сервиса из контейнера:
   - `OrderUpdateMapperInterface`
   - `OrderUpdateValidatorInterface`
   - `OrderUpdateApplierInterface`
3) Запустить пайплайн обновления:
   - `$mapper->map($payload)` → canonical array
   - `$validator->validate($canonical)` → `422` при ошибках валидации
   - `DB::transaction(fn() => $applier->apply($order, $validated) + $order->save())`
4) Вернуть `ApiResponse::success($order, '')`.

Vendor-дефолты регистрируются в `Seiger\sCommerce\sCommerceServiceProvider` только если:

- установлен sApi (проверка `interface_exists(\Seiger\sApi\Contracts\RouteProviderInterface::class)`), и
- интерфейс ещё не `bound()` (чтобы проектные overrides имели приоритет).

## Кастомная интеграция (если стандартный контракт не подходит)

Поддерживаются 2 уровня кастомизации: **override логики (рекомендуется)** и **override роутов**.

### 1) Override маппинга/валидации/apply (рекомендуется)

Оставляем vendor роуты + контроллер, но принимаем проектный формат payload через DI overrides.

Создайте проектные реализации:

- Mapper (внешний payload → canonical): `implements OrderUpdateMapperInterface`
- Validator (canonical → нормализованные данные): `implements OrderUpdateValidatorInterface`
- Applier (нормализованные данные → изменения `sOrder`): `implements OrderUpdateApplierInterface`

И зарегистрируйте bindings в custom ServiceProvider:

```php
$this->app->bind(\Seiger\sCommerce\Api\Contracts\OrderUpdateMapperInterface::class, \Project\Api\OrderUpdateMapper::class);
$this->app->bind(\Seiger\sCommerce\Api\Contracts\OrderUpdateValidatorInterface::class, \Project\Api\OrderUpdateValidator::class);
$this->app->bind(\Seiger\sCommerce\Api\Contracts\OrderUpdateApplierInterface::class, \Project\Api\OrderUpdateApplier::class);
```

Подключите ServiceProvider через `core/custom/config/app/providers/*.php` (имя файла управляет порядком загрузки).

### 2) Override route provider (полная замена endpoint)

Если стандартный endpoint/контроллер не подходит, реализуйте свой `RouteProviderInterface` и зарегистрируйте его в `core/custom/composer.json` в `extra.sapi.route_providers` для того же `{version}/{endpoint}`. sApi discovery отдаёт приоритет custom descriptor над vendor.

