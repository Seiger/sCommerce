<?php

function app($key = null) {
    $app = Illuminate\Container\Container::getInstance();
    return $key === null ? $app : $app->make($key);
}
function evo() { return app(); }
require __DIR__ . '/bootstrap.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Seiger\sCommerce\Models\sProduct;

$app = new class extends Container { public function getLocale() { return 'uk'; } };
Container::setInstance($app);
Facade::setFacadeApplication($app);
$db = new Manager($app);
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->setAsGlobal();
$db->setEventDispatcher(new Dispatcher($app));
$db->bootEloquent();
$app['config']->set('app.locale', 'uk');
$app->instance('db', $db->getDatabaseManager());
$app->instance('db.schema', $db->schema());
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$migration = require dirname(__DIR__, 2) . '/database/migrations/2026_10_26_000000_move_physical_properties_to_attributes.php';

foreach ([false, true] as $legacy) {
    foreach (['s_product_attribute_values', 's_attribute_category', 's_attribute_translates', 's_attributes', 's_products'] as $table) {
        $db->schema()->dropIfExists($table);
    }
    $db->schema()->create('s_attributes', static function (Blueprint $table): void {
        $table->id();
        $table->string('alias');
        $table->integer('type')->default(0);
        $table->integer('published')->default(0);
        $table->integer('asfilter')->default(0);
        $table->integer('position')->default(0);
        $table->text('helptext');
        $table->timestamps();
    });
    $db->schema()->create('s_attribute_category', static function (Blueprint $table): void {
        $table->integer('attribute');
        $table->integer('category');
    });
    $db->schema()->create('s_attribute_translates', static function (Blueprint $table): void {
        $table->id('atid');
        $table->integer('attribute');
        $table->string('lang');
        $table->string('pagetitle');
        $table->timestamps();
    });
    $db->schema()->create('s_product_attribute_values', static function (Blueprint $table): void {
        $table->integer('product');
        $table->integer('attribute');
        $table->integer('valueid')->default(0);
        $table->text('value');
    });
    $db->schema()->create('s_products', static function (Blueprint $table) use ($legacy): void {
        $table->id();
        $table->integer('rating')->default(0);
        $table->timestamps();
        if ($legacy) {
            foreach (sProduct::PHYSICAL_ATTRIBUTES as $alias) {
                $table->decimal($alias, 11, 4)->default(0);
            }
        }
    });
    if ($legacy) {
        $db->table('s_products')->insert(['id' => 1, 'weight' => 0.0125, 'width' => 1.2345, 'volume' => 0.0001]);
        $db->table('s_attributes')->insert(['id' => 50, 'alias' => 'weight', 'type' => 0, 'helptext' => 'Existing merchant setting']);
        $db->table('s_product_attribute_values')->insert(['product' => 1, 'attribute' => 50, 'valueid' => 0, 'value' => '0']);
    }
    $migration->up();
    $check($db->table('s_attributes')->count() === 5, 'Seed five physical attributes');
    $check($db->table('s_attribute_category')->where('category', 0)->count() === 5, 'Make attributes global');
    $check($db->table('s_attribute_translates')->where('lang', 'uk')->count() === 5, 'Seed Ukrainian titles');
    $translations = $db->table('s_attribute_translates')->count();
    $migration->up();
    $check($db->table('s_attributes')->count() === 5 && $db->table('s_attribute_category')->count() === 5, 'Rerun without duplicate definitions');
    $check($db->table('s_attribute_translates')->count() === $translations, 'Rerun preserves translations');
    $check($db->schema()->hasColumn('s_products', 'weight') === $legacy, 'Never add or drop legacy columns');

    if ($legacy) {
        $product = sProduct::withoutGlobalScopes()->findOrFail(1);
        $check($product->physicalValues()['weight'] === '0.0000', 'Migration leaves existing attribute values unchanged');
        $check($product->physicalValues()['width'] === '0.0000', 'Reads never fall back to legacy columns');
        $product->save();
        $product = $product->fresh();
        $check($product->physicalValues()['weight'] === '0.0125', 'Positive legacy column replaces attribute on save');
        $check($product->physicalValues()['width'] === '1.2345' && $product->physicalValues()['volume'] === '0.0001', 'Backfill fractional values');
        $check($db->table('s_products')->where('id', 1)->value('weight') == 0, 'Zero consumed legacy column');
        $check($db->table('s_attributes')->where('id', 50)->value('helptext') === 'Existing merchant setting', 'Preserve merchant metadata');
        $check($db->table('s_product_attribute_values')->count() === 3, 'Consume only positive legacy columns');
        $db->table('s_products')->where('id', 1)->update(['width' => 3.25]);
        $db->getConnection()->statement("CREATE TRIGGER physical_failure BEFORE UPDATE ON s_product_attribute_values BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        try {
            $failing = sProduct::withoutGlobalScopes()->findOrFail(1);
            $failing->save();
            $check(false, 'Simulated pivot failure must interrupt save');
        } catch (Illuminate\Database\QueryException) {
            $check($db->table('s_products')->where('id', 1)->value('width') == 3.25, 'Pivot failure rolls back column reset');
            $check(sProduct::withoutGlobalScopes()->findOrFail(1)->physicalValues()['width'] === '1.2345', 'Pivot failure preserves attribute');
        }
        $db->getConnection()->statement('DROP TRIGGER physical_failure');
        $failing->save();
        $check($db->table('s_products')->where('id', 1)->value('width') == 0, 'Retry on same model consumes legacy column after rollback');
        $source = sProduct::withoutGlobalScopes()->findOrFail(1);
        $source->save();
        $source->setPhysicalValue('width', '2.5')->save();
        $source->save();
        $check($source->fresh()->physicalValues()['width'] === '2.5000', 'Consumed zero column does not overwrite edited attribute');
        $clone = new sProduct();
        $clone->save();
        foreach ($source->attrValues as $attribute) {
            $clone->attrValues()->attach($attribute->id, ['valueid' => $attribute->pivot->valueid, 'value' => $attribute->pivot->value]);
        }
        $check($clone->fresh()->physicalValues() === $source->physicalValues(), 'Copy physical values through ordinary attribute rows');
        $check($db->table('s_products')->where('id', $clone->id)->value('width') == 0, 'Copy never repopulates static columns');

    }

    $product = new sProduct();
    $product->setPhysicalValue('weight', '0,0125');
    $product->setPhysicalValue('width', '1.2345');
    $product->setPhysicalValue('volume', '0.0001');
    $check($product->physicalValues()['weight'] === '0.0125', 'Read pending physical assignment');
    $product->save();
    $saved = $product->fresh();
    $check($saved->attribute('weight')->value === '0.0125', 'Attribute helper retains fractional weight');
    $check($saved->physicalValues()['weight'] === '0.0125' && $saved->physicalValues()['width'] === '1.2345', 'Persist explicit physical writes as attributes');
    $saved->setAppends([]);
    $payload = $saved->physicalValues();
    $check($payload['weight'] === '0.0125' && $payload['volume'] === '0.0001', 'Build delivery values without legacy columns');
    $check($payload['height'] === '0.0000', 'Missing property defaults to zero');
    $saved->setPhysicalValue('weight', 0);
    $saved->save();
    $check($saved->fresh()->physicalValues()['weight'] === '0.0000', 'Explicit zero survives update');
    $count = $db->table('s_product_attribute_values')->count();
    $saved->save();
    $check($db->table('s_product_attribute_values')->count() === $count, 'Unrelated save does not duplicate values');
    foreach (['-1', 'invalid'] as $value) {
        try {
            $saved->setPhysicalValue('weight', $value);
            $check(false, 'Reject invalid physical value');
        } catch (InvalidArgumentException) {
            $check(true, 'Reject invalid physical value');
        }
    }
    $saved->setPhysicalValue('weight', '0.025');
    $saved->saveQuietly();
    $check($saved->fresh()->physicalValues()['weight'] === '0.0250', 'Quiet saves persist physical attributes');
    $check(!$db->schema()->hasColumn('s_products', 'weight') || $saved->fresh()->getRawOriginal('weight') == 0, 'Attribute writes never refill legacy columns');
    $migration->down();
    $check($db->table('s_product_attribute_values')->count() === $count, 'Rollback retains merchant values');
    $db->table('s_attributes')->where('alias', 'height')->update(['type' => 5]);
    try {
        $migration->up();
        $check(false, 'Reject alias conflict');
    } catch (RuntimeException) {
        $check($db->table('s_product_attribute_values')->count() === $count, 'Alias conflict changes no product data');
    }
    $before = $db->table('s_products')->count();
    try {
        $invalid = new sProduct();
        $invalid->setPhysicalValue('height', '1.25');
        $invalid->save();
        $check(false, 'Reject incompatible definition before saving');
    } catch (InvalidArgumentException) {
        $check($db->table('s_products')->count() === $before, 'Failed compatibility write leaves no partial product');
    }
}

// Run the actual installation migration, not only a simulated new product schema.
foreach (['s_product_attribute_values', 's_attribute_category', 's_attribute_translates', 's_attributes', 's_products'] as $table) {
    $db->schema()->dropIfExists($table);
}
$db->schema()->create('site_templates', static function (Blueprint $table): void {
    $table->id();
    $table->string('templatealias');
    $table->string('templatename');
    $table->string('description');
    $table->string('icon');
    $table->integer('createdon')->nullable();
    $table->integer('editedon')->nullable();
});
$install = require dirname(__DIR__, 2) . '/database/migrations/2023_12_27_190234_ecommerce_structure_tables.php';
$install->up();
$migration->up();
foreach (sProduct::PHYSICAL_ATTRIBUTES as $alias) {
    $check(!$db->schema()->hasColumn('s_products', $alias), "Fresh installation has no static {$alias} column");
}
$check($db->table('s_attributes')->count() === 5, 'Real installation seeds five attributes');
$fresh = new sProduct();
$fresh->sku = 'test';
$fresh->alias = 'test';
$fresh->cover = '';
$fresh->setPhysicalValue('weight', '0.0125');
$fresh->save();
$check($fresh->fresh()->physicalValues()['weight'] === '0.0125', 'Real installation accepts a fractional physical value');
echo "OK: {$checks} checks; isolated SQLite, no CMS database.\n";
