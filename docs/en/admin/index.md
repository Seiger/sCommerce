---
id: admin
title: Admin Guide
sidebar_position: 3
---

# Admin guide

sCommerce provides a product catalog, cart, checkout and order management for Evolution CMS. The interface and available tabs depend on store configuration and installed integrations.

![Dashboard](https://github.com/user-attachments/assets/a03e4564-c087-4839-887f-b9c6106e85b8)
![List of Orders](https://github.com/user-attachments/assets/16031ada-cd5d-4c38-abf4-66d6031c666d)

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

## Primary and additional categories

Category is the primary category. Additional categories contains only other assignments; the primary category cannot be selected there again. Changing the primary selection updates this field immediately. In multisite mode each domain has its own primary category.

Set the product name in its language/content tab, SKU, alias, availability, price and currency. Choose a primary category and optional additional categories; add images through sGallery. Publish the product and verify its storefront page.

## Exchange rates

Choose the available currencies and main currency in Settings. The Currencies tab appears when more than one currency is enabled. A → B is a separate conversion multiplier: 50.55 and 0.012345 are valid rates. Configure the reverse pair explicitly; rates are not fetched automatically. Values are stored in core/custom/config/seiger/settings/sCommerceCurrencies.php. A missing pair defaults to 1; an explicitly configured zero is not a reliable 1:1 fallback. Product display precision is configured separately from exchange-rate precision.

## CSV import and export

Use Integrations → CSV import/export. Export a fresh file as a template; the separator is a semicolon and category-list entries use ||. The translated Category column (category) contains the primary ID for a single site. Additional categories (categories) contains the other IDs and excludes every domain primary. For multisite, category uses domain:id entries, for example default:2||shop:234; categories remains a list of additional IDs. Existing `parent_<domain>` import fields remain supported; the standalone parent column has been removed. Both streaming and temporary-table import preserve domain scopes and category positions. For old files, move the former category list to categories and the former primary ID to category before importing.

## Guides

- [Admin guide](./management.md)
- [Primary and additional categories](./products.md)
- [CSV import and export](./import-export.md)
- [Exchange rates](../developers/currencies.md)
- [Payment](../developers/payments/methods.md)

Use the API integration guide for sApi and the separate sCommerce API package. Customer login/profile pages, external payment gateways and delivery providers are supplied by the project or their integration packages.
