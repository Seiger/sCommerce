---
id: api
title: API интеграция
sidebar_position: 7
---

## Пакет sCommerce API

sCommerce больше не регистрирует `sApi` route providers самостоятельно. Endpoints для обмена категориями, товарами, заказами, оплатами, статусами и курсами валют предоставляет отдельный пакет `seiger/scommerce-api`.

Если проекту нужна API-интеграция, установите `seiger/sapi` и `seiger/scommerce-api`. Пакет `scommerce-api` владеет metadata `extra.sapi.route_providers`, поэтому может предоставлять `/api/v1/*` endpoints без конфликта с пакетом витрины.

## Проектные overrides

Проектные endpoints по-прежнему можно регистрировать через `core/custom/composer.json` в секции `extra.sapi.route_providers`. sApi discovery отдаёт custom descriptors приоритет над vendor descriptors для того же ключа `{version}/{endpoint}`.
