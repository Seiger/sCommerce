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
            'position' => 3, 'alias' => ['length' => '125', 'height' => '0', 'width' => '1', 'weight' => '1000'][$alias], 'code' => '',
            'base' => ['length' => '12.5cm', 'height' => '0mm', 'width' => '1mm', 'weight' => '1kg'][$alias]]);
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
foreach ([352, 353, 354, 363] as $id) {
    $attribute = $db->table('s_attributes')->where('id', $id)->first();
    $check((int) $attribute->type === 3 && (int) $attribute->asfilter === 1, 'Retain manually chosen select type and filter settings');
    $check($db->table('s_attribute_category')->where('attribute', $id)->where('category', 7)->exists(), 'Retain category links');
}
$check($db->table('s_attribute_values')->orderBy('avid')->get()->toJson() === $options, 'Migration never rewrites merchant options');
$check($db->table('s_product_attribute_values')->orderBy('attribute')->get()->toJson() === $pivots, 'Migration never rewrites selected options');
$check($fresh->fresh()->physicalValues()['weight'] === 1000, 'Delivery reads the option alias rather than its ID or label');
$check($fresh->fresh()->physicalValues()['length'] === 125 && $fresh->fresh()->physicalValues()['height'] === 0, 'Read millimetre select options');
$before = $snapshot();
$migration->up();
$check($snapshot() === $before, 'Migration is idempotent');

$db->schema()->table('s_products', static function (Illuminate\Database\Schema\Blueprint $table): void {
    $table->decimal('weight', 11, 4)->default(0);
});
$db->table('s_products')->where('id', $fresh->id)->update(['weight' => '1000']);
$product = Seiger\sCommerce\Models\sProduct::withoutGlobalScopes()->findOrFail($fresh->id);
$product->save();
$row = $db->table('s_product_attribute_values')->where('product', $fresh->id)->where('attribute', 363)->first();
$check((int) $row->valueid === 2363 && $row->value == 2363, 'Legacy transfer selects existing matching option');
$check($db->table('s_products')->where('id', $fresh->id)->value('weight') == 0, 'Legacy column reset after select write');
$check((int) $db->table('s_attributes')->where('id', 363)->value('type') === 3, 'Legacy transfer preserves select type');
$product->setPhysicalValue('weight', '250')->save();
$row = $db->table('s_product_attribute_values')->where('product', $fresh->id)->where('attribute', 363)->first();
$option = $db->table('s_attribute_values')->where('avid', $row->valueid)->first();
$check($option->alias === '250' && $option->base === '250g', 'Create option with the number as alias and a unit label');
$check($product->fresh()->physicalValues()['weight'] === 250, 'Read newly selected option');
$count = $db->table('s_attribute_values')->count();
$product->setPhysicalValue('weight', '250')->saveQuietly();
$check($db->table('s_attribute_values')->count() === $count, 'Reuse option on repeat writes');
$merchant = $db->table('s_attribute_values')->insertGetId(['attribute' => 363, 'alias' => '1500', 'base' => '1,5 kg', 'code' => ''], 'avid');
$product->setPhysicalValue('weight', 1500)->save();
$check((int) $db->table('s_product_attribute_values')->where('product', $fresh->id)->where('attribute', 363)->value('valueid') === $merchant
    && $db->table('s_attribute_values')->where('avid', $merchant)->value('base') === '1,5 kg', 'Reuse merchant option by alias and keep its label');
$reset();
$db->table('s_attribute_values')->where('attribute', 363)->update(['alias' => 'one-kilo', 'base' => '1 kg']);
$beforeOptions = $db->table('s_attribute_values')->get()->toJson();
$migration->up();
$check($db->table('s_attribute_values')->get()->toJson() === $beforeOptions, 'Migration preserves even manually configured text choices');
try {
    $fresh->fresh()->physicalValues();
    $check(false, 'Do not guess weight from nonnumeric choice');
} catch (InvalidArgumentException) {
    $check(true, 'Nonnumeric selected measurement produces an explicit error');
}
$payload = $fresh->fresh()->physicalPayload();
$check(!array_key_exists('weight', $payload) && $payload['length'] === 125, 'Cart payload leaves out a nonnumeric option instead of failing');
echo "OK: {$checks} combined checks; select types and merchant data preserved.\n";
