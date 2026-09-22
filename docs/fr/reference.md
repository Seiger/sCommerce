# Référence des prix

`sPriceResolver` fournit des champs cohérents. Les événements `sCommerce.ResolveProductPriceMode` et `sCommerce.ResolveProductPrice` restent compatibles.

Priorité: prix personnel ou cumulatif sPricing, prix promotionnel, prix de base. L'ancien prix est la base de la réduction.

## Événements

Contrats complets et exemples : [événements sCommerce (anglais)](../en/developers/events.md).

- `sCommerce.CheckoutValidationRules` — Règles du checkout par référence (`data`, `rules`) : ajout, remplacement ou suppression.
- `sCommerce.ResolveProductPriceMode` — Mode de prix (`product`, `optionId`, `priceMode`) : la première chaîne non vide détermine `auto` ou `wholesale`.
- `sCommerce.ResolveProductPrice` — Prix du produit (`product`, `optionId`, `priceMode`, `currency`, `pricing`) : la première réponse numérique ou le premier tableau remplace le prix. sPricing écoute cet événement.
