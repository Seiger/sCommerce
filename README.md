# sCommerce for Evolution CMS
![sCommerce](https://repository-images.githubusercontent.com/683186810/d71c1c9b-f143-4000-8125-5104eeee067b)
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/sCommerce?label=version)](https://packagist.org/packages/seiger/scommerce)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/scommerce)
[![License](https://img.shields.io/packagist/l/seiger/scommerce)](https://packagist.org/packages/seiger/scommerce)
[![Issues](https://img.shields.io/github/issues/Seiger/sCommerce)](https://github.com/Seiger/sCommerce/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/scommerce)](https://packagist.org/packages/seiger/scommerce)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/scommerce)](https://packagist.org/packages/seiger/scommerce)

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

- [ ] Order Management.
- [ ] Order Status Management.
- [x] Products Catalog.
- [x] Products Attributes.
    - [x] Number Attribute.
    - [ ] Checkbox Attribute.
    - [ ] Radio Attribute.
    - [ ] Select Attribute.
    - [ ] Multiselect Attribute.
    - [x] Text Attribute.
    - [ ] TextArea Attribute.
    - [ ] RichText Attribute.
    - [ ] Color Attribute.
    - [ ] Date Attribute.
    - [ ] DateTime Attribute.
    - [ ] Image Attribute.
    - [ ] File Attribute.
    - [ ] Geolocation Attribute.
    - [ ] Constructor Attribute.
    - [x] Custom Attribute.
- [ ] Dynamic Filters for Product Search.
- [ ] Promo Code System.
- [ ] Customer Reviews and Ratings.
- [ ] Multi-currency Support.
- [ ] Integration with Payment Systems.
- [ ] Integration with Warehouses.
- [ ] Integration with Trading Platforms.
- [x] **[sLang](https://github.com/Seiger/sLang)** Integration.
- [x] **[sGallery](https://github.com/Seiger/sGallery)** Integration.
- [x] **[sMultisite](https://github.com/Seiger/sMultisite)** Integration.
- [ ] Advanced Analytics and Reporting.
- [ ] Personalized Recommendations.
- [ ] Social Media Integration.
- [ ] AI-Powered Product Search.
- [ ] Flexible Product Bundles.
- [ ] Automated Email Marketing.
- [ ] Wishlist and Favorites.

## Minimum requirements

- Evolution CMS 3.2.0
- PHP 8.1.0
- Composer 2.2.0
- PostgreSQL 10.23.0
- MySQL 8.0.3
- MariaDB 10.5.2
- SQLite 3.25.0

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

[See full documentation here](https://seiger.github.io/sCommerce/)