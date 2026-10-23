<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Seiger\sCommerce\Models\sPaymentMethod;

/**
 * Rename the legacy `s_payment_methods` credentials column, whose first letter is
 * Cyrillic U+0441, to the Latin `credentials`.
 *
 * Laravel 12 uses native RENAME COLUMN (MySQL 8+, MariaDB 10.5.2+, SQLite 3.25+, PostgreSQL).
 * Deploy the compatible sPaymentMethod model before running it: the model reads and writes
 * both spellings, while older code and direct SQL that use the legacy name do not.
 *
 * @since 1.5.0
 */
return new class extends Migration {
    private const TABLE = 's_payment_methods';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, sPaymentMethod::LEGACY_CREDENTIALS)) {
            return;
        }

        if (!Schema::hasColumn(self::TABLE, sPaymentMethod::CREDENTIALS)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->renameColumn(sPaymentMethod::LEGACY_CREDENTIALS, sPaymentMethod::CREDENTIALS);
            });
            sPaymentMethod::forgetCredentialsColumn();
            return;
        }

        // Both columns exist after a partial manual fix: keep non-empty canonical values.
        $conflicts = [];
        $rows = DB::table(self::TABLE)->select(['id', sPaymentMethod::LEGACY_CREDENTIALS, sPaymentMethod::CREDENTIALS])->get();
        foreach ($rows as $row) {
            $legacy = $row->{sPaymentMethod::LEGACY_CREDENTIALS};
            $current = $row->{sPaymentMethod::CREDENTIALS};
            if ($this->isEmpty($legacy)) {
                continue;
            }
            if ($this->isEmpty($current)) {
                DB::table(self::TABLE)->where('id', $row->id)->update([sPaymentMethod::CREDENTIALS => $legacy]);
            } elseif (json_decode((string)$legacy, true) !== json_decode((string)$current, true)) {
                $conflicts[] = (int)$row->id;
            }
        }

        if ($conflicts !== []) {
            // Never drop differing secrets; only ids are logged, never credential values.
            Log::warning('sCommerce credentials migration kept the legacy column: both spellings hold different values', [
                'payment_method_ids' => $conflicts,
            ]);
        } else {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropColumn(sPaymentMethod::LEGACY_CREDENTIALS);
            });
        }
        sPaymentMethod::forgetCredentialsColumn();
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)
            || !Schema::hasColumn(self::TABLE, sPaymentMethod::CREDENTIALS)
            || Schema::hasColumn(self::TABLE, sPaymentMethod::LEGACY_CREDENTIALS)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->renameColumn(sPaymentMethod::CREDENTIALS, sPaymentMethod::LEGACY_CREDENTIALS);
        });
        sPaymentMethod::forgetCredentialsColumn();
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || trim((string)$value) === '') {
            return true;
        }

        $decoded = json_decode((string)$value, true);
        return json_last_error() === JSON_ERROR_NONE && ($decoded === [] || $decoded === null);
    }
};
