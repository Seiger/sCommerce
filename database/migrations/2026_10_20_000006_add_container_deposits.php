<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Returnable container deposits: the deposit per product unit and the journal of container
 * returns (refunded in cash or credited to an order), with or without a source order.
 *
 * @since 1.5.0
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('s_products', 'deposit')) {
            Schema::table('s_products', function (Blueprint $table) {
                $table->decimal('deposit', 12, 2)->unsigned()->default(0)->comment('Returnable container deposit per unit, in the product currency');
            });
        }

        if (!Schema::hasTable('s_container_returns')) {
            Schema::create('s_container_returns', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('reference', 191)->unique('uniq_container_return_reference')->comment('Idempotency key from the manager form');
                $table->unsignedBigInteger('source_order_id')->nullable()->index()->comment('Order that charged the deposit, if known');
                $table->string('line_key', 64)->nullable();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('title', 255)->default('');
                $table->unsignedInteger('quantity');
                $table->decimal('unit_amount', 12, 2);
                $table->decimal('amount', 12, 2);
                $table->char('currency', 3);
                $table->string('settlement', 16)->comment('cash|credit');
                $table->unsignedBigInteger('credit_order_id')->nullable()->index()->comment('Order the amount was credited to');
                $table->unsignedInteger('user_id')->default(0)->index();
                $table->json('customer')->nullable()->comment('Name, phone and email of a customer without an account');
                $table->string('terms', 255)->default('')->comment('Contract wording that defines the container as returnable, at the time of return');
                $table->string('note', 255)->default('');
                $table->unsignedInteger('actor_id')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('s_container_returns');
        if (Schema::hasColumn('s_products', 'deposit')) {
            Schema::table('s_products', function (Blueprint $table) {
                $table->dropColumn('deposit');
            });
        }
    }
};
