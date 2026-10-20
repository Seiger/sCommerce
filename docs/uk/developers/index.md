---
id: developers
title: Посібник розробника
sidebar_position: 4
---

# Розробнику

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

## Frontend

Пакет надає обробники data-sc-buy, data-sc-remove, data-sc-fast-buy та data-sc-quantity. Поле кількості розміщуйте поряд із кнопкою купівлі, як очікує скрипт. Призначайте callbacks після завантаження скриптів пакета. Сумісність зі старими CustomEvent позначена deprecated.

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

## Посібники

- [Процес розробки](./workflow.md)
- [Catalog API](./integration.md)
- [API / sApi](./api.md)
- [Events](./events.md)
- [Курси валют](./currencies.md)
- [Payment](./payments/index.md)
- [Delivery](./delivery-widgets.md)
- [CSV-імпорт та експорт](../admin/import-export.md)

sApi та окремий пакет sCommerce API описані в посібнику інтеграції API. Авторизацію й профіль клієнта, зовнішні платіжні шлюзи та провайдерів доставки додає проєкт або інтеграційні пакети.
