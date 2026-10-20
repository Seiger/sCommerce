---
id: intro
title: О sCommerce
slug: /
sidebar_position: 1
---

# sCommerce для Evolution CMS

sCommerce предоставляет каталог, корзину, оформление и управление заказами для Evolution CMS. Интерфейс и вкладки зависят от настроек магазина и установленных интеграций.

![Dashboard](https://github.com/user-attachments/assets/a03e4564-c087-4839-887f-b9c6106e85b8)
![List of Orders](https://github.com/user-attachments/assets/16031ada-cd5d-4c38-abf4-66d6031c666d)
![List of Products](https://github.com/user-attachments/assets/8dd1127c-5055-4795-954c-95eb75eadf31)
![Products by Category](https://github.com/user-attachments/assets/c6d9a6e3-aad4-4efd-b775-0ee626a4714c)
![sCommerce Settings block](https://github.com/user-attachments/assets/3c2283bf-a2b8-4af1-a01b-97e88b0ecc21)

## Текущие возможности

- Корзина, оформление заказа, заказ в один клик и список желаний.
- Простые и сгруппированные товары; атрибуты, диапазоны цен, галереи, отзывы и рейтинг.
- Основные и дополнительные категории, позиции товаров, фильтрация и сортировка.
- Заказы, статусы, массовые действия, экспорт, повторение заказов и учёт платежей.
- Наличные и банковский счёт; курьер и самовывоз. Другие провайдеры подключаются отдельно.
- CSV-импорт/экспорт переводов, SEO-полей, галерей, атрибутов и мультидоменных категорий.
- XML-фиды Google Merchant; фоновые задания и кеш каталога через sTask.
- Статистика dashboard и исторические метрики товаров и отзывов.
- Несколько валют, ручные дробные курсы и дополнительная интеграция sPricing.

## Интеграции

- [sLang](https://github.com/Seiger/sLang)
- [sGallery](https://github.com/Seiger/sGallery)
- [sMultisite](https://github.com/Seiger/sMultisite)
- [sApi](https://github.com/Seiger/sApi)
- [sTask](https://github.com/Seiger/sTask)
- [sSeo](https://github.com/Seiger/sSeo)
- sPricing

sApi и отдельный пакет sCommerce API описаны в руководстве API. Авторизацию, профиль клиента, внешние платёжные шлюзы и службы доставки добавляет проект или интеграционные пакеты.

## Требования

- PHP **8.4+**
- Evolution CMS **3.5.8+**
- Composer
- sGallery, sTask (Composer dependencies)

## Установка

Выполняйте команды в установленном проекте Evolution CMS. Перед заменой переопределений проверьте публикуемые файлы; не используйте --force без необходимости.

```bash
cd core
php artisan package:installrequire seiger/scommerce "*"
php artisan vendor:publish --provider="Seiger\sCommerce\sCommerceServiceProvider"
php artisan migrate
```

## Руководства

- [Начало работы](./getting-started.md)
- [Управление магазином](./admin/index.md)
- [Разработчику](./developers/index.md)
- [API / sApi](./developers/api.md)
- [CSV-импорт и экспорт](./admin/import-export.md)

Лицензия: GPL-3.0-or-later. Разработка и поддержка — [Seiger IT](https://seigerit.com/).
