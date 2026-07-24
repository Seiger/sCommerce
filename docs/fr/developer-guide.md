# Prix dynamiques

`sPriceResolver` est la source unique de `price`, `oldPrice`, `priceAsFloat` et `oldPriceAsFloat` pour le modèle produit, la vitrine, le panier et le checkout.

Lorsque sPricing est disponible, il reçoit le contexte de l'utilisateur courant. Sinon, sCommerce conserve la règle historique `price_special` / `price_regular`.

Les données produit et page mises en cache restent neutres. Le resolver applique le prix utilisateur dans la requête, ce qui évite toute fuite de prix dans le cache partagé.
