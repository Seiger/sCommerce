# Віджети доставки

Віджети доставки дають змогу гнучко налаштовувати поля форми оформлення замовлення для різних способів доставки в sCommerce. Кожен спосіб доставки може мати власний Blade-шаблон, який відмалює потрібні поля.

## Огляд

Починаючи з версії 1.x, sCommerce підтримує користувацькі віджети доставки на основі Blade-шаблонів. Це дозволяє:

- налаштовувати форму оформлення замовлення для кожного способу доставки;
- створювати проєктні реалізації доставки;
- інтегрувати сторонні сервіси (Нова Пошта, UPS тощо);
- підтримувати єдиний стиль серед усіх способів доставки.

## Пошук шаблону

Шаблони шукаються у такому порядку:

1. **Шаблони проєкту** (найвищий пріоритет)
   ```
   views/delivery/{delivery-name}.blade.php
   ```

2. **Шаблони з кастомного пакета**
   ```
   core/custom/packages/seiger/scommerce/views/delivery/{delivery-name}.blade.php
   ```

3. **Шаблони з пакета постачальника** (найнижчий пріоритет)
   ```
   core/vendor/seiger/scommerce/views/delivery/{delivery-name}.blade.php
   ```

Достатньо скопіювати файл у каталог із вищим пріоритетом, щоб перевизначити шаблон доставки.

## Доступні змінні

Усі шаблони віджетів доставки отримують такі змінні:

### `$delivery`
Масив із даними про спосіб доставки:
- `$delivery['name']` — унікальний ідентифікатор (наприклад, `courier`, `pickup`);
- `$delivery['title']` — локалізована назва;
- `$delivery['description']` — локалізований опис.

### `$checkout`
Масив із поточними даними оформлення:
- `$checkout['user']` — інформація про користувача;
- `$checkout['user']['address']` — адреса (місто, вулиця, будинок, приміщення тощо);
- `$checkout['cart']` — дані кошика;
- інша пов’язана інформація.

### `$settings`
Масив із налаштуваннями способу доставки з адмінпанелі:
- набір ключів залежить від способу доставки;
- приклад кур’єра: `$settings['cities']`, `$settings['info']`;
- приклад самовивозу: `$settings['locations']`.

## Створення власного віджета доставки

### Крок 1. Клас доставки

Створіть клас, що наслідує `BaseDeliveryMethod`:

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
        return "<b>Custom Delivery</b> (custom)";
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
            // Поля налаштувань для адмінпанелі
        ];
    }

    public function prepareSettings(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
```

### Крок 2. Шаблон віджета

Створіть Blade-шаблон для віджета доставки.

**Файл:** `views/delivery/custom.blade.php`

```blade
{{-- 
    Custom Delivery Widget
    
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

### Крок 3. Реєстрація способу доставки

Спосіб доставки буде зареєстровано автоматично, якщо його додати до бази з правильним іменем класу.

## Приклади

### Кур’єр

Шаблон за промовчанням: `core/vendor/seiger/scommerce/views/delivery/courier.blade.php`

Можливості:
- поле міста з швидким вибором;
- поля для вулиці, будинку, квартири;
- вибір отримувача (самостійно / інша особа).

Щоб кастомізувати, скопіюйте файл у `views/delivery/courier.blade.php`.

### Самовивіз

Шаблон за промовчанням: `core/vendor/seiger/scommerce/views/delivery/pickup.blade.php`

Можливості:
- перемикачі для вибору пункту самовивозу;
- відображення всіх налаштованих адрес.

Кастомізація: `views/delivery/pickup.blade.php`.

### Приклад інтеграції з Новою Поштою

Приклад: `core/custom/packages/seiger/scommerce/views/delivery/nova-poshta.blade.php`

Можливості:
- автодоповнення міста через API Нової Пошти;
- вибір відділення за містом;
- поля даних отримувача;
- приклад інтеграції JavaScript.

## Правила іменування полів

Використовуйте формат:

```html
name="delivery[{{$delivery['name']}}][field_name]"
```

Це гарантує коректну структуру даних для:
- валідації;
- збереження в замовленні;
- підтримки кількох способів доставки.

## Використання віджетів у checkout

У шаблоні оформлення достатньо вивести віджет:

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

У змінній `$delivery['widget']` вже міститься згенерований HTML із шаблону доставки.

## Розширені можливості

### Додавання JavaScript

Використовуйте директиву `@push('scripts')`, щоб підключити скрипт:

```blade
<div id="delivery-{{$delivery['name']}}">
    {{-- Ваші поля --}}
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Delivery widget initialized');
});
</script>
@endpush
```

### Умовні поля через Alpine.js

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

### Інтеграція API

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

## Найкращі практики

1. **Завжди валідуюйте дані** — визначайте правила у `getValidationRules()`.
2. **Використовуйте перекладні ключі** — застосовуйте `__('key')` для мультимовності.
3. **Зберігайте введені дані** — хелпер `old()` допомагає відновити значення.
4. **Перевіряйте існування налаштувань** — переконайтеся, що ключі є в `$settings`.
5. **Зосереджуйте віджет на одній задачі** — один віджет = один спосіб доставки.
6. **Документуйте складну логіку** — додавайте коментарі.
7. **Тестуйте в різних браузерах** — забезпечте сумісність.

## Усунення проблем

### Віджет не відображається

1. Перевірте, чи існує шаблон у доступних шляхах.
2. Переконайтеся, що клас доставки реалізує `DeliveryMethodInterface`.
3. Перевірте лог `storage/logs/scommerce.log`.

### Помилки валідації

1. Поля мають відповідати правилам у `getValidationRules()`.
2. Переконайтеся, що всі обов’язкові поля присутні в шаблоні.
3. Перевірте коректність імен полів.

### Проблеми зі стилями

1. Зіставте класи CSS проєкту з HTML віджета.
2. За потреби створіть власний шаблон у `views/delivery/`.
3. Використовуйте інструменти розробника в браузері.

## Міграція з жорстко прописаних форм

Якщо форма була вбудована в `checkout.blade.php`:

1. Створіть шаблон `views/delivery/{name}.blade.php`.
2. Перенесіть HTML з checkout.
3. Замініть жорсткі значення на змінні шаблону.
4. Імена полів приведіть до `delivery[{{$delivery['name']}}][field]`.
5. Протестуйте процес оформлення.
6. Видаліть стару форму з checkout.

## Див. також

- [Методи оплати](payments/methods.md)
- [Події](events.md)
- [Налаштування](settings.md)

