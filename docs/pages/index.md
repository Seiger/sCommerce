---
layout: doc
title: sCommerce Documentation
description: Powerful eCommerce module for Evolution CMS.
permalink: /
---

# sCommerce Documentation

sCommerce is a powerful, extensible and modern eCommerce module built for [Evolution CMS](https://evo.im).  
It provides everything you need to manage products, orders, attributes, delivery methods and more.

---

## Get Started

Start with the basics.

- [Installation guide](/getting-started/)
- [Quick integration](/integration/)
- [Management interface](/management/)

---

## API Reference

Coming soon — use sCommerce via RESTful API.  
You'll be able to create orders, fetch products, track user carts and more.

---

## Examples

```php
// Add product to cart
sCommerce::cart()->add($productId, 2);
```

```json
// Sample product data
{
  "id": 103,
  "name": "Black T-Shirt",
  "price": 24.99
}
```

---

## Why sCommerce?

- ⚡ Fast — zero performance impact on frontend
- 🧩 Modular — extend anything: shipping, payment, admin tabs
- 🔐 Secure — built on modern Laravel architecture
- 🔄 Compatible — works with Evolution CMS 3.2+
