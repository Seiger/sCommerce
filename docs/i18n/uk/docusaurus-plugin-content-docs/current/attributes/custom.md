---
id: custom
title: Користувацький атрибут
sidebar_position: 1
---

Користувацькі атрибути дозволяють створювати власні типи полів для товарів.

## Створення користувацького атрибута

1. Перейдіть до **Панель адміністратора -> Модулі -> Commerce -> Атрибут**
2. Натисніть "Додати атрибут"
3. В полі "Тип вводу" виберіть "Користувацький"
4. Заповніть необхідні поля:
   - **Назва атрибута**: Назва атрибута
   - **Ключ**: Унікальний ключ (наприклад, `custom_field`)
   - **Опис**: Опис атрибута
   - **Довідковий текст**: Підказка для користувача

## Налаштування представлення

Для користувацького атрибута необхідно створити файл представлення в:
`assets/modules/scommerce/views/attributes/custom_field.php`

### Приклад файлу представлення:

```php
<?php
/**
 * Представлення для користувацького атрибута
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

## Використання в коді

### Отримання значення атрибута:

```php
$product = sProduct::find(1);
$customValue = $product->attribute('custom_field');
echo $customValue->value ?? '';
```

### Відображення в Blade шаблоні:

```php
@if($product->attribute('custom_field'))
    <div class="custom-attribute">
        <strong>{{ $product->attribute('custom_field')->label }}:</strong>
        {{ $product->attribute('custom_field')->value }}
    </div>
@endif
```

## Фільтрація товарів

Якщо атрибут налаштований як фільтр, він автоматично буде доступний для фільтрації:

```php
// Отримання товарів з певним значенням атрибута
$products = sProduct::whereHas('attributes', function($query) {
    $query->where('alias', 'custom_field')
          ->where('value', 'specific_value');
})->get();
```

## Приклади типів полів

### Текстове поле:
```php
<input type="text" name="attributes[<?= $attribute->alias ?>]" value="<?= htmlspecialchars($value) ?>">
```

### Числове поле:
```php
<input type="number" name="attributes[<?= $attribute->alias ?>]" value="<?= htmlspecialchars($value) ?>">
```

### Список вибору:
```php
<select name="attributes[<?= $attribute->alias ?>]">
    <option value="">Виберіть опцію</option>
    <option value="option1" <?= $value === 'option1' ? 'selected' : '' ?>>Опція 1</option>
    <option value="option2" <?= $value === 'option2' ? 'selected' : '' ?>>Опція 2</option>
</select>
```

### Прапорець:
```php
<input type="checkbox" 
       name="attributes[<?= $attribute->alias ?>]" 
       value="1" 
       <?= $value ? 'checked' : '' ?>>
```

### Багаторядковий текст:
```php
<textarea name="attributes[<?= $attribute->alias ?>]" rows="4"><?= htmlspecialchars($value) ?></textarea>
```