<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('s_orders', 'marketing')) {
            return;
        }

        Schema::table('s_orders', function (Blueprint $table) {
            $table->json('marketing')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('s_orders', 'marketing')) {
            return;
        }

        Schema::table('s_orders', function (Blueprint $table) {
            $table->dropColumn('marketing');
        });
    }
};
