---
id: integration
title: Интеграция
sidebar_position: 6
---

Интегрируйте возможности sCommerce в ваш код, используя приведенные ниже примеры.

## Дерево подкатегорий

Для получения нужной категории вместе с подкатегориями используйте метод для рекурсивного
построения дерева категорий `sCommerce::getTreeActiveCategories(evo()->documentIdentifier, 10)`.

Этот метод вернет искомую категорию вместе с рекурсивно построенными подкатегориями.
Глубина вложенности зависит от параметра `$dept` (по умолчанию 10).

```php
 Seiger\sCommerce\Models\sCategory {#1902 ▼
  #connection: "default"
  #table: "site_content"
  ...
  #attributes: array:37 [▼
    "id" => "50"
    "type" => "document"
    ...
    "subcategories" => EvolutionCMS\Extensions\Collection {#1889 ▼
      #items: array:4 [▼
        0 => Seiger\sCommerce\Models\sCategory {#1888 ▼
          #connection: "default"
          #table: "site_content"
          ...
          #attributes: array:37 [▶]
          #original: array:36 [▶]
          ...
        }
        1 => Seiger\sCommerce\Models\sCategory {#1886 ▶}
        2 => Seiger\sCommerce\Models\sCategory {#1884 ▶}
        3 => Seiger\sCommerce\Models\sCategory {#1882 ▶}
      ]
      #escapeWhenCastingToString: false
    }
  ]
  #original: array:36 [▶]
  ...
}
```

#### Вызов метода getTreeActiveCategories()

```php
namespace EvolutionCMS\Main\Controllers;

use Seiger\sCommerce\Facades\sCommerce;

class CategoryController extends BaseController
{
    public function render()
    {
        parent::render();
        ...
        $this->data['category'] = sCommerce::getTreeActiveCategories(evo()->documentIdentifier, 10);
        ...
    }
}
```

#### Аргументы метода getTreeActiveCategories()

Из приведенного выше примера:

`evo()->documentIdentifier` - ID категории, для которой нужно получить данные. Тип number integer.

`10` - Глубина вложенности для получения подкатегорий. Тип number integer. По умолчанию 10.

#### Применение результата вызова getTreeActiveCategories()

Ниже приведен пример использования результата вызова метода getTreeActiveCategories() в Blade шаблоне.

```php
...
<section>
    <h1>{% raw %}{{$category->pagetitle}}{% endraw %}</h1>
    {% raw %}{!!$category->content!!}{% endraw %}
</section>
@if($category->subcategories->count())
    @foreach($category->subcategories as $subcategory)
        <section>
            <h2>{% raw %}{{$subcategory->pagetitle}}{% endraw %}</h2>
            <p>{% raw %}{{$subcategory->introtext}}{% endraw %}</p>
        </section>
    @endforeach
@endif
```

## Товары по категории

Для получения всех товаров, связанных с текущей категорией, достаточно вызвать связанные модели товаров
так:

```php
...
use Seiger\sCommerce\Models\sCategory;
...
$category = sCategory::find(evo()->documentIdentifier);
$products = $category->products()->count(); // Количество товаров
$products = $category->products; // Список товаров
```

## Товары категории и подкатегории

Для отображения всех товаров корневой категории и её связанных подкатегорий,
нужно использовать метод `getCategoryProducts()`.

#### Вызов метода getCategoryProducts()

```php
namespace EvolutionCMS\Main\Controllers;

use Seiger\sCommerce\Facades\sCommerce;

class CategoryController extends BaseController
{
    public function render()
    {
        parent::render();
        ...
        $products = sCommerce::getCategoryProducts(evo()->documentIdentifier, 'base', 10);
        ...
    }
}
```

#### Аргументы метода getCategoryProducts()

Из приведенного выше примера:

Этот метод можно вызвать без аргументов.
В этом случае будет возвращен список товаров, где корневой категорией будет **текущая категория**.
Если используется многоязычность, то данные текстовых полей будут представлены **текущим языком**.
Глубина вложенности подкатегорий будет **10**.

`evo()->documentIdentifier` - ID корневой категории, для которой нужно получить данные.
Тип number integer или null. По умолчанию null.

`'base'` - Язык для отображения полей товара. Строка кода языка или null. По умолчанию null.

`10` - Глубина вложенности для получения подкатегорий. Тип number integer. По умолчанию 10.

#### Результат вызова getCategoryProducts()

```php
Illuminate\Database\Eloquent\Collection {#1422 ▼
  #items: array:3 [▼
    0 => Seiger\sCommerce\Models\sProduct {#1359 ▼
      #connection: "default"
      #table: "s_products"
      #primaryKey: "id"
      #keyType: "int"
      +incrementing: true
      #with: []
      #withCount: []
      +preventsLazyLoading: false
      #perPage: 15
      +exists: true
      +wasRecentlyCreated: false
      #escapeWhenCastingToString: false
      #attributes: array:35 [▶]
      #original: array:35 [▶]
      #changes: []
      #casts: []
      #classCastCache: []
      #attributeCastCache: []
      #dateFormat: null
      #appends: array:2 [▶]
      #dispatchesEvents: []
      #observables: []
      #relations: []
      #touches: []
      +timestamps: true
      +usesUniqueIds: false
      #hidden: []
      #visible: []
      #fillable: []
      #guarded: array:1 [▶]
    }
    1 => Seiger\sCommerce\Models\sProduct {#1421 ▶}
    2 => Seiger\sCommerce\Models\sProduct {#1370 ▶}
  ]
  #escapeWhenCastingToString: false
}
```

## Атрибут товара

Вы можете получить значение атрибута товара, передав его [ключ](../attributes/index.md#key).

#### Результат вызова attribute()

```php
 Seiger\sCommerce\Models\sAttribute {#1663 ▼
  #connection: "default"
  #table: "s_attributes"
  #primaryKey: "id"
  #keyType: "int"
  +incrementing: true
  #with: []
  #withCount: []
  +preventsLazyLoading: false
  #perPage: 15
  +exists: true
  +wasRecentlyCreated: false
  #escapeWhenCastingToString: false
  #attributes: array:11 [▼
    "id" => "1"
    "published" => "1"
    "asfilter" => "1"
    "position" => "0"
    "type" => "3"
    "alias" => "brand"
    "helptext" => "Brand"
    "created_at" => "2024-11-25 20:55:13"
    "updated_at" => "2024-11-25 20:58:37"
    "value" => "leon"
    "label" => "Leon"
  ]
  #original: array:13 [▶]
  #changes: []
  #casts: []
  #classCastCache: []
  #attributeCastCache: []
  #dateFormat: null
  #appends: []
  #dispatchesEvents: []
  #observables: []
  #relations: array:1 [▶]
  #touches: []
  +timestamps: true
  +usesUniqueIds: false
  #hidden: []
  #visible: []
  #fillable: []
  #guarded: array:1 [▶]
}
```

#### Пример вызова attribute()

```php
<?php namespace EvolutionCMS\Main\Controllers;

...

class SCommerceProductController extends BaseController
{
    public function render()
    {
        parent::render();

        $product = evo()->documentObject['product'];
        $brand = $product->attribute('brand');
        dd($brand);
        
        ...
    }
    
    ...
    
}
```

#### Представление атрибута в blade

```php
<div class="item-brand">
    @lang('Brand'): <a href="@makeUrl(sCommerce::config('basic.catalog_root'))?brand={{$product->attribute('brand')?->value ?? ''}}">
        {{$product->attribute('brand')?->label ?? ''}}
    </a>
</div>
```
