<?php

use EvolutionCMS\Models\SiteTemplate;
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
        | The attributes's tables structure
        |--------------------------------------------------------------------------
        */
        Schema::create('s_attributes', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedTinyInteger('published')->default(0)->index()->comment('0-Unpublished|1-Published');
            $table->unsignedTinyInteger('asfilter')->default(0)->index()->comment('0-Not filter|1-Using as filter');
            $table->unsignedInteger('position')->default(0)->index()->comment('Position the Attribute in list');
            $table->unsignedInteger('type')->default(0)->comment('Type of input the attribute');
            $table->string('alias', 512)->index()->comment('It using for generate url');
            $table->tinyText('helptext')->comment('Description about this Attribute in adminpanel');
            $table->timestamps();
        });

        Schema::create('s_attribute_translates', function (Blueprint $table) {
            $table->id('atid');
            $table->foreignId('attribute')->comment('Attribute ID')->constrained('s_attributes')->cascadeOnDelete();
            $table->string('lang', 10)->index()->default('base');
            $table->string('pagetitle', 255)->index()->default('');
            $table->string('longtitle', 512)->default('');
            $table->mediumText('introtext')->default('');
            $table->longText('content')->default('');
            $table->unique(['attribute', 'lang']);
            $table->timestamps();
        });

        Schema::create('s_attribute_values', function (Blueprint $table) {
            $table->id('avid');
            $table->foreignId('attribute')->comment('Attribute ID')->constrained('s_attributes')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0)->index()->comment('Position the Attribute value in list');
            $table->string('alias', 512)->index()->comment('It using for generate url');
            $table->tinyText('base')->default('');
            $table->unique(['attribute', 'alias']);
            $table->timestamps();
        });

        Schema::create('s_attribute_category', function (Blueprint $table) {
            $table->foreignId('attribute')->comment('Attribute ID')->constrained('s_attributes')->cascadeOnDelete();
            $table->unsignedInteger('category')->default(0)->index()->comment('Resource ID as Category');
        });

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
            $table->unsignedInteger('position')->default(0)->index()->comment('Position the product in list');
            $table->unsignedInteger('views')->default(0)->index()->comment('Count view the product');
            $table->unsignedInteger('rating')->default(5)->index()->comment('Rating the product base on votes');
            $table->unsignedInteger('type')->default(0)->comment('Type the product');
            $table->unsignedDecimal('price_regular', 9, 2)->default(0);
            $table->unsignedDecimal('price_special', 9, 2)->default(0);
            $table->unsignedDecimal('price_opt_regular', 9, 2)->default(0);
            $table->unsignedDecimal('price_opt_special', 9, 2)->default(0);
            $table->unsignedDecimal('weight', 11, 4)->default(0);
            $table->integer('quantity')->default(0)->comment('Quantity products in stock');
            $table->char('currency', 3)->default('USD')->comment('Currency price this product');
            $table->string('cover', 512)->default('')->comment('Cover image file link');
            $table->jsonb('relevants')->default(new Expression('(JSON_ARRAY())'));
            $table->jsonb('similar')->default(new Expression('(JSON_ARRAY())'));
            $table->jsonb('tmplvars')->default(new Expression('(JSON_ARRAY())'));
            $table->jsonb('votes')->default(new Expression('(JSON_ARRAY())'));
            $table->jsonb('additional')->default(new Expression('(JSON_ARRAY())'));
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
            $table->jsonb('builder')->default(new Expression('(JSON_ARRAY())'));
            $table->jsonb('constructor')->default(new Expression('(JSON_ARRAY())'));
            $table->unique(['product', 'lang']);
            $table->timestamps();
        });

        Schema::create('s_product_category', function (Blueprint $table) {
            $table->foreignId('product')->comment('Product ID')->constrained('s_products')->cascadeOnDelete();
            $table->unsignedInteger('category')->default(0)->index()->comment('Resource ID as Category');
        });

        Schema::create('s_product_attribute_values', function (Blueprint $table) {
            $table->foreignId('product')->comment('Product ID')->constrained('s_products')->cascadeOnDelete();
            $table->foreignId('attribute')->comment('Attribute ID')->constrained('s_attributes')->cascadeOnDelete();
            $table->unsignedInteger('valueid')->default(0)->index()->comment('This is Id if the attribute value is given as an element from the data set of values');
            $table->string('value', 1024)->index()->comment('It using for value if valueid is null');
        });

        /*
        |--------------------------------------------------------------------------
        | Create a Product template
        |--------------------------------------------------------------------------
        */
        $templateProduct = SiteTemplate::whereTemplatealias('s_commerce_product')->first();
        if (!$templateProduct) {
            $templateProduct = new SiteTemplate();
            $templateProduct->templatealias = 's_commerce_product';
            $templateProduct->templatename = 'sCommerce Product';
            $templateProduct->description = 'Template for sCommerce Product';
            $templateProduct->icon = 'fa fa-store';
            $templateProduct->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Delete a Product template
        |--------------------------------------------------------------------------
        */
        $templateProduct = SiteTemplate::whereTemplatealias('s_commerce_product')->delete();

        /*
        |--------------------------------------------------------------------------
        | The product's tables structure
        |--------------------------------------------------------------------------
        */
        Schema::dropIfExists('s_product_attribute_values');
        Schema::dropIfExists('s_product_category');
        Schema::dropIfExists('s_product_translates');
        Schema::dropIfExists('s_products');

        /*
        |--------------------------------------------------------------------------
        | The attribute's tables structure
        |--------------------------------------------------------------------------
        */
        Schema::dropIfExists('s_attribute_category');
        Schema::dropIfExists('s_attribute_values');
        Schema::dropIfExists('s_attribute_translates');
        Schema::dropIfExists('s_attributes');
    }
};
