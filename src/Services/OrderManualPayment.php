<?php namespace Seiger\sCommerce\Services;

use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Payment\PaymentFlow;

/** Called inside a transaction with the order locked. Never changes provider transactions. */
final class OrderManualPayment
{
    public function apply(sOrder $order, int $managerId): bool
    {
        if ((int) $order->payment_status === sOrder::PAYMENT_STATUS_PAID) return false;
        $info = is_array($order->payment_info) ? $order->payment_info : [];
        if (!in_array($info['method'] ?? '', ['', 'cash', 'bank_invoice'], true)
            || !in_array((int) $order->payment_status, [sOrder::PAYMENT_STATUS_PENDING, sOrder::PAYMENT_STATUS_AWAITING_CONFIRMATION, sOrder::PAYMENT_STATUS_PARTIALLY_PAID], true)
            || !is_numeric($order->cost) || (float) $order->cost <= 0 || !preg_match('/\A[A-Z]{3}\z/', (string) $order->currency)) {
            throw new \DomainException(__('sCommerce::global.bulk_paid_ineligible'));
        }
        $flow = new PaymentFlow();
        $paymentId = null;
        if ($flow->ledgerAvailable()) {
            $payments = sOrderPayment::query()->where('order_id', $order->id)->lockForUpdate()->get();
            foreach ($payments as $payment) {
                if ((string) $payment->provider !== 'manual' || $payment->currency !== $order->currency || (float) $payment->amount < 0
                    || !in_array($payment->kind, ['manual', 'deposit', 'final'], true)
                    || !in_array($payment->status, ['pending', 'captured', 'failed', 'canceled', 'expired', 'rejected'], true)) {
                    throw new \DomainException(__('sCommerce::global.bulk_paid_ineligible'));
                }
            }
            $captured = (float) $payments->where('status', 'captured')->sum('amount');
            // The payment ledger persists amounts with two decimal places.
            $remaining = max(0, round((float) $order->cost - $captured, 2));
            // A legacy partial status without ledger amounts cannot be safely completed in bulk.
            if ((int) $order->payment_status === sOrder::PAYMENT_STATUS_PARTIALLY_PAID && $captured <= 0) {
                throw new \DomainException(__('sCommerce::global.bulk_paid_ineligible'));
            }
            if ($remaining > 0) {
                $payment = new sOrderPayment([
                    'order_id' => $order->id, 'sequence' => (int) $payments->max('sequence') + 1,
                    'kind' => 'manual', 'status' => 'captured', 'amount' => $remaining,
                    'currency' => $order->currency, 'provider' => 'manual', 'captured_at' => now(),
                    'metadata' => ['source' => 'manager_bulk_paid', 'manager_user_id' => $managerId],
                ]);
                if (!$payment->save()) throw new \RuntimeException('Manual payment save canceled');
                $paymentId = $payment->id;
            }
            $flow->syncOrderPaymentStatusFromLedger($order);
            if ((int) $order->fresh()->payment_status !== sOrder::PAYMENT_STATUS_PAID) {
                throw new \DomainException(__('sCommerce::global.bulk_paid_ineligible'));
            }
        } elseif ((int) $order->payment_status === sOrder::PAYMENT_STATUS_PARTIALLY_PAID) {
            throw new \DomainException(__('sCommerce::global.bulk_paid_ineligible'));
        }
        $history = is_array($order->history) ? $order->history : [];
        $history[] = ['payment_status' => sOrder::PAYMENT_STATUS_PAID, 'timestamp' => now()->toDateTimeString(),
            'user_id' => $managerId, 'source' => 'manager_bulk_paid', 'payment_id' => $paymentId];
        $order->history = $history;
        $order->payment_status = sOrder::PAYMENT_STATUS_PAID;
        if (!$order->save()) throw new \RuntimeException('Order payment save canceled');
        return true;
    }
}
