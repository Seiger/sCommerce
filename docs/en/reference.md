# Pricing reference

`sPriceResolver` provides consistent display fields for all sCommerce surfaces. Existing events `sCommerce.ResolveProductPriceMode` and `sCommerce.ResolveProductPrice` remain supported for project overrides.

Priority: applicable sPricing personal or cumulative price, then promotional price, then base price. The old price is the price from which the effective discount was granted.

## Events

Full contracts and examples: [sCommerce events](developers/events.md).

- `sCommerce.CheckoutValidationRules` — Checkout rules by reference (`data`, `rules`); add, replace or remove rules.
- `sCommerce.ResolveProductPriceMode` — Product price mode (`product`, `optionId`, `priceMode`); the first non-empty string selects `auto` or `wholesale`.
- `sCommerce.ResolveProductPrice` — Product pricing (`product`, `optionId`, `priceMode`, `currency`, `pricing`); the first numeric or array response overrides the price. sPricing listens to this event.
