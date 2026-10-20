---
id: developers
title: Developer Guide
sidebar_position: 4
---

# Developer guide

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

## Frontend

The package supplies data-sc-buy, data-sc-remove, data-sc-fast-buy and data-sc-quantity handlers. Put the quantity input beside the buy button, as read by the supplied script. Use callbacks after the package scripts have loaded. Legacy CustomEvent compatibility is deprecated.

```html
<div>
  <input type="number" value="1" min="1">
  <button type="button" data-sc-buy="123">Buy</button>
</div>
```

```javascript
sCommerce.onAddedToCart = (data) => console.log(data);
sCommerce.onRemovedFromCart = (data) => console.log(data);
sCommerce.onUpdatedCart = (data) => console.log(data);
sCommerce.onFastOrder = (data) => console.log(data);
sCommerce.onWishlist = (data) => console.log(data);
```

## Guides

- [Development workflow](./workflow.md)
- [Catalog API](./integration.md)
- [API / sApi](./api.md)
- [Events](./events.md)
- [Exchange rates](./currencies.md)
- [Payment](./payments/index.md)
- [Delivery](./delivery-widgets.md)
- [CSV import and export](../admin/import-export.md)

Use the API integration guide for sApi and the separate sCommerce API package. Customer login/profile pages, external payment gateways and delivery providers are supplied by the project or their integration packages.
