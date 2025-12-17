<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * @deprecated
 * @since 1.0.6
 * @todo [remove@1.5] Remove in sCommerce v1.5
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('s_orders')) {
            Schema::table('s_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('s_orders', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable()->after('updated_at')->index();
                }
                if (!Schema::hasColumn('s_orders', 'domain')) {
                    $table->string('domain', 50)->after('lang')->index()->default('default');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('s_orders')) {
            Schema::table('s_orders', function (Blueprint $table) {
                if (Schema::hasColumn('s_orders', 'deleted_at')) {
                    $table->dropColumn('deleted_at');
                }
                if (Schema::hasColumn('s_orders', 'domain')) {
                    $table->dropColumn('domain');
                }
            });
        }
    }
};
