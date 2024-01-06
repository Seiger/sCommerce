<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | The product's tables structure
        |--------------------------------------------------------------------------
        */
        Schema::create('s_products', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedTinyInteger('published')->default(0)->index()->comment('0-Unpublished|1-Published');
            $table->unsignedTinyInteger('availability')->default(0)->index()->comment('0-Not available|1-In stock|2-On order');
            $table->unsignedInteger('category')->default(0)->index()->comment('Resource ID as Category');
            $table->string('sku')->index()->comment('It is the SKU Product code');
            $table->string('alias', 512)->index()->comment('It using for generate url');
            $table->unsignedInteger('position')->default(0)->comment('Position the product in list');
            $table->unsignedInteger('views')->default(0)->comment('Count view the product');
            $table->integer('quantity')->default(0)->comment('Quantity products in stock');
            $table->unsignedDecimal('price_regular', 9, 2)->default(0);
            $table->unsignedDecimal('price_special', 9, 2)->default(0);
            $table->unsignedDecimal('price_opt_regular', 9, 2)->default(0);
            $table->unsignedDecimal('price_opt_special', 9, 2)->default(0);
            $table->unsignedDecimal('weight', 11, 4)->default(0);
            $table->string('cover', 512)->default('')->comment('Cover image file link');
            $table->jsonb('relevants')->default(new Expression('(JSON_ARRAY())'));
            $table->jsonb('similar')->default(new Expression('(JSON_ARRAY())'));
            $table->jsonb('tmplvars')->default(new Expression('(JSON_ARRAY())'));
            $table->jsonb('votes')->default(new Expression('(JSON_ARRAY())'));
            $table->enum('type', ['simple', 'variable', 'optional'])->default('simple');
            $table->string('representation')->default('default')->comment('Representation of product fields in the backend');
            $table->timestamps();
        });

        Schema::create('s_product_translates', function (Blueprint $table) {
            $table->id('tid');
            $table->foreignId('product')->comment('Product ID')->constrained('s_products')->cascadeOnDelete();
            $table->string('lang', 10)->index()->default('base');
            $table->string('pagetitle', 255)->index()->default('');
            $table->string('longtitle', 512)->default('');
            $table->mediumText('introtext')->default('');
            $table->longText('content')->default('');
            $table->string('seotitle', 100)->default('');
            $table->string('seodescription', 255)->default('');
            $table->enum('seorobots', ['index,follow', 'noindex,nofollow'])->default('index,follow');
            $table->jsonb('builder')->default(new Expression('(JSON_ARRAY())'));
            $table->jsonb('constructor')->default(new Expression('(JSON_ARRAY())'));
            $table->unique(['product', 'lang']);
            $table->timestamps();
        });

        Schema::create('s_product_category', function (Blueprint $table) {
            $table->foreignId('product')->comment('Product ID')->constrained('s_products')->cascadeOnDelete();
            $table->unsignedInteger('category')->default(0)->index()->comment('Resource ID as Category');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | The product's tables structure
        |--------------------------------------------------------------------------
        */
        Schema::dropIfExists('s_product_category');
        Schema::dropIfExists('s_product_translates');
        Schema::dropIfExists('s_products');
    }
};
