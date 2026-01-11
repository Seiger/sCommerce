---
id: api
title: API integration (sApi)
sidebar_position: 7
---

## Standard integration

sCommerce itself does not register `/{base}/{version}/*` routes. API endpoints are exposed only when `seiger/sapi` is installed and discovers route providers from Composer metadata (`extra.sapi.route_providers`).

### Orders API routes

The package provides the `orders` endpoint via:

- Route provider: `Seiger\sCommerce\Api\Routes\OrdersRouteProvider`
- Routes:
  - `GET  /{base}/{version}/orders`
  - `PUT  /{base}/{version}/orders/{order_id}`

Exact `{base}` and `{version}` are defined by sApi configuration/discovery.

### Update flow (PUT /orders/{order_id})

`Seiger\sCommerce\Api\Controllers\OrdersController@update` is intentionally thin:

1) Find `sOrder` by id, or return `404`.
2) Resolve 3 services from the container:
   - `OrderUpdateMapperInterface`
   - `OrderUpdateValidatorInterface`
   - `OrderUpdateApplierInterface`
3) Run the update pipeline:
   - `$mapper->map($payload)` → canonical array
   - `$validator->validate($canonical)` → `422` on validation errors
   - `DB::transaction(fn() => $applier->apply($order, $validated) + $order->save())`
4) Return `ApiResponse::success($order, '')`.

Vendor defaults are registered in `Seiger\sCommerce\sCommerceServiceProvider` only when:

- sApi is installed (checked via `interface_exists(\Seiger\sApi\Contracts\RouteProviderInterface::class)`), and
- the interface is not already `bound()` (so project overrides are respected).

## Custom integration (when the standard contract does not fit)

There are 2 supported customization levels: **logic override (recommended)** and **route override**.

### 1) Override mapping/validation/apply (recommended)

Keep vendor routes + controller, but accept a project-specific payload format by overriding DI services.

Create project implementations:

- Mapper (external → canonical): `implements OrderUpdateMapperInterface`
- Validator (canonical → normalized data): `implements OrderUpdateValidatorInterface`
- Applier (normalized data → `sOrder` mutations): `implements OrderUpdateApplierInterface`

Then bind them in a custom ServiceProvider:

```php
$this->app->bind(\Seiger\sCommerce\Api\Contracts\OrderUpdateMapperInterface::class, \Project\Api\OrderUpdateMapper::class);
$this->app->bind(\Seiger\sCommerce\Api\Contracts\OrderUpdateValidatorInterface::class, \Project\Api\OrderUpdateValidator::class);
$this->app->bind(\Seiger\sCommerce\Api\Contracts\OrderUpdateApplierInterface::class, \Project\Api\OrderUpdateApplier::class);
```

Register the ServiceProvider through `core/custom/config/app/providers/*.php` (file name controls load order).

### 2) Override the route provider (replace the endpoint)

If the standard endpoint/controller is not suitable, implement your own `RouteProviderInterface` and register it in `core/custom/composer.json` under `extra.sapi.route_providers` for the same `{version}/{endpoint}`. sApi discovery gives priority to the custom descriptor over vendor descriptors.

