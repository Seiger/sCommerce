---
id: integration
title: Integration
sidebar_position: 6
---

Integrate sCommerce capabilities into your code using the examples below.

## Tree of subcategories

In order to get the desired category together with subcategories, use the method for recursive
construction of the category tree `sCommerce::getTreeActiveCategories(evo()->documentIdentifier, 10)`.

This method will return the searched category along with the recursively constructed subcategories.
Nesting depth depends on the `$dept` parameter (default 10).

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

#### Call the getTreeActiveCategories() method

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

#### Arguments the getTreeActiveCategories() method

From the example above:

`evo()->documentIdentifier` - The ID of the category for which you want to retrieve data. Number type integer.

`10` - Nesting depth to get subcategories. Number type integer. Default 10.

#### Applying the result of the getTreeActiveCategories() call

See below for an example of using the result of the getTreeActiveCategories() method call in the Blade template.

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

## Products by category

To get all the products related to the current category, it is enough to call the related product models
like this:

```php
...
use Seiger\sCommerce\Models\sCategory;
...
$category = sCategory::find(evo()->documentIdentifier);
$products = $category->products()->count(); // Products count
$products = $category->products; // Products list
```

## Category and subcategory products

To display all products of the root category and its associated subcategories,
you need to use the `getCategoryProducts()` method.

#### Call the getCategoryProducts() method

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

#### Arguments the getCategoryProducts() method

From the example above:

This method can be called with no arguments.
In this case, a list of products will be returned, where the root category will be the **current category**.
If a multi-language is used, then the data of the text fields will be presented in the **current language**.
The nesting **depth of** subcategories will be **10**.

`evo()->documentIdentifier` - The ID of the root category for which data is to be retrieved.
Number type integer or null. Default null.

`'base'` - Language for displaying product fields. String language code or null. Default null.

`10` - Nesting depth to get subcategories. Number type integer. Default 10.

#### Result of the getCategoryProducts() call

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

## Product attribute

You can get the value of a product attribute by passing its [key](../attributes/index.md#key).

#### Result of the attribute() call

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

#### Example of the attribute() call

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

#### Present attribute in the blade

```php
<div class="item-brand">
    @lang('Brand'): <a href="@makeUrl(sCommerce::config('basic.catalog_root'))?brand={{$product->attribute('brand')?->value ?? ''}}">
        {{$product->attribute('brand')?->label ?? ''}}
    </a>
</div>
```