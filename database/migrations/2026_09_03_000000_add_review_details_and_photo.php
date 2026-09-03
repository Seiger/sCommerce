<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade existing reviews to the details/photo schema shipped with fresh installs.
 *
 * Existing optional fields and longer message types are preserved so the migration
 * also supports stores that previously applied these changes manually.
 *
 * @since 1.4.0
 */
return new class extends Migration
{
    /**
     * Add missing review fields and widen messages without rewriting review rows.
     *
     * Repeated execution is safe. SQLite/PostgreSQL text already has sufficient
     * capacity; MySQL/MariaDB text is widened to mediumtext.
     *
     * @return void
     * @since 1.4.0
     */
    public function up(): void
    {
        if (!Schema::hasTable('s_reviews')) {
            return;
        }

        $columns = array_column(Schema::getColumns('s_reviews'), null, 'name');
        $comments = ['pros' => 'The Pros', 'cons' => 'The Cons', 'image' => 'The Image'];
        foreach ($comments as $column => $comment) {
            if (!isset($columns[$column])) {
                Schema::table('s_reviews', static function (Blueprint $table) use ($column, $comment): void {
                    $table->string($column)->default('')->comment($comment);
                });
            }
        }

        $message = $columns['message'] ?? null;
        if ($message === null) {
            return;
        }

        $type = strtolower($message['type_name']);
        $mysql = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
        if (in_array($type, ['mediumtext', 'longtext'], true) || (!$mysql && $type === 'text')) {
            return;
        }

        Schema::table('s_reviews', static function (Blueprint $table) use ($message): void {
            $table->mediumText('message')->nullable((bool) $message['nullable'])
                ->default('')->comment('The Message')->change();
        });
    }

    /**
     * Retain review data when rolling back the package version.
     *
     * Dropping fields could remove manually created columns, and shrinking messages
     * could truncate customer content. This additive migration is intentionally
     * non-destructive on rollback; reapplying it remains safe.
     *
     * @return void
     * @since 1.4.0
     */
    public function down(): void
    {
        // Keep the expanded schema and its data.
    }
};
