<?php
// Isolated SQLite/cache checks. No CMS bootstrap or production database.
if (PHP_SAPI !== 'cli') { exit(1); }
function app($key = null) {
    $app = Illuminate\Container\Container::getInstance();
    return $key === null ? $app : $app->make($key);
}
function evo() { return app(); }
function request($key = null) { return $key === null ? app('request') : app('request')->input($key); }
require __DIR__ . '/bootstrap.php';
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sReview;
use Illuminate\Support\Facades\Cache;
$app = new class extends Illuminate\Container\Container {
    public int $documentIdentifier = 200;
    public int $invalidations = 0;
    public function getLocale() { return 'uk'; }
    public function getPlaceholder($key) { return false; }
    public function clearCache($type) { $this->invalidations++; Cache::flush(); }
};
Illuminate\Container\Container::setInstance($app);
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$app->instance('request', Illuminate\Http\Request::create('/catalog/example'));
$app->instance('cache', new Illuminate\Cache\Repository(new Illuminate\Cache\ArrayStore()));
$capsule = new Illuminate\Database\Capsule\Manager($app);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$capsule->setEventDispatcher(new Illuminate\Events\Dispatcher($app));
$capsule->bootEloquent();
$app->instance('db', $capsule->getDatabaseManager());
$app->bind('db.schema', fn () => $capsule->getConnection()->getSchemaBuilder());
$db = $capsule->getConnection();
$db->statement('CREATE TABLE s_products (id INTEGER PRIMARY KEY, published INTEGER, rating REAL)');
$db->statement('CREATE TABLE s_product_translates (product INTEGER, lang TEXT, pagetitle TEXT)');
$db->statement('CREATE TABLE s_product_category (product INTEGER, category INTEGER, scope TEXT, position INTEGER)');
$db->statement('CREATE TABLE s_reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, product INTEGER, published INTEGER, rating INTEGER, created_at TEXT, updated_at TEXT)');
foreach ([1, 2, 3] as $id) {
    $db->table('s_products')->insert(['id' => $id, 'published' => 1, 'rating' => 4.5]);
    $db->table('s_product_translates')->insert(['product' => $id, 'lang' => 'base', 'pagetitle' => 'Product ' . $id]);
    $db->table('s_product_category')->insert(['product' => $id, 'category' => 200, 'scope' => 'primary', 'position' => $id]);
}
$makeReview = function ($product, $published) {
    $review = new sReview();
    $review->forceFill(['product' => $product, 'published' => $published, 'rating' => 5]);
    $review->save();
    return $review;
};
$hidden = $makeReview(2, 0);
$makeReview(3, 0);
$visible = $makeReview(3, 1);
$checks = 0;
$check = function ($expected, $actual, $label) use (&$checks) {
    if ($expected !== $actual) { throw new RuntimeException($label . ': ' . var_export($actual, true)); }
    $checks++;
};
$query = fn () => sProduct::withoutGlobalScope('translate');
$check(0, $query()->find(1)->reviewsCount, 'no reviews');
$check(0, $query()->find(2)->reviews_count, 'all unpublished, snake-case accessor');
$check(1, $query()->find(3)->reviewsCount, 'mixed reviews');
$check(2, $query()->find(3)->reviews()->count(), 'manager relationship remains unfiltered');
$check(1, $query()->with('reviews')->find(3)->reviewsCount, 'loaded unfiltered relation');
$check(1, $query()->with('activeReviews')->find(3)->reviewsCount, 'loaded published relation');
$check(1, $query()->withCount('reviews')->find(3)->reviewsCount, 'unfiltered aggregate ignored');
Illuminate\Pagination\Paginator::currentPageResolver(fn () => 1);
Illuminate\Pagination\Paginator::currentPathResolver(fn () => '/catalog/example');
$db->enableQueryLog();
$db->flushQueryLog();
$products = (new Seiger\sCommerce\sCommerce())->setSort('position')->getProducts([1, 2, 3], 'uk');
$queries = count($db->getQueryLog());
$check([0, 0, 1], $products->pluck('reviews_count')->all(), 'catalogue aliases show published counts');
$check([0, 0, 1], $products->pluck('reviewsCount')->all(), 'camel-case matches catalogue');
$check($queries, count($db->getQueryLog()), 'preloaded counts avoid N+1 queries');
$cached = fn ($product) => Cache::rememberForever('product-' . $product, fn () => $query()->find($product)->reviewsCount);
$check(0, $cached(2), 'warm hidden-review cache');
$hidden->published = 1;
$hidden->save();
$check(1, $cached(2), 'publication refreshes cached count');
$hidden->published = 0;
$hidden->save();
$check(0, $cached(2), 'unpublication refreshes cached count');
$check(1, $cached(3), 'warm source product cache');
$visible->product = 1;
$visible->save();
$check(0, $cached(3), 'moving review refreshes old product');
$check(1, $cached(1), 'moving review refreshes new product');
$visible->delete();
$check(0, $cached(1), 'deletion refreshes count');
$before = $app->invalidations;
$makeReview(1, 0);
$check($before, $app->invalidations, 'hidden creation does not flush storefront cache');
$check(4.5, (float) $db->table('s_products')->where('id', 1)->value('rating'), 'rating remains unchanged');
echo "Published review count checks passed: {$checks}\n";
