# Справочник цен

`sPriceResolver` возвращает единые поля цен. События `sCommerce.ResolveProductPriceMode` и `sCommerce.ResolveProductPrice` сохраняют поддержку.

Приоритет: персональная или накопительная цена sPricing, акционная, базовая. Старая цена — основа предоставленной скидки.

## События

Полный контракт и примеры: [события sCommerce](developers/events.md).

- `sCommerce.CheckoutValidationRules` — Правила checkout по ссылке (`data`, `rules`); добавление, замена и удаление правил.
- `sCommerce.ResolveProductPriceMode` — Режим цены товара (`product`, `optionId`, `priceMode`); первый непустой строковый ответ определяет `auto` или `wholesale`.
- `sCommerce.ResolveProductPrice` — Цена товара (`product`, `optionId`, `priceMode`, `currency`, `pricing`); первый числовой ответ или массив переопределяет цену. Это событие слушает sPricing.
