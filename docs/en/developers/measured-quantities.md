# Measured quantities

Since 1.5.0, a product can be sold by weight, length, volume or count instead of by the piece: cable by the metre, paint by the litre, sand by the kilogram, ad impressions per thousand. Quantities stay integers. A measured quantity is a whole number of **base units**:

| Measure | Base unit | Price units | Price basis |
|---|---|---|---|
| `weight` | gram (`g`) | `kg`, `g` | 1, 10, 100 or 1000 price units, at most 1 kg |
| `length` | millimetre (`mm`) | `m`, `cm`, `mm` | 1, 10, 100 or 1000 price units, at most 1 m |
| `volume` | millilitre (`ml`) | `l`, `ml` | 1, 10, 100 or 1000 price units, at most 1 l |
| `count` | item | — | per 1, 1,000 or 1,000,000 items |

The price refers to `factor` base units: base units per price unit × price basis. `€1.20 / m` has factor 1000, `€2.49 / 100 g` has factor 100, `€2.50 / 1,000` impressions has factor 1000. A line total is `round_half_up(price × quantity ÷ factor)` in cents, computed in integers from the unrounded price (`price_regular` has five decimals). The unit price is never rounded to cents first.

Piece products, old orders and themes that post integer quantities behave exactly as before. Run the sCommerce migrations to add the product columns.

## Products

The product form has a **Sold by** block with the measure, the price unit, the price basis, rounding, step, minimum and tolerance. Empty values use the site defaults. The stock field shows the base unit (**Quantity, mm**): stock of a measured product is in base units.

| Column on `s_products` | Meaning |
|---|---|
| `measure` | `0` piece, `1` weight, `2` length, `3` volume, `4` count |
| `price_unit` | unit code the price refers to (`kg`, `m`, …); `NULL` = site default |
| `price_per` | price basis in price units (`100` for a price per 100 g); `NULL` = site default |
| `quantity_step` | orderable step in base units (`10` = cut to the centimetre); `NULL` = site default |
| `quantity_min` | minimum order in base units, at least one step; `NULL` = site default |
| `quantity_tolerance` | allowed overrun of an actual quantity in basis points (`500` = 5 %); `NULL` = site default |
| `quantity_rounding` | storefront rounding to the step: `up`, `nearest` or `down`; `NULL` = site setting of the price unit |

Changing the measure of a product that has stock or appears in an order is refused, because it would change the meaning of the stored integers. Measured products cannot have a returnable container deposit. Physical attributes of a measured product describe **one price unit** (a cable's grams per metre). `count` products are normally services without shipping and stock; if they track stock, their maximum quantity is 2,000,000,000.

The CSV import and export have the columns `measure` (`piece`, `weight`, `length`, `volume`, `count`), `price_unit`, `price_per`, `quantity_step`, `quantity_min`, `quantity_tolerance` and `quantity_rounding`. Missing columns keep the product's values; invalid values fail the row.

## Settings

The product settings tab has **Measured quantities**: per measure the default price unit, price basis, step, minimum, maximum (base units), tolerance and input decimals (`product.measures`), and **Rounding to the step per unit** (`product.quantity_rounding`). Defaults: `up` for length and volume (too short is useless), `nearest` for weight; a unit without a setting rounds up. Invalid values are not saved and the previous ones are kept. Changing the defaults never changes stored order lines.

## Storefront

`sCart::addProduct()` keeps its signature. For measured products, an `amount` field in the price unit (`2.53` metres, always with a `.` separator) takes precedence over `quantity` (base units). The quantity is raised to the minimum, rounded to the step with the product's rounding, capped at the maximum and at the available stock rounded **down** to a step. When the quantity changes, the response contains `quantityAdjusted: true` and the message says so. The default quantity is the minimum.

```blade
{{-- Amount input in the price unit; piece products get the usual quantity input. --}}
<div>
    {!! sCommerce::quantityInput($product) !!}
    <button type="button" data-sc-buy="{{$product->id}}">Buy</button>
</div>
<span>{{ sCommerce::unitPrice($product) }}</span> {{-- €2.49 / 100 g --}}

{{-- In the cart: --}}
{!! sCommerce::quantityInput($product, ['data-sc-quantity' => $product->id, 'value' => $amount]) !!}
```

`quantityInput()` renders `<input type="number" name="amount">` with `min`, `step` and `max` in the price unit and `data-sc-measure`, `data-sc-factor` (base units per price unit), `data-sc-step`, `data-sc-min` (base units), `data-sc-decimals` and `data-sc-unit`. The supplied script posts the `amount` of such inputs; quick orders (`data-sc-fast-buy`) send it with the other inputs.

Labels follow the language: units come from the language file (`kg` is `кг` in Ukrainian) and so do the decimal and thousands separators (`2,53 м`). Use `MeasuredQuantity::label($quantity, $rules, $locale)` and `MeasuredQuantity::unitPriceLabel($formattedPrice, $rules, $locale)`.

## Order lines

Measured lines carry these keys next to the usual ones; piece lines have none of them:

| Key | Example | Notes |
|---|---|---|
| `quantity` | `2530` | integer, base units |
| `measure` | `"length"` | `weight`, `length`, `volume` or `count` |
| `base_unit` | `"mm"` | `g`, `mm`, `ml` or `unit` |
| `price_unit` | `"m"` | unit the price refers to |
| `price_per` | `1` | price basis (`100` for `€2.49 / 100 g`) |
| `factor` | `1000` | base units the price refers to, a power of ten |
| `quantity_rounding`, `quantity_step`, `quantity_min`, `quantity_tolerance` | `"up"`, `10`, `1000`, `500` | rules at checkout |
| `quantity_label`, `unit_price_label` | `"2.53 m"`, `"€1.20 / m"` | display only, in the order language |
| `line_weight` | `475` | grams for the whole line, when known |
| `actual_quantity`, `actual_quantity_at`, `actual_quantity_by`, `actual_quantity_source`, `actual_quantity_label` | `2553` | only when an actual quantity is set |

Rules for consumers:

- The billable quantity is `actual_quantity ?? quantity`: `MeasuredQuantity::effective($line)`.
- The rules of a stored line come from the line, never from the current product: `MeasuredQuantity::fromLine($line)`.
- Never send labels as data. For accounting, use `MeasuredQuantity::forAccounting($quantity, $price, $rules)`: weight, length and volume are expressed in the largest unit (`0.330 kg × 24.90`, `2.530 m × 1.20`; UN/ECE Rec 20 `KGM`, `MTR`, `LTR`), and `count` in basis units (`1.234567 × 1,000,000 tokens @ 0.50`). Both are exact; the accounting line total equals the sCommerce line total. The accounting system must accept 3 quantity decimals, and up to 6 for `count`.
- Payment providers that need integer quantities send a measured line as quantity 1 with the exact line total and a name such as `2.53 m × €1.20`.
- Delivery integrations should prefer `line_weight` over `weight × quantity`.

The manager order form shows measured quantities in the price unit and the manager's language. Rule and actual-quantity keys of existing lines are taken from the stored line on save; lines added in the manager take them from the product. Quantities are checked strictly (minimum, step, maximum) and never rounded. Repeat orders apply the current rules of the product and refuse a product whose measure has changed.

## Actual quantities

Cut or weighed goods often differ from the ordered amount: 2530 mm ordered, 2553 mm cut. `Seiger\sCommerce\Services\OrderActualQuantity::set()` records the actual quantity of a measured line:

```php
use Seiger\sCommerce\Services\OrderActualQuantity;

$order = (new OrderActualQuantity())->set($order, '12:0', 2553, 'erp', 0, 'cut-REF-2026-0042');
```

- Arguments: order, `line_key`, actual quantity in base units, source (`manager`, `erp` or `api`), actor id, an idempotency key and `overTolerance`.
- The same key with the same value returns the order unchanged; with another value it is a conflict (409). The last 50 keys are kept in `meta.actual_quantity_ops`.
- There is no step check: the actual quantity is a measurement. It must be at least 1 and at most the line maximum.
- It may exceed the ordered quantity by the line's `quantity_tolerance`. More needs `overTolerance = true`, meaning the customer agreed; in the manager this needs the permission `scommerce_order_actual_over_tolerance`.
- Cancelled and deleted orders, piece lines and lines already in a shipped or delivered shipment are refused (a correction after dispatch is a return or refund).
- The line sum, retail excise, line weight, order cost and history are updated in one transaction, with one `sCommerce.OrderUpdated` in the event scope of the source. Locally committed stock follows the change.
- Refusals throw `MeasuredQuantityException` with `messageKey` and an HTTP `status`; a stock shortage throws `StockShortageException`.

In the manager, measured lines have an **Actual** input on the order screen.

## Stock and shipments

Stock, stock events, `StockWriter`, `StockProviderInterface` and shipments keep their signatures; for measured products their integers are base units. Stock reduction and shipment coverage use the billable quantity, so a longer cut needs a longer shipment and takes more stock. `StockCommitter::adjust($order)` applies changed, added and removed lines of a locally committed order to stock; it runs after manager edits and actual quantities. Provider-committed orders are left to the provider, which receives `sCommerce.OrderUpdated`.

## Payments

`sOrder::authorizationCeiling()` returns the cost plus, for every measured line with a tolerance, the line total times the tolerance. Methods that authorize and capture later may reserve up to this amount; existing methods are unchanged. After an actual quantity changes the cost, capture the new amount, collect the difference with the final payment link or refund an overpayment, as for any cost change.

## Detecting support

`sCommerce::supportsIntegration('measured-quantities')` and `sCommerce::supportsIntegration('actual-quantities')` report support. `INTEGRATION_EVENTS_VERSION` stays `1`.
