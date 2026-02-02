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
        $start = (int) sCommerce::config('orders.reference.start_default', 0);
        $padLeft = (int) sCommerce::config('orders.reference.pad_left', 0);

        return DB::transaction(function () use ($start, $padLeft) {
            $scope = 'default';

            $row = DB::table('s_order_counters')->where('scope', $scope)->lockForUpdate()->first();

            if (!$row) {
                DB::table('s_order_counters')->insert([
                    'scope' => $scope,
                    'current' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $currentRaw = (int)($row->current ?? 0);
            // Backward compatibility: if `current` ever stored the display number, convert it back to ordinal.
            $currentOrdinal = ($start > 0 && $currentRaw >= $start) ? max(0, $currentRaw - $start) : $currentRaw;
            $ordinalNext = $currentOrdinal + 1;
            $displayNumber = $start > 0 ? ($start + $ordinalNext) : $ordinalNext;

            DB::table('s_order_counters')
                ->where('scope', $scope)
                ->update([
                    'current' => $ordinalNext,
                    'updated_at' => now(),
                ]);

            $number = (string)$displayNumber;
            if ($padLeft > 0) {
                $number = str_pad($number, $padLeft, '0', STR_PAD_LEFT);
            }

            // IMPORTANT: `s_orders.reference` stores the numeric order number (with pad_left if configured),
            // without any prefix. Prefix is added only for integrations / API output.
            return $number;
        });
    }
}
