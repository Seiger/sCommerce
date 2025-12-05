<?php namespace Seiger\sCommerce\Controllers;

use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Models\sOrder;

/**
 * TabOrderController
 * 
 * Controller for managing order operations
 */
class TabOrderController
{
    /**
     * Soft delete an order
     * 
     * @param int $orderId
     * @return array
     */
    public function delete(int $orderId): array
    {
        try {
            $order = sOrder::find($orderId);

            if (!$order) {
                return [
                    'success' => false,
                    'message' => __('sCommerce::global.order_not_found')
                ];
            }

            // Store order info for logging
            $orderInfo = [
                'id' => $order->id,
                'identifier' => $order->identifier,
                'user_id' => (int)evo()->getLoginUserID('mgr'),
                'deleted_at' => now()->toDateTimeString()
            ];

            // Soft delete the order
            $order->delete();

            // Log the deletion
            Log::channel('scommerce')->info('Order soft deleted', $orderInfo);

            // Set session data for confirmation message
            $_SESSION['itemaction'] = 'Deleting Order #' . $order->id;
            $_SESSION['itemname'] = __('sCommerce::global.title');

            return [
                'success' => true,
                'message' => __('sCommerce::global.order_deleted_successfully'),
                'order_id' => $orderId
            ];
        } catch (\Throwable $e) {
            Log::channel('scommerce')->error('Failed to delete order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => __('sCommerce::global.order_delete_error') . ': ' . $e->getMessage()
            ];
        }
    }

    /**
     * Restore a soft deleted order
     * 
     * @param int $orderId
     * @return array
     */
    public function restore(int $orderId): array
    {
        try {
            $order = sOrder::withTrashed()->find($orderId);

            if (!$order) {
                return [
                    'success' => false,
                    'message' => __('sCommerce::global.order_not_found')
                ];
            }

            if (!$order->trashed()) {
                return [
                    'success' => false,
                    'message' => __('sCommerce::global.order_not_deleted')
                ];
            }

            // Restore the order
            $order->restore();

            // Log the restoration
            Log::channel('scommerce')->info('Order restored', [
                'id' => $order->id,
                'identifier' => $order->identifier,
                'user_id' => (int)evo()->getLoginUserID('mgr'),
                'restored_at' => now()->toDateTimeString()
            ]);

            $_SESSION['itemaction'] = 'Restoring Order #' . $order->id;
            $_SESSION['itemname'] = __('sCommerce::global.title');

            return [
                'success' => true,
                'message' => __('sCommerce::global.order_restored_successfully'),
                'order_id' => $orderId
            ];
        } catch (\Throwable $e) {
            Log::channel('scommerce')->error('Failed to restore order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => __('sCommerce::global.order_restore_error') . ': ' . $e->getMessage()
            ];
        }
    }
}
