<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateSOrderPaymentsTable
 *
 * Creates payments ledger for orders (deposit/final/manual/refund).
 * The table is optional for older installations; business logic can fall back
 * to legacy order fields if the table is absent.
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
        if (Schema::hasTable('s_order_payments')) {
            return;
        }

        Schema::create('s_order_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedInteger('sequence')->default(0)->index();
            $table->string('kind', 32)->comment('deposit|final|manual|refund');
            $table->string('status', 32)->default('pending')->comment('pending|authorized|captured|failed|refunded|partially_refunded|canceled|rejected|expired|disputed');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('provider', 32)->default('manual')->comment('stripe|manual|other');
            $table->string('provider_ref', 255)->nullable()->comment('Universal provider reference (e.g., Stripe event id)');
            $table->json('metadata')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_ref'], 'uniq_provider_ref');
            $table->foreign('order_id')->references('id')->on('s_orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('s_order_payments');
    }
};