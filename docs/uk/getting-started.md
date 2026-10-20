---
id: getting-started
title: Швидкий старт
sidebar_position: 2
---

# Початок роботи

sCommerce надає каталог товарів, кошик, оформлення й керування замовленнями для Evolution CMS. Інтерфейс і набір вкладок залежать від налаштувань магазину та встановлених інтеграцій.

## Вимоги

- PHP **8.4+**
- Evolution CMS **3.5.8+**
- Composer

## Встановлення

Виконуйте команди у встановленому проєкті Evolution CMS. Перед заміною локальних перевизначень перевіряйте опубліковані файли; не застосовуйте --force без потреби.

```bash
cd core
php artisan package:installrequire seiger/scommerce "*"
php artisan vendor:publish --provider="Seiger\sCommerce\sCommerceServiceProvider"
php artisan migrate
```

## Керування магазином

Установіть пакет, виконайте міграції та відкрийте Модулі → Комерція в менеджері. У налаштуваннях задайте корінь каталогу, доступні валюти й основну валюту. Створіть ресурси категорій у дереві Evolution CMS, а товари — в Комерції.

У мовній вкладці контенту заповніть назву товару; задайте SKU, псевдонім, доступність, ціну й валюту. Виберіть основну та за потреби додаткові категорії, додайте фото через sGallery. Опублікуйте товар і перевірте його сторінку.

## Основна й додаткові категорії

Категорія — основна категорія товару. Додаткові категорії містять лише інші прив’язки: основну не можна вибрати там повторно. Зміна основної категорії одразу оновлює поле. У мультидоменному режимі кожен домен має свою основну категорію.

## Розробнику

Використовуйте сервіс відповідної операції: sCommerce для каталогу, sCart для кошика, sCheckout для оформлення. getProduct приймає числовий ID; getProducts — список ID або запит, а не масив параметрів фільтрації. Тексти товару зберігаються через texts(), прив’язки категорій — через categories(), значення атрибутів — attrValues, опубліковані відгуки — activeReviews. Аксесор category повертає ID, а не модель категорії.

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

sApi та окремий пакет sCommerce API описані в посібнику інтеграції API. Авторизацію й профіль клієнта, зовнішні платіжні шлюзи та провайдерів доставки додає проєкт або інтеграційні пакети.

- [Керування магазином](./admin/index.md)
- [Процес розробки](./developers/workflow.md)
- [API / sApi](./developers/api.md)
