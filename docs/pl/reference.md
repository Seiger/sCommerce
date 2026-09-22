# Dokumentacja cen

`sPriceResolver` zapewnia spójne pola ceny we wszystkich powierzchniach sCommerce. Zdarzenia `sCommerce.ResolveProductPriceMode` i `sCommerce.ResolveProductPrice` są nadal obsługiwane.

Priorytet: cena osobista lub narastająca sPricing, potem promocyjna, potem bazowa. Stara cena jest ceną, od której zastosowano rabat.

## Zdarzenia

Pełne kontrakty i przykłady: [zdarzenia sCommerce (angielski)](../en/developers/events.md).

- `sCommerce.CheckoutValidationRules` — Reguły checkout przez referencję (`data`, `rules`): dodawanie, zastępowanie i usuwanie reguł.
- `sCommerce.ResolveProductPriceMode` — Tryb ceny (`product`, `optionId`, `priceMode`): pierwszy niepusty ciąg określa `auto` lub `wholesale`.
- `sCommerce.ResolveProductPrice` — Cena produktu (`product`, `optionId`, `priceMode`, `currency`, `pricing`): pierwsza odpowiedź liczbowa lub tablica nadpisuje cenę. sPricing nasłuchuje tego zdarzenia.
