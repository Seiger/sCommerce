# Виджеты доставки

Виджеты доставки позволяют гибко настраивать поля формы оформления заказа для разных способов доставки в sCommerce. Каждый способ доставки может иметь собственный Blade-шаблон, который выводит нужные поля.

## Обзор

Начиная с версии 1.x, sCommerce поддерживает настраиваемые виджеты доставки на основе Blade-шаблонов. Это дает возможность:

- адаптировать форму оформления заказа под каждый способ доставки;
- реализовывать проектные интеграции служб доставки;
- подключать сторонние сервисы (Новая Почта, UPS и т.д.);
- сохранять единый стиль между методами доставки.

## Поиск шаблонов

Шаблоны ищутся в следующем порядке:

1. **Шаблоны проекта** (наивысший приоритет)
   ```
   views/delivery/{delivery-name}.blade.php
   ```

2. **Шаблоны из custom-пакета**
   ```
   core/custom/packages/seiger/scommerce/views/delivery/{delivery-name}.blade.php
   ```

3. **Шаблоны поставщика** (низший приоритет)
   ```
   core/vendor/seiger/scommerce/views/delivery/{delivery-name}.blade.php
   ```

Чтобы переопределить шаблон, достаточно скопировать его в каталог с более высоким приоритетом.

## Доступные переменные

Все шаблоны виджетов доставки получают следующие переменные:

### `$delivery`
Массив с данными о способе доставки:
- `$delivery['name']` — уникальный идентификатор (например, `courier`, `pickup`);
- `$delivery['title']` — локализованное название;
- `$delivery['description']` — локализованное описание.

### `$checkout`
Массив с текущими данными оформления заказа:
- `$checkout['user']` — данные пользователя;
- `$checkout['user']['address']` — адрес (город, улица, дом, помещение и т.д.);
- `$checkout['cart']` — данные корзины;
- другая сопутствующая информация.

### `$settings`
Массив с настройками метода доставки из админ-панели:
- набор ключей зависит от конкретного способа;
- пример курьерской доставки: `$settings['cities']`, `$settings['info']`;
- пример самовывоза: `$settings['locations']`.

## Создание собственных виджетов доставки

### Шаг 1. Класс доставки

Создайте класс, наследующий `BaseDeliveryMethod`:

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
        return 50.00; // Фиксированная стоимость или собственный расчет
    }

    public function defineFields(): array
    {
        return [
            // Поля настроек для админ-панели
        ];
    }

    public function prepareSettings(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
```

### Шаг 2. Шаблон виджета

Создайте Blade-шаблон для виджета доставки.

**Файл:** `views/delivery/custom.blade.php`

```blade
{{-- 
    Custom Delivery Widget
    
    Доступные переменные: $delivery, $checkout, $settings
--}}

<label class="form-label">
    <input
        type="text"
        name="delivery[{{$delivery['name']}}][address]"
        value="{{old('delivery.'.$delivery['name'].'.address', '')}}"
        placeholder="Введите адрес доставки"
        required
    />
    <span>Адрес доставки</span>
</label>

<label class="form-label">
    <input
        type="tel"
        name="delivery[{{$delivery['name']}}][phone]"
        placeholder="Номер телефона"
        required
    />
    <span>Контактный телефон</span>
</label>
```

### Шаг 3. Регистрация метода доставки

Метод доставки будет зарегистрирован автоматически, если добавить его в базу с корректным именем класса.

## Примеры

### Курьер

Шаблон по умолчанию: `core/vendor/seiger/scommerce/views/delivery/courier.blade.php`

Особенности:
- поле города с быстрым выбором;
- поля улицы, дома, квартиры;
- выбор получателя (самостоятельно или другое лицо).

Для кастомизации скопируйте файл в `views/delivery/courier.blade.php`.

### Самовывоз

Шаблон по умолчанию: `core/vendor/seiger/scommerce/views/delivery/pickup.blade.php`

Особенности:
- переключатели для выбора точки выдачи;
- вывод всех настроенных адресов.

Кастомизация: `views/delivery/pickup.blade.php`.

### Интеграция с Новой Почтой

Пример: `core/custom/packages/seiger/scommerce/views/delivery/nova-poshta.blade.php`

Особенности:
- автодополнение города через API Новой Почты;
- выбор отделения по выбранному городу;
- поля с данными получателя;
- пример подключения JavaScript.

## Формат имен полей

Используйте структуру:

```html
name="delivery[{{$delivery['name']}}][field_name]"
```

Это гарантирует корректную структуру данных для:
- валидации;
- сохранения в заказе;
- поддержки нескольких методов доставки.

## Использование виджетов в checkout

В шаблоне оформления заказа выведите виджет:

```blade
@foreach($deliveries as $delivery)
    <div data-delivery="{{$delivery['name']}}">
        
        {{-- Информационное сообщение из настроек --}}
        @if(isset($delivery['info']) && trim($delivery['info']))
            <div class="message-info">
                {!! nl2br(e($delivery['info'])) !!}
            </div>
        @endif

        {{-- Виджет доставки --}}
        {!! $delivery['widget'] !!}
        
    </div>
@endforeach
```

Переменная `$delivery['widget']` уже содержит рендер HTML-шаблона способа доставки.

## Расширенные возможности

### Добавление JavaScript

Используйте директиву `@push('scripts')`, чтобы подключить скрипт:

```blade
<div id="delivery-{{$delivery['name']}}">
    {{-- Ваши поля --}}
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Delivery widget initialized');
});
</script>
@endpush
```

### Условные поля через Alpine.js

```blade
<div x-data="{ showAdvanced: false }">
    <label>
        <input type="checkbox" x-model="showAdvanced">
        Показать дополнительные опции
    </label>
    
    <div x-show="showAdvanced" x-transition>
        {{-- Дополнительные поля --}}
    </div>
</div>
```

### Интеграция API

```blade
<div id="delivery-api-widget-{{$delivery['name']}}"></div>

@push('scripts')
<script src="https://api.delivery-service.com/widget.js"></script>
<script>
DeliveryServiceWidget.init({
    container: '#delivery-api-widget-{{$delivery['name']}}',
    apiKey: '{{$settings['api_key'] ?? ''}}',
    onSelect: function(data) {
        // Обработка выбранных данных
    }
});
</script>
@endpush
```

## Рекомендации

1. **Всегда валидируйте данные** — определяйте правила в `getValidationRules()`.
2. **Используйте ключи переводов** — применяйте `__('key')` для мультиязычности.
3. **Сохраняйте введенные данные** — используйте хелпер `old()` для подстановки значений.
4. **Проверяйте наличие настроек** — убедитесь, что нужные ключи присутствуют в `$settings`.
5. **Один виджет — один метод** — не перегружайте шаблон лишними сценариями.
6. **Комментируйте сложную логику** — добавляйте пояснения в коде.
7. **Тестируйте в разных браузерах** — убедитесь в корректном отображении.

## Возможные проблемы

### Виджет не отображается

1. Проверьте наличие шаблона в одном из путей.
2. Убедитесь, что класс доставки реализует `DeliveryMethodInterface`.
3. Просмотрите лог `storage/logs/scommerce.log`.

### Ошибки валидации

1. Имена полей должны соответствовать правилам `getValidationRules()`.
2. Проверьте, что все обязательные поля присутствуют в шаблоне.
3. Убедитесь в корректности структуры массива `delivery`.

### Проблемы со стилями

1. Сверьте классы CSS проекта с HTML виджета.
2. При необходимости создайте кастомный шаблон в `views/delivery/`.
3. Используйте инструменты разработчика браузера.

## Миграция со встроенных форм

Если форма доставки была жестко прописана в `checkout.blade.php`:

1. Создайте шаблон `views/delivery/{name}.blade.php`.
2. Перенесите HTML формы в новый шаблон.
3. Замените фиксированные значения на переменные.
4. Приведите имена полей к формату `delivery[{{$delivery['name']}}][field]`.
5. Протестируйте процесс оформления.
6. Удалите старую форму из checkout.

## См. также

- [Методы оплаты](payments/methods.md)
- [События](events.md)
- [Настройки](settings.md)

