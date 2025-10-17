---
id: methods
title: Методи
sidebar_position: 11
---

## Базова функціональність

### getProduct

Отримує товар на основі заданого ID та мови.

```php
$product = sCommerce::getProduct(1, 'uk');
```

### getProductByAlias

Отримує товар за його аліасом.

```php
$product = sCommerce::getProductByAlias('my-product-alias');
```

### getCategoryProducts

Отримує товари, що належать до певної категорії.

```php
$products = sCommerce::getCategoryProducts(10, 'uk', 5);
```

### getActiveCategoriesTree

Отримує дерево активних категорій.

```php
$categories = sCommerce::getActiveCategoriesTree(10);
```

## Технічна функціональність

### documentListing

Отримує список товарів з кешу або встановлює його, якщо не знайдено.

```php
$listing = sCommerce::documentListing();
```

### moduleUrl

Отримує URL модуля.

```php
$url = sCommerce::moduleUrl();
```

### config

Отримує значення з конфігураційного файлу на основі заданого ключа.

```php
$catalogRoot = sCommerce::config('basic.catalog_root');
$friendlyUrlSuffix = sCommerce::config('basic.friendlyUrlSuffix');
```

## Додаткові методи

### getTreeActiveCategories

Рекурсивно отримує дерево категорій з підкатегоріями.

```php
$category = sCommerce::getTreeActiveCategories(10, 5);
```

### getCurrencies

Отримує список всіх доступних валют.

```php
$currencies = sCommerce::getCurrencies();
```

### tabRender

Рендерить вкладку для адміністративної панелі.

```php
$view = sCommerce::tabRender('mypage', 'template', $data, 'Title', 'icon', 'help');
```

## Приклади використання

### Отримання товарів категорії:

```php
use Seiger\sCommerce\Facades\sCommerce;

class ProductController extends BaseController
{
    public function index()
    {
        $categoryId = evo()->documentIdentifier;
        $products = sCommerce::getCategoryProducts($categoryId, 'uk', 10);
        
        return view('products.index', compact('products'));
    }
}
```

### Робота з конфігурацією:

```php
// Отримання налаштувань
$catalogRoot = sCommerce::config('basic.catalog_root');
$mainCurrency = sCommerce::config('currencies.main', 'USD');

// Встановлення налаштувань
sCommerce::config('basic.friendlyUrlSuffix', '.html');
```

### Робота з валютами:

```php
// Отримання списку валют
$currencies = sCommerce::getCurrencies();

// Отримання конкретної валюти
$usd = $currencies->firstWhere('alpha', 'USD');
echo $usd['name']; // United States Dollar
```
