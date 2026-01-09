<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('s_orders', 'loaded')) {
            return;
        }

        Schema::table('s_orders', function (Blueprint $table) {
            $table->boolean('loaded')->default(false)->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('s_orders', 'loaded')) {
            return;
        }

        Schema::table('s_orders', function (Blueprint $table) {
            $table->dropColumn('loaded');
        });
    }
};
