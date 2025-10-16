---
id: custom
title: Пользовательский атрибут
sidebar_position: 1
---

Пользовательские атрибуты позволяют создавать собственные типы полей для товаров.

## Создание пользовательского атрибута

1. Перейдите в **Панель администратора -> Модули -> Commerce -> Атрибут**
2. Нажмите "Добавить атрибут"
3. В поле "Тип ввода" выберите "Пользовательский"
4. Заполните необходимые поля:
   - **Название атрибута**: Название атрибута
   - **Ключ**: Уникальный ключ (например, `custom_field`)
   - **Описание**: Описание атрибута
   - **Справочный текст**: Подсказка для пользователя

## Настройка представления

Для пользовательского атрибута необходимо создать файл представления в:
`assets/modules/scommerce/views/attributes/custom_field.php`

### Пример файла представления:

```php
<?php
/**
 * Представление для пользовательского атрибута
 * Файл: assets/modules/scommerce/views/attributes/custom_field.php
 */

$value = $attribute->value ?? '';
$label = $attribute->label ?? '';
?>

<div class="form-group">
    <label for="attribute_<?= $attribute->id ?>">
        <?= htmlspecialchars($label) ?>
    </label>
    <input type="text" 
           id="attribute_<?= $attribute->id ?>" 
           name="attributes[<?= $attribute->alias ?>]" 
           value="<?= htmlspecialchars($value) ?>"
           class="form-control"
           placeholder="<?= htmlspecialchars($attribute->helptext ?? '') ?>">
</div>
```

## Использование в коде

### Получение значения атрибута:

```php
$product = sProduct::find(1);
$customValue = $product->attribute('custom_field');
echo $customValue->value ?? '';
```

### Отображение в Blade шаблоне:

```php
@if($product->attribute('custom_field'))
    <div class="custom-attribute">
        <strong>{{ $product->attribute('custom_field')->label }}:</strong>
        {{ $product->attribute('custom_field')->value }}
    </div>
@endif
```

## Фильтрация товаров

Если атрибут настроен как фильтр, он автоматически будет доступен для фильтрации:

```php
// Получение товаров с определенным значением атрибута
$products = sProduct::whereHas('attributes', function($query) {
    $query->where('alias', 'custom_field')
          ->where('value', 'specific_value');
})->get();
```

## Примеры типов полей

### Текстовое поле:
```php
<input type="text" name="attributes[<?= $attribute->alias ?>]" value="<?= htmlspecialchars($value) ?>">
```

### Числовое поле:
```php
<input type="number" name="attributes[<?= $attribute->alias ?>]" value="<?= htmlspecialchars($value) ?>">
```

### Список выбора:
```php
<select name="attributes[<?= $attribute->alias ?>]">
    <option value="">Выберите опцию</option>
    <option value="option1" <?= $value === 'option1' ? 'selected' : '' ?>>Опция 1</option>
    <option value="option2" <?= $value === 'option2' ? 'selected' : '' ?>>Опция 2</option>
</select>
```

### Флажок:
```php
<input type="checkbox" 
       name="attributes[<?= $attribute->alias ?>]" 
       value="1" 
       <?= $value ? 'checked' : '' ?>>
```

### Многострочный текст:
```php
<textarea name="attributes[<?= $attribute->alias ?>]" rows="4"><?= htmlspecialchars($value) ?></textarea>
```