<?php namespace Seiger\sCommerce\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Seiger\sCommerce\Models\sOrder;

/**
 * Builds editable order copies and persists them without reusing payment/shipment state.
 *
 * Callers must authorize the manager and validate the submitted draft first.
 *
 * @since 1.4.0
 */
final class OrderRepeater
{
    /**
     * Build an unsaved snapshot; opening/canceling it never reserves an order number.
     *
     * Prices remain those of the source order for the manager to review.
     *
     * @param sOrder $source Existing, non-deleted source order.
     * @return sOrder Draft without identifiers, timestamps or previous audit entries.
     * @since 1.4.0
     */
    public function draft(sOrder $source): sOrder
    {
        $delivery = is_array($source->delivery_info) ? $source->delivery_info : [];
        $method = is_string($delivery['method'] ?? null) ? $delivery['method'] : '';
        $addressKeys = array_flip(['city', 'city_ref', 'warehouse', 'warehouse_ref', 'address',
            'street', 'house', 'flat', 'zip', 'postcode', 'country']);
        $address = array_intersect_key(is_array($delivery[$method] ?? null) ? $delivery[$method] : [], $addressKeys);
        $payment = is_array($source->payment_info) ? $source->payment_info : [];
        $products = $source->products ?? [];
        $cost = 0.0;
        foreach ($products as &$product) {
            $product += ['title' => '', 'sku' => '', 'link' => '', 'coverSrc' => ''];
            $price = isset($product['priceNumber']) && is_numeric($product['priceNumber'])
                ? (float) $product['priceNumber']
                : \Seiger\sCommerce\Facades\sCommerce::convertPriceNumber($product['price'] ?? 0, $source->currency, $source->currency);
            $product['price'] = $product['priceNumber'] = $price;
            $cost += $price * (int) ($product['quantity'] ?? 1);
        }
        unset($product);
        return (new sOrder())->forceFill([
            'user_id' => (int) $source->user_id,
            'user_info' => array_intersect_key($source->user_info ?? [], array_flip([
                'first_name', 'middle_name', 'last_name', 'email', 'phone', 'recipient_person', 'recipient_phone',
            ])),
            'products' => $products,
            'currency' => $source->currency,
            'cost' => round($cost, 2),
            'lang' => $source->lang,
            'domain' => $source->domain,
            'delivery_info' => array_intersect_key($delivery, $addressKeys)
                + ($method !== '' ? ['method' => $method, $method => $address] : []) + ['cost' => 0],
            'payment_info' => is_string($payment['method'] ?? null) ? ['method' => $payment['method']] : [],
            'status' => sOrder::ORDER_STATUS_NEW,
            'payment_status' => sOrder::PAYMENT_STATUS_PENDING,
            'is_quick' => false, 'do_not_call' => false,
            'manager_info' => [], 'manager_notes' => [], 'history' => [],
        ]);
    }

    /**
     * Save a validated repeat once, serializing retries on the source row lock.
     *
     * Uses the checkout reference generator. No payment provider, ledger, email or
     * shipment is invoked here; the controller dispatches the save event after commit.
     *
     * @param int $sourceId Source order, rechecked at save time.
     * @param string $identifier Server-derived idempotency identifier.
     * @param array{products: array, user_info: array, cost: float, note: string} $data Validated edits.
     * @param int $managerId Authorized manager identifier.
     * @return array{item: sOrder, created: bool}
     * @since 1.4.0
     */
    public function save(int $sourceId, string $identifier, array $data, int $managerId): array
    {
        return DB::transaction(function () use ($sourceId, $identifier, $data, $managerId): array {
            $source = sOrder::query()->lockForUpdate()->findOrFail($sourceId);
            $existing = sOrder::withTrashed()->where('identifier', $identifier)->first();
            if ($existing) {
                if ($existing->trashed()) {
                    throw new \OutOfBoundsException('Repeated order was deleted');
                }
                return ['item' => $existing, 'created' => false];
            }
            $item = $this->draft($source);
            $item->identifier = $identifier;
            $item->uuid = (string) Str::uuid();
            $item->products = $data['products'];
            $item->user_info = array_replace($item->user_info, $data['user_info']);
            $item->cost = $data['cost'];
            $item->history = [['status' => sOrder::ORDER_STATUS_NEW, 'repeated_from' => $sourceId,
                'timestamp' => now()->toDateTimeString(), 'user_id' => $managerId]];
            if ($data['note'] !== '') {
                $item->manager_notes = [['comment' => strip_tags($data['note']),
                    'timestamp' => now()->toDateTimeString(), 'user_id' => $managerId]];
            }
            if (!$item->save()) {
                throw new \RuntimeException('Order creation canceled');
            }
            $item->reference = app(OrderReferenceGenerator::class)->generate((int) $item->id);
            if ($item->reference === '' || !$item->save()) {
                throw new \RuntimeException('Order numbering failed');
            }
            return ['item' => $item, 'created' => true];
        });
    }
}
