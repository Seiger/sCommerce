<?php namespace Seiger\sCommerce\Api\Services;

use Illuminate\Support\Carbon;
use Seiger\sCommerce\Api\Contracts\OrderUpdateApplierInterface;
use Seiger\sCommerce\Models\sOrder;

final class OrderUpdateApplier implements OrderUpdateApplierInterface
{
    public function apply(sOrder $order, array $data): void
    {
        // Scalars (vendor schema)
        foreach (['status', 'payment_status', 'comment', 'cost', 'currency', 'loaded'] as $key) {
            if (array_key_exists($key, $data)) {
                $order->setAttribute($key, $data[$key]);
            }
        }

        // JSON merges (vendor schema)
        if (isset($data['user']) && is_array($data['user'])) {
            $order->user_info = $this->mergeJsonObject((array)($order->user_info ?? []), $data['user']);
        }

        if (isset($data['delivery']) && is_array($data['delivery'])) {
            $order->delivery_info = $this->mergeJsonObject((array)($order->delivery_info ?? []), $data['delivery']);
        }

        if (isset($data['payment']) && is_array($data['payment'])) {
            $order->payment_info = $this->mergeJsonObject((array)($order->payment_info ?? []), $data['payment']);
        }

        // Items/products replace (vendor schema)
        if (array_key_exists('items', $data)) {
            $order->products = is_array($data['items']) ? $data['items'] : [];
        }

        $this->appendHistory($order, $data);
    }

    private function mergeJsonObject(array $existing, array $patch): array
    {
        return array_replace_recursive($existing, $patch);
    }

    private function appendHistory(sOrder $order, array $data): void
    {
        $history = (array)($order->history ?? []);

        $userId = 0;
        if (class_exists(\Seiger\sApi\Logging\RequestContext::class)) {
            $ctxUserId = \Seiger\sApi\Logging\RequestContext::get('user_id');
            if (is_numeric($ctxUserId) && (int)$ctxUserId > 0) {
                $userId = (int)$ctxUserId;
            }
        }

        $entry = [
            'timestamp' => now()->toDateTimeString(),
            'user_id' => $userId,
        ];

        $hasAny = false;
        if (array_key_exists('status', $data)) {
            $entry['status'] = (int)$data['status'];
            $hasAny = true;
        }
        if (array_key_exists('payment_status', $data)) {
            $entry['payment_status'] = (int)$data['payment_status'];
            $hasAny = true;
        }

        if (!$hasAny) {
            return;
        }

        $history[] = $entry;
        $order->history = $history;
    }
}
