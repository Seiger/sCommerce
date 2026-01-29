<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an immutable business order reference (prefix + sequence) and creates a counter table.
 *
 * - s_orders.reference: integration-friendly order number (does NOT replace PK id)
 * - s_order_counters: global sequential counter storage (scope="default")
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // 1) Add reference to orders
        Schema::table('s_orders', function (Blueprint $table) {
            $table->string('reference', 64)->nullable()->unique()->after('identifier');
        });

        // 2) Create counters table
        Schema::create('s_order_counters', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 50)->unique();
            $table->unsignedBigInteger('current')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        // Drop counters table first (independent).
        Schema::dropIfExists('s_order_counters');

        // Remove reference from orders
        Schema::table('s_orders', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });
    }
};
