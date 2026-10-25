<?php

require __DIR__ . '/bootstrap.php';
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Collection;
/**
 * Provide a controlled fixture for package behavior comparison.
 *
 * @since 1.4.0
 */
class TreeFixtureCategory extends Model
{
    protected $table = 'categories';
    public $timestamps = false;
    /**
     * Apply fixture scopes through the real Eloquent query builder.
     *
     * @since 1.4.0
     */
    protected static function booted()
    {
        static::addGlobalScope('deleted', fn($q) => $q->where('deleted', 0));
    }
    /**
     * Expose the fixture child relation used by both traversal implementations.
     *
     * @since 1.4.0
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent')->withoutGlobalScope('deleted');
    }
    /**
     * Reproduce the original existence query for the baseline traversal.
     *
     * @since 1.4.0
     */
    public function hasChildren()
    {
        return self::where('parent', $this->id)->count() > 0;
    }
    /**
     * Apply the same publication and deletion predicates as the category fixture.
     *
     * @since 1.4.0
     */
    public function scopeActive($q)
    {
        return $q->where('published', 1)->where('deleted', 0);
    }
}
/**
 * Provide a controlled fixture for package behavior comparison.
 *
 * @since 1.4.0
 */
class TreeProbeController extends Seiger\sCommerce\Controllers\sCommerceController
{
    public string $locale = 'en';
    public array $hooks = [];
    /**
     * Apply deterministic localized text without bootstrapping manager services.
     *
     * @since 1.4.0
     */
    protected function applyCategoryTranslation(object $category): void
    {
        $this->hooks[] = 'single:' . $category->id;
        $category->pagetitle = $this->locale . ':' . $category->id;
    }
    /**
     * Record batch translation order while applying fixture locale values.
     *
     * @since 1.4.0
     */
    protected function applyCategoriesTranslations(Collection $categories): void
    {
        $this->hooks[] = 'batch:' . implode(',', $categories->modelKeys());
        foreach ($categories as $category) {
            $category->pagetitle = $this->locale . ':' . $category->id;
        }
    }
}
$method = str_replace('sCategory::whereId(0)', '\TreeFixtureCategory::whereId(0)', file_get_contents(__DIR__ . '/category-tree-baseline.txt'));
eval('class LegacyTreeProbeController extends TreeProbeController {' . $method . '}');
$db = new Capsule();
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->setAsGlobal();
$db->bootEloquent();
$c = $db->getConnection();
$c->statement('create table categories(id integer primary key,parent integer,published integer,deleted integer,menuindex integer,pagetitle text)');
foreach ([[1, 0, 1, 0, 0], [2, 1, 1, 0, 20], [3, 1, 1, 0, 10], [4, 1, 0, 0, 5], [5, 1, 1, 1, 1], [6, 2, 1, 0, 0], [7, 6, 1, 0, 0], [8, 3, 0, 0, 0], [9, 3, 1, 1, 0]] as $r) {
    $c->table('categories')->insert(array_combine(['id', 'parent', 'published', 'deleted', 'menuindex'], $r) + ['pagetitle' => 'base']);
}
/**
 * Support isolated package assertions without a site bootstrap.
 *
 * @since 1.4.0
 */
function snapshot($node)
{
    return [$node->id, $node->pagetitle, $node->subcategories::class, $node->subcategories->map(fn($n) => snapshot($n))->all()];
}
foreach (['en', 'uk', 'base'] as $locale) {
    foreach ([-1, 0, 1, 2, 3] as $depth) {
        foreach ([1, 3, 7] as $rootId) {
            $old = (new ReflectionClass(LegacyTreeProbeController::class))->newInstanceWithoutConstructor();
            $new = (new ReflectionClass(TreeProbeController::class))->newInstanceWithoutConstructor();
            $old->locale = $new->locale = $locale;
            $a = TreeFixtureCategory::find($rootId);
            $b = TreeFixtureCategory::find($rootId);
            $c->enableQueryLog();
            $c->flushQueryLog();
            $old->listSubCategories($a, $depth);
            $oldQueries = $c->getQueryLog();
            $c->flushQueryLog();
            $new->listSubCategories($b, $depth);
            $newQueries = $c->getQueryLog();
            if (snapshot($a) !== snapshot($b)) {
                throw new RuntimeException('Tree differs ' . $locale . '/' . $depth . '/' . $rootId);
            }
            foreach ($newQueries as $q) {
                if (str_contains($q['query'], 'count(*)') || in_array(0, $q['bindings'], true) && str_contains($q['query'], '"id" = ?')) {
                    throw new RuntimeException('Sentinel/count query remains');
                }
            }
            if ($depth === 0 && count($newQueries) !== 0) {
                throw new RuntimeException('Depth boundary performs SQL');
            }
            if (count($newQueries) >= count($oldQueries)) {
                throw new RuntimeException('Expected query reduction');
            }
            echo 'PASS ' . $locale . ' depth=' . $depth . ' root=' . $rootId . ' queries=' . count($oldQueries) . '->' . count($newQueries) . PHP_EOL;
        }
    }
}
echo 'PHP ' . PHP_VERSION . PHP_EOL;
