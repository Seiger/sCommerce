---
id: workflow
title: Флоу разработки
sidebar_position: 1
---

# Процесс разработки

1. Подготовьте тестовую CMS и установите пакет по руководству начала работы.
2. Настройте каталог, языки, домены, валюты, доставку и оплату.
3. Создавайте товары в менеджере или используйте пример модели в согласованной команде проекта.
4. Создайте шаблоны через сервисы каталога, корзины и оформления; подключите нужные провайдеры.
5. Проверьте категории, цены, наличие, оформление и callbacks; CSV проверяйте на тестовой базе.
6. Проверьте изменения и выполните необходимые миграции и публикацию файлов по процессу проекта.

## Установка

Выполняйте команды в установленном проекте Evolution CMS. Перед заменой переопределений проверьте публикуемые файлы; не используйте --force без необходимости.

```bash
cd core
php artisan package:installrequire seiger/scommerce "*"
php artisan vendor:publish --provider="Seiger\sCommerce\sCommerceServiceProvider"
php artisan migrate
```

## Product

Используйте sCommerce для каталога, sCart для корзины, sCheckout для оформления. getProduct принимает числовой ID; getProducts — список ID или запрос, а не массив фильтров. Тексты хранятся через texts(), категории — categories(), атрибуты — attrValues, опубликованные отзывы — activeReviews. category возвращает ID, а не модель категории.

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

Доступны обработчики data-sc-buy, data-sc-remove, data-sc-fast-buy и data-sc-quantity. Поле количества располагайте рядом с кнопкой покупки. Назначайте callbacks после загрузки скриптов. Старые CustomEvent помечены deprecated.

```javascript
sCommerce.onAddedToCart = (data) => console.log(data);
sCommerce.onRemovedFromCart = (data) => console.log(data);
sCommerce.onUpdatedCart = (data) => console.log(data);
sCommerce.onFastOrder = (data) => console.log(data);
sCommerce.onWishlist = (data) => console.log(data);
```

## Руководства

- [Основные и дополнительные категории](../admin/products.md)
- [CSV-импорт и экспорт](../admin/import-export.md)
- [Events](./events.md)
- [Payment](./payments/index.md)
- [API / sApi](./api.md)

sApi и отдельный пакет sCommerce API описаны в руководстве API. Авторизацию, профиль клиента, внешние платёжные шлюзы и службы доставки добавляет проект или интеграционные пакеты.
