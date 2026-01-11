<?php namespace Seiger\sCommerce\Api\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Seiger\sApi\Http\ApiResponse;
use Seiger\sCommerce\Api\Contracts\OrderListQueryApplierInterface;
use Seiger\sCommerce\Api\Contracts\OrderUpdateApplierInterface;
use Seiger\sCommerce\Api\Contracts\OrderUpdateMapperInterface;
use Seiger\sCommerce\Api\Contracts\OrderUpdateValidatorInterface;
use Seiger\sCommerce\Models\sOrder;

final class StatusesController
{
    public function index(Request $request)
    {
        $statuses = sOrder::listOrderStatuses();

        $results = [];

        foreach ($statuses as $id => $name) {
            $results[] = [
                "id" => $id,
                "name" => $name,
            ];
        }

        return ApiResponse::success([
            'results' => $results,
        ], '');
    }
}
