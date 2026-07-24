# Dynamische Preise

`sPriceResolver` ist die zentrale Quelle für `price`, `oldPrice`, `priceAsFloat` und `oldPriceAsFloat` in Produktmodell, Storefront, Warenkorb und Checkout.

Wenn sPricing verfügbar ist, erhält es den Kontext des aktuellen Benutzers. Andernfalls bleibt die historische Regel `price_special` / `price_regular` erhalten.

Gecachte Produkt- und Seitendaten bleiben benutzerneutral. Der Resolver wendet den Preis des aktuellen Benutzers erst in der Anfrage an, damit der gemeinsame Cache keinen Kundenpreis weitergibt.
