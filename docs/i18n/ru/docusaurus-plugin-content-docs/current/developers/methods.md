---
id: methods
title: Методы
sidebar_position: 11
---

## Базовая функциональность

### getProduct

Получает товар на основе заданного ID и языка.

```php
$product = sCommerce::getProduct(1, 'ru');
```

### getProductByAlias

Получает товар по его алиасу.

```php
$product = sCommerce::getProductByAlias('my-product-alias');
```

### getCategoryProducts

Получает товары, принадлежащие к определенной категории.

```php
$products = sCommerce::getCategoryProducts(10, 'ru', 5);
```

## Техническая функциональность

### documentListing

Получает список товаров из кеша или устанавливает его, если не найдено.

```php
$listing = sCommerce::documentListing();
```

### moduleUrl

Получает URL модуля.

```php
$url = sCommerce::moduleUrl();
```

### config

Получает значение из конфигурационного файла на основе заданного ключа.

```php
$catalogRoot = sCommerce::config('basic.catalog_root');
$friendlyUrlSuffix = sCommerce::config('basic.friendlyUrlSuffix');
```

## Дополнительные методы

### getTreeActiveCategories

Рекурсивно получает дерево категорий с подкатегориями. Автоматически применяет текущий язык, если установлен **sLang**, и может загружать TV.

```php
$category = sCommerce::getTreeActiveCategories(10, 5, ['menu_main', 'menu_footer']);
```

- Первый аргумент — идентификатор категории.
- Второй аргумент — глубина (опционально, по умолчанию `10`).
- Третий аргумент — массив TV (опционально). Требует **sLang**, чтобы заполнить переведённые значения.

### getCurrencies

Получает список всех доступных валют.

```php
$currencies = sCommerce::getCurrencies();
```

### tabRender

Рендерит вкладку для административной панели.

```php
$view = sCommerce::tabRender('mypage', 'template', $data, 'Title', 'icon', 'help');
```

## Примеры использования

### Получение товаров категории:

```php
use Seiger\sCommerce\Facades\sCommerce;

class ProductController extends BaseController
{
    public function index()
    {
        $categoryId = evo()->documentIdentifier;
        $products = sCommerce::getCategoryProducts($categoryId, 'ru', 10);
        
        return view('products.index', compact('products'));
    }
}
```

### Работа с конфигурацией:

```php
// Получение настроек
$catalogRoot = sCommerce::config('basic.catalog_root');
$mainCurrency = sCommerce::config('currencies.main', 'USD');

// Установка настроек
sCommerce::config('basic.friendlyUrlSuffix', '.html');
```

### Работа с валютами:

```php
// Получение списка валют
$currencies = sCommerce::getCurrencies();

// Получение конкретной валюты
$usd = $currencies->firstWhere('alpha', 'USD');
echo $usd['name']; // United States Dollar
```
