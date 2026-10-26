<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipments and tracking of orders, written by managers, ERPs and carrier integrations
 * through {@see \Seiger\sCommerce\Services\OrderShipments}.
 *
 * @since 1.5.0
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('s_order_shipments')) {
            return;
        }

        Schema::create('s_order_shipments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('status', 32)->default('pending')->index()->comment('pending|ready|shipped|in_transit|delivered|failed|returned|canceled');
            $table->string('carrier', 64)->nullable()->comment('Lowercase carrier code, e.g. packeta, dpd, gls, inpost');
            $table->string('service', 64)->nullable()->comment('Carrier service, e.g. pickup_point, home');
            $table->string('tracking_number', 128)->nullable()->index();
            $table->string('tracking_url', 512)->nullable();
            $table->json('items')->nullable()->comment('[{line, line_key, product_id, sku, quantity}]; null = whole order');
            $table->string('source', 32)->default('manual')->comment('manual|erp|carrier|delivery_method|api');
            $table->string('external_ref', 191)->nullable()->comment('Id in the source system');
            $table->json('metadata')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['source', 'external_ref'], 'uniq_shipment_source_ref');
            $table->foreign('order_id')->references('id')->on('s_orders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s_order_shipments');
    }
};
