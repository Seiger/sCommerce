# Методи доставки

Віджети доставки надають гнучкий спосіб налаштування полів форми оформлення замовлення для різних методів доставки в sCommerce. Кожен метод доставки може мати власний Blade-шаблон, який відображає необхідні поля форми.

## Огляд

Починаючи з версії 1.x, sCommerce підтримує налаштовувані віджети доставки через Blade-шаблони. Це дозволяє:

- Налаштовувати форми оформлення замовлення для кожного методу доставки
- Створювати специфічні для проєкту реалізації доставки
- Інтегрувати віджети сторонніх служб доставки (Нова Пошта, UPS тощо)
- Підтримувати єдиний стиль для всіх методів доставки

## Пріоритет пошуку шаблонів

Шаблони шукаються в наступному порядку пріоритету:

1. **Види проєкту** (найвищий пріоритет)
   ```
   views/delivery/{назва-доставки}.blade.php
   ```

2. **Vendor за замовчуванням** (найнижчий пріоритет)
   ```
   core/vendor/seiger/scommerce/views/delivery/{назва-доставки}.blade.php
   ```

Це дозволяє перевизначити будь-який шаблон доставки, просто скопіювавши його в директорію views вашого проєкту.

## Доступні змінні

Усі шаблони віджетів доставки отримують наступні змінні:

### `$delivery`
Масив з інформацією про метод доставки:
- `$delivery['name']` - Унікальний ідентифікатор доставки (наприклад, 'courier', 'pickup')
- `$delivery['title']` - Локалізована назва методу доставки
- `$delivery['description']` - Локалізований опис методу доставки

### `$checkout`
Масив з поточними даними оформлення замовлення:
- `$checkout['user']` - Інформація про користувача
- `$checkout['user']['address']` - Дані адреси (місто, вулиця, будинок, квартира тощо)
- `$checkout['cart']` - Дані кошика покупок
- Інша інформація, пов'язана з оформленням замовлення

### `$settings`
Масив з налаштуваннями методу доставки, сконфігурованими в адмін-панелі:
- Користувацькі налаштування різняться для кожного методу доставки
- Приклад для кур'єра: `$settings['cities']`, `$settings['info']`
- Приклад для самовивозу: `$settings['locations']`

## Створення власного віджета доставки

### Крок 1: Створення класу доставки

Створіть новий клас методу доставки, що розширює `BaseDeliveryMethod`:

```php
<?php namespace App\Delivery;

use Seiger\sCommerce\Delivery\BaseDeliveryMethod;

class CustomDelivery extends BaseDeliveryMethod
{
    public function getName(): string
    {
        return 'custom';
    }

    public function getType(): string
    {
        return "<b>Користувацька доставка</b> (custom)";
    }

    public function getValidationRules(): array
    {
        return [
            'delivery.custom.address' => 'required|string|max:255',
            'delivery.custom.phone' => 'required|string',
        ];
    }

    public function calculateCost(array $order): float
    {
        return 50.00; // Фіксована вартість або власний розрахунок
    }

    public function defineFields(): array
    {
        return [
            // Поля налаштувань адмін-панелі
        ];
    }

    public function prepareSettings(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
```

### Крок 2: Створення шаблону віджета

Створіть Blade-шаблон для вашого віджета доставки:

**Файл:** `views/delivery/custom.blade.php`

```blade
{{--
    Віджет користувацької доставки
    
    Доступні змінні: $delivery, $checkout, $settings
--}}

<label class="form-label">
    <input 
        type="text" 
        name="delivery[{{$delivery['name']}}][address]" 
        value="{{old('delivery.'.$delivery['name'].'.address', '')}}"
        placeholder="Введіть адресу доставки"
        required
    />
    <span>Адреса доставки</span>
</label>

<label class="form-label">
    <input 
        type="tel" 
        name="delivery[{{$delivery['name']}}][phone]" 
        placeholder="Номер телефону"
        required
    />
    <span>Контактний телефон</span>
</label>
```

### Крок 3: Реєстрація методу доставки

Метод доставки буде автоматично зареєстрований при додаванні в базу даних з правильним ім'ям класу.

## Приклади

### Кур'єрська доставка

Розташування шаблону за замовчуванням: `core/vendor/seiger/scommerce/views/delivery/courier.blade.php`

Можливості:
- Введення міста з швидким вибором міста
- Поля вулиці, будинку, квартири
- Вибір одержувача (я сам або інша особа)

Для налаштування скопіюйте в: `views/delivery/courier.blade.php`

### Самовивіз

Розташування шаблону за замовчуванням: `core/vendor/seiger/scommerce/views/delivery/pickup.blade.php`

Можливості:
- Радіокнопки для вибору пункту самовивозу
- Відображення всіх налаштованих адрес пунктів видачі

Для налаштування скопіюйте в: `views/delivery/pickup.blade.php`

## Угода про іменування полів

Завжди використовуйте наступний шаблон іменування для полів введення:

```html
name="delivery[{{$delivery['name']}}][назва_поля]"
```

Це забезпечує правильну структуру даних для:
- Обробки валідації
- Зберігання даних замовлення
- Підтримки декількох способів доставки

## Використання віджетів в оформленні замовлення

У вашому поданні оформлення замовлення просто виведіть віджет:

```blade
@foreach($deliveries as $delivery)
    <div data-delivery="{{$delivery['name']}}">
        
        {{-- Інформаційне повідомлення з налаштувань --}}
        @if(isset($delivery['info']) && trim($delivery['info']))
            <div class="message-info">
                {!! nl2br(e($delivery['info'])) !!}
            </div>
        @endif

        {{-- Віджет доставки --}}
        {!! $delivery['widget'] !!}
        
    </div>
@endforeach
```

`$delivery['widget']` містить попередньо відрендерений HTML з шаблону методу доставки.

## Розширене використання

### Додавання JavaScript

Використовуйте директиву `@push('scripts')` для додавання JavaScript у ваш віджет:

```blade
<div id="delivery-{{$delivery['name']}}">
    {{-- Поля вашої форми --}}
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ваш JavaScript код
    console.log('Віджет доставки ініціалізовано');
});
</script>
@endpush
```

### Умовні поля з Alpine.js

```blade
<div x-data="{ showAdvanced: false }">
    <label>
        <input type="checkbox" x-model="showAdvanced">
        Показати додаткові опції
    </label>
    
    <div x-show="showAdvanced" x-transition>
        {{-- Додаткові поля --}}
    </div>
</div>
```

### Інтеграція з API

Для сторонніх служб доставки:

```blade
<div id="delivery-api-widget-{{$delivery['name']}}"></div>

@push('scripts')
<script src="https://api.delivery-service.com/widget.js"></script>
<script>
DeliveryServiceWidget.init({
    container: '#delivery-api-widget-{{$delivery['name']}}',
    apiKey: '{{$settings['api_key'] ?? ''}}',
    onSelect: function(data) {
        // Обробка вибору доставки
    }
});
</script>
@endpush
```

## Кращі практики

1. **Завжди валідуйте введення** - Визначте правила валідації в `getValidationRules()`
2. **Використовуйте ключі перекладу** - Зробіть віджети багатомовними за допомогою `__('key')`
3. **Зберігайте дані користувача** - Використовуйте хелпер `old()` для відновлення форми
4. **Обробляйте помилки коректно** - Перевіряйте існування налаштувань перед їх використанням
5. **Тримайте віджети сфокусованими** - Один віджет = один метод доставки
6. **Документуйте код** - Додавайте коментарі, що пояснюють складну логіку
7. **Тестуйте в різних браузерах** - Забезпечте сумісність з цільовими браузерами

## Усунення проблем

### Віджет не відображається

1. Перевірте, чи існує файл шаблону в одному зі шляхів пошуку
2. Переконайтеся, що клас методу доставки реалізує `DeliveryMethodInterface`
3. Перевірте логи Laravel на наявність помилок рендерингу: `storage/logs/scommerce.log`

### Помилки валідації

1. Переконайтеся, що імена полів відповідають правилам валідації
2. Перевірте метод `getValidationRules()` в класі доставки
3. Переконайтеся, що всі обов'язкові поля присутні у віджеті

### Проблеми зі стилями

1. Перевірте, чи відповідають CSS-класи вашого проєкту HTML віджета
2. Розгляньте створення власного шаблону в `views/delivery/`
3. Використовуйте інструменти розробника браузера для перевірки застосованих стилів

## Міграція з жорстко закодованих форм

Якщо у вас є існуючі жорстко закодовані форми доставки в `checkout.blade.php`:

1. Створіть шаблон віджета: `views/delivery/{назва}.blade.php`
2. Скопіюйте HTML форми з checkout в шаблон віджета
3. Замініть жорстко закодовані значення змінними шаблону
4. Оновіть імена полів введення на формат `delivery[{{$delivery['name']}}][поле]`
5. Ретельно протестуйте процес оформлення замовлення
6. Видаліть стару жорстко закодовану форму з checkout.blade.php

## Дивіться також

- [Методи оплати](payments/methods.md)
- [Події](events.md)
- [Налаштування](settings.md)

