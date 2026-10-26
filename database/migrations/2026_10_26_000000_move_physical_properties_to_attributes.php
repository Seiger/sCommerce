<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Install missing physical attributes while preserving existing numeric and select types.
 *
 * The migration is additive and rerunnable. Legacy values are consumed on product save;
 * alias conflicts fail before any definitions are written.
 *
 * @since 1.4.0
 */
return new class extends Migration {
    private const FIELDS = ['weight', 'width', 'height', 'length', 'volume'];

    /**
     * Seed globally available numeric attributes for fresh and upgraded installations.
     *
     * @since 1.4.0
     * @return void
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (self::FIELDS as $alias) {
                $matches = DB::table('s_attributes')->where('alias', $alias)->lockForUpdate()->get();
                if ($matches->count() > 1 || ($matches->count() === 1 && !in_array((int) $matches[0]->type, [0, 3], true))) {
                    throw new RuntimeException("Cannot migrate physical attribute {$alias}: expected one numeric or select attribute.");
                }
            }
            foreach (self::FIELDS as $alias) {
                $attribute = DB::table('s_attributes')->where('alias', $alias)->first();
                $id = $attribute?->id ?? DB::table('s_attributes')->insertGetId([
                    'alias' => $alias, 'type' => 0, 'published' => 1, 'asfilter' => 0,
                    'position' => 0, 'helptext' => '', 'created_at' => now(), 'updated_at' => now(),
                ]);
                if (!DB::table('s_attribute_category')->where('attribute', $id)->where('category', 0)->exists()) {
                    DB::table('s_attribute_category')->insert(['attribute' => $id, 'category' => 0]);
                }
                foreach (glob(dirname(__DIR__, 2) . '/lang/*/global.php') as $file) {
                    $lang = basename(dirname($file));
                    $labels = require $file;
                    if (!DB::table('s_attribute_translates')->where('attribute', $id)->where('lang', $lang)->exists()) {
                        DB::table('s_attribute_translates')->insert([
                            'attribute' => $id, 'lang' => $lang, 'pagetitle' => $labels[$alias] ?? ucfirst($alias),
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
                if (!DB::table('s_attribute_translates')->where('attribute', $id)->where('lang', 'base')->exists()) {
                    DB::table('s_attribute_translates')->insert([
                        'attribute' => $id, 'lang' => 'base', 'pagetitle' => ucfirst($alias),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

            }
        });
    }

    /**
     * Retain attributes and values on rollback to avoid deleting merchant data.
     *
     * @since 1.4.0
     * @return void
     */
    public function down(): void
    {
        // Data may have been edited since migration; removal is intentionally non-destructive.
    }
};
