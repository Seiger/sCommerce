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
Глибина вкладеності залежить від параметра `$dept` (типово 10). Якщо встановлено пакет **sLang**,
автоматично застосовується поточна локаль, а третім аргументом можна передати перелік TV.

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
        $this->data['category'] = sCommerce::getTreeActiveCategories(
            evo()->documentIdentifier,
            10,
            ['menu_main', 'menu_footer'] // опційно: перелік TV
        );
        ...
    }
}
```

#### Аргументи методу getTreeActiveCategories()

З наведеного вище прикладу:

`evo()->documentIdentifier` - ID категорії, для якої потрібно отримати дані. Тип number integer.

`10` - Глибина вкладеності для отримання підкатегорій. Тип number integer. Типово 10.

`['menu_main', 'menu_footer']` *(опційно)* – Масив із назвами TV, які потрібно додати до кожної категорії (працює, якщо встановлено **sLang**). Типово порожній масив.

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

Ви можете отримати значення атрибута товару, передавши його [ключ](../attributes/index.md#ключ).

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

## Знімок рядка замовлення

Починаючи з 1.5.0, кожен новий рядок замовлення (кошик на сайті, швидке замовлення, редактор у менеджері та повторні замовлення) зберігає свою ідентичність у JSON `products` замовлення поруч з наявними ключами (`id`, `title`, `price`, `quantity` та іншими):

| Ключ | Тип | Значення |
|---|---|---|
| `line_key` | string | `"{productId}:{optionId}"`, наприклад `"5:3"` |
| `product_id` | int | id товару (збігається з `id`) |
| `option_id` | int | id опції кошика; `0` — опції немає |
| `sku` | string | фактичний SKU; дорівнює `product_sku`, якщо слухач `sCommerce.ResolveLineIdentity` не задав SKU варіанта |
| `product_sku` | string | власний SKU товару |
| `gtin` | string або null | GTIN товару |
| `unit_price_net`, `tax_rate` | float або null | заготовки для податків, заповнюються лише слухачами `sCommerce.ResolveOrderTaxes` |
| `requires_shipping` | bool | рядок доставляється |
| `tracks_inventory` | bool | рядок враховується в залишках |
| `retail_excise_rate`, `retail_excise_amount` | float | лише в рядках із роздрібним акцизом: його ставка та сума, включена в суму рядка, для чека |
| `deposit_unit`, `exchange_quantity`, `deposit_quantity`, `deposit_amount`, `deposit_returnable`, `deposit_terms`, `deposit_returned_quantity` | mixed | лише в рядках із заставною вартістю зворотної тари, див. [Заставна вартість зворотної тари](payments/index.md#заставна-вартість-зворотної-тари) |

Податкові слухачі також можуть додавати `tax_code`, `unit_price_gross`, `tax_amount` і `prices_include_tax`. Див. [Події](events.md#рядки-замовлення-виконання-та-податки).

Два рядки кошика з одним товаром і різними опціями тепер розрізняються за `option_id` і `line_key`. Значення id опції визначає проєкт, наприклад його цінові слухачі; воно не вказує на рядок `s_product_modifications`.

Замовлення, створені до 1.5.0, не містять цих ключів. Споживачі мають використовувати резервні значення:

- `id` для `product_id`;
- `0` для `option_id`;
- `sku` для `sku` і `product_sku`;
- `true` для `requires_shipping` і `tracks_inventory`.

`line_key` унікальний у межах замовлення, створеного з кошика. Менеджер може додати той самий товар двічі, тому споживачам слід також використовувати індекс рядка. Рядки, додані в менеджері, отримують ідентичність на сервері під час збереження замовлення; рядки, що вже мають `line_key`, зберігають його.

`sCommerce::supportsIntegration('line-identity')` повідомляє про підтримку.
