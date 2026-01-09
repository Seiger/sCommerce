<?php namespace Seiger\sCommerce\Api\Controllers;

use Illuminate\Http\Request;
use Seiger\sApi\Http\ApiResponse;
use Seiger\sCommerce\Models\sOrder;

final class OrdersController
{
    public function index(Request $request)
    {
        $loaded = (int)$request->query('loaded', 0);
        $limit = (int)$request->query('limit', 50);
        if ($limit < 1) $limit = 50;
        if ($limit > 500) $limit = 500;

        $query = sOrder::where('loaded', $loaded)->orderByDesc('id');

        $total = (int)(clone $query)->count();
        $results = $query->limit($limit)->get();

        return ApiResponse::success([
            'results' => $results,
            'total'   => $total,
            'limit'   => $limit,
        ], '');
    }

    public function update(int $order_id, Request $request)
    {
        $order = sOrder::find($order_id);

        if (!$order) {
            return ApiResponse::error('Order not found.', 404, (object)[]);
        }

        // ACK by default
        $loaded = $request->input('loaded', 1);
        $order->loaded = (int)(bool)$loaded;
        $order->save();

        return ApiResponse::success(['result' => $order], '');
    }
}
