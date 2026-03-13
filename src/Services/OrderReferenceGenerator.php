<?php namespace Seiger\sCommerce\Services;

use Illuminate\Support\Facades\DB;
use Seiger\sCommerce\sCommerce;

/**
 * Generates immutable business order references.
 *
 * Local checkout orders prefer `s_orders.id + start_default`.
 * If that candidate is already taken (for example by imported 1C references),
 * the generator falls back to `MAX(reference) + 1`.
 */
class OrderReferenceGenerator
{
    /**
     * Generate the next order reference string.
     *
     * @since 1.0.11
     * @return string
     */
    public function generate(?int $orderId = null): string
    {
        $start = (int) sCommerce::config('orders.reference.start_default', 0);
        $padLeft = (int) sCommerce::config('orders.reference.pad_left', 0);

        return DB::transaction(function () use ($orderId, $start, $padLeft) {
            $maxReferenceFromOrders = (int)(
                DB::table('s_orders')
                    ->selectRaw('MAX(CAST(reference AS UNSIGNED)) AS max_reference')
                    ->value('max_reference') ?? 0
            );

            $displayNumber = max(1, $start);
            $offset = max(0, $start - 1);

            if ($orderId !== null && $orderId > 0) {
                $candidate = $orderId + $offset;
                if (!$this->referenceExists((string)$candidate, $orderId)) {
                    $displayNumber = $candidate;
                } else {
                    $displayNumber = $maxReferenceFromOrders + 1;
                }
            } else {
                $displayNumber = max($displayNumber, $maxReferenceFromOrders + 1);
            }

            $number = (string)$displayNumber;
            if ($padLeft > 0) {
                $number = str_pad($number, $padLeft, '0', STR_PAD_LEFT);
            }

            // IMPORTANT: `s_orders.reference` stores the numeric order number (with pad_left if configured),
            // without any prefix. Prefix is added only for integrations / API output.
            return $number;
        });
    }

    /**
     * Check whether the given business reference is already used by another order.
     *
     * @since 1.0.12
     * @param string $reference Candidate numeric reference to validate.
     * @param int|null $ignoreOrderId Existing order id to exclude from the lookup during post-insert assignment.
     * @return bool True when the reference is already occupied by a different order.
     */
    private function referenceExists(string $reference, ?int $ignoreOrderId = null): bool
    {
        $query = DB::table('s_orders')->where('reference', $reference);

        if ($ignoreOrderId !== null && $ignoreOrderId > 0) {
            $query->where('id', '!=', $ignoreOrderId);
        }

        return $query->exists();
    }
}
