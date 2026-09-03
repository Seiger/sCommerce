---
id: api
title: API інтеграція
sidebar_position: 7
---

## Пакет sCommerce API

sCommerce більше не реєструє `sApi` route providers самостійно. Endpoints для обміну категоріями, товарами, замовленнями, оплатами, статусами та курсами валют надає окремий пакет `seiger/scommerce-api`.

Якщо проєкту потрібна API-інтеграція, встановіть `seiger/sapi` та `seiger/scommerce-api`. Пакет `scommerce-api` володіє metadata `extra.sapi.route_providers`, тому може надавати `/api/v1/*` endpoints без конфлікту з пакетом вітрини.

## Проєктні overrides

Проєктні endpoints і надалі можна реєструвати через `core/custom/composer.json` у секції `extra.sapi.route_providers`. sApi discovery надає custom descriptors пріоритет над vendor descriptors для того самого ключа `{version}/{endpoint}`.
