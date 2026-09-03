<?php namespace Seiger\sCommerce\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Services\OrderManualPayment;

final class OrderBulkMenuController
{
    public const MAX_ORDERS = 500;

    public function handle(Request $request): JsonResponse
    {
        if ($request->getRealMethod() !== 'POST' || !$request->isMethod('POST')) {
            return new JsonResponse(['message' => __('sCommerce::global.bulk_status_post_only')], 405, ['Allow' => 'POST']);
        }
        if (empty($_SESSION['mgrValidated']) || (int) evo()->getLoginUserID('mgr') < 1 || !evo()->hasPermission('exec_module')) {
            return $this->error('bulk_menu_forbidden', 403);
        }
        $token = $request->input('_token', $request->header('X-CSRF-TOKEN'));
        $sessionToken = $_SESSION['_token'] ?? null;
        if (!is_string($token) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            return $this->error('bulk_status_session_expired', 403);
        }
        $action = $request->input('action');
        $ids = $request->input('ids');
        if (!in_array($action, ['paid', 'print', 'delete'], true) || !is_array($ids) || !$ids || count($ids) > self::MAX_ORDERS) {
            return $this->error('bulk_menu_invalid', 422);
        }
        $ids = array_map(static fn ($id) => (is_int($id) || is_string($id))
            ? filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false, $ids);
        if (in_array(false, $ids, true) || count(array_unique($ids)) !== count($ids)) return $this->error('bulk_menu_invalid', 422);
        $managerId = (int) evo()->getLoginUserID('mgr');
        try {
            if ($action === 'print') {
                $orders = sOrder::query()->whereIn('id', $ids)->get()->keyBy('id');
                if ($orders->count() !== count($ids)) return $this->error('bulk_menu_missing', 409);
                $ordered = array_map(static fn ($id) => $orders->get($id), array_values($ids));
                try {
                    $deliveryMethods = collect(\Seiger\sCommerce\Facades\sCheckout::getDeliveries())->keyBy('name')->all();
                } catch (\Throwable) {
                    $deliveryMethods = [];
                }
                $html = view('sCommerce::ordersPrint', ['orders' => $ordered, 'deliveryMethods' => $deliveryMethods])->render();
                return new JsonResponse(['success' => true, 'html' => $html], 200, ['Cache-Control' => 'private, no-store']);
            }
            $changed = DB::transaction(static function () use ($ids, $action, $managerId) {
                $orders = sOrder::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                if ($orders->count() !== count($ids)) throw new \OutOfBoundsException();
                $changed = [];
                foreach ($orders as $order) {
                    if ($action === 'paid') {
                        if (!(new OrderManualPayment())->apply($order, $managerId)) continue;
                    } else {
                        $history = is_array($order->history) ? $order->history : [];
                        $history[] = ['action' => 'bulk_soft_delete', 'timestamp' => now()->toDateTimeString(), 'user_id' => $managerId];
                        $order->history = $history;
                        if (!$order->save() || !$order->delete()) throw new \RuntimeException('Order deletion canceled');
                    }
                    $changed[] = $order;
                }
                return $changed;
            });
        } catch (\OutOfBoundsException) {
            return $this->error('bulk_menu_missing', 409);
        } catch (\DomainException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            Log::error('sCommerce bulk menu failed', ['action' => $action, 'manager_id' => $managerId, 'exception' => $exception]);
            return $this->error('bulk_menu_error', 500);
        }
        // Do not report a rollback if a listener/log fails after the transaction committed.
        $warning = false;
        foreach ($changed as $item) {
            try {
                if ($action === 'paid') evo()->invokeEvent('sCommerceAfterOrderSave', compact('item'));
                else Log::channel('scommerce')->info('Order soft deleted', ['id' => $item->id, 'user_id' => $managerId, 'source' => 'bulk_menu']);
            } catch (\Throwable $exception) {
                $warning = true;
            }
        }
        $message = __('sCommerce::global.bulk_menu_success', ['updated' => count($changed), 'unchanged' => count($ids) - count($changed)]);
        if ($warning) $message .= ' ' . __('sCommerce::global.bulk_menu_warning');
        return new JsonResponse(['success' => true, 'message' => $message, 'updated' => count($changed), 'unchanged' => count($ids) - count($changed)]);
    }

    private function error(string $key, int $status): JsonResponse
    {
        return new JsonResponse(['message' => __('sCommerce::global.' . $key, ['limit' => self::MAX_ORDERS])], $status);
    }
}
