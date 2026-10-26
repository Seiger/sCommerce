<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Seiger\sTask\Models\sWorker;

/**
 * Marks orders whose stock was committed (`product.inventory_commit`) and registers the hidden
 * sTask worker that retries missed stock commits and releases.
 *
 * @since 1.5.0
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('s_orders', 'inventory_committed_at')) {
            Schema::table('s_orders', function (Blueprint $table) {
                $table->timestamp('inventory_committed_at')->nullable()->index();
            });
        }

        if (Schema::hasTable('s_workers') && class_exists(sWorker::class)) {
            sWorker::updateOrCreate(
                ['identifier' => 'sCommerceStockReconciliation'],
                [
                    'scope' => 'sCommerce',
                    'class' => 'Seiger\sCommerce\Integration\StockReconciliation',
                    'active' => true,
                    'position' => ((int) sWorker::max('position')) + 1,
                    'hidden' => 1,
                    'settings' => [
                        'schedule' => [
                            'type' => 'periodic',
                            'enabled' => true,
                            'frequency' => 'every_15min',
                            'time' => '',
                            'days' => [],
                        ],
                    ],
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('s_workers') && class_exists(sWorker::class)) {
            sWorker::where('identifier', 'sCommerceStockReconciliation')->delete();
        }

        if (Schema::hasColumn('s_orders', 'inventory_committed_at')) {
            Schema::table('s_orders', function (Blueprint $table) {
                $table->dropIndex(['inventory_committed_at']);
                $table->dropColumn('inventory_committed_at');
            });
        }
    }
};
