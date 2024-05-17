---
layout: page
title: Integration
description: Integration sCommerce methods to You code
permalink: /integration/
---

Integrate sCommerce capabilities into your code using the examples below.

## Tree of subcategories

In order to get the desired category together with subcategories, use the method for recursive
construction of the category tree `sCommerce::getTreeActiveCategories(evo()->documentIdentifier, 10)`.

This method will return the searched category along with the recursively constructed subcategories.
Nesting depth depends on the `$dept` parameter (default 10).

```php
EvolutionCMS\Models\SiteContent {#1902 ▼
  #connection: "default"
  #table: "site_content"
  ...
  #attributes: array:37 [▼
    "id" => "50"
    "type" => "document"
    ...
    "subcategories" => EvolutionCMS\Extensions\Collection {#1889 ▼
      #items: array:4 [▼
        0 => EvolutionCMS\Models\SiteContent {#1888 ▼
          #connection: "default"
          #table: "site_content"
          ...
          #attributes: array:37 [▶]
          #original: array:36 [▶]
          ...
        }
        1 => EvolutionCMS\Models\SiteContent {#1886 ▶}
        2 => EvolutionCMS\Models\SiteContent {#1884 ▶}
        3 => EvolutionCMS\Models\SiteContent {#1882 ▶}
      ]
      #escapeWhenCastingToString: false
    }
  ]
  #original: array:36 [▶]
  ...
}
```

### Call the getTreeActiveCategories() method

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

### Arguments the getTreeActiveCategories() method

From the example above:

`evo()->documentIdentifier` - The ID of the category for which you want to retrieve data. Number type integer.
`10` - Nesting depth to get subcategories. Number type integer. Default 10.

### Applying the result of the getTreeActiveCategories() call

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