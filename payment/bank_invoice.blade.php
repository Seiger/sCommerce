{{--
    Default Bank Invoice Payment template for sCommerce
    
    This is the default template that will be used if no custom template
    is found in assets/modules/scommerce/payment/bank_invoice.blade.php
    
    To customize this template, create a copy at:
    assets/modules/scommerce/payment/bank_invoice.blade.php
    
    Available template variables:
    - $title            : Payment method title (localized)
    - $account_holder   : Company or individual name receiving payments
    - $bank_name        : Full name of the bank
    - $account_number   : IBAN account number
    - $bank_code        : Bank identification code (MFO/SWIFT)
    - $tax_id           : Tax ID / EDRPOU / IPN
    - $amount           : Payment amount
    - $currency         : Currency code (UAH, USD, EUR, etc.)
    - $order_id         : Order ID/number
    - $payment_terms    : Number of days for payment
    - $info             : Additional payment instructions (plain text)
--}}
<div class="bank-invoice-details">
    <h3>{{$title}}</h3>
    <div class="payment-info">
        <p class="payment-amount"><strong>{{$amount}} {{$currency}}</strong></p>
        <div class="bank-details">
            <h4>Bank Details:</h4>
            <table class="bank-details-table">
                <tr>
                    <td><strong>Recipient:</strong></td>
                    <td>{{$account_holder}}</td>
                </tr>
                <tr>
                    <td><strong>Bank:</strong></td>
                    <td>{{$bank_name}}</td>
                </tr>
                <tr>
                    <td><strong>IBAN:</strong></td>
                    <td>{{$account_number}}</td>
                </tr>
                <tr>
                    <td><strong>Bank Code:</strong></td>
                    <td>{{$bank_code}}</td>
                </tr>
                <tr>
                    <td><strong>Tax ID:</strong></td>
                    <td>{{$tax_id}}</td>
                </tr>
                <tr>
                    <td><strong>Amount to Pay:</strong></td>
                    <td>{{$amount}} {{$currency}}</td>
                </tr>
                <tr>
                    <td><strong>Payment Purpose:</strong></td>
                    <td>Payment for order #{{$order_id}}</td>
                </tr>
            </table>
        </div>
        <div class="payment-terms">
            <p><strong>Payment Terms:</strong> {{$payment_terms}} days from order date</p>
        </div>
        @if($info)
            <div class="payment-instructions">
                {!!nl2br(e($info))!!}
            </div>
        @endif
        <div class="payment-actions">
            <button type="button" class="btn btn-secondary" onclick="window.print()">
                Print Details
            </button>
        </div>
    </div>
</div>

<style>
.bank-invoice-details {padding:20px;background:#f9f9f9;border-radius:8px;margin:20px 0;}
.bank-details-table {width:100%;border-collapse:collapse;margin:15px 0;}
.bank-details-table td {padding:10px;border-bottom:1px solid #ddd;}
.bank-details-table td:first-child {width:200px;color:#666;}
.payment-amount {font-size:1.5em;color:#2c5aa0;margin:10px 0;}
.payment-instructions {background:#e8f4f8;padding:15px;border-radius:4px;margin:15px 0;}
.payment-terms {color:#d9534f;font-weight:bold;margin:15px 0;}
@media print {.payment-actions {display: none;}}
</style>