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
        $db->table('s_products')->insert(['id' => 1, 'weight' => 125, 'width' => 1235, 'volume' => 1.5]);
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
        $check($product->physicalValues()['weight'] === 0, 'Migration leaves existing attribute values unchanged');
        $check($product->physicalValues()['width'] === 0, 'Reads never fall back to legacy columns');
        $product->save();
        $product = $product->fresh();
        $check($product->physicalValues()['weight'] === 125, 'Positive legacy column replaces attribute on save');
        $check($product->physicalValues()['width'] === 1235, 'Backfill whole values');
        $check($db->table('s_products')->where('id', 1)->value('weight') == 0, 'Zero consumed legacy column');
        $check($product->physicalValues()['volume'] === 0 && $db->table('s_products')->where('id', 1)->value('volume') == 1.5,
            'Fractional legacy value has no known unit and stays for manual entry');
        $check($db->table('s_attributes')->where('id', 50)->value('helptext') === 'Existing merchant setting', 'Preserve merchant metadata');
        $check($db->table('s_product_attribute_values')->count() === 2, 'Consume only positive whole legacy columns');
        $db->table('s_products')->where('id', 1)->update(['width' => 3250]);
        $db->getConnection()->statement("CREATE TRIGGER physical_failure BEFORE UPDATE ON s_product_attribute_values BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        try {
            $failing = sProduct::withoutGlobalScopes()->findOrFail(1);
            $failing->save();
            $check(false, 'Simulated pivot failure must interrupt save');
        } catch (Illuminate\Database\QueryException) {
            $check($db->table('s_products')->where('id', 1)->value('width') == 3250, 'Pivot failure rolls back column reset');
            $check(sProduct::withoutGlobalScopes()->findOrFail(1)->physicalValues()['width'] === 1235, 'Pivot failure preserves attribute');
        }
        $db->getConnection()->statement('DROP TRIGGER physical_failure');
        $failing->save();
        $check($db->table('s_products')->where('id', 1)->value('width') == 0, 'Retry on same model consumes legacy column after rollback');
        $source = sProduct::withoutGlobalScopes()->findOrFail(1);
        $source->save();
        $source->setPhysicalValue('width', '2500')->save();
        $source->save();
        $check($source->fresh()->physicalValues()['width'] === 2500, 'Consumed zero column does not overwrite edited attribute');
        $source->setPhysicalValue('volume', 1500)->save();
        $check($source->fresh()->physicalValues()['volume'] === 1500 && $db->table('s_products')->where('id', 1)->value('volume') == 0,
            'Explicit value replaces a fractional legacy column');
        $clone = new sProduct();
        $clone->save();
        foreach ($source->attrValues as $attribute) {
            $clone->attrValues()->attach($attribute->id, ['valueid' => $attribute->pivot->valueid, 'value' => $attribute->pivot->value]);
        }
        $check($clone->fresh()->physicalValues() === $source->physicalValues(), 'Copy physical values through ordinary attribute rows');
        $check($db->table('s_products')->where('id', $clone->id)->value('width') == 0, 'Copy never repopulates static columns');

    }

    $product = new sProduct();
    $product->setPhysicalValue('weight', '1000,0000');
    $product->setPhysicalValue('width', 1235);
    $product->setPhysicalValue('volume', '500');
    $check($product->physicalValues()['weight'] === 1000, 'Read pending physical assignment');
    $product->save();
    $saved = $product->fresh();
    $check($saved->attribute('weight')->value === 1000 && $saved->attribute('weight')->label === '1kg', 'Attribute helper labels grams with a unit');
    $check($saved->attribute('width')->label === '1.235m' && $saved->attribute('volume')->label === '500ml', 'Attribute helper labels millimetres and millilitres');
    $check($saved->physicalValues()['weight'] === 1000 && $saved->physicalValues()['width'] === 1235, 'Persist explicit physical writes as attributes');
    $saved->setAppends([]);
    $payload = $saved->physicalValues();
    $check($payload['weight'] === 1000 && $payload['volume'] === 500, 'Build delivery values without legacy columns');
    $check($payload['height'] === 0, 'Missing property defaults to zero');
    $check($saved->physicalPayload() === $payload, 'Cart payload equals readable physical values');
    $saved->setPhysicalValue('weight', 0);
    $saved->save();
    $check($saved->fresh()->physicalValues()['weight'] === 0, 'Explicit zero survives update');
    $count = $db->table('s_product_attribute_values')->count();
    $saved->save();
    $check($db->table('s_product_attribute_values')->count() === $count, 'Unrelated save does not duplicate values');
    foreach (['-1', 'invalid', '0.5', '1,25', -3, 1.5] as $value) {
        try {
            $saved->setPhysicalValue('weight', $value);
            $check(false, 'Reject invalid physical value');
        } catch (InvalidArgumentException) {
            $check(true, 'Reject invalid physical value');
        }
    }
    $saved->setPhysicalValue('weight', '25');
    $saved->saveQuietly();
    $check($saved->fresh()->physicalValues()['weight'] === 25, 'Quiet saves persist physical attributes');
    $weightId = $db->table('s_attributes')->where('alias', 'weight')->value('id');
    $db->table('s_product_attribute_values')->where('product', $saved->id)->where('attribute', $weightId)->update(['value' => '0.0125']);
    $stale = $saved->fresh();
    try {
        $stale->physicalValues();
        $check(false, 'Fractional stored measurement is not rounded');
    } catch (InvalidArgumentException) {
        $check(!array_key_exists('weight', $stale->physicalPayload()) && $stale->physicalPayload()['volume'] === 500,
            'Cart payload leaves out an unreadable measurement and keeps the others');
    }
    $db->table('s_product_attribute_values')->where('product', $saved->id)->where('attribute', $weightId)->update(['value' => '25']);
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
        $invalid->setPhysicalValue('height', '125');
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
$fresh->setPhysicalValue('weight', '125');
$fresh->save();
$check($fresh->fresh()->physicalValues()['weight'] === 125, 'Real installation accepts a whole-number physical value');
foreach ([['weight', 0, '0g'], ['weight', 250, '250g'], ['weight', 1000, '1kg'], ['weight', 1500, '1.5kg'], ['length', 7, '7mm'],
             ['length', 255, '25.5cm'], ['height', 2000, '2m'], ['volume', 330, '330ml'], ['volume', 1750, '1.75l']] as [$alias, $value, $label]) {
    $check(sProduct::physicalLabel($alias, $value) === $label, "Label {$value} {$alias} as {$label}");
}
$check(sProduct::normalizePhysicalValue('1000.0000') === 1000 && sProduct::normalizePhysicalValue(null) === 0 && sProduct::normalizePhysicalValue('+007') === 7,
    'Accept whole numbers in decimal column notation');
$check(sProduct::physicalUnit('weight') === 'g' && sProduct::physicalUnit('height') === 'mm' && sProduct::physicalUnit('volume') === 'ml'
    && sProduct::physicalUnit('sku') === '', 'Entry units are grams, millimetres and millilitres');
echo "OK: {$checks} checks; isolated SQLite, no CMS database.\n";
