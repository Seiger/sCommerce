# Довідник цін

`sPriceResolver` повертає узгоджені поля відображення ціни для всіх поверхонь sCommerce. Події `sCommerce.ResolveProductPriceMode` і `sCommerce.ResolveProductPrice` підтримують проєктні перевизначення.

Пріоритет: застосовна персональна або накопичувальна ціна sPricing, далі акційна ціна, далі базова ціна. Старою ціною показується ціна, від якої фактично надано знижку.

## Події

Повний контракт і приклади: [події sCommerce](developers/events.md).

- `sCommerce.CheckoutValidationRules` — Масив правил checkout за посиланням (`data`, `rules`); дозволяє додавати, замінювати й видаляти правила.
- `sCommerce.ResolveProductPriceMode` — Режим ціни товару (`product`, `optionId`, `priceMode`); перший непорожній рядок визначає `auto` або `wholesale`.
- `sCommerce.ResolveProductPrice` — Ціна товару (`product`, `optionId`, `priceMode`, `currency`, `pricing`); перша числова відповідь або масив перевизначає ціну. Цю подію слухає sPricing.
