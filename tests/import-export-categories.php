<?php

// Run with PHP CLI. Executes the category sections without CMS bootstrap or a database.
namespace Seiger\sCommerce\Facades {
    class sCommerce
    {
        public static function config($key, $default = null) { return 2; }
    }
}

namespace Seiger\sMultisite\Models {
    class sMultisite
    {
        public static function all() { return [(object)['key' => 'default'], (object)['key' => 'shop']]; }
    }
}

namespace {
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }

    function evo() {
        return new class {
            public function getConfig($key, $default = null) {
                return $key === 'check_sMultisite' ? $GLOBALS['multisite'] : $default;
            }
        };
    }

    function check($actual, $expected, string $message): void {
        if ($actual !== $expected) {
            throw new \RuntimeException($message . '\nActual: ' . var_export($actual, true));
        }
    }

    $source = file_get_contents(dirname(__DIR__) . '/src/Integration/ImportExportCSV.php');
    $start = strpos($source, "                                    case 'categories':");
    $end = strpos($source, "                                    case 'cover':", $start);
    if ($start === false || $end === false) {
        throw new \RuntimeException('Category export cases not found');
    }
    $export = eval('return function ($p, $field) { $a = []; switch ($field) {'
        . substr($source, $start, $end - $start) . '} return $a[$field] ?? null; };');

    $start = strpos($source, '        // Process the category list');
    $end = strpos($source, '        $product->categories()->sync([]);', $start);
    if ($start === false || $end === false) {
        throw new \RuntimeException('Category import section not found');
    }
    $import = eval('return function ($data, $prodCats = []) {'
        . str_replace('sCommerce::config', '\\Seiger\\sCommerce\\Facades\\sCommerce::config', substr($source, $start, $end - $start))
        . 'return $categories; };');

    $category = static fn ($id, $scope) => (object) ['id' => $id, 'pivot' => (object) ['scope' => $scope]];
    $product = (object) ['categories' => new class([
        $category(240, ''), $category(234, ''), $category(2, 'primary'),
    ]) extends \ArrayObject {
        public function pluck($key) {
            return new class(array_map(static fn ($item) => $item->{$key}, $this->getArrayCopy())) extends \ArrayObject {
                public function toArray() { return $this->getArrayCopy(); }
            };
        }
    }];

    $GLOBALS['multisite'] = false;
    check($export($product, 'category'), 2, 'Primary category must be selected by scope, not list order');
    check($export($product, 'categories'), '240||234', 'Additional categories must exclude the primary ID');
    $roundTrip = $import([
        'category' => $export($product, 'category'),
        'categories' => $export($product, 'categories'),
    ], [240 => 7, 234 => 8, 2 => 9]);
    check($roundTrip, [240 => ['position' => 7], 234 => ['position' => 8], 2 => ['scope' => 'primary', 'position' => 9]],
        'Export/import must retain the primary category and additional memberships');
    $primaryOnly = (object) ['categories' => [$category(2, 'primary')]];
    check($export($primaryOnly, 'categories'), '', 'No additional categories when only primary exists');
    check($export((object) ['categories' => [$category(240, '')]], 'categories'), '240', 'No primary must not drop additional categories');
    check($export($product, 'parent'), null, 'Removed parent key must have no category export implementation');
    $data = ['category' => 234, 'categories' => '240||234||2', 'parent' => 999];
    $result = $import($data, [240 => 7, 234 => 8, 2 => 9]);
    check($result, [240 => ['position' => 7], 234 => ['scope' => 'primary', 'position' => 8], 2 => ['position' => 9]],
        'Import must keep memberships/positions and apply only category as primary');
    check($import(['category' => 234]), [234 => ['scope' => 'primary', 'position' => 0]], 'Primary-only import');
    check($import([]), [2 => ['scope' => 'primary', 'position' => 0]], 'Default catalog category');

    $GLOBALS['multisite'] = true;
    check($export($product, 'category'), '', 'Single-site primary scope must not be treated as a domain primary');
    check($export($product, 'categories'), '240||234', 'Multisite additional list excludes primary ID');
    $multisiteProduct = (object)['categories' => [
        $category(240, ''), $category(2, 'primary_default'), $category(234, 'primary_shop'),
    ]];
    check($export($multisiteProduct, 'category'), 'default:2||shop:234', 'Export both domain primary categories');
    check($export($multisiteProduct, 'categories'), '240', 'All domain primary IDs must be excluded from additional categories');
    $multisiteRow = [
        'category' => $export($multisiteProduct, 'category'),
        'categories' => $export($multisiteProduct, 'categories'),
    ];
    $result = $import($multisiteRow, [240 => 7, 2 => 8, 234 => 9]);
    check($result, [240 => ['position' => 7], 2 => ['scope' => 'primary_default', 'position' => 8], 234 => ['scope' => 'primary_shop', 'position' => 9]],
        'Two-domain export/import must preserve exact scopes and positions without introducing primary');
    check($import(['parent_default' => 2, 'parent_shop' => 234, 'categories' => '240']),
        [240 => ['position' => 0], 2 => ['scope' => 'primary_default', 'position' => 0], 234 => ['scope' => 'primary_shop', 'position' => 0]],
        'Existing per-domain parent fields must remain supported');
    check($import(['category_default' => 2, 'category_shop' => 234, 'parent_shop' => 999]),
        [2 => ['scope' => 'primary_default', 'position' => 0], 234 => ['scope' => 'primary_shop', 'position' => 0]],
        'Category-prefixed domain keys take precedence over legacy parent-prefixed keys');
    check($import([]), [], 'Multisite must not inject the single-site catalog root');
    check($import(['category' => 'default:2||shop:234', 'parent_shop' => 0]),
        [2 => ['scope' => 'primary_default', 'position' => 0]], 'A disabled domain must not acquire a primary category');

    $start = strpos($source, '            $tempData = [');
    $end = strpos($source, '            $batch[] = $tempData;', $start);
    $prepare = substr($source, $start, $end - $start);
    $start = strpos($source, '    private function inflateTempTableRow(');
    $end = strpos($source, '    /**', $start);
    $inflate = str_replace('private function', 'public function', substr($source, $start, $end - $start));
    $tempPath = eval('return new class {
        public function prepare($data) {
            $defaultPayload = $textPayload = $seoPayload = [];
            ' . $prepare . 'return $tempData;
        }
        ' . $inflate . '
        private function encodeJson($value) { return json_encode($value); }
        private function decodeJsonToArray($value) { return json_decode($value ?? "[]", true) ?: []; }
        private function prepareAttributesForTempTable($data) { return null; }
        private function flattenTextPayloadByLang($data) { return []; }
        private function flattenSeoPayloadByLang($data) { return []; }
    };');
    foreach ([$multisiteRow, ['parent_default' => 2, 'parent_shop' => 234, 'categories' => '240'], ['category_default' => 2, 'category_shop' => 234]] as $row) {
        $inflated = $tempPath->inflateTempTableRow($tempPath->prepare($row));
        check($import($inflated), $import($row), 'Large-file import must match streaming import for domain categories');
    }

    // The large-file path must retain the plural list through its temporary table.
    check(str_contains($source, "\$table->text('categories')->nullable();"), true, 'Temporary table category list column');
    check(str_contains($source, "\$table->text('category')->nullable();"), true, 'Temporary table must retain domain:id strings');
    check(str_contains($source, "'categories' => \$data['categories'] ?? null,"), true, 'Temporary row retains category list');
    check(str_contains($source, "'parent' => __('sCommerce::global.primary_category_id')"), false, 'Removed parent header');
    check(str_contains($source, "'categories' => __('sCommerce::global.categories')"), true, 'Plural category header enabled');
    echo "CSV category regression checks passed\n";
}
