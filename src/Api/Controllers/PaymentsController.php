<?php namespace Seiger\sCommerce\Api\Controllers;

use Illuminate\Http\Request;
use Seiger\sApi\Http\ApiResponse;
use Seiger\sCommerce\Models\sPaymentMethod;

final class PaymentsController
{
    public function index(Request $request)
    {
        $query = sPaymentMethod::query();

        $active = $request->query('active');
        if ($active !== null) {
            if (!is_scalar($active) || !is_numeric($active) || !in_array((int)$active, [0, 1], true)) {
                return ApiResponse::error('Validation error.', 422, (object)[
                    'errors' => [
                        'active' => 'Must be 0 or 1.',
                    ],
                ]);
            }

            $query->where('active', (int)$active);
        }

        $results = $query->get()->toArray();

        return ApiResponse::success([
            'results' => $results,
        ], '');
    }
}
