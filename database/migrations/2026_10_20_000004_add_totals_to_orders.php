<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order tax totals written by `sCommerce.ResolveOrderTaxes` listeners. sCommerce never computes taxes.
 *
 * @since 1.5.0
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('s_orders', 'totals')) {
            return;
        }

        Schema::table('s_orders', function (Blueprint $table) {
            $table->json('totals')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('s_orders', 'totals')) {
            return;
        }

        Schema::table('s_orders', function (Blueprint $table) {
            $table->dropColumn('totals');
        });
    }
};
