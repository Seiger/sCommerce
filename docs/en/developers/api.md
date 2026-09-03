---
id: api
title: API integration
sidebar_position: 7
---

## sCommerce API package

sCommerce no longer registers `sApi` route providers itself. Data exchange endpoints for categories, products, orders, payments, statuses, and currency rates are provided by the separate `seiger/scommerce-api` package.

Install `seiger/sapi` and `seiger/scommerce-api` in the project when API integration is required. The `scommerce-api` package owns the `extra.sapi.route_providers` metadata, so it can expose `/api/v1/*` endpoints without conflicting with the storefront package.

## Project overrides

Project-specific endpoints can still be registered through `core/custom/composer.json` under `extra.sapi.route_providers`. sApi discovery gives custom descriptors priority over vendor descriptors for the same `{version}/{endpoint}` key.
