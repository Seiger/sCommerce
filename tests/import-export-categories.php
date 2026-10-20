<?php

// Run with PHP CLI. Executes the category sections without CMS bootstrap or a database.
namespace Seiger\sCommerce\Facades {
    class sCommerce
    {
        public static function config($key, $default = null) { return 2; }
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
    check($export($product, 'category'), 2, 'Primary category stays scalar in multisite');
    check($export($product, 'categories'), '240||234', 'Multisite additional list excludes primary ID');
    check($export((object) ['categories' => [$category(2, 'primary'), $category(234, 'primary_shop')]], 'categories'),
        'shop:234', 'Other site scopes must remain in the additional list');
    $result = $import(['category' => 2, 'categories' => 'default:2||shop:234']);
    check($result, [2 => ['scope' => 'primary', 'position' => 0], 234 => ['scope' => 'primary_shop', 'position' => 0]],
        'Multisite scopes must survive import');

    // The large-file path must retain the plural list through its temporary table.
    check(str_contains($source, "\$table->text('categories')->nullable();"), true, 'Temporary table category list column');
    check(str_contains($source, "'categories' => \$data['categories'] ?? null,"), true, 'Temporary row retains category list');
    check(str_contains($source, "'parent' => __('sCommerce::global.primary_category_id')"), false, 'Removed parent header');
    check(str_contains($source, "'categories' => __('sCommerce::global.categories')"), true, 'Plural category header enabled');
    echo "CSV category regression checks passed\n";
}
