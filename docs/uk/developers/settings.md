---
id: settings
title: Налаштування
sidebar_position: 7
---

## Корінь каталогу

`sCommerce::config('basic.catalog_root')`

Цей параметр визначає ID сторінки, яка служить кореневою категорією для каталогу товарів sCommerce.

### Приклад використання:

```php
// Отримання ID кореневої категорії
$catalogRoot = sCommerce::config('basic.catalog_root');

// Використання в контролері
class CategoryController extends BaseController
{
    public function render()
    {
        parent::render();
        
        $catalogRoot = sCommerce::config('basic.catalog_root');
        $this->data['catalogRoot'] = $catalogRoot;
        
        // Отримання всіх товарів каталогу
        $products = sCommerce::getCategoryProducts($catalogRoot);
    }
}
```

### Налаштування через адмін-панель:

1. Перейдіть до **Панель адміністратора -> Модулі -> Commerce -> Налаштування**
2. Знайдіть поле "Catalog root"
3. Введіть ID сторінки, яка буде кореневою категорією
4. Збережіть налаштування

### Важливі зауваження:

- Коренева категорія повинна існувати та бути активною
- Всі товари каталогу повинні бути пов'язані з цією категорією або її підкатегоріями
- Зміна кореневої категорії може вплинути на відображення товарів на сайті

## Інші налаштування

### Базова конфігурація:

```php
// Отримання всіх базових налаштувань
$basicConfig = sCommerce::config('basic');

// Окремі налаштування
$catalogRoot = sCommerce::config('basic.catalog_root');
$friendlyUrlSuffix = sCommerce::config('basic.friendlyUrlSuffix');
```

### Приклад повної конфігурації:

```php
return [
    'basic' => [
        'catalog_root' => 10,           // ID кореневої категорії
        'friendlyUrlSuffix' => '.html', // Суфікс для дружніх URL
        // інші налаштування...
    ],
    // інші секції налаштувань...
];
```
