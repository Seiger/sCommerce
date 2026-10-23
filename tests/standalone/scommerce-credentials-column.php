<?php

// php tests/standalone/scommerce-credentials-column.php
// Isolated upgrade checks for the payment-method credentials column: in-memory SQLite, no CMS bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/bootstrap.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Seiger\sCommerce\Models\sPaymentMethod;
use Seiger\sCommerce\Payment\BankInvoicePayment;

$app = new Container();
Container::setInstance($app);
Facade::setFacadeApplication($app);
$app->instance('log', new class {
    public array $warnings = [];
    public function warning($message, $context = []) { $this->warnings[] = [$message, $context]; }
    public function error($message, $context = []) {}
    public function channel($name = null) { return $this; }
});
$db = new Capsule($app);
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->setEventDispatcher(new Dispatcher($app));
$db->setAsGlobal();
$db->bootEloquent();
$app->instance('db', $db->getDatabaseManager());
$app->instance('db.schema', $db->schema());
$package = dirname(__DIR__, 2);
$migration = require $package . '/database/migrations/2026_10_20_000001_rename_payment_method_credentials_column.php';
$checks = 0;
$check = static function (bool $passed, string $label) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException($label);
    }
    $checks++;
};
$legacy = sPaymentMethod::LEGACY_CREDENTIALS;
$createTable = static function (array $columns): void {
    Schema::create('s_payment_methods', static function (Blueprint $table) use ($columns): void {
        $table->id();
        $table->uuid('uuid')->nullable()->unique();
        $table->string('name');
        $table->string('class')->default('');
        $table->string('identifier')->default('');
        $table->boolean('active')->default(false);
        $table->integer('position')->default(0);
        $table->text('title')->nullable();
        $table->text('description')->nullable();
        foreach ($columns as $column) {
            $table->text($column)->nullable();
        }
        $table->text('settings')->nullable();
        $table->string('mode')->default('');
        $table->timestamps();
    });
    sPaymentMethod::forgetCredentialsColumn();
};
$gateway = static fn () => new class('') extends BankInvoicePayment {
    public function defineCredentials(): array
    {
        return [['fields' => ['api_key' => [], 'merchant' => []]]];
    }
    public function method(): sPaymentMethod { return $this->method; }
};
$secret = json_encode(['api_key' => 'sk_test_value', 'merchant' => 'M-1']);

$check(mb_strlen($legacy) === 11 && mb_substr($legacy, 0, 1) === "\u{0441}" && substr($legacy, 2) === 'redentials', 'Legacy constant is the Cyrillic-initial column name');
$migration->up();
$migration->down();
$check(!Schema::hasTable('s_payment_methods'), 'Missing table is left to the installation migration');

// 1. Legacy schema: both spellings read and write the legacy column.
$createTable([$legacy]);
$check(sPaymentMethod::credentialsColumn() === $legacy, 'Legacy column detected before migration');
$method = new sPaymentMethod(['name' => 'bank_invoice', 'class' => BankInvoicePayment::class]);
$method->{sPaymentMethod::CREDENTIALS} = $secret;
$method->save();
$check(!empty($method->uuid), 'Payment method receives a UUID on creation');
$check($db->table('s_payment_methods')->value($legacy) === $secret, 'Canonical spelling writes the legacy column');
$loaded = sPaymentMethod::query()->first();
$check($loaded->{sPaymentMethod::CREDENTIALS} === $secret && $loaded->{$legacy} === $secret, 'Both spellings read the legacy column');
$check(isset($loaded->{sPaymentMethod::CREDENTIALS}), 'isset() works with the canonical spelling');
$loaded->{$legacy} = json_encode(['api_key' => 'legacy-writer']);
$loaded->save();
$check(json_decode($db->table('s_payment_methods')->value($legacy), true) === ['api_key' => 'legacy-writer'], 'Old third-party writes keep working');
$loaded->{sPaymentMethod::CREDENTIALS} = $secret;
$loaded->save();
$instance = $gateway();
$check($instance->credentials === ['api_key' => 'sk_test_value', 'merchant' => 'M-1'], 'Gateway loads credentials from the legacy column');
$check(!array_key_exists($legacy, $instance->method()->getAttributes()), 'Gateway unsets loaded credentials from its model');

// 2. Rename keeps data and both spellings.
$migration->up();
$check(Schema::hasColumn('s_payment_methods', sPaymentMethod::CREDENTIALS) && !Schema::hasColumn('s_payment_methods', $legacy), 'Column renamed');
$check(sPaymentMethod::credentialsColumn() === sPaymentMethod::CREDENTIALS, 'Migration refreshes the detected column');
$check($db->table('s_payment_methods')->value(sPaymentMethod::CREDENTIALS) === $secret, 'Rename preserves data');
$loaded = sPaymentMethod::query()->first();
$check($loaded->{$legacy} === $secret && $loaded->{sPaymentMethod::CREDENTIALS} === $secret, 'Both spellings read after migration');
$loaded->{$legacy} = $secret;
$check(array_keys($loaded->getDirty()) === [], 'Legacy spelling maps to the renamed column');
$check($gateway()->credentials['api_key'] === 'sk_test_value', 'Gateway loads credentials after migration');
$migration->up();
$check($db->table('s_payment_methods')->value(sPaymentMethod::CREDENTIALS) === $secret, 'Repeated up() is a no-op');

// 3. Rollback restores the legacy column.
$migration->down();
$check(Schema::hasColumn('s_payment_methods', $legacy) && !Schema::hasColumn('s_payment_methods', sPaymentMethod::CREDENTIALS), 'down() restores the legacy column');
$check($db->table('s_payment_methods')->value($legacy) === $secret && sPaymentMethod::credentialsColumn() === $legacy, 'down() preserves data and detection');
$migration->down();
$check(Schema::hasColumn('s_payment_methods', $legacy), 'Repeated down() is a no-op');
Schema::drop('s_payment_methods');

// 4. A partial manual fix left both columns: copy only into empty canonical values.
$createTable([$legacy, sPaymentMethod::CREDENTIALS]);
$db->table('s_payment_methods')->insert([
    ['id' => 1, 'name' => 'copy', $legacy => $secret, sPaymentMethod::CREDENTIALS => '[]'],
    ['id' => 2, 'name' => 'keep', $legacy => '[]', sPaymentMethod::CREDENTIALS => $secret],
    ['id' => 3, 'name' => 'same', $legacy => $secret, sPaymentMethod::CREDENTIALS => $secret],
    ['id' => 4, 'name' => 'null', $legacy => $secret, sPaymentMethod::CREDENTIALS => null],
]);
$check(sPaymentMethod::credentialsColumn() === sPaymentMethod::CREDENTIALS, 'Canonical column wins when both exist');
$check(sPaymentMethod::query()->find(2)->{$legacy} === $secret, 'Legacy spelling reads the canonical column when both exist');
$migration->up();
$check(!Schema::hasColumn('s_payment_methods', $legacy), 'Legacy column dropped without conflicts');
$check($db->table('s_payment_methods')->pluck(sPaymentMethod::CREDENTIALS)->all() === [$secret, $secret, $secret, $secret], 'Empty canonical values copied, non-empty ones kept');
Schema::drop('s_payment_methods');

$createTable([$legacy, sPaymentMethod::CREDENTIALS]);
$other = json_encode(['api_key' => 'other_secret_value']);
$db->table('s_payment_methods')->insert([
    ['id' => 7, 'name' => 'conflict', $legacy => $other, sPaymentMethod::CREDENTIALS => $secret],
    ['id' => 8, 'name' => 'copy', $legacy => $secret, sPaymentMethod::CREDENTIALS => ''],
]);
$migration->up();
$check(Schema::hasColumn('s_payment_methods', $legacy), 'Conflicting values keep the legacy column');
$check($db->table('s_payment_methods')->where('id', 7)->value(sPaymentMethod::CREDENTIALS) === $secret, 'Canonical value preserved on conflict');
$check($db->table('s_payment_methods')->where('id', 8)->value(sPaymentMethod::CREDENTIALS) === $secret, 'Non-conflicting rows still copied');
$warnings = $app['log']->warnings;
$check(count($warnings) === 1 && $warnings[0][1] === ['payment_method_ids' => [7]], 'Conflict reported by id');
$check(!str_contains(json_encode($warnings), 'secret_value') && !str_contains(json_encode($warnings), 'M-1'), 'Conflict report contains no credentials');
$model = sPaymentMethod::query()->find(7);
unset($model->{$legacy});
$check(!array_key_exists(sPaymentMethod::CREDENTIALS, $model->getAttributes()) && array_key_exists($legacy, $model->getAttributes()), 'unset() maps to the canonical column when both exist');

// 5. No Cyrillic lookalikes remain in PHP code (comments and translations excluded).
$offenders = [];
foreach (['src', 'module', 'plugins'] as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($package . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
            if (!is_array($token) || in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML], true)) {
                continue;
            }
            $identifier = in_array($token[0], [T_STRING, T_VARIABLE, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true);
            // Identifiers must be Latin; literals may contain real Cyrillic words but no mixed-script words.
            if (($identifier && preg_match('/[\x{0400}-\x{04FF}]/u', $token[1]))
                || preg_match('/(?:[A-Za-z][\x{0400}-\x{04FF}]|[\x{0400}-\x{04FF}][A-Za-z])/u', $token[1])) {
                $offenders[] = substr($file->getPathname(), strlen($package) + 1) . ':' . $token[2];
            }
        }
    }
}
$check($offenders === [], 'No Cyrillic identifiers or mixed-script literals: ' . implode(', ', $offenders));
echo "$checks credentials column checks passed (in-memory SQLite; no site database)\n";
