---
id: developers
title: Руководство разработчика
sidebar_position: 4
---

# Разработчику

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

## Frontend

Доступны обработчики data-sc-buy, data-sc-remove, data-sc-fast-buy и data-sc-quantity. Поле количества располагайте рядом с кнопкой покупки. Назначайте callbacks после загрузки скриптов. Старые CustomEvent помечены deprecated.

```html
<div>
  <input type="number" value="1" min="1">
  <button type="button" data-sc-buy="123">Buy</button>
</div>
```

```javascript
sCommerce.onAddedToCart = (data) => console.log(data);
sCommerce.onRemovedFromCart = (data) => console.log(data);
sCommerce.onUpdatedCart = (data) => console.log(data);
sCommerce.onFastOrder = (data) => console.log(data);
sCommerce.onWishlist = (data) => console.log(data);
```

## Руководства

- [Процесс разработки](./workflow.md)
- [Catalog API](./integration.md)
- [API / sApi](./api.md)
- [Events](./events.md)
- [Курсы валют](./currencies.md)
- [Payment](./payments/index.md)
- [Delivery](./delivery-widgets.md)
- [CSV-импорт и экспорт](../admin/import-export.md)

sApi и отдельный пакет sCommerce API описаны в руководстве API. Авторизацию, профиль клиента, внешние платёжные шлюзы и службы доставки добавляет проект или интеграционные пакеты.
