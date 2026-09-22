# Preisreferenz

`sPriceResolver` liefert einheitliche Preisfelder. Die Ereignisse `sCommerce.ResolveProductPriceMode` und `sCommerce.ResolveProductPrice` werden weiterhin unterstützt.

Priorität: persönliche oder kumulative sPricing-Preis, Aktionspreis, Basispreis. Der alte Preis ist die Preisbasis des Rabatts.

## Ereignisse

Vollständige Verträge und Beispiele: [sCommerce-Ereignisse (Englisch)](../en/developers/events.md).

- `sCommerce.CheckoutValidationRules` — Checkout-Regeln als Referenz (`data`, `rules`): Regeln hinzufügen, ersetzen oder entfernen.
- `sCommerce.ResolveProductPriceMode` — Preismodus (`product`, `optionId`, `priceMode`): Die erste nicht leere Zeichenfolge bestimmt `auto` oder `wholesale`.
- `sCommerce.ResolveProductPrice` — Produktpreis (`product`, `optionId`, `priceMode`, `currency`, `pricing`): Die erste numerische Antwort oder das erste Array überschreibt den Preis. sPricing verwendet dieses Ereignis.
