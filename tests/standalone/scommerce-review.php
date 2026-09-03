<?php

// Reuse the isolated dependencies, SQLite and real Blade compiler.
require __DIR__ . '/scommerce-bulk-status.php';

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Seiger\sCommerce\Models\sReview;

$app->instance('db.schema', $db->schema());
$checks = 0;
$migration = file_get_contents($package . '/database/migrations/2023_12_27_190234_ecommerce_structure_tables.php');
$start = strpos($migration, "\$this->createTableIfMissing('s_reviews', ");
$end = strpos($migration, "\n        });", $start) + strlen("\n        });");
$schema = substr($migration, $start, $end - $start);
$schema = str_replace(["\$this->createTableIfMissing", 'Blueprint $table'], ['\\Illuminate\\Support\\Facades\\Schema::create', '\\Illuminate\\Database\\Schema\\Blueprint $table'], $schema);
eval($schema);
foreach (['message', 'pros', 'cons', 'image'] as $column) {
    $check(Schema::hasColumn('s_reviews', $column), 'Review column exists: ' . $column);
}
$review = new sReview();
$all = ['name' => 'Test', 'title' => 'Keep', 'message' => str_repeat('Review ', 200),
    'pros' => 'Good', 'cons' => 'None', 'image' => 'images/scommerce.svg', 'published' => 1, '_token' => 'ignored'];
$module = file_get_contents($package . '/module/sCommerceModule.php');
$start = strpos($module, "\$columns = Schema::getColumnListing('s_reviews');");
$end = strpos($module, '/*$review->product', $start);
$save = str_replace('Schema::', '\\Illuminate\\Support\\Facades\\Schema::', substr($module, $start, $end - $start));
eval($save);
$saved = $review->fresh();
$check($saved->image === 'images/scommerce.svg' && $saved->pros === 'Good' && $saved->cons === 'None', 'Review extras persist');
$check(strlen($saved->message) > 255, 'Long review message persists');
$all = ['image' => '', '_token' => 'ignored'];
eval($save);
$check($review->fresh()->image === '' && $review->fresh()->title === 'Keep', 'Removal clears only the supplied photo field');

define('EVO_BASE_PATH', $package . '/');
define('EVO_SITE_URL', 'https://example.test/');
$template = file_get_contents($package . '/views/reviewTab.blade.php');
$start = strpos($template, '    @if(!empty($item->image)');
$end = strpos($template, '</form>', $start);
$photo = $compiler->compileString(substr($template, $start, $end - $start));
$__env = $view;
$renderPhoto = static function (string $path) use ($photo, $__env): string {
    $item = (object) ['image' => $path];
    ob_start();
    try {
        eval('?>' . $photo);
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
};
$html = $renderPhoto('images/scommerce.svg');
$check(str_contains($html, 'https://example.test/images/scommerce.svg'), 'Stored photo is rendered');
$check(str_contains($html, 'name="image"') && str_contains($html, 'data-confirm='), 'Photo removal includes field and escaped confirmation');
$check(trim($renderPhoto('')) === '' && trim($renderPhoto('missing-photo.jpg')) === '', 'Empty/missing photo is hidden');
$check(str_contains($template, '@csrf'), 'Review form includes CSRF');
foreach (['uk', 'en', 'ru', 'de', 'fr', 'pl'] as $locale) {
    $labels = require $package . '/lang/' . $locale . '/global.php';
    foreach (['photo', 'pros', 'cons', 'remove', 'are_you_sure_to_remove_photo'] as $key) {
        $check(!empty($labels[$key]), $locale . ': translated ' . $key);
    }
}
foreach (['dashboardTab', 'orderTab', 'ordersTab', 'reviewTab', 'reviewsTab', 'ordersPrint', 'partials/orderStatus',
    'scripts/ordersBulkStatus', 'scripts/ordersBulkExport', 'scripts/ordersBulkMenu'] as $name) {
    token_get_all($compiler->compileString(file_get_contents($package . '/views/' . $name . '.blade.php')), TOKEN_PARSE);
    $checks++;
}
$check(str_starts_with((new ReflectionClass(sReview::class))->getFileName(), $package . '/src/'), 'Model loads from original checkout');
echo "$checks review/schema/Blade checks passed (isolated SQLite, no site database)\n";
