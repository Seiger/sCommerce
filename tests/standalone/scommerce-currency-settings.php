<?php

// php tests/standalone/scommerce-currency-settings.php
// Saving the settings keeps every available currency once, in the submitted order.
require __DIR__ . '/bootstrap.php';

use Seiger\sCommerce\Controllers\sCommerceController;

$checks = 0;
$same = static function (mixed $expected, mixed $actual, string $message) use (&$checks): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': ' . var_export($actual, true));
    }
    $checks++;
};

$same(['USD', 'UAH', 'EUR'], sCommerceController::uniqueCurrencyCodes(['USD', 'UAH', 'USD', 'EUR', 'UAH']), 'Repeats are dropped, the first position wins');
$same(['UAH', 'EUR'], sCommerceController::uniqueCurrencyCodes([' UAH', '', 'UAH ', null, ['EUR'], 'EUR']), 'Blanks, arrays and padded repeats are dropped');
$same([], sCommerceController::uniqueCurrencyCodes(null), 'A missing list stays empty');
$same(['UAH'], sCommerceController::uniqueCurrencyCodes('UAH'), 'A single value becomes a list');

$controller = file_get_contents(dirname(__DIR__, 2) . '/src/Controllers/sCommerceController.php');
$save = substr($controller, strpos($controller, 'public function updateFileConfigs'), 2500);
$same(true, str_contains($save, "\$all['basic__available_currencies'] = static::uniqueCurrencyCodes("), 'The settings save deduplicates the posted list');

$view = file_get_contents(dirname(__DIR__, 2) . '/views/partials/settingsCurrencyPriceConfiguration.blade.php');
$same(true, str_contains($view, "@foreach(array_unique((array)sCommerce::config('basic.available_currencies', [])) as \$cur)"), 'A stored duplicate renders one card');

echo "Passed {$checks} currency settings checks." . PHP_EOL;
