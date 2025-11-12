---
id: methods
title: Built-in Payment Methods
sidebar_position: 2
---

# Built-in Payment Methods

sCommerce includes two payment methods out of the box:

1. **Cash Payment** - Accept cash payments (cash on delivery)
2. **Bank Invoice Payment** - Accept bank transfers via invoice

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

## Bank Invoice Payment

### Overview

The Bank Invoice Payment method allows customers to pay via bank transfer. This payment method is ideal for:

- B2B (business-to-business) transactions
- Corporate clients who prefer invoicing
- High-value orders requiring formal documentation
- Customers who need payment documentation for accounting
- International transfers requiring bank details

### Features

- ✅ **Complete bank details** - Display all necessary bank information
- ✅ **Configurable payment terms** - Set payment deadlines (1-30 days)
- ✅ **Invoice generation** - Optional automatic invoice creation
- ✅ **Payment confirmation** - Manual verification of received payments
- ✅ **Custom instructions** - Add specific payment guidance
- ✅ **Multi-language support** - Localized bank details display
- ✅ **Printable format** - Print-friendly bank details

### Configuration

#### Step 1: Access Payment Methods

1. Navigate to: **Admin Panel → Modules → Commerce → Payments**
2. Find "Bank Invoice" in the list of payment methods
3. Click on the payment method to configure it

#### Step 2: Bank Details (Credentials)

Configure your bank account details that will be displayed to customers:

**Account Holder**: Company or individual name receiving payments
- Example: "Your Company LLC"

**Bank Name**: Full name of your bank
- Example: "PrivatBank" or "Monobank"

**Account Number (IBAN)**: Your IBAN account number
- Format: UA123456789012345678901234567
- Must be valid IBAN format

**Bank Code (MFO/SWIFT)**: Bank identification code
- MFO: 6-digit code (Ukraine)
- SWIFT: 8 or 11 characters (International)
- Example: 305299 (MFO) or PBANUA2X (SWIFT)

**Tax ID / EDRPOU**: Company tax identification
- EDRPOU: 8 digits (companies in Ukraine)
- IPN: 10 digits (individuals in Ukraine)
- Example: 12345678

#### Step 3: Payment Settings

**Payment Terms (days)**: Number of days for payment
- Default: 7 days
- Range: 1-30 days
- Displayed to customers as payment deadline

**Auto-generate Invoice**: Enable automatic invoice generation
- When enabled: Invoice PDF is created automatically
- When disabled: Manual invoice generation required

**Require Payment Confirmation**: Require admin verification
- When enabled: Orders require manual payment confirmation
- When disabled: Automatic confirmation (not recommended)

**Payment Instructions**: Additional information for customers
- Example: "Please include order number in payment purpose"
- Example: "Payment must be received within 7 business days"
- Example: "Bank transfers may take 1-3 business days to process"

### Order Processing

When processing orders with bank invoice payment:

1. **Order Created**: Status set to "awaiting payment"
2. **Invoice Sent**: Customer receives email with bank details
3. **Payment Made**: Customer completes bank transfer
4. **Payment Confirmed**: Admin verifies payment and updates status to "paid"
5. **Order Processed**: Continue with normal order fulfillment

### Frontend Display

#### Bank Details Display

When customers select Bank Invoice payment, they see:

- Complete bank account information
- Payment amount in their currency
- Order reference number
- Payment deadline (based on payment terms)
- Custom instructions (if configured)
- Print button for easy reference

#### Example Display

```
Bank Invoice Payment
--------------------
Amount: 5,000 UAH

Bank Details:
Recipient:          Your Company LLC
Bank:              PrivatBank
IBAN:              UA123456789012345678901234567
Bank Code (MFO):   305299
Tax ID (EDRPOU):   12345678
Amount to Pay:     5,000 UAH
Payment Purpose:   Payment for order #123

Payment Terms: 7 days from order date

Instructions: Please include order number in payment purpose
```

### Frontend Integration

#### Display Payment Method

```php
$checkout = new sCheckout();
$paymentMethods = $checkout->getPayments();

foreach ($paymentMethods as $method) {
    if ($method['key'] === 'bank_invoice') {
        echo '<div class="payment-method">';
        echo '<h3>' . $method['title'] . '</h3>';
        echo '<p>' . $method['description'] . '</p>';
        
        // Display payment button/details
        $paymentButton = $method['instance']->payButton($order);
        echo $paymentButton;
        
        echo '</div>';
    }
}
```

#### Get Bank Details

```php
$checkout = new sCheckout();
$bankInvoice = $checkout->getPayment('bank_invoice');

// Access bank details
echo $bankInvoice['account_holder'];
echo $bankInvoice['bank_name'];
echo $bankInvoice['account_number'];
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
            "id": 2,
            "key": "bank_invoice",
            "name": "bank_invoice",
            "title": "Bank Invoice",
            "description": "Pay via bank transfer",
            "account_holder": "Your Company LLC",
            "bank_name": "PrivatBank",
            "account_number": "UA123456789012345678901234567",
            "payment_terms": 7
        }
    ]
}
```

#### Process Bank Invoice Payment

```http
POST /api/checkout/pay/bank_invoice
```

Request body:
```json
{
    "order_id": 123,
    "payment_method": "bank_invoice"
}
```

Response:
```json
{
    "success": true,
    "message": "Order created. Bank details sent to email.",
    "order": {
        "id": 123,
        "status": "awaiting_payment",
        "payment_method": "bank_invoice",
        "bank_details": {
            "account_holder": "Your Company LLC",
            "bank_name": "PrivatBank",
            "account_number": "UA123456789012345678901234567",
            "bank_code": "305299",
            "tax_id": "12345678",
            "amount": 5000,
            "currency": "UAH",
            "payment_purpose": "Payment for order #123"
        }
    }
}
```

### Best Practices

#### 1. **Clear Instructions**
- Provide clear payment purpose format
- Specify exact payment deadlines
- Mention processing times for bank transfers

#### 2. **Payment Verification**
- Check bank statements daily
- Match payment amounts exactly
- Verify order numbers in payment purposes
- Update order statuses promptly

#### 3. **Communication**
- Send automated email with bank details
- Remind customers before deadline expires
- Confirm receipt of payment
- Provide receipts after payment

#### 4. **Documentation**
- Keep records of all bank transfers
- Store payment confirmations
- Generate invoices for all transactions
- Maintain audit trail

### Email Templates

#### Order Confirmation with Bank Details

```blade
<h2>Order #{{ $order->id }} - Payment Required</h2>

<p>Thank you for your order. Please transfer {{ $order->total }} {{ $order->currency }} to the following account:</p>

<table>
    <tr>
        <td><strong>Recipient:</strong></td>
        <td>{{ $bankDetails['account_holder'] }}</td>
    </tr>
    <tr>
        <td><strong>Bank:</strong></td>
        <td>{{ $bankDetails['bank_name'] }}</td>
    </tr>
    <tr>
        <td><strong>IBAN:</strong></td>
        <td>{{ $bankDetails['account_number'] }}</td>
    </tr>
    <tr>
        <td><strong>Bank Code:</strong></td>
        <td>{{ $bankDetails['bank_code'] }}</td>
    </tr>
    <tr>
        <td><strong>Payment Purpose:</strong></td>
        <td>Payment for order #{{ $order->id }}</td>
    </tr>
</table>

<p><strong>Payment Deadline:</strong> {{ $deadline }}</p>
```

### Admin Functions

#### Verify Payment

```php
use Seiger\sCommerce\Models\sOrder;

// Find order
$order = sOrder::find($orderId);

// Verify payment received
if ($paymentReceived) {
    $order->update([
        'status' => 'paid',
        'paid_at' => now(),
        'payment_note' => 'Bank transfer verified on ' . now()->format('Y-m-d H:i'),
    ]);
    
    // Send confirmation email
    Mail::to($order->customer_email)->send(new PaymentConfirmed($order));
}
```

#### Generate Invoice

```php
use Seiger\sCommerce\Models\sOrder;
use App\Services\InvoiceGenerator;

$order = sOrder::find($orderId);
$invoice = InvoiceGenerator::generate($order);

// Save invoice
$order->invoice_path = $invoice->save();
$order->save();

// Send to customer
Mail::to($order->customer_email)
    ->send(new InvoiceGenerated($order, $invoice));
```

### Statistics and Reporting

Track bank invoice payments:

```php
use Seiger\sCommerce\Models\sOrder;

// Get all bank invoice orders
$bankInvoiceOrders = sOrder::where('payment_method', 'bank_invoice')
    ->where('status', 'paid')
    ->get();

// Calculate total received
$totalReceived = sOrder::where('payment_method', 'bank_invoice')
    ->where('status', 'paid')
    ->sum('total');

// Get awaiting payment
$awaitingPayment = sOrder::where('payment_method', 'bank_invoice')
    ->where('status', 'awaiting_payment')
    ->get();

// Get overdue payments
$overduePayments = sOrder::where('payment_method', 'bank_invoice')
    ->where('status', 'awaiting_payment')
    ->where('created_at', '<', now()->subDays(7))
    ->get();
```

### Customizing Payment Display

The Bank Invoice payment method uses a Blade template that can be customized to match your site's design.

#### Template Resolution

The system looks for templates in this order:
1. **Project override**: `views/payment/bank_invoice.blade.php`
2. **Custom package override**: `core/custom/packages/seiger/views/sCommercePro/Payment/bank_invoice.blade.php`
3. **Vendor default**: `core/vendor/seiger/scommerce/views/payment/bank_invoice.blade.php`

#### Customization

To customize the payment display, copy the vendor template to your project:

```bash
# Copy the default template to your project overrides directory
cp core/vendor/seiger/scommerce/views/payment/bank_invoice.blade.php views/payment/bank_invoice.blade.php
```

#### Available Variables

You can use the following variables in your Blade template (see Blade comments in the template file for full documentation):

- `$title` - Payment method title (localized)
- `$account_holder` - Company or individual name
- `$bank_name` - Full name of the bank
- `$account_number` - IBAN account number
- `$bank_code` - Bank code (MFO/SWIFT)
- `$tax_id` - Tax ID / EDRPOU / IPN
- `$amount` - Payment amount
- `$currency` - Currency code
- `$order_id` - Order ID/number
- `$payment_terms` - Number of days for payment
- `$info` - Additional instructions (text)

#### Example Customization

```blade
{{-- Custom template example --}}
<div class="my-custom-invoice">
    <h2>{{ $title }}</h2>
    
    <div class="amount-display">
        Amount to pay: <strong>{{ $amount }} {{ $currency }}</strong>
    </div>
    
    <div class="bank-info">
        <p>Bank: {{ $bank_name }}</p>
        <p>Account: {{ $account_number }}</p>
        <p>Purpose: Payment for order #{{ $order_id }}</p>
    </div>
    
    @if($info)
        <div class="instructions">
            {!! nl2br(e($info)) !!}
        </div>
    @endif
</div>

<style>
.my-custom-invoice {
    /* Your custom styles */
}
</style>
```

#### Benefits of Blade Templates

- **Security**: Automatic escaping of variables
- **Flexibility**: Full Blade directive support (@if, @foreach, etc.)
- **Clean Syntax**: Easy to read and maintain
- **No Comments in Output**: Blade comments won't appear in HTML source

#### Fallback Template

If the template file doesn't exist or fails to render, the system will use a built-in default template. This ensures the payment method works even if the template file is missing.

### Troubleshooting

#### Payment Not Showing

1. **Check bank details**: Ensure all required fields are filled
2. **Verify active status**: Payment method must be enabled
3. **Check permissions**: Ensure payment methods are globally enabled
4. **Check template**: Verify template file exists at `views/payment/bank_invoice.blade.php`

#### Template Not Loading

1. **Check file path**: Ensure template is in correct location
2. **Check file permissions**: File must be readable by web server
3. **Check Blade syntax**: Ensure Blade syntax is valid
4. **Clear cache**: Clear Blade cache and system cache
5. **Check logs**: Review error logs for template rendering errors

#### Customers Not Receiving Details

1. **Check email templates**: Verify email template includes bank details
2. **Test email sending**: Send test emails to verify delivery
3. **Check spam folders**: Emails may be filtered as spam

#### Payment Confirmation Issues

1. **Match amounts exactly**: Including decimal places
2. **Verify order numbers**: Check payment purposes
3. **Check transaction dates**: Ensure within payment terms

## Adding More Payment Methods

Cash and Bank Invoice payment methods are just the beginning. You can extend sCommerce with additional payment methods:

### Available Third-Party Integrations

- **Stripe** - Credit card processing
- **PayPal** - Global payment platform
- **LiqPay** - Ukrainian payment gateway
- **WayForPay** - Ukrainian payment system
- **Fondy** - International payment gateway
- **Custom integrations** - Build your own

### Creating Custom Payment Methods

To integrate with other payment gateways or create custom payment logic, see:
- [Creating Custom Payment Methods](index.md) - Developer guide

## Links

- [Creating Custom Payment Methods](../payments.md) - Guide for developers
- [Checkout Process](../checkout.md) - Complete checkout flow
- [Order Management](../orders.md) - Managing orders
- [API Documentation](../api.md) - Payment API reference
