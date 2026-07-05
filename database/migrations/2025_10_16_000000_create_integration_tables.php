<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Seiger\sTask\Models\sWorker;

/**
 * Migration: integrations storage.
 */
return new class extends Migration {
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Create a default Integrations
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('s_workers')) {
            return;
        }

        $integrations = [
            [
                'identifier' => 'sProductsListingCache',
                'scope' => 'sCommerce',
                'class' => 'Seiger\sCommerce\Integration\ProductsListingCache',
                'active' => true,
                'position' => 0,
                'hidden' => 1,
            ],
            [
                'identifier' => 'sImportExportCSV',
                'scope' => 'sCommerce',
                'class' => 'Seiger\sCommerce\Integration\ImportExportCSV',
                'active' => true,
                'position' => 1
            ]
        ];

        foreach ($integrations as $integration) {
            sWorker::updateOrCreate(
                ['identifier' => $integration['identifier']],
                $integration
            );
        }
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Delete a default Integrations
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('s_workers')) {
            return;
        }

        sWorker::where('identifier', 'sProductsListingCache')->delete();
        sWorker::where('identifier', 'sImportExportCSV')->delete();
    }
};
