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
        Schema::create('s_products', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedTinyInteger('published')->default(0)->index()->comment('0-Unpublished|1-Published');
            $table->unsignedTinyInteger('availability')->default(0)->index()->comment('0-Not available|1-In stock|2-On order');
            $table->unsignedInteger('category')->default(0)->index()->comment('ID Resource as Category');
            $table->string('code')->index()->comment('It is the Product code');
            $table->string('alias', 512)->index()->comment('It using for generate url');
            $table->unsignedTinyInteger('type')->default(0)->comment('0-Simple|1-Variable|2-Optional');
            $table->unsignedInteger('position')->default(0)->comment('Position the product in list');
            $table->unsignedInteger('views')->default(0)->comment('Count view the product');
            $table->unsignedDecimal('price', 9, 2)->default(0);
            $table->unsignedDecimal('price_old', 9, 2)->default(0);
            $table->unsignedDecimal('weight', 11, 4)->default(0.00);
            $table->string('cover', 512)->default('')->comment('Cover image file link');
            $table->jsonb('relevants')->default(new Expression('(JSON_ARRAY())'));
            $table->jsonb('similar')->default(new Expression('(JSON_ARRAY())'));
            $table->jsonb('tmplvars')->default(new Expression('(JSON_ARRAY())'));
            $table->jsonb('votes')->default(new Expression('(JSON_ARRAY())'));
            $table->timestamps();
        });

        Schema::create('s_product_translates', function (Blueprint $table) {
            $table->id('tid');
            $table->foreignId('product')->constrained('s_products')->cascadeOnDelete()->index()->comment('Product ID');
            $table->string('lang', 4)->index()->default('base');
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('s_products');
    }
};
