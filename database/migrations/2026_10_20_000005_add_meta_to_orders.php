<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project and integration data of an order, e.g. booking fields and the trusted checkout
 * context in `meta.integration`.
 *
 * @since 1.5.0
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('s_orders', 'meta')) {
            return;
        }

        Schema::table('s_orders', function (Blueprint $table) {
            $table->json('meta')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('s_orders', 'meta')) {
            return;
        }

        Schema::table('s_orders', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
