---
id: integration
title: Інтеграція
sidebar_position: 6
---

Інтегруйте можливості sCommerce у ваш код, використовуючи наведені нижче приклади.

## Дерево підкатегорій

Для отримання потрібної категорії разом з підкатегоріями використовуйте метод для рекурсивної
побудови дерева категорій `sCommerce::getTreeActiveCategories(evo()->documentIdentifier, 10)`.

Цей метод поверне шукану категорію разом з рекурсивно побудованими підкатегоріями.
Глибина вкладеності залежить від параметра `$dept` (за замовчуванням 10).

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

#### Виклик методу getTreeActiveCategories()

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

#### Аргументи методу getTreeActiveCategories()

З наведеного вище прикладу:

`evo()->documentIdentifier` - ID категорії, для якої потрібно отримати дані. Тип number integer.

`10` - Глибина вкладеності для отримання підкатегорій. Тип number integer. За замовчуванням 10.

#### Застосування результату виклику getTreeActiveCategories()

Нижче наведено приклад використання результату виклику методу getTreeActiveCategories() в Blade шаблоні.

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

## Товари за категорією

Для отримання всіх товарів, пов'язаних з поточною категорією, достатньо викликати пов'язані моделі товарів
так:

```php
...
use Seiger\sCommerce\Models\sCategory;
...
$category = sCategory::find(evo()->documentIdentifier);
$products = $category->products()->count(); // Кількість товарів
$products = $category->products; // Список товарів
```

## Товари категорії та підкатегорії

Для відображення всіх товарів кореневої категорії та її пов'язаних підкатегорій,
потрібно використовувати метод `getCategoryProducts()`.

#### Виклик методу getCategoryProducts()

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

#### Аргументи методу getCategoryProducts()

З наведеного вище прикладу:

Цей метод можна викликати без аргументів.
В цьому випадку буде повернено список товарів, де кореневою категорією буде **поточна категорія**.
Якщо використовується багатомовність, то дані текстових полів будуть представлені **поточною мовою**.
Глибина вкладеності підкатегорій буде **10**.

`evo()->documentIdentifier` - ID кореневої категорії, для якої потрібно отримати дані.
Тип number integer або null. За замовчуванням null.

`'base'` - Мова для відображення полів товару. Рядок коду мови або null. За замовчуванням null.

`10` - Глибина вкладеності для отримання підкатегорій. Тип number integer. За замовчуванням 10.

#### Результат виклику getCategoryProducts()

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

## Атрибут товару

Ви можете отримати значення атрибута товару, передавши його [ключ](./attributes/index.md#key).

#### Результат виклику attribute()

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

#### Приклад виклику attribute()

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

#### Представлення атрибута в blade

```php
<div class="item-brand">
    @lang('Brand'): <a href="@makeUrl(sCommerce::config('basic.catalog_root'))?brand={{$product->attribute('brand')?->value ?? ''}}">
        {{$product->attribute('brand')?->label ?? ''}}
    </a>
</div>
```
