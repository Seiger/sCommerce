---
id: intro
title: About sCommerce
slug: /
sidebar_position: 1
---

# sCommerce for Evolution CMS

sCommerce provides a product catalog, cart, checkout and order management for Evolution CMS. The interface and available tabs depend on store configuration and installed integrations.

## Screenshots

![Dashboard](https://github.com/user-attachments/assets/a03e4564-c087-4839-887f-b9c6106e85b8)
![List of Orders](https://github.com/user-attachments/assets/16031ada-cd5d-4c38-abf4-66d6031c666d)
![List of Products](https://github.com/user-attachments/assets/8dd1127c-5055-4795-954c-95eb75eadf31)
![Products by Category](https://github.com/user-attachments/assets/c6d9a6e3-aad4-4efd-b775-0ee626a4714c)
![sCommerce Settings block](https://github.com/user-attachments/assets/3c2283bf-a2b8-4af1-a01b-97e88b0ecc21)

## Current capabilities

- Shopping cart, checkout, one-click orders, wishlist and favorites.
- Simple and grouped products; attributes, price ranges, galleries, reviews and ratings.
- Primary and additional categories, per-category product positions, filtering and sorting.
- Orders, statuses, bulk actions, order export, repeat orders and payment records.
- Cash and bank invoice payment methods; courier delivery and pickup. Other providers extend the package.
- CSV product import/export with translations, SEO fields, galleries, attributes and multisite categories.
- Google Merchant XML feeds; background processing and listing caches through sTask.
- Dashboard statistics and historical product/review metrics.
- Multiple currencies and manually configured decimal exchange rates; optional contextual pricing through sPricing.

## Integrations

- [sLang](https://github.com/Seiger/sLang)
- [sGallery](https://github.com/Seiger/sGallery)
- [sMultisite](https://github.com/Seiger/sMultisite)
- [sApi](https://github.com/Seiger/sApi)
- [sTask](https://github.com/Seiger/sTask)
- [sSeo](https://github.com/Seiger/sSeo)
- sPricing

Use the API integration guide for sApi and the separate sCommerce API package. Customer login/profile pages, external payment gateways and delivery providers are supplied by the project or their integration packages.

## Requirements

- PHP **8.4+**
- Evolution CMS **3.5.8+**
- Composer
- sGallery, sTask (Composer dependencies)

## Installation

Run these commands from an installed Evolution CMS project. Review published files before replacing project overrides; do not use --force indiscriminately.

```bash
cd core
php artisan package:installrequire seiger/scommerce "*"
php artisan vendor:publish --provider="Seiger\sCommerce\sCommerceServiceProvider"
php artisan migrate
```

## Guides

- [Getting started](./getting-started.md)
- [Admin guide](./admin/index.md)
- [Developer guide](./developers/index.md)
- [API / sApi](./developers/api.md)
- [CSV import and export](./admin/import-export.md)

License: GPL-3.0-or-later. Developed and maintained by [Seiger IT](https://seigerit.com/).
