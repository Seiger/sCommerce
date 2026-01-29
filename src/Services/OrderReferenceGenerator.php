<?php namespace Seiger\sCommerce\Services;

use Illuminate\Support\Facades\DB;
use Seiger\sCommerce\sCommerce;

/**
 * Generates sequential, immutable order references for integrations (prefix + sequence).
 *
 * Concurrency-safe implementation using a transaction and row-level locking.
 */
class OrderReferenceGenerator
{
    /**
     * Generate the next order reference string using a global counter scope ("default").
     *
     * @return string
     */
    public function generate(): string
    {
        $prefix = (string) sCommerce::config('orders.reference.prefix_default', 'ORD_');
        $start = (int) sCommerce::config('orders.reference.start_default', 1);
        $padLeft = (int) sCommerce::config('orders.reference.pad_left', 0);

        return DB::transaction(function () use ($prefix, $start, $padLeft) {
            $scope = 'default';

            $row = DB::table('s_order_counters')->where('scope', $scope)->lockForUpdate()->first();

            if (!$row) {
                $current = max(0, $start - 1);

                DB::table('s_order_counters')->insert([
                    'scope' => $scope,
                    'current' => $current,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sequence = $current + 1;
            } else {
                $sequence = ((int) $row->current) + 1;
            }

            DB::table('s_order_counters')
                ->where('scope', $scope)
                ->update([
                    'current' => $sequence,
                    'updated_at' => now(),
                ]);

            $number = $padLeft > 0
                ? str_pad((string) $sequence, $padLeft, '0', STR_PAD_LEFT)
                : (string) $sequence;

            return $prefix . $number;
        });
    }
}
