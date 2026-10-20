---
id: getting-started
title: Getting Started
sidebar_position: 2
---

# Getting started

sCommerce provides a product catalog, cart, checkout and order management for Evolution CMS. The interface and available tabs depend on store configuration and installed integrations.

## Requirements

- PHP **8.4+**
- Evolution CMS **3.5.8+**
- Composer

## Installation

Run these commands from an installed Evolution CMS project. Review published files before replacing project overrides; do not use --force indiscriminately.

```bash
cd core
php artisan package:installrequire seiger/scommerce "*"
php artisan vendor:publish --provider="Seiger\sCommerce\sCommerceServiceProvider"
php artisan migrate
```

## Admin guide

Install the package, run migrations and open Modules → Commerce in the manager. Configure the catalog root, available currencies and main currency in Settings. Create category resources in the Evolution CMS resource tree, then create products in Commerce.

Set the product name in its language/content tab, SKU, alias, availability, price and currency. Choose a primary category and optional additional categories; add images through sGallery. Publish the product and verify its storefront page.

## Primary and additional categories

Category is the primary category. Additional categories contains only other assignments; the primary category cannot be selected there again. Changing the primary selection updates this field immediately. In multisite mode each domain has its own primary category.

## Developer guide

Use the service that owns the operation: sCommerce for catalog reads, sCart for cart state and sCheckout for checkout. getProduct expects a numeric product ID; getProducts expects product IDs or a query, not a filter-options array. Product text is stored through texts(), category assignments through categories(), attribute values through attrValues, and published reviews through activeReviews. The category accessor is an ID, not a category model.

```php
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Facades\sCart;

$products = sCommerce::getCategoryProducts(24, $categoryId, evo()->getLocale());
$product = sCommerce::getProduct(123, evo()->getLocale());
if ($product->id) {
    $title = $product->pagetitle;
    $price = $product->priceTo('UAH');
}
$miniCart = sCart::getMiniCart();
```

Use the API integration guide for sApi and the separate sCommerce API package. Customer login/profile pages, external payment gateways and delivery providers are supplied by the project or their integration packages.

- [Admin guide](./admin/index.md)
- [Development workflow](./developers/workflow.md)
- [API / sApi](./developers/api.md)
