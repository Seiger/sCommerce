# Dynamiczne ceny

`sPriceResolver` jest jednym źródłem pól `price`, `oldPrice`, `priceAsFloat` i `oldPriceAsFloat` dla modelu produktu, witryny, koszyka i checkout.

Jeżeli sPricing jest dostępny, otrzymuje kontekst bieżącego użytkownika. Gdy pakiet nie jest zainstalowany albo nie zwraca ceny, sCommerce zachowuje historyczną regułę `price_special` / `price_regular`.

Dane strony i produktu w cache pozostają neutralne dla użytkownika. Resolver nakłada cenę bieżącego użytkownika w żądaniu, więc wspólny cache nie ujawnia ceny innego klienta.
