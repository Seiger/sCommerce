---
layout: page
title: Events
description: sCommerce Events list
permalink: /events/
---
Evo's events provide a simple observer pattern implementation, allowing you to subscribe and listen
for various events that occur within your application. Using events, it is convenient to manage
additional sCommerce parameters. Below is a list of reserved events.

## Enhancement of interface management capabilities

### sCommerceManagerAddTabEvent

```php
Event::listen('evolution.sCommerceManagerAddTabEvent', function($params) {
    dd($params);
});
```

## Product manipulation

### sCommerceAfterProductSave

```php
Event::listen('evolution.sCommerceAfterProductSave', function($params) {
    dd($params);
});
```

### sCommerceAfterProductDuplicate

```php
Event::listen('evolution.sCommerceAfterProductDuplicate', function($params) {
    dd($params);
});
```