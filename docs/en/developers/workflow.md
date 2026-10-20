---
id: workflow
title: Development Workflow
sidebar_position: 1
---

# Development workflow

1. Configure a test CMS and install the package using the commands in Getting started.
2. Define catalog resources, languages, domains, currencies, delivery and payment methods.
3. Create products in the manager or use the model example below in an authorized project command.
4. Build storefront templates using the catalog, cart and checkout services; add project authentication and providers as required.
5. Test category assignments, prices, stock states, checkout and callbacks. Run CSV export/import on a test database before using production data.
6. Review changes, deploy through the project workflow and run only the required migrations/publication steps.

## Installation

Run these commands from an installed Evolution CMS project. Review published files before replacing project overrides; do not use --force indiscriminately.

```bash
cd core
php artisan package:installrequire seiger/scommerce "*"
php artisan vendor:publish --provider="Seiger\sCommerce\sCommerceServiceProvider"
php artisan migrate
```

## Product

Use the service that owns the operation: sCommerce for catalog reads, sCart for cart state and sCheckout for checkout. getProduct expects a numeric product ID; getProducts expects product IDs or a query, not a filter-options array. Product text is stored through texts(), category assignments through categories(), attribute values through attrValues, and published reviews through activeReviews. The category accessor is an ID, not a category model.

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sProductTranslate;

$categoryId = 123; // An existing category resource in this project.
$locale = evo()->getLocale();
$product = DB::transaction(function () use ($categoryId, $locale) {
    $product = new sProduct();
    $product->uuid = (string) Str::uuid();
    $product->alias = 'example-product'; // Use a unique alias.
    $product->sku = 'EXAMPLE-001';
    $product->published = 1;
    $product->availability = sProduct::AVAILABILITY_IN_STOCK;
    $product->inventory = 10;
    $product->price_regular = 100;
    $product->currency = 'UAH';
    $product->save();

    $text = new sProductTranslate();
    $text->product = $product->id;
    $text->lang = $locale;
    $text->pagetitle = 'Example product';
    $text->content = '<p>Product description.</p>';
    $text->save();

    $scope = evo()->getConfig('check_sMultisite', false)
        ? 'primary_' . evo()->getConfig('site_key', 'default') : 'primary';
    $product->categories()->sync([$categoryId => ['scope' => $scope, 'position' => 0]]);
    return $product;
});
```

## Frontend

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

The package supplies data-sc-buy, data-sc-remove, data-sc-fast-buy and data-sc-quantity handlers. Put the quantity input beside the buy button, as read by the supplied script. Use callbacks after the package scripts have loaded. Legacy CustomEvent compatibility is deprecated.

```javascript
sCommerce.onAddedToCart = (data) => console.log(data);
sCommerce.onRemovedFromCart = (data) => console.log(data);
sCommerce.onUpdatedCart = (data) => console.log(data);
sCommerce.onFastOrder = (data) => console.log(data);
sCommerce.onWishlist = (data) => console.log(data);
```

## Guides

- [Primary and additional categories](../admin/products.md)
- [CSV import and export](../admin/import-export.md)
- [Events](./events.md)
- [Payment](./payments/index.md)
- [API / sApi](./api.md)

Use the API integration guide for sApi and the separate sCommerce API package. Customer login/profile pages, external payment gateways and delivery providers are supplied by the project or their integration packages.
