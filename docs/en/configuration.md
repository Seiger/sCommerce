# Configuration

sCommerce needs no configuration for legacy prices. Install sPricing and configure active price types to enable contextual prices. If sPricing is absent, existing product fields and flows continue unchanged.

## Stock

Since 1.5.0, orders can reduce product stock. The settings are on the product settings tab, next to **Quantity**:

| Setting | Values | Default |
|---|---|---|
| `product.inventory_commit` (**Reduce stock on orders**) | `none`: never; `order_created`: when the order is placed; `order_paid`: when the payment status becomes *Paid* | `none` |
| `product.inventory_release_on_cancel` (**Return stock on cancellation**) | `1`: a cancelled or deleted order returns what it took; `0`: stock stays reduced | `1` |

- Each order reduces stock once. All its lines change or none: when a product has less stock than the order needs, nothing changes and the order is retried later. Stock never becomes negative.
- Lines that do not track inventory (virtual and service products) and orders of trusted test users never change stock.
- A returned order gets back exactly the quantities it took, even if its lines were edited afterwards. An order that leaves the cancelled status reduces stock again.
- `s_orders.inventory_committed_at` marks orders whose stock is reduced; `meta.inventory` keeps the reduced quantities.
- The reduction runs after the order is saved, independently of event listeners. The hidden sTask worker **Stock reconciliation** retries failed reductions and returns every 15 minutes.
- When an ERP is the stock source and imports quantities that already exclude open orders, keep `none`; otherwise open orders are subtracted twice. A bound stock provider can also reduce and return stock in the ERP itself; see [Stock events](developers/events.md#stock-events).
- The settings tab shows a warning when the reduction is on while product quantities are turned off, or when the migrations have not run.
