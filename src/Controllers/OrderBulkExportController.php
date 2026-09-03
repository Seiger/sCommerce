<?php namespace Seiger\sCommerce\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Facades\sCheckout;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Services\OrderCsvExporter;

final class OrderBulkExportController
{
    public const MAX_ORDERS = 500;

    public function handle(Request $request): Response|JsonResponse
    {
        if ($request->getRealMethod() !== 'POST' || !$request->isMethod('POST')) {
            return new JsonResponse(['message' => __('sCommerce::global.bulk_status_post_only')], 405, ['Allow' => 'POST']);
        }
        if (empty($_SESSION['mgrValidated']) || (int) evo()->getLoginUserID('mgr') < 1 || !evo()->hasPermission('exec_module')) {
            return new JsonResponse(['message' => __('sCommerce::global.bulk_export_forbidden')], 403);
        }
        $token = $request->input('_token', $request->header('X-CSRF-TOKEN'));
        $sessionToken = $_SESSION['_token'] ?? null;
        if (!is_string($token) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            return new JsonResponse(['message' => __('sCommerce::global.bulk_status_session_expired')], 403);
        }
        $ids = $request->input('ids');
        if (!is_array($ids) || !$ids || count($ids) > self::MAX_ORDERS) {
            return $this->invalid();
        }
        $ids = array_map(static fn ($id) => (is_int($id) || is_string($id))
            ? filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false, $ids);
        if (in_array(false, $ids, true) || count(array_unique($ids)) !== count($ids)) {
            return $this->invalid();
        }
        try {
            $orders = sOrder::query()->whereIn('id', $ids)->get([
                'id', 'reference', 'user_info', 'created_at', 'cost', 'currency', 'status', 'payment_status', 'delivery_info', 'domain',
            ])->keyBy('id');
            if ($orders->count() !== count($ids)) {
                return new JsonResponse(['message' => __('sCommerce::global.bulk_export_missing')], 409);
            }
            try {
                $deliveryMethods = collect(sCheckout::getDeliveries())->keyBy('name')->all();
            } catch (\Throwable) {
                $deliveryMethods = []; // Saved delivery name/method remains available if an integration was removed.
            }
            $domains = null;
            if (evo()->getConfig('check_sMultisite', false) && class_exists(\Seiger\sMultisite\Models\sMultisite::class)) {
                $domains = \Seiger\sMultisite\Models\sMultisite::query()->pluck('domain', 'key')->all();
            }
            // Preserve the visible selection order, including the current table sort.
            $csv = (new OrderCsvExporter())->render(array_map(static fn ($id) => $orders->get($id), array_values($ids)), $deliveryMethods, $domains);
            return new Response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="sCommerce_orders_' . date('Y-m-d_H-i-s') . '.csv"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $exception) {
            Log::error('sCommerce order CSV export failed', ['manager_id' => (int) evo()->getLoginUserID('mgr'), 'exception' => $exception]);
            return new JsonResponse(['message' => __('sCommerce::global.bulk_export_error')], 500);
        }
    }

    private function invalid(): JsonResponse
    {
        return new JsonResponse(['message' => __('sCommerce::global.bulk_export_invalid', ['limit' => self::MAX_ORDERS])], 422);
    }
}
