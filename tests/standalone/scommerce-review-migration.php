<?php

// Isolated upgrade tests: no CMS bootstrap or connection to the site database.
require __DIR__ . '/bootstrap.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;

$app = new Container();
Container::setInstance($app);
Facade::setFacadeApplication($app);
$db = new Capsule($app);
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->setAsGlobal();
$app->instance('db', $db->getDatabaseManager());
$app->instance('db.schema', $db->schema());
$migration = require dirname(__DIR__, 2) . '/database/migrations/2026_09_03_000000_add_review_details_and_photo.php';
$checks = 0;
$check = static function (bool $passed, string $label) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException($label);
    }
    $checks++;
};

$migration->up();
$migration->down();
$check(!Schema::hasTable('s_reviews'), 'Missing reviews table is left to the installation migration');

foreach (['legacy', 'partial', 'current', 'nullable'] as $scenario) {
    Schema::create('s_reviews', static function (Blueprint $table) use ($scenario): void {
        $table->id();
        $table->string('name')->default('');
        if ($scenario === 'current') {
            $table->mediumText('message')->default('');
        } else {
            $table->string('message')->nullable($scenario === 'nullable')->default('');
        }
        if (in_array($scenario, ['partial', 'current'], true)) {
            $table->string('image')->default('');
        }
        if ($scenario === 'current') {
            $table->string('pros')->default('');
            $table->string('cons')->default('');
        }
    });
    $row = ['name' => 'Existing customer', 'message' => $scenario === 'nullable' ? null : 'Existing review'];
    if (in_array($scenario, ['partial', 'current'], true)) {
        $row['image'] = 'assets/reviews/existing.jpg';
    }
    if ($scenario === 'current') {
        $row['pros'] = 'Existing advantages';
        $row['cons'] = 'Existing disadvantages';
    }
    $db->table('s_reviews')->insert($row);
    $migration->up();
    foreach (['pros', 'cons', 'image'] as $column) {
        $check(Schema::hasColumn('s_reviews', $column), "$scenario: $column exists");
    }
    $saved = (array) $db->table('s_reviews')->first();
    foreach ($row as $column => $value) {
        $check($saved[$column] === $value, "$scenario: preserve $column");
    }
    $check(Schema::getColumnType('s_reviews', 'message') === 'text', "$scenario: message widened");
    foreach (array_diff(['pros', 'cons', 'image'], array_keys($row)) as $column) {
        $check($saved[$column] === '', "$scenario: empty default for $column");
    }
    $longMessage = str_repeat('Відгук ', 400);
    $db->table('s_reviews')->update(['message' => $longMessage]);
    $before = Schema::getColumns('s_reviews');
    $migration->up();
    $check(Schema::getColumns('s_reviews') === $before, "$scenario: repeat does not alter schema");
    $check($db->table('s_reviews')->value('message') === $longMessage, "$scenario: repeat preserves long content");
    $migration->down();
    $check(Schema::getColumns('s_reviews') === $before, "$scenario: rollback retains schema");
    $check($db->table('s_reviews')->value('message') === $longMessage, "$scenario: rollback retains long content");
    $migration->up();
    $check($db->table('s_reviews')->count() === 1, "$scenario: reapply preserves rows");
    Schema::drop('s_reviews');
}

echo "$checks review migration checks passed (in-memory SQLite; no site database)\n";
