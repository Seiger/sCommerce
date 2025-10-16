---
id: settings
title: Настройки
sidebar_position: 7
---

## Корень каталога

`sCommerce::config('basic.catalog_root')`

Этот параметр определяет ID страницы, которая служит корневой категорией для каталога товаров sCommerce.

### Пример использования:

```php
// Получение ID корневой категории
$catalogRoot = sCommerce::config('basic.catalog_root');

// Использование в контроллере
class CategoryController extends BaseController
{
    public function render()
    {
        parent::render();
        
        $catalogRoot = sCommerce::config('basic.catalog_root');
        $this->data['catalogRoot'] = $catalogRoot;
        
        // Получение всех товаров каталога
        $products = sCommerce::getCategoryProducts($catalogRoot);
    }
}
```

### Настройка через админ-панель:

1. Перейдите в **Панель администратора -> Модули -> Commerce -> Настройки**
2. Найдите поле "Catalog root"
3. Введите ID страницы, которая будет корневой категорией
4. Сохраните настройки

### Важные замечания:

- Корневая категория должна существовать и быть активной
- Все товары каталога должны быть связаны с этой категорией или её подкатегориями
- Изменение корневой категории может повлиять на отображение товаров на сайте

## Другие настройки

### Базовая конфигурация:

```php
// Получение всех базовых настроек
$basicConfig = sCommerce::config('basic');

// Отдельные настройки
$catalogRoot = sCommerce::config('basic.catalog_root');
$friendlyUrlSuffix = sCommerce::config('basic.friendlyUrlSuffix');
```

### Пример полной конфигурации:

```php
return [
    'basic' => [
        'catalog_root' => 10,           // ID корневой категории
        'friendlyUrlSuffix' => '.html', // Суффикс для дружественных URL
        // другие настройки...
    ],
    // другие секции настроек...
];
```
