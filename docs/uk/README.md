---
id: intro
title: Про sCommerce
slug: /
sidebar_position: 1
---

# sCommerce для Evolution CMS

sCommerce надає каталог товарів, кошик, оформлення й керування замовленнями для Evolution CMS. Інтерфейс і набір вкладок залежать від налаштувань магазину та встановлених інтеграцій.

## Скриншоти

![Dashboard](https://github.com/user-attachments/assets/a03e4564-c087-4839-887f-b9c6106e85b8)
![List of Orders](https://github.com/user-attachments/assets/16031ada-cd5d-4c38-abf4-66d6031c666d)
![List of Products](https://github.com/user-attachments/assets/8dd1127c-5055-4795-954c-95eb75eadf31)
![Products by Category](https://github.com/user-attachments/assets/c6d9a6e3-aad4-4efd-b775-0ee626a4714c)
![sCommerce Settings block](https://github.com/user-attachments/assets/3c2283bf-a2b8-4af1-a01b-97e88b0ecc21)

## Актуальні можливості

- Кошик, оформлення замовлення, замовлення в один клік та список бажань.
- Прості та згруповані товари; атрибути, діапазони цін, галереї, відгуки й рейтинг.
- Основна й додаткові категорії, позиції товару в категоріях, фільтрація та сортування.
- Замовлення, статуси, масові дії, експорт і повторення замовлень, облік платежів.
- Готівка та банківський рахунок; кур’єрська доставка й самовивіз. Інші провайдери розширюють пакет.
- CSV-імпорт/експорт товарів із перекладами, SEO-полями, галереями, атрибутами й мультидоменними категоріями.
- XML-фіди Google Merchant; фонові завдання та кеш каталогу через sTask.
- Статистика dashboard та історичні метрики товарів і відгуків.
- Кілька валют і ручні дробові курси; необов’язкова інтеграція sPricing для контекстних цін.

## Інтеграції

- [sLang](https://github.com/Seiger/sLang)
- [sGallery](https://github.com/Seiger/sGallery)
- [sMultisite](https://github.com/Seiger/sMultisite)
- [sApi](https://github.com/Seiger/sApi)
- [sTask](https://github.com/Seiger/sTask)
- [sSeo](https://github.com/Seiger/sSeo)
- sPricing

sApi та окремий пакет sCommerce API описані в посібнику інтеграції API. Авторизацію й профіль клієнта, зовнішні платіжні шлюзи та провайдерів доставки додає проєкт або інтеграційні пакети.

## Вимоги

- PHP **8.4+**
- Evolution CMS **3.5.8+**
- Composer
- sGallery, sTask (Composer dependencies)

## Встановлення

Виконуйте команди у встановленому проєкті Evolution CMS. Перед заміною локальних перевизначень перевіряйте опубліковані файли; не застосовуйте --force без потреби.

```bash
cd core
php artisan package:installrequire seiger/scommerce "*"
php artisan vendor:publish --provider="Seiger\sCommerce\sCommerceServiceProvider"
php artisan migrate
```

## Посібники

- [Початок роботи](./getting-started.md)
- [Керування магазином](./admin/index.md)
- [Розробнику](./developers/index.md)
- [API / sApi](./developers/api.md)
- [CSV-імпорт та експорт](./admin/import-export.md)

Ліцензія: GPL-3.0-or-later. Розробка та підтримка — [Seiger IT](https://seigerit.com/).
