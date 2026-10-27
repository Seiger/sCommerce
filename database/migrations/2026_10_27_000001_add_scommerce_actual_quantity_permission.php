<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manager permission to set an actual quantity above the tolerance of a measured order line,
 * assigned to the administrator role.
 *
 * @since 1.5.0
 */
return new class extends Migration {
    /**
     * PostgreSQL aborts the whole transaction after the first failed statement; the inserts
     * below retry after duplicate-key races, so the migration runs without a transaction.
     * Every step is idempotent.
     *
     * @var bool
     */
    public $withinTransaction = false;

    /** @var array<string, string> Permission key => English name. */
    private const PERMISSIONS = [
        'scommerce_order_actual_over_tolerance' => 'sCommerce: exceed the actual-quantity tolerance',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions_groups') || !Schema::hasTable('permissions')) {
            return;
        }

        $groupId = $this->getOrCreateGroup();
        foreach (self::PERMISSIONS as $key => $name) {
            $this->upsertPermission($groupId, $key, $name);
            $this->assignPermissionToAdmin($key);
        }
    }

    public function down(): void
    {
        $keys = array_keys(self::PERMISSIONS);
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->whereIn('permission', $keys)->delete();
        }
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('key', $keys)->delete();
        }
        if (Schema::hasTable('permissions_groups')) {
            $group = DB::table('permissions_groups')->where('name', 'Seiger packages')->first();
            if ($group && !(Schema::hasTable('permissions') && DB::table('permissions')->where('group_id', $group->id)->exists())) {
                DB::table('permissions_groups')->where('id', $group->id)->delete();
            }
        }
    }

    protected function getOrCreateGroup(): int
    {
        $group = DB::table('permissions_groups')->where('name', 'Seiger packages')->first();
        if ($group) {
            return $group->id;
        }

        $row = ['name' => 'Seiger packages', 'lang_key' => 'seiger_packages', 'created_at' => now(), 'updated_at' => now()];
        try {
            return DB::table('permissions_groups')->insertGetId($row);
        } catch (QueryException $e) {
            $this->fixPostgresSequence('permissions_groups');
            try {
                return DB::table('permissions_groups')->insertGetId($row);
            } catch (QueryException $e2) {
                $group = DB::table('permissions_groups')->where('name', 'Seiger packages')->first();
                if ($group) {
                    return $group->id;
                }
                throw $e2;
            }
        }
    }

    protected function upsertPermission(int $groupId, string $key, string $name): void
    {
        $values = [
            'name' => $name,
            'lang_key' => 'sCommerce::global.permission_' . substr($key, strlen('scommerce_')),
            'group_id' => $groupId,
            'disabled' => 0,
            'updated_at' => now(),
        ];

        if (DB::table('permissions')->where('key', $key)->exists()) {
            DB::table('permissions')->where('key', $key)->update($values);
            return;
        }

        try {
            DB::table('permissions')->insert(['key' => $key, 'created_at' => now()] + $values);
        } catch (QueryException $e) {
            $this->fixPostgresSequence('permissions');
            if (!DB::table('permissions')->where('key', $key)->exists()) {
                DB::table('permissions')->insert(['key' => $key, 'created_at' => now()] + $values);
            }
            DB::table('permissions')->where('key', $key)->update($values);
        }
    }

    protected function assignPermissionToAdmin(string $key): void
    {
        if (!Schema::hasTable('role_permissions')
            || DB::table('role_permissions')->where('role_id', 1)->where('permission', $key)->exists()) {
            return;
        }

        try {
            DB::table('role_permissions')->insert([
                'role_id' => 1,
                'permission' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Inserted concurrently.
        }
    }

    protected function fixPostgresSequence(string $table): void
    {
        try {
            $fullTable = DB::getTablePrefix() . $table;
            $maxId = DB::table($table)->max('id') ?? 0;
            DB::statement("SELECT setval(pg_get_serial_sequence('{$fullTable}', 'id'), " . ($maxId + 1) . ", false)");
        } catch (\Exception $e) {
            // Not PostgreSQL, or no permission to change the sequence.
        }
    }
};
