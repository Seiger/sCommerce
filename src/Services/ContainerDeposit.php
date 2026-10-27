<?php namespace Seiger\sCommerce\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sContainerReturn;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Payment\LedgerEntry;
use Seiger\sCommerce\Payment\PaymentActionException;
use Seiger\sCommerce\Payment\PaymentFlow;

/**
 * Returnable container deposits (e.g. a bottle deposit for water).
 *
 * The deposit is charged per unit on top of the goods price and is not part of it: it is not
 * a discount and is outside VAT. Units the customer exchanges for empty containers carry no new
 * deposit. Order lines store `deposit_unit`, `exchange_quantity`, `deposit_quantity`,
 * `deposit_amount`, `deposit_returnable` and the contract wording `deposit_terms` that defines
 * the container as returnable (e.g. for the VAT exclusion of art. 189.2 of the Tax Code of Ukraine).
 *
 * Optional feature: with `product.container_deposit_on` off, no new deposits are charged; returns
 * of deposits already on orders stay possible.
 *
 * A container return is recorded in `s_container_returns` and settled in cash or as a credit
 * toward an order. A return from its source order lowers that order's deposit and cost.
 *
 * @since 1.5.0
 */
final class ContainerDeposit
{
    public const EVENT = 'sCommerce.ResolveProductDeposit';
    /** Ledger provider of deposit refunds, transfers and credits. */
    public const PROVIDER = 'container_deposit';
    /** Event scope source of container returns. */
    public const SOURCE = 'manager_container_return';

    public function __construct(private readonly PaymentFlow $flow = new PaymentFlow())
    {
    }

    /**
     * Whether returnable container deposits are used (`product.container_deposit_on`).
     *
     * @since 1.5.0
     */
    public static function enabled(): bool
    {
        return (int)sCommerce::config('product.container_deposit_on', 0) === 1;
    }

    /**
     * Whether customers may declare empty containers they exchange (`product.container_exchange_on`).
     * The declaration waives the deposit before anyone has seen the containers, so it is off by default.
     *
     * @since 1.5.0
     */
    public static function exchangeEnabled(): bool
    {
        return self::enabled() && (int)sCommerce::config('product.container_exchange_on', 0) === 1;
    }

    /**
     * Deposit per unit of a product in `$currency`; the first non-negative numeric listener response wins.
     * Always 0 while the feature is off, and for products sold by weight, length, volume or count.
     *
     * @param object $product Product model or object; `deposit` is in the product currency.
     * @param int $optionId Cart option id; 0 = none.
     * @since 1.5.0
     */
    public static function unit(object $product, int $optionId = 0, ?string $currency = null): float
    {
        if (!self::enabled() || MeasuredQuantity::rules($product)->isMeasured()) {
            return 0.0;
        }
        $currency = $currency ?: sCommerce::currentCurrency();
        $base = (float)($product->deposit ?? 0);
        $unit = $base > 0 ? (float)sCommerce::convertPriceNumber($base, (string)($product->currency ?? $currency), $currency) : 0.0;

        foreach (Event::dispatch(self::EVENT, [['product' => $product, 'optionId' => $optionId, 'currency' => $currency, 'deposit' => $unit]]) as $override) {
            if (is_numeric($override) && (float)$override >= 0) {
                $unit = (float)$override;
                break;
            }
        }

        return round(max(0.0, $unit), 2);
    }

    /**
     * Deposit keys of an order line; empty without a deposit.
     *
     * @param float $unit Deposit per unit.
     * @param int $quantity Ordered quantity.
     * @param int $exchange Empty containers the customer exchanges (capped at the quantity).
     * @return array<string, mixed>
     * @since 1.5.0
     */
    public static function lineFields(float $unit, int $quantity, int $exchange = 0): array
    {
        if ($unit <= 0) {
            return [];
        }
        $exchange = max(0, min($exchange, $quantity));
        $fields = [
            'deposit_unit' => round($unit, 2),
            'exchange_quantity' => $exchange,
            'deposit_quantity' => $quantity - $exchange,
            'deposit_amount' => round($unit * ($quantity - $exchange), 2),
            'deposit_returnable' => true,
        ];
        $terms = trim((string)sCommerce::config('product.container_terms', ''));
        if ($terms !== '') {
            $fields['deposit_terms'] = mb_substr($terms, 0, 255);
        }

        return $fields;
    }

    /**
     * Recalculate the deposit of edited lines from their quantity and exchange.
     *
     * Returned containers stay returned: the exchange cannot drop the deposit below them.
     * Measured lines never carry a deposit and are skipped.
     *
     * @param array<int, mixed> $lines Order `products` snapshot.
     * @return array<int, mixed>
     * @since 1.5.0
     */
    public static function applyToLines(array $lines): array
    {
        foreach ($lines as $index => $line) {
            $unit = is_array($line) ? (float)($line['deposit_unit'] ?? 0) : 0.0;
            if ($unit <= 0 || isset($line['measure'])) {
                continue;
            }
            $quantity = max(0, (int)($line['quantity'] ?? 0));
            $returned = max(0, (int)($line['deposit_returned_quantity'] ?? 0));
            $exchange = max(0, min((int)($line['exchange_quantity'] ?? 0), $quantity - min($returned, $quantity)));
            $lines[$index]['exchange_quantity'] = $exchange;
            $lines[$index]['deposit_quantity'] = $quantity - $exchange;
            $lines[$index]['deposit_amount'] = round($unit * ($quantity - $exchange), 2);
        }

        return $lines;
    }

    /**
     * Sum of `deposit_amount` minus the deposits already returned from the lines.
     *
     * @param array<int, mixed> $lines
     * @since 1.5.0
     */
    public static function total(array $lines): float
    {
        $total = 0.0;
        foreach ($lines as $line) {
            if (is_array($line) && (float)($line['deposit_unit'] ?? 0) > 0) {
                $total += (float)($line['deposit_amount'] ?? 0) - (float)$line['deposit_unit'] * (int)($line['deposit_returned_quantity'] ?? 0);
            }
        }

        return round(max(0.0, $total), 2);
    }

    /**
     * Containers of a line whose deposit can still be returned.
     *
     * @param array<string, mixed> $line
     * @since 1.5.0
     */
    public static function returnable(array $line): int
    {
        if ((float)($line['deposit_unit'] ?? 0) <= 0) {
            return 0;
        }

        return max(0, (int)($line['deposit_quantity'] ?? 0) - (int)($line['deposit_returned_quantity'] ?? 0));
    }

    /**
     * Record returned containers and settle their deposit.
     *
     * - With `sourceOrder` and `lineIndex`: the line's deposit was paid on that order. Its cost is
     *   lowered and a refund (`container_deposit` provider) is recorded there.
     * - Without a source order (a standalone return): `unit` or the product deposit gives the amount.
     * - `settlement = credit`: the amount is recorded as a payment of `creditOrder`, capped at its
     *   balance due; `cash`: the money is paid out and the return row is the journal entry.
     *
     * The same `reference` returns the existing record, so a resubmitted form records once.
     *
     * @param array{reference: string, quantity: int, settlement: string, sourceOrder?: ?sOrder, lineIndex?: ?int,
     *              product?: ?object, unit?: ?float, currency?: ?string, title?: string, creditOrder?: ?sOrder,
     *              userId?: int, customer?: array<string, string>, note?: string} $input
     * @throws PaymentActionException When the return is not allowed; the message key explains why.
     * @since 1.5.0
     */
    public function returnContainers(array $input, int $actorId): sContainerReturn
    {
        $reference = mb_substr(trim((string)$input['reference']), 0, 191);
        $quantity = (int)$input['quantity'];
        $settlement = (string)$input['settlement'];
        if ($reference === '' || $quantity < 1 || !in_array($settlement, [sContainerReturn::CASH, sContainerReturn::CREDIT], true)) {
            throw new PaymentActionException('container_return_invalid', 422);
        }
        if (($existing = sContainerReturn::query()->where('reference', $reference)->first()) !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($input, $reference, $quantity, $settlement, $actorId): sContainerReturn {
            $source = $input['sourceOrder'] ?? null;
            $credit = $settlement === sContainerReturn::CREDIT ? ($input['creditOrder'] ?? null) : null;
            if ($settlement === sContainerReturn::CREDIT && !$credit) {
                throw new PaymentActionException('container_return_invalid', 422);
            }
            if (($source || $credit) && !$this->flow->ledgerAvailable()) {
                throw new PaymentActionException('payment_action_ineligible');
            }
            $row = ['reference' => $reference, 'quantity' => $quantity, 'settlement' => $settlement, 'actor_id' => $actorId,
                'note' => mb_substr(trim((string)($input['note'] ?? '')), 0, 255)];

            if ($source) {
                /** @var sOrder $source */
                $source = sOrder::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
                $lines = is_array($source->products) ? $source->products : [];
                $index = $input['lineIndex'] ?? null;
                $line = is_int($index) && is_array($lines[$index] ?? null) ? $lines[$index] : null;
                if (!$line || self::returnable($line) < $quantity) {
                    throw new PaymentActionException('container_return_exceeds', 422);
                }
                $unit = (float)$line['deposit_unit'];
                $amount = round($unit * $quantity, 2);
                if ($this->flow->summary($source)->netMinor() < PaymentFlow::toMinor($amount)) {
                    throw new PaymentActionException('container_return_unpaid', 422);
                }
                $row += ['source_order_id' => $source->id, 'line_key' => isset($line['line_key']) ? (string)$line['line_key'] : null,
                    'product_id' => (int)($line['product_id'] ?? $line['id'] ?? 0) ?: null, 'title' => mb_substr((string)($line['title'] ?? ''), 0, 255),
                    'currency' => (string)$source->currency, 'user_id' => (int)$source->user_id,
                    'customer' => array_intersect_key(is_array($source->user_info) ? $source->user_info : [], array_flip(['first_name', 'last_name', 'phone', 'email'])),
                    'terms' => (string)($line['deposit_terms'] ?? sCommerce::config('product.container_terms', ''))];

                $lines[$index]['deposit_returned_quantity'] = (int)($line['deposit_returned_quantity'] ?? 0) + $quantity;
                $history = is_array($source->history) ? $source->history : [];
                $history[] = ['containers_returned' => $quantity, 'line_key' => $row['line_key'], 'amount' => $amount, 'settlement' => $settlement,
                    'timestamp' => now()->toDateTimeString(), 'user_id' => $actorId];
                $source->products = $lines;
                $source->history = $history;
                $source->cost = max(0.0, round((float)$source->cost - $amount, 2));
                $source->save();
                $this->flow->record($source, new LedgerEntry('refund', 'refunded', $amount, (string)$source->currency, self::PROVIDER,
                    "container-return/$reference", null, ['source' => 'container_return', 'settlement' => $settlement, 'containers' => $quantity, 'line_key' => $row['line_key']]));
            } else {
                $product = $input['product'] ?? null;
                $currency = $credit ? (string)$credit->currency : trim((string)($input['currency'] ?? ''));
                $currency = strtoupper($currency !== '' ? $currency : (string)sCommerce::config('basic.main_currency', 'USD'));
                $unit = isset($input['unit']) && is_numeric($input['unit']) && (float)$input['unit'] > 0
                    ? round((float)$input['unit'], 2) : ($product ? self::unit($product, 0, $currency) : 0.0);
                if ($unit <= 0) {
                    throw new PaymentActionException('container_return_invalid', 422);
                }
                $amount = round($unit * $quantity, 2);
                $row += ['product_id' => $product ? ((int)($product->id ?? 0) ?: null) : null,
                    'title' => mb_substr(trim((string)($input['title'] ?? '')) ?: (string)($product->title ?? $product->pagetitle ?? ''), 0, 255),
                    'currency' => $currency, 'user_id' => max(0, (int)($input['userId'] ?? 0)),
                    'customer' => array_filter(array_map(static fn ($value) => mb_substr(trim((string)$value), 0, 255), (array)($input['customer'] ?? []))) ?: null,
                    'terms' => (string)sCommerce::config('product.container_terms', '')];
            }

            if ($credit) {
                /** @var sOrder $credit */
                $credit = sOrder::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();
                if (strtoupper((string)$credit->currency) !== $row['currency']
                    || $this->flow->summary($credit)->outstandingMinor() < PaymentFlow::toMinor($amount)) {
                    throw new PaymentActionException('container_credit_exceeds', 422);
                }
                $this->flow->record($credit, new LedgerEntry('manual', 'captured', $amount, $row['currency'], self::PROVIDER,
                    "container-credit/$reference", null, ['source' => 'container_return', 'containers' => $quantity, 'source_order_id' => $row['source_order_id'] ?? null]));
                $row['credit_order_id'] = $credit->id;
            }

            $row['unit_amount'] = $unit;
            $row['amount'] = $amount;
            $row['terms'] = mb_substr(trim($row['terms']), 0, 255);

            return sContainerReturn::query()->create($row);
        });
    }
}
