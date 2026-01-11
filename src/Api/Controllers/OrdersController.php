<?php namespace Seiger\sCommerce\Api\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Seiger\sApi\Http\ApiResponse;
use Seiger\sCommerce\Api\Contracts\OrderListQueryApplierInterface;
use Seiger\sCommerce\Api\Contracts\OrderUpdateApplierInterface;
use Seiger\sCommerce\Api\Contracts\OrderUpdateMapperInterface;
use Seiger\sCommerce\Api\Contracts\OrderUpdateValidatorInterface;
use Seiger\sCommerce\Models\sOrder;

final class OrdersController
{
    public function index(Request $request, OrderListQueryApplierInterface $queryApplier)
    {
        $limit = (int)$request->query('limit', 50);
        if ($limit < 1) $limit = 50;
        if ($limit > 500) $limit = 500;

        $query = sOrder::query()->orderByDesc('id');
        $query = $queryApplier->apply($query, $request);

        $total = (int)(clone $query)->count();
        $results = $query->limit($limit)->get();

        return ApiResponse::success([
            'results' => $results,
            'total'   => $total,
            'limit'   => $limit,
        ], '');
    }

    /**
     * Update an existing order.
     *
     * Standard-first: vendor default updates ONLY vendor `s_orders` columns (plus JSONB merges).
     * Project-specific fields/mappings MUST be done via custom mapper/validator/applier.
     */
    public function update(int $order_id, Request $request)
    {
        /** @var sOrder|null $order */
        $order = sOrder::query()->find($order_id);

        if (!$order) {
            return ApiResponse::error('Order not found.', 404, (object)[]);
        }

        $payload = $request->except(['q']);
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            $mapper = app(OrderUpdateMapperInterface::class);
            $validator = app(OrderUpdateValidatorInterface::class);
            $applier = app(OrderUpdateApplierInterface::class);
        } catch (\Throwable) {
            return ApiResponse::error('Server misconfigured.', 500, (object)[]);
        }

        $mapped = $mapper->map($payload);
        $result = $validator->validate(is_array($mapped) ? $mapped : []);

        if (!(bool)($result['ok'] ?? false)) {
            return ApiResponse::error('Validation error.', 422, (object)['errors' => (array)($result['errors'] ?? [])]);
        }

        $data = (array)($result['data'] ?? []);

        try {
            DB::transaction(function () use ($applier, $order, $data): void {
                $applier->apply($order, $data);
                $order->save();
            });
        } catch (\Throwable) {
            return ApiResponse::error('Internal server error.', 500, (object)[]);
        }

        try {
            $order->refresh();
        } catch (\Throwable) {
            //
        }

        return ApiResponse::success($order, '');
    }
}
