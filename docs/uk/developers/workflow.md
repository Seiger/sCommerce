---
id: workflow
title: Флоу розробки
sidebar_position: 1
---

# Процес розробки

1. Підготуйте тестову CMS та встановіть пакет за інструкцією початку роботи.
2. Налаштуйте ресурси каталогу, мови, домени, валюти, доставку й оплату.
3. Створюйте товари в менеджері або використовуйте наведений приклад моделі в погодженій команді проєкту.
4. Побудуйте шаблони через сервіси каталогу, кошика й оформлення; за потреби додайте авторизацію та провайдерів.
5. Перевірте категорії, ціни, стани наявності, оформлення й callbacks. Імпорт/експорт перевіряйте на тестовій базі.
6. Перегляньте зміни, застосуйте процес розгортання проєкту та виконайте потрібні міграції й публікацію файлів.

## Встановлення

Виконуйте команди у встановленому проєкті Evolution CMS. Перед заміною локальних перевизначень перевіряйте опубліковані файли; не застосовуйте --force без потреби.

```bash
cd core
php artisan package:installrequire seiger/scommerce "*"
php artisan vendor:publish --provider="Seiger\sCommerce\sCommerceServiceProvider"
php artisan migrate
```

## Product

Використовуйте сервіс відповідної операції: sCommerce для каталогу, sCart для кошика, sCheckout для оформлення. getProduct приймає числовий ID; getProducts — список ID або запит, а не масив параметрів фільтрації. Тексти товару зберігаються через texts(), прив’язки категорій — через categories(), значення атрибутів — attrValues, опубліковані відгуки — activeReviews. Аксесор category повертає ID, а не модель категорії.

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sProductTranslate;

$categoryId = 123; // An existing category resource in this project.
$locale = evo()->getLocale();
$product = DB::transaction(function () use ($categoryId, $locale) {
    $product = new sProduct();
    $product->uuid = (string) Str::uuid();
    $product->alias = 'example-product'; // Use a unique alias.
    $product->sku = 'EXAMPLE-001';
    $product->published = 1;
    $product->availability = sProduct::AVAILABILITY_IN_STOCK;
    $product->inventory = 10;
    $product->price_regular = 100;
    $product->currency = 'UAH';
    $product->save();

    $text = new sProductTranslate();
    $text->product = $product->id;
    $text->lang = $locale;
    $text->pagetitle = 'Example product';
    $text->content = '<p>Product description.</p>';
    $text->save();

    $scope = evo()->getConfig('check_sMultisite', false)
        ? 'primary_' . evo()->getConfig('site_key', 'default') : 'primary';
    $product->categories()->sync([$categoryId => ['scope' => $scope, 'position' => 0]]);
    return $product;
});
```

## Frontend

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

Пакет надає обробники data-sc-buy, data-sc-remove, data-sc-fast-buy та data-sc-quantity. Поле кількості розміщуйте поряд із кнопкою купівлі, як очікує скрипт. Призначайте callbacks після завантаження скриптів пакета. Сумісність зі старими CustomEvent позначена deprecated.

```javascript
sCommerce.onAddedToCart = (data) => console.log(data);
sCommerce.onRemovedFromCart = (data) => console.log(data);
sCommerce.onUpdatedCart = (data) => console.log(data);
sCommerce.onFastOrder = (data) => console.log(data);
sCommerce.onWishlist = (data) => console.log(data);
```

## Посібники

- [Основна й додаткові категорії](../admin/products.md)
- [CSV-імпорт та експорт](../admin/import-export.md)
- [Events](./events.md)
- [Payment](./payments/index.md)
- [API / sApi](./api.md)

sApi та окремий пакет sCommerce API описані в посібнику інтеграції API. Авторизацію й профіль клієнта, зовнішні платіжні шлюзи та провайдерів доставки додає проєкт або інтеграційні пакети.
