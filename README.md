# sCommerce

sCommerce is an e-commerce toolkit for Evolution CMS.

## API (sApi integration)

sCommerce **does not** register `/rest/*` (or `/{SAPI_BASE_PATH}/*`) API routes by itself.
API endpoints are exposed only when `seiger/sapi` is installed, via sApi provider discovery.

This package declares API route providers in Composer metadata:

`core/vendor/seiger/scommerce/composer.json` → `extra.sapi.route_providers`

Example (orders):
- `GET /{SAPI_BASE_PATH}/{SAPI_VERSION}/orders` (if `SAPI_VERSION` is empty → `/{SAPI_BASE_PATH}/orders`)
- `PUT /{SAPI_BASE_PATH}/{SAPI_VERSION}/orders/{order_id}`

Provider class:
- `Seiger\\sCommerce\\Api\\Routes\\OrdersRouteProvider`
