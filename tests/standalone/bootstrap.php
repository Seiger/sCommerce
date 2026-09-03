<?php

// Use installed dependencies without bootstrapping a CMS or its database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$autoload = getenv('SCOMMERCE_TEST_AUTOLOAD') ?: dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Set SCOMMERCE_TEST_AUTOLOAD to an Evolution CMS core/vendor/autoload.php.');
}
require $autoload;
$packageSource = dirname(__DIR__, 2) . '/src/';
// Prepend to override even an optimized Composer class map from the consumer.
spl_autoload_register(static function (string $class) use ($packageSource): void {
    $prefix = 'Seiger\\sCommerce\\';
    if (str_starts_with($class, $prefix)) {
        $path = $packageSource . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
}, true, true);
