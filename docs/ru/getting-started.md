---
id: getting-started
title: Быстрый старт
sidebar_position: 2
---

# Начало работы

sCommerce предоставляет каталог, корзину, оформление и управление заказами для Evolution CMS. Интерфейс и вкладки зависят от настроек магазина и установленных интеграций.

## Требования

- PHP **8.4+**
- Evolution CMS **3.5.8+**
- Composer

## Установка

Выполняйте команды в установленном проекте Evolution CMS. Перед заменой переопределений проверьте публикуемые файлы; не используйте --force без необходимости.

```bash
cd core
php artisan package:installrequire seiger/scommerce "*"
php artisan vendor:publish --provider="Seiger\sCommerce\sCommerceServiceProvider"
php artisan migrate
```

## Управление магазином

Установите пакет, выполните миграции и откройте Модули → Коммерция. В настройках задайте корень каталога, доступные и основную валюту. Категории создаются как ресурсы в дереве Evolution CMS, товары — в Коммерции.

Заполните название в языковой вкладке контента, SKU, псевдоним, доступность, цену и валюту. Выберите основную и дополнительные категории, добавьте фото через sGallery. Опубликуйте товар и проверьте его страницу.

## Основные и дополнительные категории

Категория — основная категория товара. Дополнительные категории содержат только другие привязки; основную нельзя выбрать повторно. Изменение основной категории сразу обновляет поле. В мультидоменном режиме у каждого домена своя основная категория.

## Разработчику

Используйте sCommerce для каталога, sCart для корзины, sCheckout для оформления. getProduct принимает числовой ID; getProducts — список ID или запрос, а не массив фильтров. Тексты хранятся через texts(), категории — categories(), атрибуты — attrValues, опубликованные отзывы — activeReviews. category возвращает ID, а не модель категории.

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

sApi и отдельный пакет sCommerce API описаны в руководстве API. Авторизацию, профиль клиента, внешние платёжные шлюзы и службы доставки добавляет проект или интеграционные пакеты.

- [Управление магазином](./admin/index.md)
- [Процесс разработки](./developers/workflow.md)
- [API / sApi](./developers/api.md)
