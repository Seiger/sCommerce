---
layout: page
title: Getting started
description: Getting started with sCommerce
permalink: /getting-started/
---

## Minimum requirements

- Evolution CMS 3.2.0
- PHP 8.1.0
- Composer 2.2.0
- PostgreSQL 10.23.0
- MySQL 8.0.3
- MariaDB 10.5.2
- SQLite 3.25.0

## Install by artisan package

Go to You /core/ folder

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

## Extra

If you write your own code that can integrate with the sCommerce module, you can check the presence of this module in the system through a configuration variable.

```php
if (evo()->getConfig('check_sCommerce', false)) {
    // You code
}
```

If the plugin is installed, the result of ```evo()->getConfig('check_sCommerce', false)``` will always be ```true```. Otherwise, you will get an ```false```.
