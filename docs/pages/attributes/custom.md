---
id: custom
title: Custom Attribute
sidebar_position: 1
---

## About

An attribute of type Custom gives you extensive authority to control the values of the attribute.
The data is stored in the form of json. When saving values of this type, the data must be transferred
to the server in the form of an array.

All problems related to the presentation of values of this type rest on the shoulders of the developer.
To create a presentation, you need to place the Blade template in the
`../assets/modules/scommerce/attribute/attributekey.blade.php` directory.

All representations for Attributes of type Custom must be stored in the directory
`../assets/modules/scommerce/attribute/`, the template file name must be as the
[key field](./index.md#key) of the Attribute.

## Agreements

### Attribute structure

The Attribute structure is available in the `$attribute` object, but it is not possible to get the value
through this object:

```php
Seiger\sCommerce\Models\sAttribute {#1298 ▼
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
  #attributes: array:16 [▼
    "id" => "3"
    "published" => "1"
    "asfilter" => "0"
    "position" => "0"
    "type" => "15"
    "alias" => "dates"
    "helptext" => ""
    "created_at" => "2024-05-03 11:54:06"
    "updated_at" => "2024-05-03 11:54:06"
    "atid" => "3"
    "attribute" => "3"
    "lang" => "base"
    "pagetitle" => "Dates"
    "longtitle" => ""
    "introtext" => "Dates for tour available"
    "content" => ""
    "pivot_value" => "{"0":{"from":"2024-05-09","to":"2024-05-12","info":"sold","price":"55","text":"Text"},"1001":{"from":"","to":"","info":"","price":"","text":""}}"
  ]
  #original: array:16 [▶]
  #changes: []
  #casts: []
  #classCastCache: []
  #attributeCastCache: []
  #dateFormat: null
  #appends: []
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
```

### Location

All custom field template files must be placed in the `../attribute/` directory of your server.

For example `/var/www/html/my-site.com/assets/modules/scommerce/attribute/`.

### Name

All custom field template files must be Blade templates and named as an Attribute
[key field](./index.md#key).

For example, for the Attribute named **Dates**, which has the key **dates**,
the file will be named `dates.blade.php`.

### Representation

In the database, attribute values are stored as a JSON string. Therefore, the formation of form
fields is proposed as more convenient for the developer.

For example, the template for filling the list with dates, which is located in
file `/var/www/html/my-site.com/assets/modules/scommerce/attribute/dates.blade.php`.

```php
@php
$values = [];
$vals = json_decode($attribute->value ?? '', true);
if ($vals) {
    foreach ($vals as $v) {
        if (count(array_diff($v, [""]))) {
            $values[] = $v;
        }
    }
}
@endphp
<div class="row-col col-12">
    <div class="row form-row">
        <div class="col-auto col-title">
            <label for="attribute__{% raw %}{{$attribute->id}}{% endraw %}">{% raw %}{{$attribute->pagetitle}}{% endraw %}</label>
            @if(trim($attribute->helptext))<i class="fa fa-question-circle" data-tooltip="{% raw %}{{$attribute->helptext}}{% endraw %}"></i>@endif
            <br/>&emsp;<i onclick="add{% raw %}{{$attribute->id}}{% endraw %}Attr(this)" class="fa fa-plus-circle text-success"></i>
        </div>
        <div class="col">
            @if(count($values))
                @foreach($values as $val)
                    <div class="row form-row">
                        <div class="col">
                            <div class="row form-row">
                                <div class="input-group mb-1 row-col col-lg-1 col-lg-2 col-md-3 col-6">
                                    <div class="input-group-prepend"><span class="input-group-text"><small>Date from</small></span></div>
                                    <input value="{% raw %}{{$val['from']}}{% endraw %}" type="date" class="form-control" autocomplete="off" onchange="this.nextElementSibling.value = this.value">
                                    <input name="attribute__{% raw %}{{$attribute->id}}{% endraw %}[{% raw %}{{$loop->index}}{% endraw %}][from]" value="{% raw %}{{$val['from']}}{% endraw %}" type="hidden" onchange="documentDirty=true;">
                                </div>
                                <div class="input-group mb-1 row-col col-lg-1 col-lg-2 col-md-3 col-6">
                                    <div class="input-group-prepend"><span class="input-group-text"><small>Date to</small></span></div>
                                    <input value="{% raw %}{{$val['to']}}{% endraw %}" type="date" class="form-control" autocomplete="off" onchange="this.nextElementSibling.value = this.value">
                                    <input name="attribute__{% raw %}{{$attribute->id}}{% endraw %}[{% raw %}{{$loop->index}}{% endraw %}][to]" value="{% raw %}{{$val['to']}}{% endraw %}" type="hidden" onchange="documentDirty=true;">
                                </div>
                                <div class="input-group mb-1 row-col col-lg-1 col-lg-6 col-md-3 col-6">
                                    <div class="input-group-prepend"><span class="input-group-text"><small>Info text</small></span></div>
                                    <input name="attribute__{% raw %}{{$attribute->id}}{% endraw %}[{% raw %}{{$loop->index}}{% endraw %}][info]" value="{% raw %}{{$val['info']}}{% endraw %}" type="text" class="form-control" onchange="documentDirty=true;">
                                </div>
                                <div class="input-group mb-1 row-col col-lg-1 col-lg-2 col-md-3 col-6">
                                    <div class="input-group-prepend"><span class="input-group-text"><small>Price</small></span></div>
                                    <input name="attribute__{% raw %}{{$attribute->id}}{% endraw %}[{% raw %}{{$loop->index}}{% endraw %}][price]" value="{% raw %}{{$val['price']}}{% endraw %}" type="text" class="form-control" onchange="documentDirty=true;">
                                </div>
                            </div>
                            <div class="row form-row">
                                <div class="input-group mb-1 row-col col-12">
                                    <div class="input-group-prepend"><span class="input-group-text"><small>Second text</small></span></div>
                                    <input name="attribute__{% raw %}{{$attribute->id}}{% endraw %}[{% raw %}{{$loop->index}}{% endraw %}][text]" value="{% raw %}{{$val['text']}}{% endraw %}" type="text" class="form-control" onchange="documentDirty=true;">
                                </div>
                            </div>
                        </div>
                        <div class="col-auto"><br/><br/><i onclick="del{% raw %}{{$attribute->id}}{% endraw %}Attr(this)" class="fa fa-minus-circle text-danger b-btn-del"></i></div>
                    </div>
                @endforeach
            @else
                <div class="row form-row">
                    <div class="col">
                        <div class="row form-row">
                            <div class="input-group mb-1 row-col col-lg-1 col-lg-2 col-md-3 col-6">
                                <div class="input-group-prepend"><span class="input-group-text"><small>Date from</small></span></div>
                                <input type="date" class="form-control" autocomplete="off" onchange="this.nextElementSibling.value = this.value">
                                <input name="attribute__{% raw %}{{$attribute->id}}{% endraw %}[0][from]" value="" type="hidden" onchange="documentDirty=true;">
                            </div>
                            <div class="input-group mb-1 row-col col-lg-1 col-lg-2 col-md-3 col-6">
                                <div class="input-group-prepend"><span class="input-group-text"><small>Date to</small></span></div>
                                <input type="date" class="form-control" autocomplete="off" onchange="this.nextElementSibling.value = this.value">
                                <input name="attribute__{% raw %}{{$attribute->id}}{% endraw %}[0][to]" value="" type="hidden" onchange="documentDirty=true;">
                            </div>
                            <div class="input-group mb-1 row-col col-lg-1 col-lg-6 col-md-3 col-6">
                                <div class="input-group-prepend"><span class="input-group-text"><small>Info text</small></span></div>
                                <input name="attribute__{% raw %}{{$attribute->id}}{% endraw %}[0][info]" value="" type="text" class="form-control" onchange="documentDirty=true;">
                            </div>
                            <div class="input-group mb-1 row-col col-lg-1 col-lg-2 col-md-3 col-6">
                                <div class="input-group-prepend"><span class="input-group-text"><small>Price</small></span></div>
                                <input name="attribute__{% raw %}{{$attribute->id}}{% endraw %}[0][price]" value="" type="text" class="form-control" onchange="documentDirty=true;">
                            </div>
                        </div>
                        <div class="row form-row">
                            <div class="input-group mb-1 row-col col-12">
                                <div class="input-group-prepend"><span class="input-group-text"><small>Second text</small></span></div>
                                <input name="attribute__{% raw %}{{$attribute->id}}{% endraw %}[0][text]" value="" type="text" class="form-control" onchange="documentDirty=true;">
                            </div>
                        </div>
                    </div>
                    <div class="col-auto"><br/><br/><i onclick="del{% raw %}{{$attribute->id}}{% endraw %}Attr(this)" class="fa fa-minus-circle text-danger b-btn-del"></i></div>
                </div>
            @endif
        </div>
    </div>
</div>
<script>
    function add{% raw %}{{$attribute->id}}{% endraw %}Attr (e) {
        let block = e.parentNode.nextElementSibling;
        let index = block.children.length + 1000;
        let element = block.firstElementChild.innerHTML.replaceAll(/attribute__{% raw %}{{$attribute->id}}{% endraw %}\[0\]/gi, 'attribute__{% raw %}{{$attribute->id}}{% endraw %}['+index+']').replaceAll(/(value)=("[^"]*")/gi, 'value=""');
        block.insertAdjacentHTML('beforeend', '<div class="row form-row">'+element+'</div>');
        documentDirty=true;
    }
    function del{% raw %}{{$attribute->id}}{% endraw %}Attr (e) {
        let element = e.parentNode.parentNode;
        element.remove();
        documentDirty=true;
    }
</script>
```

### Field names

All fields must contain the prefix `attribute__{% raw %}{{$attribute->id}}{% endraw %}` in the name.

For example `name="attribute__{% raw %}{{$attribute->id}}{% endraw %}[0][text]"`.

### Data storage

The Attribute values for this Product are saved when the Product is saved. All data from
the form must be sent to the server in the form of an array.

It is forbidden to use the `<form>` tag.

For example, the simplest type of data fields:

```html
<input name="attribute__{% raw %}{{$attribute->id}}{% endraw %}[]" value="value one" type="text" onchange="documentDirty=true;">
<input name="attribute__{% raw %}{{$attribute->id}}{% endraw %}[]" value="value two" type="text" onchange="documentDirty=true;">
```

### Indication of changes

You must use the `onchange` attribute for the input field with the value `documentDirty=true;`
to show the content manager when the data has been changed but not yet saved.
