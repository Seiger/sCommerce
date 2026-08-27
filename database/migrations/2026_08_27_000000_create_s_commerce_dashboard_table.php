<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Seiger\sTask\Models\sWorker;

/**
 * Introduces persistent daily snapshots for the no-cart dashboard.
 *
 * Besides creating the date-unique metrics table, the migration registers a
 * hidden sTask worker with a daily 23:55 schedule. Registration is skipped
 * when sTask storage is unavailable, which keeps package installation safe;
 * rollback removes both the owned worker record and the snapshot table.
 *
 * @since 1.3.5
 */
return new class extends Migration {
    /**
     * Create dashboard snapshot storage and register its scheduled worker.
     *
     * The table keeps cumulative totals together with the calculated daily
     * view delta. Worker registration is idempotent and preserves a single
     * stable identifier used by the sTask scheduler.
     *
     * @return void
     * @since 1.3.5
     */
    public function up(): void
    {
        if (!Schema::hasTable('s_commerce_dashboard')) {
            Schema::create('s_commerce_dashboard', function (Blueprint $table) {
                $table->id();
                $table->date('date')->unique();
                $table->unsignedBigInteger('views_total')->default(0);
                $table->unsignedBigInteger('views_delta')->nullable();
                $table->unsignedInteger('products_total')->default(0);
                $table->unsignedInteger('reviews_total')->default(0);
                $table->decimal('rating_average', 4, 2)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('s_workers')) {
            sWorker::updateOrCreate(
                ['identifier' => 'sCommerceDashboardMetrics'],
                [
                    'scope' => 'sCommerce',
                    'class' => 'Seiger\sCommerce\Integration\DashboardMetrics',
                    'active' => true,
                    'position' => ((int) sWorker::max('position')) + 1,
                    'hidden' => 1,
                    'settings' => [
                        'schedule' => [
                            'type' => 'periodic',
                            'enabled' => true,
                            'time' => '23:55',
                            'frequency' => 'daily',
                            'days' => [],
                        ],
                    ],
                ]
            );
        }
    }

    /**
     * Remove the scheduled worker and dashboard snapshot storage.
     *
     * Only the worker owned by this migration is deleted. The table removal
     * also discards the collected historical dashboard series.
     *
     * @return void
     * @since 1.3.5
     */
    public function down(): void
    {
        if (Schema::hasTable('s_workers')) {
            sWorker::where('identifier', 'sCommerceDashboardMetrics')->delete();
        }

        Schema::dropIfExists('s_commerce_dashboard');
    }
};
