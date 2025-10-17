---
id: management
title: Management
sidebar_position: 10
---

After installing the module, you can use it immediately. Path to the module in the 
administrator panel **Admin Panel -> Modules -> Commerce**.

You can also fix quick access to the module through the main menu of the Admin Panel. 
This can be done on the configuration tab (only available for the administrator role).

When moving to the middle of the store module, a list of tabs will be presented to 
your attention.

Each tab has its own functionality and access level. Let's consider each tab in more detail.

## Products

The Products tab displays a complete list of products and services provided by your store.
The list is presented in the form of an image of the product, its name, which in turn is a
hyperlink to product page to the frontend, as well as additional information and product
management buttons.

The page also includes a product search form and filters for a quick search for the required
item. For ease of management, you can sort products by all fields of the presentation table
as in both forward and reverse. The fields for the product presentation table are
customizable on the Settings tab.

If your store has more products than you chose to display on the page, other products
will automatically move to another page, and pagination will appear at the bottom.
Note that the choice of virtual of products on the backend product list page does
not affect the display of the number of frontend products.

## Attributes

The Attributes tab displays a complete list of attributes available for customizing your products.
The list is presented in the form of a title and additional information, as well as control buttons.

There is also an attribute search form on the page. For ease of management, you can sort attributes by all fields of the presentation table
both forward and reverse.

If your store has more attributes than you choose to display on the page, other attributes
will automatically move to another page and pagination will appear at the bottom.



## Settings

On the Settings tab, you manage all the settings of your store. Note that this tab
available only to users with the site administrator role.

In this tab, you can control how the store is displayed in the main menu of the admin panel
as well as other tabs displayed in the store module. And you also have an on/off option
additional functionality of the store.

You can also hide or show certain fields in a product or order, as well
rename certain fields so that the store manager has a clearer idea of which
functionality a particular field has.

## Currencies

The Currencies tab provides tools for managing the exchange rates between currencies
used in your store. Unlike other tabs, the list of available currencies is not configured
directly here but is instead registered on the Settings tab. This allows administrators
to define which currencies are available for use in the store while maintaining flexibility
in managing their exchange rates.

All exchange rate values are stored in a configuration file located at
`core/custom/config/seiger/settings/sCommerceCurrencies.php`. The data is saved in the form
of an array, ensuring quick access and easy modification when needed. This approach allows
the system to maintain consistency while simplifying integration with other modules or
custom functionalities.

The tab is designed to help administrators easily update exchange rates relative to the
store's base currency. This ensures that all prices displayed to customers are accurate
and consistent, even when dealing with multiple currencies. While automation of exchange
rate updates may be integrated in the future, the current design prioritizes manual
control for precision and reliability.

## Custom tab

You have the opportunity to expand the capabilities of the administrative part of the sCommerce module
by using the `sCommerceManagerAddTabEvent` event. To do this, use your own plugin file, or create
a new one (eg `core/custom/packages/main/plugins/SeigerPlugin.php`) and add the following content.

```php
<?php

use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Facades\sCommerce;
...

Event::listen('evolution.sCommerceManagerAddTabEvent', function($params) {
    $result['handler'] = MODX_BASE_PATH . 'core/custom/packages/main/src/Controllers/SeigerPluginCommerceHandler.php';
    $result['view'] = '';

    switch ($params['currentTab'] ?? '') {
        case 'content' :
            $result['view'] = sCommerce::tabRender('mypage', 'Main::seigerplugin.mypageTab', $params['dataInput'] ?? [], 'My Page', 'fa fa-keyboard', 'The text that is displayed when hovering');
            break;
    }

    return $result;
});

...
```

As a result, an array with two keys **handler** and **view** should be returned.
The **handler** key must contain a reference to the handler file for your custom page.
See the example handler file.

```php
<?php

use Seiger\sCommerce\Facades\sCommerce;

switch (request()->input('get')) { // current tab id
    case 'mypage': // current tab id
        $tabs = ['products', 'attributes']; // tabs that should be displayed when this tab is shown
        
        ...
        
        $data['items'] = $items; // data to be passed to the view
        break;
    case 'mypageSave': // if the data needs to be saved
        ...
        
        $back = (request()->back ?? '&get=products');
        return header('Location: ' . sCommerce::moduleUrl() . $back);
}
```

The **view** key must contain the rendering of your page. This is quite easy to achieve
if you use the `sCommerce::tabRender()` method. The following data are passed as arguments,
as in the example:

```php
'mypage', // The ID of the tab
'Main::seigerplugin.mypageTab', // The template for the tab
$params['dataInput'] ?? [], // The input data for the tab
'My Page', // The name of the tab
'fa fa-keyboard', // The icon for the tab
'The text that is displayed when hovering' // The help text for the tab
```