<?php namespace Seiger\sCommerce\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Services\MeasuredQuantity;
use Seiger\sCommerce\Services\MeasuredQuantityException;
use Seiger\sCommerce\Services\OrderActualQuantity;
use Seiger\sCommerce\Services\OrderPaymentAction;
use Seiger\sCommerce\Services\StockShortageException;

/**
 * Manager endpoint of the "Actual" input of a measured order line.
 *
 * @since 1.5.0
 */
final class OrderActualQuantityController
{
    /** Form nonce action of the actual-quantity inputs. */
    public const NONCE_ACTION = 'actual_quantity';

    /** Permission to exceed the tolerance of a line. */
    public const PERMISSION_OVER_TOLERANCE = 'scommerce_order_actual_over_tolerance';

    public function __construct(private readonly OrderActualQuantity $service = new OrderActualQuantity())
    {
    }

    /**
     * Set the actual quantity of one line from `order`, `line_key`, `amount` (price unit) and
     * `over_tolerance`, with the CSRF token and the form nonce.
     *
     * @since 1.5.0
     */
    public function handle(Request $request): JsonResponse
    {
        if ($request->getRealMethod() !== 'POST' || !$request->isMethod('POST')) {
            return new JsonResponse(['success' => false, 'message' => __('sCommerce::global.bulk_status_post_only')], 405, ['Allow' => 'POST']);
        }
        $managerId = (int)evo()->getLoginUserID('mgr');
        if (empty($_SESSION['mgrValidated']) || $managerId < 1 || !evo()->hasPermission('exec_module')) {
            return $this->fail('payment_action_forbidden', 403);
        }
        $token = $request->input('_token', $request->header('X-CSRF-TOKEN'));
        $sessionToken = $_SESSION['_token'] ?? null;
        if (!is_string($token) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            return $this->fail('bulk_status_session_expired', 403);
        }

        $orderId = is_int($order = $request->input('order')) || is_string($order)
            ? filter_var($order, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        $nonce = $request->input('nonce');
        $lineKey = $request->input('line_key');
        $amount = $request->input('amount');
        if ($orderId === false || !is_string($nonce) || !is_string($lineKey) || $lineKey === '' || strlen($lineKey) > 64 || !is_string($amount)) {
            return $this->fail('measured_quantity_invalid', 422);
        }
        if (!OrderPaymentAction::nonceMatches($nonce, $orderId, 0, self::NONCE_ACTION)) {
            return $this->fail('bulk_status_session_expired', 403);
        }
        $overTolerance = filter_var($request->input('over_tolerance', false), FILTER_VALIDATE_BOOL);
        if ($overTolerance && !evo()->hasPermission(self::PERMISSION_OVER_TOLERANCE)) {
            return $this->fail('actual_quantity_forbidden', 403);
        }
        $order = sOrder::query()->find($orderId);
        $line = null;
        foreach ($order && is_array($order->products) ? $order->products : [] as $candidate) {
            if (is_array($candidate) && ($candidate['line_key'] ?? null) === $lineKey) {
                $line = $candidate;
                break;
            }
        }
        if (!$order || !$line) {
            return $this->fail('actual_quantity_line', 422);
        }

        try {
            $actual = MeasuredQuantity::parseAmount($amount, MeasuredQuantity::fromLine($line));
            $this->service->set($order, $lineKey, $actual, 'manager', $managerId,
                'manager:' . hash('sha256', $nonce . '|' . $lineKey . '|' . $actual . '|' . ($overTolerance ? 1 : 0)), $overTolerance);
        } catch (MeasuredQuantityException $exception) {
            return new JsonResponse(['success' => false, 'message' => $exception->translated()], $exception->status);
        } catch (StockShortageException) {
            return $this->fail('actual_quantity_stock', 409);
        } catch (\Throwable $exception) {
            Log::channel('scommerce')->error('sCommerce actual quantity failed', ['order_id' => $orderId, 'exception' => $exception]);
            return $this->fail('bulk_status_error', 500);
        }

        return new JsonResponse(['success' => true, 'message' => __('sCommerce::global.actual_quantity_saved')]);
    }

    private function fail(string $key, int $status): JsonResponse
    {
        return new JsonResponse(['success' => false, 'message' => __('sCommerce::global.' . $key)], $status);
    }
}
