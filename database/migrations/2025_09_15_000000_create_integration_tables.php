<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Schema;
use Seiger\sCommerce\Models\sIntegration;

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
            $table->comment('Table that stores integration configurations. This includes metadata such as integration keys, class names, active status, position ordering, and JSON-encoded settings for various sCommerce integrations like import/export and third-party services.');
            $table->id('id')->comment('Primary key - auto-incrementing ID');
            $table->uuid('uuid')->unique()->nullable()->comment('UUID for external system integration');
            $table->string('key')->unique()->comment('Unique identifier for the integration (e.g., "simpexpcsv", "payment_stripe")');
            $table->string('class')->comment('Full PHP class name implementing the integration (e.g., "Seiger\\sCommerce\\Integration\\ImportExportCSV")');
            $table->boolean('active')->default(false)->comment('Indicates if the integration is currently active and available for use');
            $table->integer('position')->unsigned()->default(0)->comment('Sorting order for display in administrative interface (lower numbers appear first)');
            $table->jsonb('settings')->default(new Expression('(JSON_ARRAY())'))->comment('JSON-encoded settings specific to this integration (API keys, endpoints, configuration options)');
            $table->integer('hidden')->unsigned()->default(0)->comment('Visibility flag: 0=visible, 1=hidden from all users, 2=hidden from non-admin users');
            $table->timestamps();
        });

        Schema::create('s_integration_tasks', function (Blueprint $table) {
            $table->comment('Table that stores integration task execution tracking. This includes task status, progress monitoring, user tracking, metadata storage, and result data for various integration operations like import/export, API synchronization, bulk data processing, and background job execution.');
            $table->bigIncrements('id')->comment('Primary key - auto-incrementing task ID');
            $table->string('slug', 191)->comment('Integration key reference (matches s_integrations.key)');
            $table->string('action', 64)->comment('Specific action being performed (e.g., "import", "export", "sync")');
            $table->unsignedSmallInteger('status')->default(10)->comment('Task execution status: 10=queued, 20=running, 30=finished, 40=failed, 50=cancelled');
            $table->string('message', 255)->nullable()->comment('Current status message or error description');
            $table->unsignedInteger('started_by')->nullable()->comment('User ID who initiated the task');
            $table->longText('meta')->nullable()->comment('JSON-encoded task metadata and configuration');
            $table->longText('result')->nullable()->comment('Task result data (file paths, statistics, etc.)');
            $table->timestamp('start_at')->nullable()->comment('Scheduled start time for the task');
            $table->timestamp('finished_at')->nullable()->comment('Actual completion time of the task');
            $table->timestamps();
            $table->index(['slug', 'action'])->comment('Composite index for integration-specific task queries');
            $table->index('status')->comment('Index for status-based filtering and monitoring');
            $table->index('started_by')->comment('Index for user-specific task queries');
            $table->index('start_at')->comment('Index for scheduled task processing');
            $table->index('created_at')->comment('Index for chronological task ordering');
        });

        /*
        |--------------------------------------------------------------------------
        | Create a default Integrations
        |--------------------------------------------------------------------------
        */
        $integrations = [
            [
                'key' => 'splc',
                'class' => 'Seiger\sCommerce\Integration\ProductsListingCache',
                'active' => true,
                'position' => sIntegration::max('position') + 1,
                'hidden' => true,
            ],
            [
                'key' => 'simpexpcsv',
                'class' => 'Seiger\sCommerce\Integration\ImportExportCSV',
                'active' => true,
                'position' => sIntegration::max('position') + 1,
                'hidden' => false
            ]
        ];

        foreach ($integrations as $integration) {
            sIntegration::create($integration);
        }
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