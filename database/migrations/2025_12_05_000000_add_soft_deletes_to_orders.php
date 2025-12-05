<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to add soft delete support to orders table
 * 
 * This migration adds the deleted_at column to s_orders table
 * to enable soft delete functionality for orders.
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
            });
        }
    }
};


