<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Seiger\sTask\Models\sWorker;

/**
 * Creates the store of manager payment operations (capture, refund, cancel) and
 * registers the hidden sTask worker that reconciles operations and offline settlements.
 *
 * An operation row is persisted before the provider call, so a retry or a crash never
 * repeats a money movement. The ledger (`s_order_payments`) records only money facts.
 *
 * @since 1.5.0
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('s_payment_operations')) {
            Schema::create('s_payment_operations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('operation_key', 191)->unique('uniq_payment_operation_key')->comment('{action}/{orderId}/{paymentId}/{formNonce}');
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('payment_id')->index()->comment('Ledger row the action applies to');
                $table->string('action', 16)->comment('capture|refund|cancel');
                $table->decimal('amount', 12, 2)->nullable();
                $table->char('currency', 3);
                $table->string('reason', 255)->nullable();
                $table->char('request_hash', 64);
                $table->string('state', 16)->default('in_flight')->index()->comment('in_flight|pending|succeeded|failed|unknown');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->unsignedInteger('actor_id')->default(0);
                $table->json('context')->nullable();
                $table->string('provider_ref', 255)->nullable();
                $table->unsignedBigInteger('ledger_payment_id')->nullable()->comment('Ledger row written for the result');
                $table->json('result')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
                $table->foreign('order_id')->references('id')->on('s_orders')->onDelete('cascade');
                $table->foreign('payment_id')->references('id')->on('s_order_payments')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('s_workers') && class_exists(sWorker::class)) {
            sWorker::updateOrCreate(
                ['identifier' => 'sCommercePaymentReconciliation'],
                [
                    'scope' => 'sCommerce',
                    'class' => 'Seiger\sCommerce\Integration\PaymentReconciliation',
                    'active' => true,
                    'position' => ((int) sWorker::max('position')) + 1,
                    'hidden' => 1,
                    'settings' => [
                        'schedule' => [
                            'type' => 'periodic',
                            'enabled' => true,
                            'frequency' => 'every_15min',
                            'time' => '',
                            'days' => [],
                        ],
                    ],
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('s_workers') && class_exists(sWorker::class)) {
            sWorker::where('identifier', 'sCommercePaymentReconciliation')->delete();
        }

        Schema::dropIfExists('s_payment_operations');
    }
};
