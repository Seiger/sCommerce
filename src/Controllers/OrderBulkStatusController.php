<?php namespace Seiger\sCommerce\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Services\OrderStatusUpdater;

final class OrderBulkStatusController
{
    public const MAX_ORDERS = 500;

    public function handle(Request $request): JsonResponse
    {
        if ($request->getRealMethod() !== 'POST' || !$request->isMethod('POST')) {
            return new JsonResponse(['message' => __('sCommerce::global.bulk_status_post_only')], 405, ['Allow' => 'POST']);
        }
        if (empty($_SESSION['mgrValidated']) || (int) evo()->getLoginUserID('mgr') < 1 || !evo()->hasPermission('exec_module')) {
            return new JsonResponse(['message' => __('sCommerce::global.bulk_status_forbidden')], 403);
        }

        // Fail closed even on installations with the legacy optional-token middleware.
        $token = $request->input('_token', $request->header('X-CSRF-TOKEN'));
        $sessionToken = $_SESSION['_token'] ?? null;
        if (!is_string($token) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            return new JsonResponse(['message' => __('sCommerce::global.bulk_status_session_expired')], 403);
        }

        $ids = $request->input('ids');
        $status = $request->input('status');
        if (!is_array($ids) || !$ids || count($ids) > self::MAX_ORDERS) {
            return $this->invalid();
        }
        $integer = static fn ($value) => (is_int($value) || is_string($value))
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        $ids = array_map($integer, $ids);
        $status = $integer($status);
        if (in_array(false, $ids, true) || count(array_unique($ids)) !== count($ids)
            || $status === false || !array_key_exists($status, sOrder::listOrderStatuses())) {
            return $this->invalid();
        }

        // Status 0 is deliberately excluded: deletion has its own soft-delete workflow.
        $managerId = (int) evo()->getLoginUserID('mgr');
        try {
            $changed = DB::transaction(static function () use ($ids, $status, $managerId): array {
                $orders = sOrder::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                if ($orders->count() !== count($ids)) {
                    throw new ModelNotFoundException();
                }
                $changed = [];
                $updater = new OrderStatusUpdater();
                foreach ($orders as $item) {
                    if ($updater->apply($item, $status, $managerId)) {
                        if (!$item->save()) {
                            throw new \RuntimeException('Order status save was canceled');
                        }
                        $changed[] = $item;
                    }
                }
                return $changed;
            });
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => __('sCommerce::global.bulk_status_missing')], 409);
        } catch (\Throwable $exception) {
            Log::error('sCommerce bulk status update failed', ['manager_id' => $managerId, 'order_ids' => $ids, 'exception' => $exception]);
            return new JsonResponse(['message' => __('sCommerce::global.bulk_status_error')], 500);
        }

        // External listeners run only after every selected order has committed.
        $eventFailed = false;
        foreach ($changed as $item) {
            try {
                evo()->invokeEvent('sCommerceAfterOrderSave', compact('item'));
            } catch (\Throwable $exception) {
                $eventFailed = true;
                Log::error('sCommerce bulk status listener failed after commit', ['order_id' => $item->id, 'exception' => $exception]);
            }
        }
        $message = __('sCommerce::global.bulk_status_success', ['updated' => count($changed), 'unchanged' => count($ids) - count($changed)]);
        if ($eventFailed) {
            $message .= ' ' . __('sCommerce::global.bulk_status_event_warning');
        }
        return new JsonResponse(['success' => true, 'updated' => count($changed), 'unchanged' => count($ids) - count($changed), 'message' => $message]);
    }

    private function invalid(): JsonResponse
    {
        return new JsonResponse(['message' => __('sCommerce::global.bulk_status_invalid', ['limit' => self::MAX_ORDERS])], 422);
    }
}
