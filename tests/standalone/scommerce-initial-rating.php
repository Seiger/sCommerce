<?php
// Reuse the isolated SQLite setup and count/cache regressions.
require __DIR__ . '/scommerce-published-review-count.php';
use Seiger\sCommerce\Models\sProduct;
$ratingChecks = 0;
$ratingCheck = function ($expected, $actual, $label) use (&$ratingChecks) {
    if ($expected !== $actual) { throw new RuntimeException($label . ': ' . var_export($actual, true)); }
    $ratingChecks++;
};
$ratingCheck(0.0, (new sProduct())->rating, 'new model starts at zero');
foreach ([null, '', '{}', '{"total":0,"5":0}'] as $votes) {
    $product = new sProduct();
    $product->forceFill(['rating' => 5, 'votes' => $votes]);
    $ratingCheck(0.0, $product->rating, 'legacy initial rating without votes');
}
$product->forceFill(['rating' => 4.5, 'votes' => '{"total":2,"4":1,"5":1}']);
$ratingCheck(4.5, $product->rating, 'real rating unchanged');
$db->statement('CREATE TABLE old_default_products (id INTEGER PRIMARY KEY AUTOINCREMENT, rating REAL DEFAULT 5, created_at TEXT, updated_at TEXT)');
$product = new sProduct();
$product->setTable('old_default_products');
$product->save();
$ratingCheck(0.0, (float) $db->table('old_default_products')->value('rating'), 'model overrides old database default');
$module = file_get_contents(dirname(__DIR__, 2) . '/module/sCommerceModule.php');
preg_match_all('/\$product->rating = round\(\(int\)\$votes\[\'total\'\] \? \$summ \/ \$votes\[\'total\'\] : 0, 1\);/', $module, $calculations);
$ratingCheck(3, count($calculations[0]), 'all review recalculation paths');
foreach ($calculations[0] as $calculation) {
    $votes = ['total' => 0];
    $summ = 0;
    eval($calculation);
    $ratingCheck(0.0, $product->rating, 'last rating removal resets to zero');
    $votes = ['total' => 2];
    $summ = 9;
    eval($calculation);
    $ratingCheck(4.5, $product->rating, 'average calculation unchanged');
}
echo "Initial rating checks passed: {$ratingChecks}\n";
