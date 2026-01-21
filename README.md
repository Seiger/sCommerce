# sCommerce for Evolution CMS
![List of Products](https://github.com/user-attachments/assets/8dd1127c-5055-4795-954c-95eb75eadf31)
![Products by Category](https://github.com/user-attachments/assets/c6d9a6e3-aad4-4efd-b775-0ee626a4714c)
![sCommerce Settings block](https://github.com/user-attachments/assets/3c2283bf-a2b8-4af1-a01b-97e88b0ecc21)
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/sCommerce?label=version)](https://packagist.org/packages/seiger/scommerce)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/scommerce)
[![License](https://img.shields.io/packagist/l/seiger/scommerce)](https://packagist.org/packages/seiger/scommerce)
[![Issues](https://img.shields.io/github/issues/Seiger/sCommerce)](https://github.com/Seiger/sCommerce/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/scommerce)](https://packagist.org/packages/seiger/scommerce)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/scommerce)](https://packagist.org/packages/seiger/scommerce)
[![Liberapay](https://img.shields.io/liberapay/patrons/seigerkornelyuk.svg?logo=liberapay)](https://packagist.org/packages/seiger/scommerce)

# Welcome to sCommerce!

> [!IMPORTANT]  
> sCommerce not stable yet. It active development.

**sCommerce** stands as a comprehensive suite of **e-commerce** tools meticulously crafted
for Evolution CMS. Tailored to empower Evolution CMS users, this dynamic package
seamlessly integrates with the CMS platform, transforming it into a robust foundation
for online commerce. With a focus on delivering all the necessary tools, **sCommerce**
equips users with the essential features required to establish and manage a thriving
**e-commerce** presence. Whether you are a developer, website administrator, or content
management enthusiast, **sCommerce** provides a streamlined solution, unlocking the potential
of Evolution CMS for seamless and efficient **online commerce**.

## Features

- [x] Order Management.
- [x] Order Status Management.
- [x] Products Catalog.
- [x] Product position in each Category.
- [x] Products Types.
    - [x] Simple Type.
    - [x] Grouped Type.
    - [ ] Bundle Type.
    - [ ] Variable Type.
    - [ ] Optional Type.
    - [ ] Downloadable Type.
    - [ ] Virtual Type.
    - [ ] Service Type.
    - [ ] Subscription Type.
    - [ ] Preorder Type.
    - [ ] Custom Type.
- [x] Products Attributes.
    - [x] Number Attribute.
    - [x] Checkbox Attribute.
    - [ ] Radio Attribute.
    - [x] Select Attribute.
    - [x] Multiselect Attribute.
    - [x] Text Attribute.
    - [ ] TextArea Attribute.
    - [ ] RichText Attribute.
    - [x] Color Attribute.
    - [ ] Date Attribute.
    - [ ] DateTime Attribute.
    - [ ] Image Attribute.
    - [ ] File Attribute.
    - [ ] Geolocation Attribute.
    - [ ] Constructor Attribute.
    - [x] Custom Attribute.
- [x] Duplicate Product.
- [x] Dynamic Filters for Product Search.
- [x] Dynamic Sort Products in Catalog.
- [ ] AI-Powered Product Search.
- [ ] Customer Reviews and Ratings.
- [ ] Wishlist and Favorites.
- [x] Checkout.
- [x] One Click Checkout.
- [ ] Promo Code System.
- [x] Plugin events.
    - [x] sCommerceManagerAddTabEvent.
    - [x] sCommerceFormFieldRender.
    - [x] sCommerceAfterProductSave.
    - [x] sCommerceAfterProductContentSave.
    - [x] sCommerceAfterProductDuplicate.
- [x] Javascript events.
    - [x] sCommerceAddedToCart.
    - [x] sCommerceRemovedFromCart.
- [x] Multi-currency Support (ISO 4217).
- [x] Integration with Payment Systems.
    - [x] Cash.
- [x] Integration with Deliveries Methods.
    - [x] Courier.
    - [x] Pickup.
- [ ] Integration with Warehouses.
- [ ] Integration with Trading Platforms.
- [x] **[sLang](https://github.com/Seiger/sLang)** Integration.
- [x] **[sGallery](https://github.com/Seiger/sGallery)** Integration.
- [x] **[sMultisite](https://github.com/Seiger/sMultisite)** Integration.
- [x] **[sApi](https://github.com/Seiger/sApi)** Integration.
- [ ] Personalized Recommendations.
- [ ] Social Media Integration.
- [ ] Automated Email Marketing.
- [ ] Advanced Analytics and Reporting.

## Requirements

- PHP **8.4+**
- Evolution CMS 3.5+
- Composer

## Install by artisan package installer

Go to You /core/ folder:

```console
cd core
```

Run php artisan commands

```console
php artisan package:installrequire seiger/scommerce "*"
```

```console
php artisan vendor:publish --provider="Seiger\sCommerce\sCommerceServiceProvider"
```

```console
php artisan migrate
```

## API (sApi integration)

sCommerce **does not** register `/api/*` (or `/{SAPI_BASE_PATH}/*`) API routes by itself.
API endpoints are exposed only when `seiger/sapi` is installed, via sApi provider discovery.

This package declares API route providers in Composer metadata:

`core/vendor/seiger/scommerce/composer.json` → `extra.sapi.route_providers`

Example (orders):
- `GET /{SAPI_BASE_PATH}/{SAPI_VERSION}/orders` (if `SAPI_VERSION` is empty → `/{SAPI_BASE_PATH}/orders`)
- `PUT /{SAPI_BASE_PATH}/{SAPI_VERSION}/orders/{order_id}`

Provider class:
- `Seiger\\sCommerce\\Api\\Routes\\OrdersRouteProvider`

[See full documentation here](https://seiger.github.io/sCommerce/)
