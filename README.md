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

**sCommerce** is a set of e-commerce tools for Evolution CMS.
The sCommerce package allows you to use Evolution CMS as a platform
for online commerce with all the necessary tools.

## Features

- [ ] Orders list.
- [x] Products catalog.
- [ ] Filters.
- [ ] Promo codes.
- [ ] List of orders.
- [ ] Order statuses.
- [ ] Integration with payment systems.
- [ ] Integration with warehouses.
- [ ] Integration with trading platforms.

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