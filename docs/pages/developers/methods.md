---
layout: page
title: Methods
description: sCommerce Core methods
permalink: /methods/
---

## Basic functionality

### getProduct

Retrieves the product based on the given ID and language.

### getProductByAlias

Retrieves a product by its alias.

### getCategoryProducts

Retrieves the products belonging to a specific category.

### getTreeActiveCategories

Builds a category tree starting from the given category ID.

```php
$tree = sCommerce::getTreeActiveCategories(48, 5, ['menu_main', 'menu_footer']);
```

- **First argument** – category ID.
- **Second argument** – depth (optional, default `10`).
- **Third argument** – array of TV names (optional). Requires the sLang module to populate translated TV values.

## Technical functionality

### documentListing

Retrieves the products listing from cache or sets it if not found.

### moduleUrl

Retrieves the module URL.

### config

Retrieves the value from the config file based on the given key.