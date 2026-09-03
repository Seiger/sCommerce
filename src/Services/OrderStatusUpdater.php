<?php namespace Seiger\sCommerce\Services;

use Seiger\sCommerce\Models\sOrder;

final class OrderStatusUpdater
{
    /** Apply manager status/history changes; the caller saves and dispatches events. */
    public function apply(sOrder $order, int $status, int $managerId): bool
    {
        if ((int) $order->status === $status) {
            return false;
        }

        $history = is_array($order->history) ? $order->history : [];
        $history[] = [
            'status' => $status,
            'timestamp' => now()->toDateTimeString(),
            'user_id' => $managerId,
        ];
        $order->status = $status;
        $order->history = $history;

        return true;
    }
}
