<?php namespace Seiger\sCommerce\Payment;

/**
 * Ledger totals of one order in integer minor units (cents) of the order currency.
 *
 * Rows in another currency are excluded and listed in `ignoredCurrencies`.
 *
 * @since 1.5.0
 */
final readonly class PaymentSummary
{
    /**
     * @param string $currency Order currency.
     * @param int $totalMinor Order cost.
     * @param int $capturedMinor Money received, including later refunded or disputed payments.
     * @param int $refundedMinor Money returned.
     * @param int $authorizedMinor Authorized and not yet captured.
     * @param bool $disputed A payment of the order is disputed.
     * @param int $paymentStatus Projected `sOrder::PAYMENT_STATUS_*`.
     * @param list<string> $ignoredCurrencies Currencies of rows excluded from the totals.
     * @param int $unresolvedLegacyRefunds Partially refunded payments without a recorded returned amount.
     * @param array{id: string, kind: string, amount: float, amountMinor: int, due: string, label?: string}|null $nextInstallment
     *        Next open item of the order's payment schedule ({@see PaymentSchedule::next()}); null without a schedule or when nothing is open.
     * @param int $pendingMinor Payments started and not yet confirmed.
     */
    public function __construct(
        public string $currency,
        public int $totalMinor,
        public int $capturedMinor,
        public int $refundedMinor,
        public int $authorizedMinor,
        public bool $disputed,
        public int $paymentStatus,
        public array $ignoredCurrencies = [],
        public int $unresolvedLegacyRefunds = 0,
        public ?array $nextInstallment = null,
        public int $pendingMinor = 0,
    ) {}

    /**
     * Captured minus refunded, never below zero.
     *
     * @since 1.5.0
     */
    public function netMinor(): int
    {
        return max(0, $this->capturedMinor - $this->refundedMinor);
    }

    /**
     * Amount still to be paid (the balance due), never below zero.
     *
     * @since 1.5.0
     */
    public function outstandingMinor(): int
    {
        return max(0, $this->totalMinor - $this->netMinor());
    }

    /**
     * Net received money above the order cost, e.g. after the cost was lowered.
     *
     * @since 1.5.0
     */
    public function overpaidMinor(): int
    {
        return max(0, $this->netMinor() - $this->totalMinor);
    }

    /**
     * Money received (refunds not deducted), authorized or pending: what is not requested again.
     *
     * @since 1.5.0
     */
    public function allocatedMinor(): int
    {
        return $this->capturedMinor + $this->authorizedMinor + $this->pendingMinor;
    }

    /**
     * Format minor units as a decimal string, e.g. `1050` → `10.50`.
     *
     * @since 1.5.0
     */
    public static function decimal(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return $sign . intdiv($minor, 100) . '.' . str_pad((string)($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
