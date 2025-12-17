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
            $table->tinyInteger('published')->unsigned()->default(0)->index()->comment('0-Unpublished|1-Published');
            $table->tinyInteger('asfilter')->unsigned()->default(0)->index()->comment('0-Not filter|1-Using as filter');
            $table->integer('position')->unsigned()->default(0)->index()->comment('Position the Attribute in list');
            $table->integer('type')->unsigned()->default(0)->comment('Type of input the attribute');
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
            $table->integer('position')->unsigned()->default(0)->index()->comment('Position the Attribute value in list');
            $table->string('alias', 512)->index()->comment('It using for generate url');
            $table->string('code', 512)->comment('Code of Attribute value e.x. blue, #007bff');
            $table->tinyText('base')->default('')->comment('Base translate for Title');
            $table->unique(['attribute', 'alias']);
            $table->timestamps();
        });

        Schema::create('s_attribute_category', function (Blueprint $table) {
            $table->foreignId('attribute')->comment('Attribute ID')->constrained('s_attributes')->cascadeOnDelete();
            $table->integer('category')->unsigned()->default(0)->index()->comment('Resource ID as Category');
        });

        /*
        |--------------------------------------------------------------------------
        | The product's tables structure
        |--------------------------------------------------------------------------
        */
        Schema::create('s_products', function (Blueprint $table) {
            $table->id('id');
            $table->uuid('uuid')->unique()->nullable();
            $table->smallInteger('published')->unsigned()->default(0)->index()->comment('0-Unpublished|1-Published');
            $table->smallInteger('availability')->unsigned()->default(0)->index()->comment('0-Not available|1-In stock|2-On order');
            $table->string('sku')->index()->comment('It is the SKU Product code');
            $table->string('gtin', 50)->nullable()->index()->comment('Global Trade Item Number (GTIN) - UPC, EAN, JAN, ISBN, or ITF-14 for Google Merchant');
            $table->string('alias', 512)->index()->comment('It using for generate url');
            $table->integer('views')->unsigned()->default(0)->index()->comment('Count view the product');
            $table->integer('rating')->unsigned()->default(5)->index()->comment('Rating the product base on votes');
            $table->smallInteger('mode')->unsigned()->default(0)->comment('Type the product');
            $table->decimal('price_regular', 9, 2)->unsigned()->default(0)->comment('The regular price of the product');
            $table->decimal('price_special', 9, 2)->unsigned()->default(0)->comment('The special price of the product');
            $table->decimal('price_opt_regular', 9, 2)->unsigned()->default(0)->comment('The wholesale price of the product');
            $table->decimal('price_opt_special', 9, 2)->unsigned()->default(0)->comment('The special wholesale price of the product');
            $table->decimal('weight', 11, 4)->unsigned()->default(0)->comment('The weight of production is indicated if necessary for technical purposes');
            $table->decimal('width', 11, 4)->unsigned()->default(0)->comment('The width of production is indicated if necessary for technical purposes');
            $table->decimal('height', 11, 4)->unsigned()->default(0)->comment('The height of production is indicated if necessary for technical needs');
            $table->decimal('length', 11, 4)->unsigned()->default(0)->comment('The length of production is indicated if necessary for technical needs');
            $table->decimal('volume', 11, 4)->unsigned()->default(0)->comment('The volume of production is indicated if necessary for technical needs');
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
            $table->integer('category')->unsigned()->default(0)->index()->comment('Resource ID as Category');
            $table->string('scope')->index()->default('');
            $table->integer('position')->unsigned()->default(0)->comment('Product position in Category');
        });

        Schema::create('s_product_attribute_values', function (Blueprint $table) {
            $table->foreignId('product')->comment('Product ID')->constrained('s_products')->cascadeOnDelete();
            $table->foreignId('attribute')->comment('Attribute ID')->constrained('s_attributes')->cascadeOnDelete();
            $table->integer('valueid')->unsigned()->default(0)->index()->comment('This is Id if the attribute value is given as an element from the data set of values');
            $table->text('value')->index()->comment('It using for value if valueid is null');
        });

        Schema::create('s_product_modifications', function (Blueprint $table) {
            $table->foreignId('product')->comment('Product ID')->constrained('s_products')->cascadeOnDelete();
            $table->smallInteger('mode')->unsigned()->default(0)->index()->comment('Modification type (group, option, variation)');
            $table->string('sku')->index()->comment('Unique modification code');
            $table->jsonb('mods')->default(new Expression('(JSON_ARRAY())'))->comment('JSON object with modification attributes (e.g. size, color)');
            $table->jsonb('parameters')->default(new Expression('(JSON_ARRAY())'))->comment('JSON object with modification parameters (e.g. size, color)');
            $table->decimal('price_modifier', 9, 2)->nullable()->comment('Price change (+/-) for modification');
            $table->integer('inventory')->nullable()->comment('Quantity in stock (NULL if not applicable)');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | The deliveries tables structure
        |--------------------------------------------------------------------------
        */
        Schema::create('s_delivery_methods', function (Blueprint $table) {
            $table->id('id');
            $table->uuid('uuid')->unique()->nullable();
            $table->string('name')->unique()->comment('Unique identifier for the delivery method');
            $table->string('class')->comment('PHP class implementing the delivery method');
            $table->boolean('active')->default(false)->comment('Indicates if the delivery method is active');
            $table->integer('position')->unsigned()->default(0)->comment('Sorting order');
            $table->jsonb('title')->default(new Expression('(JSON_ARRAY())'))->comment('Multilang Title of the delivery method');
            $table->jsonb('description')->default(new Expression('(JSON_ARRAY())'))->comment('Multilang Description of the delivery method');
            $table->decimal('cost', 9, 2)->default(0)->comment('Base cost of delivery');
            $table->decimal('minimum', 9, 2)->nullable()->comment('Minimum delivery cost');
            $table->text('formula')->nullable()->comment('Formula for dynamic cost calculation');
            $table->char('currency', 3)->default('USD')->comment('Currency cost this delivery');
            $table->jsonb('settings')->default(new Expression('(JSON_ARRAY())'))->comment('Additional settings for the delivery method (e.g. API integrations)');
            $table->string('icon')->nullable()->comment('Icon for the delivery method');
            $table->timestamps();
        });

        Schema::create('s_payment_methods', function (Blueprint $table) {
            $table->comment('Table that stores payment methods. This includes information such as credentials, settings and other relevant data for integrating and managing website payment systems.');
            $table->id('id');
            $table->uuid('uuid')->unique()->nullable();
            $table->string('name')->index()->comment('Unique identifier for the payment method');
            $table->string('class')->index()->comment('PHP class implementing the payment method');
            $table->string('identifier')->index()->default('')->comment('Unique identifier for each method');
            $table->boolean('active')->default(false)->comment('Indicates if the payment method is active');
            $table->integer('position')->unsigned()->default(0)->comment('Sorting order');
            $table->jsonb('title')->default(new Expression('(JSON_ARRAY())'))->comment('Multilang Title of the payment method');
            $table->jsonb('description')->default(new Expression('(JSON_ARRAY())'))->comment('Multilang Description of the payment method');
            $table->jsonb('сredentials')->default(new Expression('(JSON_ARRAY())'))->comment('Stores credentials for the payment method (e.g., API keys, merchant ID, secret keys)');
            $table->jsonb('settings')->default(new Expression('(JSON_ARRAY())'))->comment('Additional settings for the payment method (e.g. API integrations)');
            $table->string('mode')->default('')->comment('Mode of the payment system, such as \'test\' for test environments or \'production\' for live environments. If the payment system does not utilize modes, this field remains empty.');
            $table->string('icon')->nullable()->comment('Icon for the payment method');
            $table->timestamps();
        });

        Schema::create('s_orders', function (Blueprint $table) {
            $table->id('id');
            $table->bigInteger('user_id')->unsigned()->default(0)->index()->comment('User ID (if authorized)');
            $table->jsonb('user_info')->default(new Expression('(JSON_ARRAY())'))->comment('User information (JSON)');
            $table->jsonb('delivery_info')->default(new Expression('(JSON_ARRAY())'))->comment('Shipping information (JSON)');
            $table->jsonb('payment_info')->default(new Expression('(JSON_ARRAY())'))->comment('Payment information (JSON)');
            $table->jsonb('products')->default(new Expression('(JSON_ARRAY())'))->comment('Product list (JSON)');
            $table->decimal('cost', 9, 2)->default(0)->comment('Total order amount');
            $table->char('currency', 3)->default('USD')->comment('Currency cost this order');
            $table->integer('payment_status')->unsigned()->default(0)->comment('Payment status (0: pending, 1: completed, 2: failed, etc.)');
            $table->integer('status')->unsigned()->default(1)->comment('Order status (1: new)');
            $table->boolean('is_quick')->default(false)->comment('Flag indicating if the order is a quick purchase');
            $table->boolean('do_not_call')->default(false)->comment('"Do not call back" option');
            $table->text('comment')->nullable()->comment('Comment on the order');
            $table->string('lang', 10)->index()->default('base');
            $table->string('domain', 50)->index()->default('default');
            $table->jsonb('manager_info')->default(new Expression('(JSON_ARRAY())'))->comment('Manager information (JSON)');
            $table->jsonb('manager_notes')->default(new Expression('(JSON_ARRAY())'))->comment('Hidden Manager comments (available only in admin panel)');
            $table->jsonb('history')->default(new Expression('(JSON_ARRAY())'))->comment('History of changes');
            $table->string('identifier')->unique()->comment('Unique order key (required by some payment systems)');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable()->index();
        });

        /*
        |--------------------------------------------------------------------------
        | The review's tables structure
        |--------------------------------------------------------------------------
        */
        Schema::create('s_reviews', function (Blueprint $table) {
            $table->id('id');
            $table->string('lang', 10)->default('base')->index()->comment('The Lang field');
            $table->bigInteger('parent')->unsigned()->default(0)->index()->comment('If it is answer');
            $table->bigInteger('product')->unsigned()->default(0)->index()->comment('The product ID');
            $table->decimal('rating', 2, 1)->unsigned()->default(5)->comment('Rating the Review');
            $table->bigInteger('user')->unsigned()->default(0)->index()->comment('The product user ID');
            $table->string('name')->default('')->comment('The User name');
            $table->string('title')->default('')->comment('The Title of Message');
            $table->string('message')->default('')->comment('The Message');
            $table->tinyInteger('published')->unsigned()->default(0)->index()->comment('0-Unpublished|1-Published');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Create a wishlist and favorites fields as JSON columns
        |--------------------------------------------------------------------------
        */
        Schema::table('user_attributes', function (Blueprint $table) {
            $table->json('wishlist')->nullable()->default(new Expression('(JSON_ARRAY())'));
            $table->json('favorites')->nullable()->default(new Expression('(JSON_ARRAY())'));
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
        | Delete a wishlist and favorites fields as JSON columns
        |--------------------------------------------------------------------------
        */
        Schema::table('user_attributes', function (Blueprint $table) {
            $table->dropColumn(['wishlist', 'favorites']);
        });

        /*
        |--------------------------------------------------------------------------
        | The order's tables structure
        |--------------------------------------------------------------------------
        */
        Schema::dropIfExists('s_orders');
        Schema::dropIfExists('s_payment_methods');
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
