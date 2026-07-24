# Dynamic pricing

`sPriceResolver` is the single source for `price`, `oldPrice`, `priceAsFloat` and `oldPriceAsFloat` in product models, storefront, cart and checkout.

When sPricing is available, it receives the current-user context. If it is not installed or cannot return an applicable price, sCommerce keeps the historical `price_special` / `price_regular` rule. Existing stores therefore remain compatible.

Cached product or page data stay user-neutral. The resolver applies the current user's price during the request, preventing a shared cache from leaking one customer's price to another.
