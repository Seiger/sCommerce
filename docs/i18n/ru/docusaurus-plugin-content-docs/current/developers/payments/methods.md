---
id: methods
title: Built-in Payment Methods
sidebar_position: 2
---

# Built-in Payment Methods

sCommerce includes the **Cash Payment** method out of the box. This simple payment method allows you to accept cash payments (such as cash on delivery).

## Cash Payment

### Overview

The Cash Payment method is a simple payment option that doesn't require integration with external payment gateways. It's ideal for:

- Cash on Delivery (COD) orders
- In-store pickups with cash payment
- Local deliveries with cash collection
- Markets where cash is the preferred payment method

### Features

- ✅ **No external dependencies** - Works without third-party integrations
- ✅ **Simple configuration** - Minimal setup required
- ✅ **Custom messaging** - Add informational text for customers
- ✅ **Multi-language support** - Localized titles and descriptions
- ✅ **No transaction fees** - Direct payment without intermediaries

### Configuration

#### Step 1: Access Payment Methods

1. Navigate to: **Admin Panel → Modules → Commerce → Payments**
2. Find "Cash" in the list of payment methods
3. Click on the payment method to configure it

#### Step 2: Basic Settings

**Active**: Toggle to enable/disable the payment method

**Position**: Set the display order (lower numbers appear first)

**Title**: Localized payment method name displayed to customers
- English: "Cash"
- Ukrainian: "Готівка"
- Russian: "Наличные"

**Description**: Localized description shown during checkout
- English: "Pay with cash upon delivery or pickup"
- Ukrainian: "Оплата готівкою при отриманні"
- Russian: "Оплата наличными при получении"

#### Step 3: Additional Settings

**Info Message**: Optional message displayed to customers during checkout

Example messages:
- "Please have exact change ready for delivery"
- "Cash payment accepted only in local currency"
- "Deliveries over $500 may require advance payment"

### Usage in Checkout

When a customer selects the Cash payment method during checkout:

1. The payment method title and description are displayed
2. The info message (if configured) is shown
3. The order is created with status "pending payment"
4. No payment button is shown (payment will be collected offline)

### Order Processing

When processing orders with cash payment:

1. **Order Created**: Status is set to "pending"
2. **Order Prepared**: Update status to "ready for shipment"
3. **Order Shipped**: Update status to "shipped"
4. **Payment Collected**: After cash is received, update status to "paid"
5. **Order Completed**: Mark order as "completed"

### Frontend Integration

#### Display Payment Method

The cash payment method is automatically included in the checkout payment methods list:

```php
$checkout = new sCheckout();
$paymentMethods = $checkout->getPayments();

foreach ($paymentMethods as $method) {
    echo '<div class="payment-method">';
    echo '<input type="radio" name="payment_method" value="' . $method['key'] . '">';
    echo '<label>' . $method['title'] . '</label>';
    echo '<p>' . $method['description'] . '</p>';
    echo '</div>';
}
```

#### Get Specific Payment Method

```php
$checkout = new sCheckout();
$cashPayment = $checkout->getPayment('cash');

// Access payment details
echo $cashPayment['title'];       // "Cash"
echo $cashPayment['description']; // "Pay with cash upon delivery"
echo $cashPayment['info'];        // Info message (if configured)
```

### API Integration

#### Get Available Payment Methods

```http
GET /api/checkout/payments
```

Response:
```json
{
    "success": true,
    "payments": [
        {
            "id": 1,
            "key": "cash",
            "name": "cash",
            "title": "Cash",
            "description": "Pay with cash upon delivery or pickup",
            "info": "Please have exact change ready"
        }
    ]
}
```

#### Process Cash Payment

```http
POST /api/checkout/pay/cash
```

Request body:
```json
{
    "order_id": 123,
    "payment_method": "cash"
}
```

Response:
```json
{
    "success": true,
    "message": "Order created successfully",
    "order": {
        "id": 123,
        "status": "pending",
        "payment_method": "cash"
    }
}
```

### Best Practices

#### 1. **Clear Communication**
- Provide clear instructions about cash payment in the description
- Specify if exact change is required
- Mention any maximum cash payment limits

#### 2. **Order Verification**
- Call customers before shipping to confirm the order
- Verify the delivery address
- Confirm the payment amount

#### 3. **Delivery Instructions**
- Train delivery personnel on cash collection procedures
- Provide receipts for all cash transactions
- Use secure cash handling methods

#### 4. **Risk Management**
- Set maximum order value for cash payments
- Require prepayment for high-value orders
- Monitor for suspicious orders

### Customization

#### Custom Info Messages by Language

```php
// In admin panel, set different messages for each language:

// English
info: "Cash payment accepted. Please have exact change ready."

// Ukrainian
info: "Приймаємо оплату готівкою. Будь ласка, підготуйте точну суму."

// Russian
info: "Принимаем оплату наличными. Пожалуйста, подготовьте точную сумму."
```

#### Display Custom Message in Template

```blade
@php
    $checkout = new \Seiger\sCommerce\Checkout\sCheckout();
    $cashPayment = $checkout->getPayment('cash');
@endphp

@if(isset($cashPayment['info']) && $cashPayment['info'])
    <div class="alert alert-info">
        <i class="icon-info"></i>
        {{ $cashPayment['info'] }}
    </div>
@endif
```

### Troubleshooting

#### Payment Method Not Showing

1. **Check if active**: Ensure the payment method is enabled in admin panel
2. **Check position**: Verify the position number is set correctly
3. **Check permissions**: Ensure payment methods are enabled globally
4. **Clear cache**: Clear system cache to refresh payment methods

#### Payment Not Processing

1. **Validate order data**: Ensure order ID and payment method are provided
2. **Check order status**: Verify order can accept payments
3. **Review logs**: Check system logs for payment processing errors

### Example Template

Complete checkout payment selection:

```blade
<div class="payment-methods">
    <h3>Choose Payment Method</h3>
    
    @php
        $checkout = new \Seiger\sCommerce\Checkout\sCheckout();
        $payments = $checkout->getPayments();
    @endphp
    
    @foreach($payments as $payment)
        <div class="payment-method-option">
            <label class="payment-method-label">
                <input 
                    type="radio" 
                    name="payment_method" 
                    value="{{ $payment['key'] }}"
                    required
                >
                <span class="payment-title">{{ $payment['title'] }}</span>
            </label>
            
            @if(!empty($payment['description']))
                <p class="payment-description">{{ $payment['description'] }}</p>
            @endif
            
            @if(!empty($payment['info']))
                <div class="payment-info">
                    <i class="icon-info"></i>
                    {{ $payment['info'] }}
                </div>
            @endif
        </div>
    @endforeach
</div>
```

### Statistics and Reporting

Track cash payment orders:

```php
use Seiger\sCommerce\Models\sOrder;

// Get all cash payment orders
$cashOrders = sOrder::where('payment_method', 'cash')
    ->where('status', 'paid')
    ->get();

// Calculate total cash collected
$totalCash = sOrder::where('payment_method', 'cash')
    ->where('status', 'paid')
    ->sum('total');

// Get pending cash orders
$pendingCash = sOrder::where('payment_method', 'cash')
    ->where('status', 'pending')
    ->get();
```

## Adding More Payment Methods

The Cash payment method is just the beginning. You can extend sCommerce with additional payment methods:

### Available Third-Party Integrations

- **Stripe** - Credit card processing
- **PayPal** - Global payment platform
- **LiqPay** - Ukrainian payment gateway
- **WayForPay** - Ukrainian payment system
- **Fondy** - International payment gateway
- **Custom integrations** - Build your own

### Creating Custom Payment Methods

To integrate with other payment gateways or create custom payment logic, see:
- [Creating Custom Payment Methods](../payments.md) - Developer guide

## Links

- [Creating Custom Payment Methods](../payments.md) - Guide for developers
- [Checkout Process](../checkout.md) - Complete checkout flow
- [Order Management](../orders.md) - Managing orders
- [API Documentation](../api.md) - Payment API reference
