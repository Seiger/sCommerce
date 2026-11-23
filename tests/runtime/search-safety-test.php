<?php

/** Run package search scopes with disposable SQLite through the runtime bootstrap. */
/**
 * Resolve services from the isolated search fixture container.
 *
 * @since 1.5.0
 */
function app($name = null)
{
    return $name === null ? $GLOBALS['searchApp'] : $GLOBALS['searchApp']->make($name);
}
/**
 * Read fixture settings without loading site configuration.
 *
 * @since 1.5.0
 */
function config($key, $default = null)
{
    return app('config')->get($key, $default);
}
/**
 * Expose the synthetic request to the actual search scopes.
 *
 * @since 1.5.0
 */
function request($key = null)
{
    return $key === null ? app('request') : app('request')->input($key);
}

require __DIR__ . '/bootstrap.php';

$GLOBALS['searchApp'] = new class extends Illuminate\Container\Container {
    public function getLocale(): string { return 'en'; }
};
$container = app();
$container->instance('config', new Illuminate\Config\Repository());
$capsule = new Illuminate\Database\Capsule\Manager($container);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'evo_']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$container->instance('db', $capsule->getDatabaseManager());
$container->instance('sCommerce', new class {
    public function config($key, $default = null) { return config('seiger.settings.sCommerce.' . $key, $default); }
});
Illuminate\Support\Facades\Facade::setFacadeApplication($container);
$schema = $capsule->schema();
$schema->create('s_products', static function ($table) {
    $table->integer('id');
    $table->string('sku');
    $table->integer('published');
});
$schema->create('s_product_translates', static function ($table) {
    $table->integer('product');
    $table->string('lang');
    foreach (['pagetitle', 'longtitle', 'introtext', 'content'] as $field) {
        $table->text($field)->default('');
    }
});
$schema->create('s_reviews', static function ($table) {
    $table->integer('id');
    $table->integer('product');
    $table->string('name');
    $table->text('message');
    $table->integer('published');
});
$schema->create('s_attributes', static function ($table) {
    $table->integer('id');
    foreach (['pagetitle', 'longtitle', 'introtext', 'content'] as $field) {
        $table->text($field)->default('');
    }
    $table->integer('published');
});
$texts = [
    1 => ['Georgia Explorer', ''],
    2 => ['Georgia', 'Explorer'],
    3 => ["O'Brien", ''],
    4 => ['support+tour@example.invalid', ''],
    5 => ['C:\\tours\\OBrien', ''],
    6 => ['100%_tour', ''],
];
foreach ($texts as $id => [$title, $extra]) {
    $capsule->table('s_products')->insert(['id' => $id, 'sku' => 'SKU-' . $id, 'published' => 1]);
    $capsule->table('s_product_translates')->insert([
        'product' => $id, 'lang' => 'en', 'pagetitle' => $title, 'introtext' => $extra,
    ]);
    $capsule->table('s_reviews')->insert([
        'id' => $id, 'product' => 100 + $id, 'name' => $title, 'message' => $extra, 'published' => 1,
    ]);
    $capsule->table('s_attributes')->insert([
        'id' => $id, 'pagetitle' => $title, 'introtext' => $extra, 'published' => 1,
    ]);
}
$models = [
    Seiger\sCommerce\Models\sProduct::class,
    Seiger\sCommerce\Models\sReview::class,
    Seiger\sCommerce\Models\sAttribute::class,
];
$checks = 0;
/**
 * Fail the run at the first behavioral regression.
 *
 * @since 1.5.0
 */
function checkSearch(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($label);
    }
    ++$checks;
}
foreach ($models as $model) {
    foreach ([
        'Georgia Explorer', "O'Brien", 'support+tour@example.invalid', 'C:\\tours\\OBrien', '100%_tour',
        "tre' OR 9071=(SELECT SLEEP(5) FROM db_root) AND 'ouJq'='ouJq",
        "1 AND 541697670314987555=CAST('so45if'||((SELECT (CASE WHEN (541697670314987555=541697670314987555) THEN 1 ELSE 0 END)))::text||'xph0ms' AS NUMERIC)",
    ] as $needle) {
        $container->instance('request', Illuminate\Http\Request::create('/search/', 'GET', ['search' => $needle]));
        $query = $model::query()->search()->active();
        checkSearch(!str_contains($query->toSql(), $needle), $model . ' uses placeholders for user values');
        $rows = $query->get();
        checkSearch($rows->count() <= count($texts), $model . ' executes input as data');
        checkSearch($query->getQuery()->getRawBindings()['select'] !== [], $model . ' binds score expressions');
    }
    $container->instance('request', Illuminate\Http\Request::create('/search/', 'GET', ['search' => "O'Brien"]));
    checkSearch($model::query()->search()->active()->get()->modelKeys() === [3], $model . ' searches apostrophes');

    $container->instance('request', Illuminate\Http\Request::create('/search/', 'GET', ['search' => 'Georgia Explorer']));
    $query = $model::query()->search()->active();
    $ranked = $query->get();
    checkSearch($ranked->modelKeys() === [1, 2], $model . ' preserves ranking');
    checkSearch($model !== Seiger\sCommerce\Models\sAttribute::class
        ? (int)$ranked[0]->points > (int)$ranked[1]->points
        : (int)$ranked[0]->points === 2 && (int)$ranked[1]->points === 2, $model . ' preserves scoring weights');
    checkSearch($query->paginate(1, ['*'], 'page', 2)->items()[0]->id === 2, $model . ' preserves pagination');
    checkSearch($capsule->table('s_products')->count() === 6, 'Search leaves fixture data intact');
}
foreach (['focused' => [1], 'all_words' => [1, 2], 'blurred' => [1, 2]] as $mode => $expected) {
    $container->make('config')->set('seiger.settings.sCommerce.basic.search', $mode);
    checkSearch(Seiger\sCommerce\Models\sProduct::query()->search()->active()->get()->modelKeys() === $expected,
        'Product search preserves ' . $mode . ' matching');
}
echo "PASS {$checks} search safety checks (SQLite memory, actual package models)\n";
