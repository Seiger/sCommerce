<?php namespace Seiger\sCommerce\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Interfaces\CancelablePaymentMethodInterface;
use Seiger\sCommerce\Interfaces\CapturablePaymentMethodInterface;
use Seiger\sCommerce\Interfaces\DelegatedPaymentOperationsInterface;
use Seiger\sCommerce\Interfaces\OfflineSettlementPaymentMethodInterface;
use Seiger\sCommerce\Interfaces\PaymentMethodInterface;
use Seiger\sCommerce\Interfaces\PaymentOperationLookupInterface;
use Seiger\sCommerce\Interfaces\RefundablePaymentMethodInterface;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Models\sPaymentOperation;
use Seiger\sCommerce\Payment\LedgerEntry;
use Seiger\sCommerce\Payment\PaymentActionException;
use Seiger\sCommerce\Payment\PaymentFlow;
use Seiger\sCommerce\Payment\PaymentMethodResolver;
use Seiger\sCommerce\Payment\PaymentOperationRequest;
use Seiger\sCommerce\Payment\PaymentOperationResult;
use Seiger\sCommerce\Payment\PaymentSchedule;
use Seiger\sCommerce\Payment\PaymentSummary;
use Throwable;

/**
 * Runs manager payment operations (capture, refund, cancel) through the payment method, and
 * records money an offline method received or returned (`receive`, `return`).
 *
 * The operation is persisted in `s_payment_operations` and its amount reserved under the
 * order lock before the provider is called; no database lock is held during provider I/O.
 * A repeated form nonce returns the stored operation instead of calling the provider again.
 * Results are recorded only through {@see PaymentFlow}. An uncertain provider result stays
 * `unknown` until the method can look it up ({@see PaymentOperationLookupInterface}).
 *
 * @since 1.5.0
 */
final class OrderPaymentAction
{
    /** @var list<string> Actions on one ledger row. */
    public const ACTIONS = ['capture', 'refund', 'cancel'];

    /** @var list<string> Order-level actions of offline methods: record received or returned money. */
    public const OFFLINE_ACTIONS = ['receive', 'return'];

    /** Order-level action: return containers of the order and settle their deposit, see {@see ContainerDeposit}. */
    public const CONTAINER_ACTION = 'containers';

    /** Manager permission per action, in addition to `exec_module`. */
    public const PERMISSIONS = [
        'capture' => 'scommerce_payment_capture',
        'refund' => 'scommerce_payment_refund',
        'cancel' => 'scommerce_payment_cancel',
        'receive' => 'scommerce_payment_record',
        'return' => 'scommerce_payment_record',
        'containers' => 'scommerce_payment_record',
        'container_return' => 'scommerce_payment_record',
    ];

    /** Session key of issued form nonces. */
    public const NONCE_SESSION_KEY = 'scommerce_payment_nonces';

    /** Seconds after which an `in_flight` operation counts as interrupted. */
    public const IN_FLIGHT_TIMEOUT = 600;

    private const MAX_NONCES = 200;
    private const PAYMENT_KINDS = ['deposit', 'final', 'manual'];

    public function __construct(private readonly PaymentFlow $flow = new PaymentFlow())
    {
    }

    /**
     * Whether a payment method can run an action.
     *
     * @since 1.5.0
     */
    public static function supports(PaymentMethodInterface $method, string $action): bool
    {
        if (in_array($action, self::OFFLINE_ACTIONS, true)) {
            return $method instanceof OfflineSettlementPaymentMethodInterface;
        }
        if ($method instanceof DelegatedPaymentOperationsInterface) {
            return in_array($action, self::ACTIONS, true) && $method->supportsPaymentOperation($action);
        }

        return match ($action) {
            'capture' => $method instanceof CapturablePaymentMethodInterface,
            'refund' => $method instanceof RefundablePaymentMethodInterface,
            'cancel' => $method instanceof CancelablePaymentMethodInterface,
            default => false,
        };
    }

    /**
     * Whether a capture may take an amount below the authorized one.
     *
     * @since 1.5.0
     */
    public static function partialCapture(PaymentMethodInterface $method): bool
    {
        return $method instanceof DelegatedPaymentOperationsInterface
            || ($method instanceof CapturablePaymentMethodInterface && $method->supportsPartialCapture());
    }

    /**
     * Ledger rows of an order with the actions the current manager may run on each, the
     * order-level offline actions and the order totals.
     *
     * Issues a form nonce per offered action. `capturable` is the suggested capture amount
     * (the authorization capped at the balance due); `partialCapture` whether it may be lowered.
     * `containerLines` lists the lines whose container deposit can be returned (index => label).
     *
     * @return array{rows: list<array{payment: sOrderPayment, parentProviderRef: ?string, refundable: ?string, capturable: ?string, actions: array<string, string>}>,
     *               operations: list<sPaymentOperation>, orderActions: array<string, string>, partialCapture: bool,
     *               totals: ?array{total: string, paid: string, due: string, overpaid: string, returnable: string, currency: string}}
     * @since 1.5.0
     */
    public function panel(sOrder $order): array
    {
        $empty = ['rows' => [], 'operations' => [], 'orderActions' => [], 'partialCapture' => false, 'totals' => null, 'containerLines' => []];
        if (!$order->exists || !$this->flow->ledgerAvailable()) {
            return $empty;
        }

        $payments = sOrderPayment::query()->where('order_id', $order->id)->orderBy('sequence')->orderBy('id')->get();
        $operations = $this->operations($order);
        $method = PaymentMethodResolver::forOrder($order);
        $summary = $this->flow->summary($order);
        $orderActions = [];
        foreach (self::OFFLINE_ACTIONS as $action) {
            if ($method && self::supports($method, $action) && self::permitted($action)
                && $this->offlineLimitMinor($action, $method, $summary, $payments) > 0) {
                $orderActions[$action] = self::issueNonce((int)$order->id, 0, $action);
            }
        }
        $containerLines = [];
        foreach (is_array($order->products) ? $order->products : [] as $index => $line) {
            if (is_array($line) && ($returnable = ContainerDeposit::returnable($line)) > 0) {
                $containerLines[$index] = trim((string)($line['title'] ?? '')) . ' (' . $returnable . ' × ' . number_format((float)$line['deposit_unit'], 2, '.', '') . ')';
            }
        }
        if ($containerLines !== [] && $payments->isNotEmpty() && self::permitted(self::CONTAINER_ACTION)) {
            $orderActions[self::CONTAINER_ACTION] = self::issueNonce((int)$order->id, 0, self::CONTAINER_ACTION);
        }
        if ($payments->isEmpty() && $orderActions === []) {
            return $empty;
        }

        $rows = [];
        foreach ($payments as $payment) {
            $actions = [];
            foreach (self::ACTIONS as $action) {
                if ($method && self::supports($method, $action) && self::permitted($action)
                    && $this->eligible($action, $order, $payment, $payments, $operations)) {
                    $actions[$action] = self::issueNonce((int)$order->id, (int)$payment->id, $action);
                }
            }
            $rows[] = [
                'payment' => $payment,
                'parentProviderRef' => self::parentRef($payment),
                'refundable' => $payment->kind === 'refund' ? null : PaymentSummary::decimal($this->refundableMinor($payment, $payments, $operations)),
                'capturable' => isset($actions['capture'])
                    ? PaymentSummary::decimal(min(PaymentFlow::toMinor($payment->getAttributes()['amount'] ?? 0), max(1, $summary->outstandingMinor())))
                    : null,
                'actions' => $actions,
            ];
        }

        return [
            'rows' => $rows,
            'operations' => $operations->filter(static fn (sPaymentOperation $operation) => in_array($operation->state, sPaymentOperation::ACTIVE_STATES, true))->values()->all(),
            'orderActions' => $orderActions,
            'partialCapture' => $method !== null && self::partialCapture($method),
            'containerLines' => isset($orderActions[self::CONTAINER_ACTION]) ? $containerLines : [],
            'totals' => [
                'total' => PaymentSummary::decimal($summary->totalMinor),
                'paid' => PaymentSummary::decimal($summary->netMinor()),
                'due' => PaymentSummary::decimal($summary->outstandingMinor()),
                'overpaid' => PaymentSummary::decimal($summary->overpaidMinor()),
                'returnable' => PaymentSummary::decimal($method ? $this->offlineLimitMinor('return', $method, $summary, $payments) : 0),
                'currency' => $summary->currency,
            ],
        ];
    }

    /**
     * Whether the current manager holds the permissions of an action.
     *
     * @since 1.5.0
     */
    public static function permitted(string $action): bool
    {
        return isset(self::PERMISSIONS[$action]) && evo()->hasPermission('exec_module') && evo()->hasPermission(self::PERMISSIONS[$action]);
    }

    /**
     * Issue a form nonce bound to one order, payment and action.
     *
     * @since 1.5.0
     */
    public static function issueNonce(int $orderId, int $paymentId, string $action): string
    {
        $nonce = bin2hex(random_bytes(16));
        $nonces = isset($_SESSION[self::NONCE_SESSION_KEY]) && is_array($_SESSION[self::NONCE_SESSION_KEY]) ? $_SESSION[self::NONCE_SESSION_KEY] : [];
        $nonces[$nonce] = "$action/$orderId/$paymentId";
        $_SESSION[self::NONCE_SESSION_KEY] = array_slice($nonces, -self::MAX_NONCES, null, true);

        return $nonce;
    }

    /**
     * Whether a nonce was issued in this session for the order, payment and action.
     *
     * Nonces stay valid after use, so a resubmitted form returns the original operation.
     *
     * @since 1.5.0
     */
    public static function nonceMatches(string $nonce, int $orderId, int $paymentId, string $action): bool
    {
        $bound = $_SESSION[self::NONCE_SESSION_KEY][$nonce] ?? null;

        return is_string($bound) && hash_equals($bound, "$action/$orderId/$paymentId");
    }

    /**
     * Run an action, or return the stored result of an earlier request with the same nonce.
     *
     * @param string $action `capture`, `refund` or `cancel`.
     * @param sOrder $order Order of the payment.
     * @param sOrderPayment $payment Ledger row to act on.
     * @param string $nonce Form nonce from {@see self::issueNonce()}.
     * @param float|null $amount Refund amount; capture amount (null captures the authorized amount); ignored for cancel.
     * @param string $reason Manager-entered reason.
     * @param int $actorId Manager user id.
     * @return array{state: string, message: string, operation: ?sPaymentOperation, payment: ?sOrderPayment}
     *               `state` is the operation state; `message` a safe provider message or empty.
     * @throws PaymentActionException When the action is refused before the provider call.
     * @since 1.5.0
     */
    public function run(string $action, sOrder $order, sOrderPayment $payment, string $nonce, ?float $amount, string $reason, int $actorId): array
    {
        if (!in_array($action, self::ACTIONS, true) || (int)$payment->order_id !== (int)$order->id || !$this->flow->ledgerAvailable()) {
            throw new PaymentActionException('payment_action_ineligible');
        }
        $method = PaymentMethodResolver::forOrder($order);
        if (!$method || !self::supports($method, $action)) {
            throw new PaymentActionException('payment_action_ineligible');
        }

        $amountMinor = match ($action) {
            'refund' => PaymentFlow::toMinor($amount ?? 0),
            'capture' => $amount === null ? null : PaymentFlow::toMinor($amount),
            default => null,
        };
        if ($amountMinor !== null && ($amountMinor <= 0 || !is_finite((float)$amount))) {
            throw new PaymentActionException('payment_action_ineligible');
        }
        $reason = mb_substr(trim($reason), 0, 255);
        $currency = strtoupper(trim((string)$order->currency));
        $key = "$action/{$order->id}/{$payment->id}/$nonce";
        $hash = hash('sha256', json_encode([$action, (int)$order->id, (int)$payment->id, $amountMinor, $currency, $reason]));

        if ($method instanceof DelegatedPaymentOperationsInterface) {
            return $this->delegate($method, new PaymentOperationRequest($action, $order, $payment,
                $amountMinor === null ? null : $amountMinor / 100, $currency, $reason, $key, $actorId));
        }

        [$operation, $mode] = DB::transaction(fn () => $this->claim($method, $order, $payment, $key, $hash, $amountMinor, $currency, $reason, $actorId));
        if ($mode === 'done') {
            return $this->outcome($operation);
        }

        $request = $this->request($operation, $order);
        if ($mode === 'recover') {
            if (!$method instanceof PaymentOperationLookupInterface) {
                return $this->outcome($operation);
            }
            try {
                $result = $method->findPaymentOperation($request);
            } catch (Throwable $exception) {
                $this->log('sCommerce payment operation lookup failed', $operation, $exception);
                return $this->outcome($operation);
            }
            // Null means the provider never received the request: resend it with the same key.
            $result ??= $this->call($method, $operation, $request);
        } else {
            $result = $this->call($method, $operation, $request);
        }

        return $result === null ? $this->outcome($operation) : $this->apply($operation, $order, $result);
    }

    /**
     * Record money an offline method received (`receive`) or returned to the customer (`return`).
     *
     * The ledger reference derives from the form nonce, so a resubmitted form records once and
     * the same nonce with another amount is refused. A receipt is capped at the balance due; it
     * is a `deposit` while it fits the open deposit installment, otherwise `final`. A return is
     * capped at the money this method received and is recorded as a refund without a parent.
     *
     * @param string $action `receive` or `return`.
     * @param string $nonce Form nonce from {@see self::issueNonce()} with payment id 0.
     * @param string $note Manager note, e.g. a receipt number.
     * @return array{state: string, message: string, operation: null, payment: sOrderPayment}
     * @throws PaymentActionException When the amount or action is not allowed.
     * @since 1.5.0
     */
    public function recordOffline(string $action, sOrder $order, string $nonce, float $amount, string $note, int $actorId): array
    {
        $method = in_array($action, self::OFFLINE_ACTIONS, true) && $this->flow->ledgerAvailable() ? PaymentMethodResolver::forOrder($order) : null;
        $amountMinor = is_finite($amount) ? PaymentFlow::toMinor($amount) : 0;
        if (!$method || !self::supports($method, $action) || $amountMinor <= 0) {
            throw new PaymentActionException('payment_action_ineligible');
        }
        $provider = $method->getName();
        $reference = "$action/{$order->id}/" . substr(hash('sha256', $nonce), 0, 24);
        $note = mb_substr(trim($note), 0, 255);

        $payment = DB::transaction(function () use ($action, $order, $method, $amountMinor, $provider, $reference, $note): sOrderPayment {
            $this->lockOrder($order);
            $existing = $this->flow->findByProviderRef($provider, $reference);
            if ($existing) {
                if (PaymentFlow::toMinor($existing->getAttributes()['amount'] ?? 0) !== $amountMinor) {
                    throw new PaymentActionException('payment_action_conflict', 409);
                }
                return $existing;
            }

            $payments = sOrderPayment::query()->where('order_id', $order->id)->orderBy('sequence')->orderBy('id')->lockForUpdate()->get();
            $summary = $this->flow->summary($order);
            if ($amountMinor > $this->offlineLimitMinor($action, $method, $summary, $payments)) {
                throw new PaymentActionException('payment_action_ineligible');
            }
            $metadata = $note === '' ? [] : ['note' => $note];
            if ($action === 'return') {
                $entry = new LedgerEntry('refund', 'refunded', $amountMinor / 100, $summary->currency, $provider, $reference, null, $metadata);
            } else {
                $next = PaymentSchedule::nextPayment($order, $summary);
                $kind = $next && $next['kind'] === 'deposit' && $amountMinor <= $next['amountMinor'] ? 'deposit' : 'final';
                $entry = new LedgerEntry($kind, 'captured', $amountMinor / 100, $summary->currency, $provider, $reference, null, $metadata);
            }

            return $this->flow->record($order, $entry)->payment;
        });

        return ['state' => sPaymentOperation::STATE_SUCCEEDED, 'message' => '', 'operation' => null, 'payment' => $payment];
    }

    /**
     * Resolve interrupted and pending operations: sync them from the ledger, or look them up
     * at the provider. Called by the payment reconciliation worker; never resends a request.
     *
     * @return int Number of operations that reached a final state.
     * @since 1.5.0
     */
    public function recover(int $limit = 100): int
    {
        if (!$this->flow->ledgerAvailable()) {
            return 0;
        }

        $resolved = 0;
        $operations = sPaymentOperation::query()->whereIn('state', sPaymentOperation::ACTIVE_STATES)->orderBy('id')->limit($limit)->get();
        foreach ($operations as $operation) {
            try {
                if ($operation->state === sPaymentOperation::STATE_IN_FLIGHT && !$this->stale($operation)) {
                    continue;
                }
                if ($this->syncFromLedger($operation)) {
                    $resolved++;
                    continue;
                }
                $order = sOrder::withTrashed()->find($operation->order_id);
                $method = $order ? PaymentMethodResolver::forOrder($order) : null;
                if (!$method instanceof PaymentOperationLookupInterface || $method instanceof DelegatedPaymentOperationsInterface) {
                    continue;
                }
                $result = $method->findPaymentOperation($this->request($operation, $order));
                if ($result !== null && in_array($this->apply($operation, $order, $result)['state'], [sPaymentOperation::STATE_SUCCEEDED, sPaymentOperation::STATE_FAILED], true)) {
                    $resolved++;
                }
            } catch (Throwable $exception) {
                $this->log('sCommerce payment operation recovery failed', $operation, $exception);
            }
        }

        return $resolved;
    }

    /**
     * Find or create the operation under the order lock; decide whether to call the provider.
     *
     * @return array{0: sPaymentOperation, 1: 'call'|'recover'|'done'}
     */
    private function claim(PaymentMethodInterface $method, sOrder $order, sOrderPayment $payment, string $key, string $hash, ?int $amountMinor, string $currency, string $reason, int $actorId): array
    {
        $this->lockOrder($order);
        /** @var sPaymentOperation|null $operation */
        $operation = sPaymentOperation::query()->where('operation_key', $key)->lockForUpdate()->first();
        if ($operation) {
            if (!hash_equals((string)$operation->request_hash, $hash)) {
                throw new PaymentActionException('payment_action_conflict', 409);
            }
            if ($operation->state === sPaymentOperation::STATE_IN_FLIGHT && !$this->stale($operation)) {
                throw new PaymentActionException('payment_action_in_progress', 409);
            }
            if (in_array($operation->state, [sPaymentOperation::STATE_IN_FLIGHT, sPaymentOperation::STATE_UNKNOWN], true)) {
                $operation->state = sPaymentOperation::STATE_UNKNOWN;
                $operation->save();
                return [$operation, 'recover'];
            }
            return [$operation, 'done'];
        }

        $payments = sOrderPayment::query()->where('order_id', $order->id)->orderBy('sequence')->orderBy('id')->lockForUpdate()->get();
        $operations = $this->operations($order, true);
        $current = $payments->firstWhere('id', $payment->id);
        if (!$current || !$this->eligible($this->actionOf($key), $order, $current, $payments, $operations, $amountMinor)) {
            throw new PaymentActionException('payment_action_ineligible');
        }
        $action = $this->actionOf($key);
        if ($action === 'refund' && $method instanceof RefundablePaymentMethodInterface && !$method->supportsPartialRefund()
            && $amountMinor !== $this->refundableMinor($current, $payments, $operations)) {
            throw new PaymentActionException('payment_action_ineligible');
        }
        if ($action === 'capture' && $amountMinor !== null && !self::partialCapture($method)
            && $amountMinor !== PaymentFlow::toMinor($current->getAttributes()['amount'] ?? 0)) {
            throw new PaymentActionException('payment_action_ineligible');
        }

        $scope = EventScope::current();
        $operation = new sPaymentOperation();
        $operation->forceFill([
            'operation_key' => $key,
            'order_id' => $order->id,
            'payment_id' => $current->id,
            'action' => $action,
            'amount' => $amountMinor === null ? null : PaymentSummary::decimal($amountMinor),
            'currency' => $currency,
            'reason' => $reason === '' ? null : $reason,
            'request_hash' => $hash,
            'state' => sPaymentOperation::STATE_IN_FLIGHT,
            'attempts' => 1,
            'actor_id' => $actorId,
            'context' => array_filter([
                'source' => $scope['source'],
                'correlation_id' => $scope['correlationId'],
                'causation_id' => $scope['causationId'],
                'integration_actor' => $scope['integrationActor'],
            ], static fn ($value) => $value !== null),
        ])->save();

        return [$operation, 'call'];
    }

    /**
     * Call the provider outside any transaction. A thrown error leaves the result unknown.
     */
    private function call(PaymentMethodInterface $method, sPaymentOperation $operation, PaymentOperationRequest $request): ?PaymentOperationResult
    {
        if ($operation->state === sPaymentOperation::STATE_UNKNOWN) {
            $operation->attempts = (int)$operation->attempts + 1;
            $operation->save();
        }

        try {
            return match ($request->action) {
                'capture' => $method->capture($request->order, $request->payment, $request->amount, $request->idempotencyKey),
                'refund' => $method->refund($request->order, $request->payment, (float)$request->amount, $request->reason, $request->idempotencyKey),
                'cancel' => $method->cancel($request->order, $request->payment, $request->reason, $request->idempotencyKey),
            };
        } catch (Throwable $exception) {
            $this->markUnknown($operation, $exception);
            return null;
        }
    }

    /**
     * Record a provider result in the ledger and the operation, under the order lock.
     *
     * @return array{state: string, message: string, operation: ?sPaymentOperation, payment: ?sOrderPayment}
     */
    private function apply(sPaymentOperation $operation, sOrder $order, PaymentOperationResult $result): array
    {
        try {
            $causation = substr((string)$operation->operation_key, 0, EventScope::MAX_ID_LENGTH);
            EventScope::run(EventScope::current()['source'], (int)$operation->actor_id, function () use ($operation, $order, $result): void {
                DB::transaction(function () use ($operation, $order, $result): void {
                    $this->lockOrder($order);
                    $operation->refresh();
                    if (in_array($operation->state, [sPaymentOperation::STATE_SUCCEEDED, sPaymentOperation::STATE_FAILED], true)) {
                        return;
                    }
                    if (!in_array($result->outcome, [PaymentOperationResult::SUCCEEDED, PaymentOperationResult::PENDING, PaymentOperationResult::FAILED], true)) {
                        throw new \UnexpectedValueException("Unknown payment operation outcome '{$result->outcome}'.");
                    }

                    $ledger = $this->record($operation, $order, $result);
                    $reference = trim((string)$result->providerRef);
                    $operation->forceFill([
                        'state' => $result->outcome,
                        'provider_ref' => $reference !== '' ? $reference : $operation->provider_ref,
                        'ledger_payment_id' => $ledger?->id ?? $operation->ledger_payment_id,
                        'result' => ['outcome' => $result->outcome, 'message' => $result->message, 'metadata' => $result->metadata],
                        'error' => null,
                    ])->save();
                });
            }, null, $causation);
        } catch (Throwable $exception) {
            // The provider may have moved money: never call it again blindly.
            $this->markUnknown($operation, $exception);
        }

        return $this->outcome($operation->refresh());
    }

    /**
     * Write the ledger effect of a provider result.
     */
    private function record(sPaymentOperation $operation, sOrder $order, PaymentOperationResult $result): ?sOrderPayment
    {
        /** @var sOrderPayment $payment */
        $payment = sOrderPayment::query()->whereKey($operation->payment_id)->firstOrFail();
        $reference = trim((string)$result->providerRef);
        $metadata = array_filter(['operation_key' => $operation->operation_key, 'reason' => $operation->reason], static fn ($value) => $value !== null)
            + $result->metadata;
        $paymentRef = (string)$payment->provider_ref;

        if ($operation->action === 'refund') {
            if ($result->outcome === PaymentOperationResult::FAILED || ($result->outcome === PaymentOperationResult::PENDING && $reference === '')) {
                return null;
            }
            if ($reference === '') {
                throw new \UnexpectedValueException('A succeeded refund must return the provider refund reference.');
            }
            $status = $result->outcome === PaymentOperationResult::SUCCEEDED ? 'refunded' : 'pending';

            return $this->flow->record($order, new LedgerEntry('refund', $status, PaymentFlow::toMinor($operation->getAttributes()['amount']) / 100,
                (string)$operation->currency, (string)$payment->provider, $reference, $paymentRef, $metadata))->payment;
        }

        if ($result->outcome !== PaymentOperationResult::SUCCEEDED) {
            return null;
        }
        if ($operation->action === 'capture') {
            // A partial capture lowers the row amount to the captured one.
            $capturedMinor = PaymentFlow::toMinor($operation->getAttributes()['amount'] ?? $payment->getAttributes()['amount']);
            return $this->flow->record($order, new LedgerEntry((string)$payment->kind, 'captured', $capturedMinor / 100,
                (string)$payment->currency, (string)$payment->provider, $paymentRef, null, $metadata + ($reference !== '' ? ['capture_ref' => $reference] : [])))->payment;
        }

        return $this->flow->recordCanceled($order, (string)$payment->provider, $paymentRef, $metadata)->payment;
    }

    /**
     * Hand the request to a method with its own operation store.
     *
     * @return array{state: string, message: string, operation: ?sPaymentOperation, payment: ?sOrderPayment}
     */
    private function delegate(DelegatedPaymentOperationsInterface $method, PaymentOperationRequest $request): array
    {
        $payment = $request->payment;
        if (!in_array((string)$payment->kind, self::PAYMENT_KINDS, true) || trim((string)$payment->provider_ref) === ''
            || strtoupper((string)$payment->currency) !== $request->currency) {
            throw new PaymentActionException('payment_action_ineligible');
        }

        try {
            $result = $method->runPaymentOperation($request);
        } catch (Throwable $exception) {
            Log::channel('scommerce')->error('sCommerce delegated payment operation failed', [
                'operation_key' => $request->idempotencyKey,
                'error' => $exception->getMessage(),
            ]);
            return ['state' => sPaymentOperation::STATE_UNKNOWN, 'message' => '', 'operation' => null, 'payment' => null];
        }
        $state = in_array($result->outcome, [PaymentOperationResult::SUCCEEDED, PaymentOperationResult::PENDING, PaymentOperationResult::FAILED], true)
            ? $result->outcome : sPaymentOperation::STATE_UNKNOWN;

        return ['state' => $state, 'message' => $result->message, 'operation' => null, 'payment' => null];
    }

    /**
     * Whether an action is allowed on a ledger row given the other rows and operations of the order.
     *
     * @param Collection<int, sOrderPayment> $payments
     * @param Collection<int, sPaymentOperation> $operations
     */
    private function eligible(string $action, sOrder $order, sOrderPayment $payment, Collection $payments, Collection $operations, ?int $amountMinor = null): bool
    {
        if ((int)$payment->order_id !== (int)$order->id || !in_array((string)$payment->kind, self::PAYMENT_KINDS, true)
            || trim((string)$payment->provider_ref) === '' || strtoupper((string)$payment->currency) !== strtoupper(trim((string)$order->currency))) {
            return false;
        }
        $busy = $operations->contains(static fn (sPaymentOperation $operation) => (int)$operation->payment_id === (int)$payment->id
            && $operation->action !== 'refund' && in_array($operation->state, sPaymentOperation::ACTIVE_STATES, true));

        return match ($action) {
            'capture' => !$busy && $payment->status === 'authorized'
                && ($amountMinor === null || $amountMinor <= PaymentFlow::toMinor($payment->getAttributes()['amount'] ?? 0)),
            'cancel' => !$busy && in_array($payment->status, ['pending', 'authorized'], true),
            'refund' => in_array($payment->status, ['captured', 'partially_refunded'], true)
                && ($refundable = $this->refundableMinor($payment, $payments, $operations)) > 0
                && ($amountMinor === null || $amountMinor <= $refundable),
            default => false,
        };
    }

    /**
     * Captured amount of a payment minus returned, pending and reserved refunds, in minor units.
     *
     * @param Collection<int, sOrderPayment> $payments
     * @param Collection<int, sPaymentOperation> $operations
     */
    private function refundableMinor(sOrderPayment $payment, Collection $payments, Collection $operations): int
    {
        if (!in_array($payment->status, ['captured', 'partially_refunded'], true)) {
            return 0;
        }
        $available = PaymentFlow::toMinor($payment->getAttributes()['amount'] ?? 0);
        if ($payment->status === 'partially_refunded') {
            $metadata = PaymentFlow::normalizeMetadata($payment->getAttributes()['metadata'] ?? null);
            $returned = $metadata[PaymentFlow::CONTEXT_KEY]['refunded_amount'] ?? $metadata['refunded_amount'] ?? null;
            if (!is_numeric($returned)) {
                return 0;
            }
            $available -= PaymentFlow::toMinor((string)$returned);
        }

        foreach ($payments as $row) {
            if ($row->kind === 'refund' && $row->provider === $payment->provider && self::parentRef($row) === $payment->provider_ref
                && in_array($row->status, ['pending', 'refunded', 'partially_refunded'], true)) {
                $available -= PaymentFlow::toMinor($row->getAttributes()['amount'] ?? 0);
            }
        }
        foreach ($operations as $operation) {
            // Operations with a ledger row are already counted above.
            if ($operation->action === 'refund' && (int)$operation->payment_id === (int)$payment->id && $operation->ledger_payment_id === null
                && in_array($operation->state, sPaymentOperation::ACTIVE_STATES, true)) {
                $available -= PaymentFlow::toMinor($operation->getAttributes()['amount'] ?? 0);
            }
        }

        return max(0, $available);
    }

    /**
     * Largest amount an offline action may record, in minor units: for a receipt the balance due
     * less payments in progress; for a return what this method received less what it returned,
     * capped at the net money received for the order.
     *
     * @param Collection<int, sOrderPayment> $payments
     */
    private function offlineLimitMinor(string $action, PaymentMethodInterface $method, PaymentSummary $summary, Collection $payments): int
    {
        if ($action === 'receive') {
            return max(0, $summary->outstandingMinor() - $summary->authorizedMinor - $summary->pendingMinor);
        }

        $provider = strtolower(trim($method->getName()));
        $currency = $summary->currency;
        $available = 0;
        foreach ($payments as $row) {
            if (strtolower((string)$row->provider) !== $provider || strtoupper((string)$row->currency) !== $currency) {
                continue;
            }
            $amount = PaymentFlow::toMinor($row->getAttributes()['amount'] ?? 0);
            if (in_array((string)$row->kind, self::PAYMENT_KINDS, true) && in_array((string)$row->status, ['captured', 'disputed'], true)) {
                // Legacy rows marked refunded as a whole are not counted as returnable.
                $available += $amount;
            } elseif ($row->kind === 'refund' && in_array((string)$row->status, ['pending', 'refunded', 'partially_refunded'], true)) {
                $available -= $amount;
            }
        }

        return max(0, min($available, $summary->netMinor()));
    }

    /**
     * Mark a pending operation final once its ledger row is final.
     */
    private function syncFromLedger(sPaymentOperation $operation): bool
    {
        // A refund owns its ledger row; capture and cancel change the original payment row.
        $rowId = $operation->action === 'refund' ? $operation->ledger_payment_id : $operation->payment_id;
        if ($rowId === null) {
            return false;
        }
        $status = (string)sOrderPayment::query()->whereKey($rowId)->value('status');
        $succeeded = match ($operation->action) {
            'refund' => ['refunded', 'partially_refunded'],
            'capture' => ['captured', 'disputed', 'refunded', 'partially_refunded'],
            default => ['canceled'],
        };
        $state = match (true) {
            in_array($status, $succeeded, true) => sPaymentOperation::STATE_SUCCEEDED,
            in_array($status, ['failed', 'rejected', 'expired', 'canceled'], true) => sPaymentOperation::STATE_FAILED,
            default => null,
        };
        if ($state === null) {
            return false;
        }
        $operation->state = $state;
        $operation->save();

        return true;
    }

    /**
     * @return Collection<int, sPaymentOperation>
     */
    private function operations(sOrder $order, bool $lock = false): Collection
    {
        $query = sPaymentOperation::query()->where('order_id', $order->id)->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->get();
    }

    private function request(sPaymentOperation $operation, sOrder $order): PaymentOperationRequest
    {
        $amount = $operation->getAttributes()['amount'] ?? null;

        return new PaymentOperationRequest(
            (string)$operation->action,
            $order,
            sOrderPayment::query()->whereKey($operation->payment_id)->firstOrFail(),
            $amount === null ? null : PaymentFlow::toMinor($amount) / 100,
            (string)$operation->currency,
            (string)($operation->reason ?? ''),
            (string)$operation->operation_key,
            (int)$operation->actor_id
        );
    }

    /**
     * @return array{state: string, message: string, operation: ?sPaymentOperation, payment: ?sOrderPayment}
     */
    private function outcome(sPaymentOperation $operation): array
    {
        $ledgerId = $operation->ledger_payment_id ?? ($operation->action === 'refund' ? null : $operation->payment_id);

        return [
            'state' => (string)$operation->state,
            'message' => (string)($operation->result['message'] ?? ''),
            'operation' => $operation,
            'payment' => $ledgerId ? sOrderPayment::query()->find($ledgerId) : null,
        ];
    }

    private function markUnknown(sPaymentOperation $operation, Throwable $exception): void
    {
        $this->log('sCommerce payment operation result is unknown', $operation, $exception);
        sPaymentOperation::query()->whereKey($operation->id)
            ->whereNotIn('state', [sPaymentOperation::STATE_SUCCEEDED, sPaymentOperation::STATE_FAILED])
            ->update(['state' => sPaymentOperation::STATE_UNKNOWN, 'error' => mb_substr($exception->getMessage(), 0, 1000), 'updated_at' => now()]);
        $operation->refresh();
    }

    private function stale(sPaymentOperation $operation): bool
    {
        return $operation->updated_at === null || $operation->updated_at->lt(now()->subSeconds(self::IN_FLIGHT_TIMEOUT));
    }

    private function lockOrder(sOrder $order): void
    {
        if (!sOrder::withTrashed()->whereKey($order->id)->lockForUpdate()->exists()) {
            throw new PaymentActionException('payment_action_ineligible');
        }
    }

    private function actionOf(string $key): string
    {
        return strstr($key, '/', true) ?: '';
    }

    private static function parentRef(sOrderPayment $payment): ?string
    {
        $parent = PaymentFlow::normalizeMetadata($payment->getAttributes()['metadata'] ?? null)[PaymentFlow::CONTEXT_KEY]['parent_provider_ref'] ?? null;

        return is_string($parent) && $parent !== '' ? $parent : null;
    }

    private function log(string $message, sPaymentOperation $operation, Throwable $exception): void
    {
        try {
            Log::channel('scommerce')->error($message, [
                'operation_key' => $operation->operation_key,
                'error' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            // Logging must not hide the operation state.
        }
    }
}
