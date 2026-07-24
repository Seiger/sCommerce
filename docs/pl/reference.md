# Dokumentacja cen

`sPriceResolver` zapewnia spójne pola ceny we wszystkich powierzchniach sCommerce. Zdarzenia `evolution.sCommerceResolveProductPriceMode` i `evolution.sCommerceResolveProductPrice` są nadal obsługiwane.

Priorytet: cena osobista lub narastająca sPricing, potem promocyjna, potem bazowa. Stara cena jest ceną, od której zastosowano rabat.
