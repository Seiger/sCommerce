---
layout: page
title: Getting started
description: Getting started with sCommerce
permalink: /getting-started/
---

## Minimum requirements

- Evolution CMS 3.2.0
- PHP 8.3.0
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

## Management

After installing the module, you can use it immediately. Path to the module in the 
administrator panel **Admin Panel -> Modules -> Commerce**.

You can also fix quick access to the module through the main menu of the Admin Panel. 
This can be done on the configuration tab (only available for the administrator role).

[Management tabs]({{site.baseurl}}/management/){: .btn .btn-sky}

## Integration

Integrate sCommerce capabilities into your code using the examples provided on the Integration page.

[Integration]({{site.baseurl}}/integration/){: .btn .btn-sky}

## Extra

If you write your own code that can integrate with the sCommerce module, 
you can check the presence of this module in the system through a configuration variable.

```php
if (evo()->getConfig('check_sCommerce', false)) {
    // You code
}
```

If the plugin is installed, the result of ```evo()->getConfig('check_sCommerce', false)``` 
will always be ```true```. Otherwise, you will get an ```false```.
