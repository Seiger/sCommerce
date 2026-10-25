<?php

/**
 * Support isolated package assertions without a site bootstrap.
 *
 * @since 1.4.0
 */
function evo()
{
    return $GLOBALS['fixtureEvo'];
}
require __DIR__ . '/bootstrap.php';
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
/**
 * Provide a controlled fixture for package behavior comparison.
 *
 * @since 1.4.0
 */
class ParentWalkModel extends Model
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
     * Resolve the fixture parent through the real Eloquent connection.
     *
     * @since 1.4.0
     */
    public function getParent()
    {
        return $this->exists ? $this->find($this->parent) : null;
    }
}
class_alias(ParentWalkModel::class, 'Seiger\sCommerce\Models\sCategory');
$GLOBALS['fixtureEvo'] = new class
{
    public array $settings = ['site_start' => 1];
    /**
     * Expose only the controlled configuration used by this isolated regression.
     *
     * @since 1.4.0
     */
    public function getConfig($k, $d = null)
    {
        return $this->settings[$k] ?? $d;
    }
};
$method = str_replace('sCategory::find', '\ParentWalkModel::find', file_get_contents(__DIR__ . '/parents-baseline.txt'));
eval('class OldParentWalk extends \Seiger\sCommerce\Controllers\sCommerceController {' . $method . '}');
$db = new Capsule();
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->setAsGlobal();
$db->bootEloquent();
$c = $db->getConnection();
$c->statement('create table categories(id integer primary key,parent integer,deleted integer,published integer)');
foreach ([[14, 36, 0, 1], [36, 0, 0, 1], [101, 14, 0, 1], [37, 0, 0, 1], [15, 999, 0, 1], [16, 17, 0, 1], [17, 36, 1, 1], [18, 19, 0, 1], [19, 36, 0, 0]] as $r) {
    $c->table('categories')->insert(array_combine(['id', 'parent', 'deleted', 'published'], $r));
}
foreach ([null, 36, 37, 14] as $root) {
    $GLOBALS['fixtureEvo']->settings = ['site_start' => 1];
    if ($root !== null) {
        $GLOBALS['fixtureEvo']->settings['catalog_root'] = $root;
    }
    foreach ([14, 36, 101, 37, 15, 16, 18, 0, -1] as $id) {
        $old = (new ReflectionClass(OldParentWalk::class))->newInstanceWithoutConstructor();
        $new = (new ReflectionClass(Seiger\sCommerce\Controllers\sCommerceController::class))->newInstanceWithoutConstructor();
        $c->enableQueryLog();
        $c->flushQueryLog();
        $before = (new ReflectionMethod($old, 'getParentsIds'))->invoke($old, $id);
        $oldCount = count($c->getQueryLog());
        $c->flushQueryLog();
        $after = (new ReflectionMethod($new, 'getParentsIds'))->invoke($new, $id);
        $queries = $c->getQueryLog();
        if ($before !== $after) {
            throw new RuntimeException('Result mismatch ' . json_encode([$id, $root, $before, $after]));
        }
        foreach ($queries as $q) {
            if (($q['bindings'][0] ?? null) === 0 && str_contains($q['query'], '"id" = ?')) {
                throw new RuntimeException('ID zero lookup remains');
            }
        }
        if (count($queries) > $oldCount) {
            throw new RuntimeException('Query regression');
        }
        echo 'PASS root=' . json_encode($root) . ' id=' . $id . ' chain=' . json_encode($after) . ' SQL=' . $oldCount . '->' . count($queries) . PHP_EOL;
    }
}
echo 'PHP ' . PHP_VERSION . PHP_EOL;
