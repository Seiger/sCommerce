<?php namespace Seiger\sCommerce\Payment;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Interfaces\InstallmentPaymentMethodInterface;
use Seiger\sCommerce\Interfaces\PaymentMethodInterface;
use Seiger\sCommerce\Models\sOrder;

/**
 * Installment schedule of an order (deposit + final), stored in `payment_info.schedule`.
 *
 * A schedule is a commerce obligation, not a provider fact: it is resolved once by
 * `sCommerce.ResolvePaymentSchedule` listeners before the order is first saved, and the
 * amount to request next is always computed on the server from the ledger.
 *
 * Item `due` values:
 * - `checkout`: requested when the order is placed;
 * - `manual`: not requested at checkout; payable by pay link at any time;
 * - `on_status:<int>`: payable once the order has reached that order status.
 *
 * @since 1.5.0
 */
final class PaymentSchedule
{
    /** Event that lets listeners define the schedule of an unsaved order. */
    public const EVENT = 'sCommerce.ResolvePaymentSchedule';

    /** @var list<string> */
    public const KINDS = ['deposit', 'final'];

    /**
     * Ask listeners for the schedule of an unsaved order.
     *
     * Listeners receive `['order' => sOrder, 'schedule' => &$schedule]` and append items
     * `{kind: 'deposit'|'final', amount: float, due: 'checkout'|'manual'|'on_status:<int>', label?: string}`.
     *
     * @return list<array{id: string, kind: string, amount: float, due: string, label?: string}> Validated schedule; empty for full payment.
     * @throws \InvalidArgumentException When a listener returns an invalid schedule.
     * @since 1.5.0
     */
    public static function resolve(sOrder $order): array
    {
        $app = Facade::getFacadeApplication();
        if (!$app || !isset($app['events'])) {
            return [];
        }

        $schedule = [];
        Event::dispatch(self::EVENT, [['order' => $order, 'schedule' => &$schedule]]);

        try {
            return self::normalize(is_array($schedule) ? $schedule : [null], $order->getAttributes()['cost'] ?? 0);
        } catch (\InvalidArgumentException $exception) {
            Log::channel('scommerce')->warning('sCommerce rejected an invalid payment schedule', [
                'reason' => $exception->getMessage(),
                'cost' => (string)($order->getAttributes()['cost'] ?? ''),
                'currency' => (string)$order->currency,
            ]);
            throw new \InvalidArgumentException(__('sCommerce::global.payment_schedule_invalid'), 0, $exception);
        }
    }

    /**
     * Validate a schedule against the order cost and assign stable item ids.
     *
     * @param array<int, mixed> $schedule Items from listeners.
     * @param mixed $cost Order cost.
     * @return list<array{id: string, kind: string, amount: float, due: string, label?: string}>
     * @throws \InvalidArgumentException
     * @since 1.5.0
     */
    public static function normalize(array $schedule, mixed $cost): array
    {
        $items = [];
        $sum = 0;
        foreach (array_values($schedule) as $index => $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException("Schedule item $index is not an array.");
            }
            $kind = strtolower(trim((string)($item['kind'] ?? '')));
            $amount = $item['amount'] ?? null;
            $amountMinor = is_numeric($amount) ? PaymentFlow::toMinor(is_string($amount) ? $amount : (string)round((float)$amount, 2)) : 0;
            $due = strtolower(trim((string)($item['due'] ?? 'checkout')));
            if (!in_array($kind, self::KINDS, true) || $amountMinor <= 0 || !preg_match('/\A(?:checkout|manual|on_status:\d{1,4})\z/', $due)) {
                throw new \InvalidArgumentException("Schedule item $index needs kind deposit|final, a positive amount and due checkout|manual|on_status:<int>.");
            }
            $normalized = ['id' => (string)($index + 1), 'kind' => $kind, 'amount' => round($amountMinor / 100, 2), 'due' => $due];
            $label = trim((string)($item['label'] ?? ''));
            if ($label !== '') {
                $normalized['label'] = mb_substr($label, 0, 255);
            }
            $items[] = $normalized;
            $sum += $amountMinor;
        }

        if ($items !== [] && abs($sum - PaymentFlow::toMinor(is_string($cost) ? $cost : (string)$cost)) > 1) {
            throw new \InvalidArgumentException('Schedule amounts must add up to the order cost.');
        }

        return $items;
    }

    /**
     * Stored schedule of an order; empty when the order is paid in full.
     *
     * @param sOrder|array<string, mixed> $order Order model or its array form.
     * @return list<array{id: string, kind: string, amount: float, due: string, label?: string}>
     * @since 1.5.0
     */
    public static function of(sOrder|array $order): array
    {
        $info = $order instanceof sOrder ? $order->payment_info : ($order['payment_info'] ?? []);
        $schedule = is_array($info) ? ($info['schedule'] ?? []) : [];

        return is_array($schedule) && array_is_list($schedule) ? array_values(array_filter($schedule, 'is_array')) : [];
    }

    /**
     * Next installment to collect.
     *
     * Money already received, authorized or pending is allocated to the items in schedule
     * order; refunds do not reopen an installment. The amount is the unallocated rest of the
     * first open item, capped at the balance due.
     *
     * With `$totalMinor`, the last item is the order cost minus the earlier items, so a cost
     * changed after checkout (a surcharge, a weighed item) is settled by the final payment.
     *
     * @param list<array<string, mixed>> $schedule Stored schedule.
     * @param int $allocatedMinor Received, authorized and pending payments in minor units.
     * @param int $balanceDueMinor Order cost minus net received money in minor units.
     * @param int|null $totalMinor Current order cost in minor units.
     * @return array{id: string, kind: string, amount: float, amountMinor: int, due: string, label?: string}|null
     * @since 1.5.0
     */
    public static function next(array $schedule, int $allocatedMinor, int $balanceDueMinor, ?int $totalMinor = null): ?array
    {
        if ($balanceDueMinor <= 0) {
            return null;
        }

        $schedule = array_values($schedule);
        $earlierMinor = 0;
        foreach ($schedule as $index => $item) {
            $itemMinor = PaymentFlow::toMinor((string)($item['amount'] ?? 0));
            if ($totalMinor !== null && $index === count($schedule) - 1) {
                $itemMinor = max(0, $totalMinor - $earlierMinor);
            }
            $earlierMinor += $itemMinor;
            $covered = min($itemMinor, max(0, $allocatedMinor));
            $allocatedMinor -= $covered;
            if ($covered < $itemMinor) {
                $amountMinor = min($itemMinor - $covered, $balanceDueMinor);
                return array_filter([
                    'id' => (string)($item['id'] ?? ''),
                    'kind' => (string)($item['kind'] ?? 'final'),
                    'amount' => round($amountMinor / 100, 2),
                    'amountMinor' => $amountMinor,
                    'due' => (string)($item['due'] ?? 'checkout'),
                    'label' => $item['label'] ?? null,
                ], static fn ($value) => $value !== null);
            }
        }

        return null;
    }

    /**
     * Next payment to request for an order: its next installment, or for an order without a
     * schedule the unpaid rest as one `final` payment due by payment link.
     *
     * Money already received, authorized or pending is not requested again.
     *
     * @return array{id: string, kind: string, amount: float, amountMinor: int, due: string, label?: string}|null
     * @since 1.5.0
     */
    public static function nextPayment(sOrder $order, PaymentSummary $summary): ?array
    {
        if (self::of($order)) {
            return $summary->nextInstallment;
        }

        return self::next([['id' => '1', 'kind' => 'final', 'amount' => 0, 'due' => 'manual']],
            $summary->allocatedMinor(), $summary->outstandingMinor(), $summary->totalMinor);
    }

    /**
     * Whether an installment may be requested now.
     *
     * @param array<string, mixed> $installment Item from {@see self::next()}.
     * @since 1.5.0
     */
    public static function isDue(array $installment, sOrder $order): bool
    {
        $due = (string)($installment['due'] ?? 'checkout');
        if (!str_starts_with($due, 'on_status:')) {
            return in_array($due, ['checkout', 'manual'], true);
        }

        $status = (int)substr($due, strlen('on_status:'));
        if ((int)$order->status === $status) {
            return true;
        }
        foreach (is_array($order->history) ? $order->history : [] as $entry) {
            if (is_array($entry) && isset($entry['status']) && (int)$entry['status'] === $status) {
                return true;
            }
        }

        return false;
    }

    /**
     * The `payment_request` passed to a payment method for an installment.
     *
     * @param array<string, mixed> $installment Item from {@see self::next()}.
     * @return array{kind: string, amount: float, currency: string, orderIdentifier: string, scheduleItem: string, key: string}
     *         `key` is stable per order, installment and amount; providers may use it to deduplicate payment sessions.
     * @since 1.5.0
     */
    public static function paymentRequest(sOrder $order, array $installment): array
    {
        $amountMinor = (int)($installment['amountMinor'] ?? PaymentFlow::toMinor((string)$installment['amount']));

        return [
            'kind' => (string)$installment['kind'],
            'amount' => round($amountMinor / 100, 2),
            'currency' => strtoupper((string)$order->currency),
            'orderIdentifier' => (string)$order->identifier,
            'scheduleItem' => (string)$installment['id'],
            'key' => "installment/{$order->id}/{$installment['id']}/$amountMinor",
        ];
    }

    /**
     * Whether a payment method honours `payment_request`.
     *
     * @since 1.5.0
     */
    public static function supports(?PaymentMethodInterface $method): bool
    {
        return $method instanceof InstallmentPaymentMethodInterface;
    }
}
