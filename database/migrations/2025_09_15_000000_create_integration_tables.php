<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: integrations storage.
 */
return new class extends Migration {
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | The integrations tables structure
        |--------------------------------------------------------------------------
        */
        Schema::create('s_integrations', function (Blueprint $table) {
            $table->id('id');
            $table->uuid('uuid')->unique()->nullable();
            $table->string('key')->unique()->comment('Unique identifier for the integration');
            $table->string('class')->comment('PHP class implementing the integration');
            $table->boolean('active')->default(false)->comment('Indicates if the integration is active');
            $table->integer('position')->unsigned()->default(0)->comment('Sorting order');
            $table->jsonb('settings')->default(new Expression('(JSON_ARRAY())'))->comment('Additional settings for the integration (e.g. API integrations)');
            $table->timestamps();
        });

        Schema::create('s_integration_tasks', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('slug', 191);
            $t->string('action', 64);
            $t->unsignedSmallInteger('status')->default(10);
            $t->string('message', 255)->nullable();
            $t->unsignedInteger('started_by')->nullable();
            $t->longText('meta')->nullable();
            $t->longText('result')->nullable();
            $t->timestamp('start_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
            $t->index(['slug', 'action']);
            $t->index('status');
            $t->index('started_by');
            $t->index('start_at');
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Delete a integrations tables structure
        |--------------------------------------------------------------------------
        */
        Schema::dropIfExists('s_integration_tasks');
        Schema::dropIfExists('s_integrations');
    }
};