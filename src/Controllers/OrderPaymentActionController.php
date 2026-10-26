<?php namespace Seiger\sCommerce\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderPayment;
use Seiger\sCommerce\Models\sPaymentOperation;
use Seiger\sCommerce\Payment\PaymentActionException;
use Seiger\sCommerce\Services\ContainerDeposit;
use Seiger\sCommerce\Services\OrderPaymentAction;

/**
 * Manager endpoint for capture, refund and cancel of one ledger row, and for recording money
 * an offline payment method received or returned (`payment` = 0).
 *
 * @since 1.5.0
 */
final class OrderPaymentActionController
{
    public function __construct(private readonly OrderPaymentAction $actions = new OrderPaymentAction())
    {
    }

    public function handle(Request $request): JsonResponse
    {
        if ($request->getRealMethod() !== 'POST' || !$request->isMethod('POST')) {
            return new JsonResponse(['success' => false, 'message' => __('sCommerce::global.bulk_status_post_only')], 405, ['Allow' => 'POST']);
        }
        $managerId = (int) evo()->getLoginUserID('mgr');
        if (empty($_SESSION['mgrValidated']) || $managerId < 1 || !evo()->hasPermission('exec_module')) {
            return $this->fail('payment_action_forbidden', 403);
        }

        // Fail closed even on installations with the legacy optional-token middleware.
        $token = $request->input('_token', $request->header('X-CSRF-TOKEN'));
        $sessionToken = $_SESSION['_token'] ?? null;
        if (!is_string($token) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            return $this->fail('bulk_status_session_expired', 403);
        }

        $action = $request->input('action');
        if ($action === OrderPaymentAction::CONTAINER_ACTION) {
            return OrderPaymentAction::permitted($action) ? $this->returnContainers($request, $managerId) : $this->fail('payment_action_forbidden', 403);
        }
        $offline = is_string($action) && in_array($action, OrderPaymentAction::OFFLINE_ACTIONS, true);
        if (!is_string($action) || (!$offline && !in_array($action, OrderPaymentAction::ACTIONS, true))) {
            return $this->fail('payment_action_ineligible', 422);
        }
        if (!OrderPaymentAction::permitted($action)) {
            return $this->fail('payment_action_forbidden', 403);
        }

        $integer = static fn ($value, int $min) => (is_int($value) || is_string($value))
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min]]) : false;
        $orderId = $integer($request->input('order'), 1);
        $paymentId = $offline ? $integer($request->input('payment', '0'), 0) : $integer($request->input('payment'), 1);
        $nonce = $request->input('nonce');
        $reason = $request->input('reason', '');
        $amount = $request->input('amount');
        $amount = is_string($amount) && trim($amount) !== '' ? trim($amount) : null;
        // Refunds and offline records need an amount; a capture amount is optional.
        $amountRequired = $offline || $action === 'refund';
        if ($orderId === false || $paymentId === false || ($offline && $paymentId !== 0) || !is_string($nonce) || !is_string($reason)
            || ($amountRequired && $amount === null)
            || ($amount !== null && $action !== 'cancel' && !preg_match('/\A\d{1,10}(?:[.,]\d{1,2})?\z/', $amount))) {
            return $this->fail('payment_action_ineligible', 422);
        }
        if (!OrderPaymentAction::nonceMatches($nonce, $orderId, $paymentId, $action)) {
            return $this->fail('bulk_status_session_expired', 403);
        }

        $order = sOrder::withTrashed()->find($orderId);
        $payment = $order && !$offline ? sOrderPayment::query()->where('order_id', $order->id)->find($paymentId) : null;
        if (!$order || (!$offline && !$payment)) {
            return $this->fail('payment_action_ineligible', 422);
        }
        $value = $amount === null || $action === 'cancel' ? null : (float) str_replace(',', '.', $amount);

        try {
            $outcome = EventScope::run('manager_payment_action', $managerId, fn () => $offline
                ? $this->actions->recordOffline($action, $order, $nonce, (float) $value, $reason, $managerId)
                : $this->actions->run($action, $order, $payment, $nonce, $value, $reason, $managerId));
        } catch (PaymentActionException $exception) {
            return $this->fail($exception->messageKey, $exception->status);
        } catch (\Throwable $exception) {
            Log::channel('scommerce')->error('sCommerce payment action failed', ['order_id' => $orderId, 'payment_id' => $paymentId, 'action' => $action, 'exception' => $exception]);
            return $this->fail('bulk_status_error', 500);
        }

        [$key, $status, $success] = match ($outcome['state']) {
            sPaymentOperation::STATE_SUCCEEDED => ['payment_action_success', 200, true],
            sPaymentOperation::STATE_PENDING => ['payment_action_pending', 202, true],
            sPaymentOperation::STATE_FAILED => ['payment_action_failed', 200, false],
            default => ['payment_action_unknown', 202, true],
        };
        $message = __('sCommerce::global.' . ($offline && $success ? 'payment_record_success' : $key));
        if ($outcome['message'] !== '') {
            $message .= ' ' . $outcome['message'];
        }

        return new JsonResponse(['success' => $success, 'state' => $outcome['state'], 'message' => $message], $status);
    }

    /**
     * Return containers of an order line: refund in cash or credit to another order.
     */
    private function returnContainers(Request $request, int $managerId): JsonResponse
    {
        $integer = static fn ($value, int $min) => (is_int($value) || is_string($value))
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min]]) : false;
        $orderId = $integer($request->input('order'), 1);
        $line = $integer($request->input('line'), 0);
        $count = $integer($request->input('count'), 1);
        $settlement = $request->input('settlement', 'cash');
        $creditId = $settlement === 'credit' ? $integer($request->input('credit_order'), 1) : null;
        $nonce = $request->input('nonce');
        if ($orderId === false || $line === false || $count === false || $creditId === false || !in_array($settlement, ['cash', 'credit'], true) || !is_string($nonce)) {
            return $this->fail('container_return_invalid', 422);
        }
        if (!OrderPaymentAction::nonceMatches($nonce, $orderId, 0, OrderPaymentAction::CONTAINER_ACTION)) {
            return $this->fail('bulk_status_session_expired', 403);
        }
        $order = sOrder::query()->find($orderId);
        $credit = $creditId ? sOrder::query()->find($creditId) : null;
        if (!$order || ($creditId && (!$credit || $credit->id === $order->id))) {
            return $this->fail('container_return_invalid', 422);
        }

        try {
            EventScope::run(ContainerDeposit::SOURCE, $managerId, fn () => (new ContainerDeposit())->returnContainers([
                'reference' => "order/$orderId/" . substr(hash('sha256', $nonce), 0, 24), 'sourceOrder' => $order, 'lineIndex' => $line,
                'quantity' => $count, 'settlement' => $settlement, 'creditOrder' => $credit, 'note' => (string)$request->input('reason', ''),
            ], $managerId));
        } catch (PaymentActionException $exception) {
            return $this->fail($exception->messageKey, $exception->status);
        } catch (\Throwable $exception) {
            Log::channel('scommerce')->error('sCommerce container return failed', ['order_id' => $orderId, 'exception' => $exception]);
            return $this->fail('bulk_status_error', 500);
        }

        return new JsonResponse(['success' => true, 'state' => 'succeeded', 'message' => __('sCommerce::global.container_return_success')]);
    }

    private function fail(string $key, int $status): JsonResponse
    {
        return new JsonResponse(['success' => false, 'message' => __('sCommerce::global.' . $key)], $status);
    }
}
