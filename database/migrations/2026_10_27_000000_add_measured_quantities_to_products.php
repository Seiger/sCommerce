<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products sold by weight, length, volume or count. Quantities stay integers in base units
 * (grams, millimetres, millilitres or items); NULL settings fall back to the site defaults.
 *
 * @since 1.5.0
 */
return new class extends Migration {
    /** @return array<string, callable(Blueprint): mixed> */
    private function columns(): array
    {
        return [
            'measure' => static fn (Blueprint $table) => $table->unsignedTinyInteger('measure')->default(0)->comment('0 piece, 1 weight, 2 length, 3 volume, 4 count'),
            'price_unit' => static fn (Blueprint $table) => $table->string('price_unit', 4)->nullable()->comment('Unit the price refers to, e.g. kg or m; NULL = site default'),
            'price_per' => static fn (Blueprint $table) => $table->unsignedInteger('price_per')->nullable()->comment('Price basis in price units, e.g. 100 for a price per 100 g; NULL = site default'),
            'quantity_step' => static fn (Blueprint $table) => $table->unsignedInteger('quantity_step')->nullable()->comment('Orderable step in base units; NULL = site default'),
            'quantity_min' => static fn (Blueprint $table) => $table->unsignedInteger('quantity_min')->nullable()->comment('Minimum order quantity in base units; NULL = site default'),
            'quantity_tolerance' => static fn (Blueprint $table) => $table->unsignedSmallInteger('quantity_tolerance')->nullable()->comment('Allowed actual-quantity overrun in basis points; NULL = site default'),
            'quantity_rounding' => static fn (Blueprint $table) => $table->string('quantity_rounding', 8)->nullable()->comment('up, nearest or down; NULL = site setting of the price unit'),
        ];
    }

    public function up(): void
    {
        foreach ($this->columns() as $column => $definition) {
            if (!Schema::hasColumn('s_products', $column)) {
                Schema::table('s_products', static function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse(array_keys($this->columns())) as $column) {
            if (Schema::hasColumn('s_products', $column)) {
                Schema::table('s_products', static function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
