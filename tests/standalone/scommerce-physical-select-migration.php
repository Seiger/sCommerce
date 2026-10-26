<?php

// Reuse isolated schemas and fresh-install/legacy-save checks; never bootstrap a site database.
require __DIR__ . '/scommerce-physical-attributes.php';

$reset = static function () use ($db, $fresh): void {
    foreach (['s_product_attribute_values', 's_attribute_values', 's_attribute_category', 's_attribute_translates', 's_attributes'] as $table) {
        $db->table($table)->delete();
    }
    foreach (['length' => 352, 'height' => 353, 'width' => 354, 'weight' => 363] as $alias => $id) {
        $db->table('s_attributes')->insert(['id' => $id, 'alias' => $alias, 'type' => 3,
            'published' => 1, 'asfilter' => 1, 'position' => 9, 'helptext' => 'Merchant metadata']);
        $db->table('s_attribute_category')->insert(['attribute' => $id, 'category' => 7]);
        $db->table('s_attribute_translates')->insert(['attribute' => $id, 'lang' => 'base', 'pagetitle' => 'Merchant title']);
        $db->table('s_attribute_values')->insert(['avid' => $id + 2000, 'attribute' => $id,
            'position' => 3, 'alias' => 'measurement-slug', 'code' => '',
            'base' => ['length' => '12,5', 'height' => '0', 'width' => '0.0001', 'weight' => '0.0125'][$alias]]);
        $db->table('s_product_attribute_values')->insert(['product' => $fresh->id, 'attribute' => $id,
            'valueid' => $id + 2000, 'value' => (string) ($id + 2000)]);
    }
};
$snapshot = static function () use ($db): array {
    $state = [];
    foreach (['s_product_attribute_values', 's_attribute_values', 's_attribute_category', 's_attribute_translates', 's_attributes', 's_products'] as $table) {
        $state[$table] = $db->table($table)->get()->toJson();
    }
    return $state;
};
$reset();
$options = $db->table('s_attribute_values')->orderBy('avid')->get()->toJson();
$pivots = $db->table('s_product_attribute_values')->orderBy('attribute')->get()->toJson();
$migration->up();
foreach ([352 => '12.5000', 353 => '0.0000', 354 => '0.0001', 363 => '0.0125'] as $id => $expected) {
    $attribute = $db->table('s_attributes')->where('id', $id)->first();
    $check((int) $attribute->type === 3 && (int) $attribute->asfilter === 1, 'Retain manually chosen select type and filter settings');
    $check($db->table('s_attribute_category')->where('attribute', $id)->where('category', 7)->exists(), 'Retain category links');
}
$check($db->table('s_attribute_values')->orderBy('avid')->get()->toJson() === $options, 'Migration never rewrites merchant options');
$check($db->table('s_product_attribute_values')->orderBy('attribute')->get()->toJson() === $pivots, 'Migration never rewrites selected options');
$check($fresh->fresh()->physicalValues()['weight'] === '0.0125', 'Delivery reads numeric label rather than option ID');
$check($fresh->fresh()->physicalValues()['length'] === '12.5000', 'Read comma decimal select label');
$before = $snapshot();
$migration->up();
$check($snapshot() === $before, 'Migration is idempotent');

$db->schema()->table('s_products', static function (Illuminate\Database\Schema\Blueprint $table): void {
    $table->decimal('weight', 11, 4)->default(0);
});
$db->table('s_products')->where('id', $fresh->id)->update(['weight' => '0.0125']);
$product = Seiger\sCommerce\Models\sProduct::withoutGlobalScopes()->findOrFail($fresh->id);
$product->save();
$row = $db->table('s_product_attribute_values')->where('product', $fresh->id)->where('attribute', 363)->first();
$check((int) $row->valueid === 2363 && $row->value == 2363, 'Legacy transfer selects existing matching option');
$check($db->table('s_products')->where('id', $fresh->id)->value('weight') == 0, 'Legacy column reset after select write');
$check((int) $db->table('s_attributes')->where('id', 363)->value('type') === 3, 'Legacy transfer preserves select type');
$product->setPhysicalValue('weight', '0.25')->save();
$row = $db->table('s_product_attribute_values')->where('product', $fresh->id)->where('attribute', 363)->first();
$check($db->table('s_attribute_values')->where('avid', $row->valueid)->value('base') === '0.2500', 'Create numeric option for previously absent measurement');
$check($product->fresh()->physicalValues()['weight'] === '0.2500', 'Read newly selected option');
$count = $db->table('s_attribute_values')->count();
$product->setPhysicalValue('weight', '0.25')->saveQuietly();
$check($db->table('s_attribute_values')->count() === $count, 'Reuse option on repeat writes');
$db->table('s_attribute_values')->insert(['attribute' => 363, 'alias' => 'duplicate-number', 'base' => '0.25', 'code' => '']);
$before = $snapshot();
try {
    $product->setPhysicalValue('weight', '0.25')->save();
    $check(false, 'Reject ambiguous numeric options');
} catch (InvalidArgumentException) {
    $check($snapshot() === $before, 'Ambiguous option write rolls back without data loss');
}
$reset();
$db->table('s_attribute_values')->where('attribute', 363)->update(['base' => 'Custom text']);
$beforeOptions = $db->table('s_attribute_values')->get()->toJson();
$migration->up();
$check($db->table('s_attribute_values')->get()->toJson() === $beforeOptions, 'Migration preserves even manually configured text choices');
try {
    $fresh->fresh()->physicalValues();
    $check(false, 'Do not guess weight from nonnumeric choice');
} catch (InvalidArgumentException) {
    $check(true, 'Nonnumeric selected measurement produces an explicit error');
}
echo "OK: {$checks} combined checks; select types and merchant data preserved.\n";
