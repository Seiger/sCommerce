---
layout: page
title: Attributes
description: sCommerce the Product Attributes
permalink: /attributes/
---
Attributes allow you to extend the capabilities of your products.

During Attribute configuration (Common path **Admin Panel -> Modules -> Commerce -> Attribute**)
when adding or editing, the Attribute configuration page is available to you.

## Basic fields

### Visibility

The Visibility field controls the availability of this Attribute in sCommerce. If the checkbox is disabled,
the display of this Attribute will be disabled everywhere except the page with the General list of Attributes.

### As a filter

The As a filter checkbox controls the availability of this Attribute as a product filter of the sCommerce
products catalog on the frontend. If the checkbox is enabled, this Attribute can be used to filter products
in the catalog.

Limited input types are available for a filter.

### Position

The Position field is responsible for sorting the list of Attributes when editing a Product. Also,
this field can be used for sorting when outputting the Product characteristics to the frontend,
or for forming a list of filters.

### Key

The Key field is the unique key for the Attribute name. If the Attribute will be used as a filter,
then the key will be displayed in the url of the product filtering page.

The key must have a unique value. Only lowercase Latin characters, numbers, and dashes are supported.

### Categories

Restrictions for this Attribute are filled in the Categories field. This Attribute will be displayed
for all Products included in the category with this Attribute.

If the selected Category contains attachments, then the Attribute will be available for all Products
included in the child Categories.

### Type of input

In the Type of input field, the type of data input that is available for this Attribute is configured.

If the Custom type is selected, you need to additionally configure the
[view for the field]({{site.baseurl}}/attributes/custom/).

### Help text

In the Help text field, you can write a text that will serve as a hint when filling in the Attribute
while editing the Product.

### Attribute Name

In the Attribute Name field, you must write the name of the Attribute. This name will be displayed in
the Attributes list in the admin panel, and may also be displayed on the frontend when displaying the
value of this Attribute.

### Description

In the Description field, you can write a text that can be displayed on the frontend when displaying
the value of this Attribute.
