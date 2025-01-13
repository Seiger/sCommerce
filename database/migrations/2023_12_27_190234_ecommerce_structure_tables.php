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
            $table->string('code', 512)->comment('Code of Attribute value e.x. blue, #007bff');
            $table->tinyText('base')->default('')->comment('Base translate for Title');
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
            $table->uuid('uuid')->default(DB::raw('(UUID())'));
            $table->unsignedTinyInteger('published')->default(0)->index()->comment('0-Unpublished|1-Published');
            $table->unsignedTinyInteger('availability')->default(0)->index()->comment('0-Not available|1-In stock|2-On order');
            $table->string('sku')->index()->comment('It is the SKU Product code');
            $table->string('alias', 512)->index()->comment('It using for generate url');
            $table->unsignedInteger('views')->default(0)->index()->comment('Count view the product');
            $table->unsignedInteger('rating')->default(5)->index()->comment('Rating the product base on votes');
            $table->unsignedInteger('type')->default(0)->comment('Type the product');
            $table->unsignedDecimal('price_regular', 9, 2)->default(0)->comment('The regular price of the product');
            $table->unsignedDecimal('price_special', 9, 2)->default(0)->comment('The special price of the product');
            $table->unsignedDecimal('price_opt_regular', 9, 2)->default(0)->comment('The wholesale price of the product');
            $table->unsignedDecimal('price_opt_special', 9, 2)->default(0)->comment('The special wholesale price of the product');
            $table->unsignedDecimal('weight', 11, 4)->default(0)->comment('The weight of production is indicated if necessary for technical purposes');
            $table->unsignedDecimal('width', 11, 4)->default(0)->comment('The width of production is indicated if necessary for technical purposes');
            $table->unsignedDecimal('height', 11, 4)->default(0)->comment('The height of production is indicated if necessary for technical needs');
            $table->unsignedDecimal('length', 11, 4)->default(0)->comment('The length of production is indicated if necessary for technical needs');
            $table->unsignedDecimal('volume', 11, 4)->default(0)->comment('The volume of production is indicated if necessary for technical needs');
            $table->integer('inventory')->default(0)->comment('Quantity products in stock');
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
            $table->string('scope')->index()->default('');
            $table->unsignedInteger('position')->default(0)->comment('Product position in Category');
        });

        Schema::create('s_product_attribute_values', function (Blueprint $table) {
            $table->foreignId('product')->comment('Product ID')->constrained('s_products')->cascadeOnDelete();
            $table->foreignId('attribute')->comment('Attribute ID')->constrained('s_attributes')->cascadeOnDelete();
            $table->unsignedInteger('valueid')->default(0)->index()->comment('This is Id if the attribute value is given as an element from the data set of values');
            $table->text('value')->comment('It using for value if valueid is null');
        });

        Schema::create('s_product_modifications', function (Blueprint $table) {
            $table->foreignId('product')->comment('Product ID')->constrained('s_products')->cascadeOnDelete();
            $table->unsignedInteger('type')->default(0)->index()->comment('Modification type (group, option, variation)');
            $table->string('sku')->index()->comment('Unique modification code');
            $table->jsonb('attributes')->default(new Expression('(JSON_ARRAY())'))->comment('JSON object with modification attributes (e.g. size, color)');
            $table->jsonb('parameters')->default(new Expression('(JSON_ARRAY())'))->comment('JSON object with modification parameters (e.g. size, color)');
            $table->decimal('price_modifier', 9, 2)->nullable()->comment('Price change (+/-) for modification');
            $table->integer('inventory')->nullable()->comment('Quantity in stock (NULL if not applicable)');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | The order's tables structure
        |--------------------------------------------------------------------------
        */
        Schema::create('s_delivery_methods', function (Blueprint $table) {
            $table->id('id');
            $table->string('title')->comment('Title of the delivery method');
            $table->string('name')->unique()->comment('Unique identifier for the delivery method');
            $table->string('class')->comment('PHP class implementing the delivery method');
            $table->boolean('active')->default(true)->comment('Indicates if the delivery method is active');
            $table->unsignedInteger('position')->default(0)->comment('Sorting order');
            $table->decimal('cost_fixed', 9, 2)->nullable()->comment('Fixed cost of delivery');
            $table->decimal('cost_minimum', 9, 2)->nullable()->comment('Minimum delivery cost');
            $table->text('cost_formula')->nullable()->comment('Formula for dynamic cost calculation');
            $table->json('regions')->nullable()->comment('Regions where the delivery method is available');
            $table->json('settings')->nullable()->comment('Additional settings for the delivery method');
            $table->string('icon')->nullable()->comment('Icon for the delivery method');
            $table->string('delivery_time')->nullable()->comment('Expected delivery time');
            $table->decimal('max_weight', 11, 4)->nullable()->comment('Maximum weight supported by the method');
            $table->text('description')->nullable()->comment('Description of the delivery method');
            $table->json('integration_data')->nullable()->comment('Data for API integrations');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | The review's tables structure
        |--------------------------------------------------------------------------
        */
        Schema::create('s_reviews', function (Blueprint $table) {
            $table->id('id');
            $table->string('lang', 10)->default('base')->index()->comment('The Lang field');
            $table->unsignedBigInteger('parent')->default(0)->index()->comment('If it is answer');
            $table->unsignedBigInteger('product')->default(0)->index()->comment('The product ID');
            $table->unsignedDecimal('rating', 2, 1)->default(5)->comment('Rating the Review');
            $table->unsignedBigInteger('user')->default(0)->index()->comment('The product user ID');
            $table->string('name')->default('')->comment('The User name');
            $table->string('title')->default('')->comment('The Title of Message');
            $table->string('message')->default('')->comment('The Message');
            $table->unsignedTinyInteger('published')->default(0)->index()->comment('0-Unpublished|1-Published');
            $table->timestamps();
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
        | The order's tables structure
        |--------------------------------------------------------------------------
        */
        Schema::dropIfExists('s_delivery_methods');

        /*
        |--------------------------------------------------------------------------
        | The review's tables structure
        |--------------------------------------------------------------------------
        */
        Schema::dropIfExists('s_reviews');

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
