<?php
/** Exercise the registered plugin listener without booting a site or database. */
namespace Illuminate\Support\Facades {
    /** Capture plugin registration without loading Laravel events. @since 1.5.0 */
    class Event {
        public static array $listeners = [];
        /** Store listeners so scenarios can invoke the real closures. @since 1.5.0 */
        public static function listen($name, $listener) { self::$listeners[$name] = $listener; }
    }
}
namespace Seiger\sCommerce\Facades {
    /** Supply isolated catalogue hits and misses. @since 1.5.0 */
    class sCommerce {
        /** Resolve the known product route through the cached listing. @since 1.5.0 */
        public static function documentListing() { return ['catalog/tour' => 42]; }
        /** Return a miss for unknown aliases. @since 1.5.0 */
        public static function getProductByAlias($alias) { return null; }
    }
    /** Avoid a real filter service when an alias is absent. @since 1.5.0 */
    class sFilter {
        /** Simulate an unmatched filter URL. @since 1.5.0 */
        public static function validateFilters() { return null; }
    }
}
namespace {
    /** Provide only the array operations used by alias resolution. @since 1.5.0 */
    class Arr {
        /** Test a cached alias key. @since 1.5.0 */
        public static function exists($items, $key) { return array_key_exists($key, $items); }
        /** Return the final route segment. @since 1.5.0 */
        public static function last($items) { return end($items); }
    }
    $mode = $argv[1] ?? 'missing';
    define('IN_MANAGER_MODE', $mode === 'manager');
    define('EVO_API_MODE', $mode === 'api');
    $requestCalls = 0;
    $segments = match ($mode) {
        'product' => ['catalog', 'tour'],
        'language' => ['en', 'catalog', 'tour'],
        'empty' => [],
        default => ['unknown'],
    };
    $site = new class {
        public array $placeholders = [];
        public string $documentMethod = 'alias';
        public int $documentIdentifier = 1;
        /** Supply language, home and distinct error resource IDs. @since 1.5.0 */
        public function getConfig($key, $default = null) { return match ($key) { 'lang' => 'en', 'error_page' => 60, 'unauthorized_page' => 61, default => $default }; }
        /** Capture the product selected by the real listener. @since 1.5.0 */
        public function setPlaceholder($key, $value) { $this->placeholders[$key] = $value; }
        /** Stop before exit and expose the selected forward target. @since 1.5.0 */
        public function sendForward($id) { throw new \RuntimeException('forward:' . $id); }
    };
    /** Provide request-binding availability without booting a CMS. @since 1.5.0 */
    function app() { return new class {
        /** Omit the binding in the legacy bootstrap regression. @since 1.5.0 */
        public function bound($key) { return $GLOBALS['mode'] !== 'missing'; }
    }; }
    /** Fail if a guarded mode tries resolving the HTTP request. @since 1.5.0 */
    function request() {
        ++$GLOBALS['requestCalls'];
        if (in_array($GLOBALS['mode'], ['missing', 'manager', 'api'], true)) {
            throw new \LogicException('Unexpected request resolution');
        }
        return new class { /** Supply route segments without a request object. @since 1.5.0 */ public function segments() { return $GLOBALS['segments']; } };
    }
    /** Return the isolated CMS double. @since 1.5.0 */
    function evo() { return $GLOBALS['site']; }
    set_error_handler(function ($severity, $message) { throw new \ErrorException($message, 0, $severity); });
    require dirname(__DIR__, 2) . '/plugins/sCommercePlugin.php';
    if ($mode === 'forwarded') { $site->documentMethod = 'id'; }
    if (in_array($mode, ['error-page', 'unauthorized-page'], true)) {
        $site->documentMethod = 'id';
        $site->documentIdentifier = $mode === 'error-page' ? 60 : 61;
        $site->placeholders['product'] = 42;
        \Illuminate\Support\Facades\Event::$listeners['evolution.OnBeforeLoadDocumentObject']([]);
        echo "PASS $mode\n";
        exit;
    }
    $forward = null;
    try {
        \Illuminate\Support\Facades\Event::$listeners['evolution.OnPageNotFound']();
    } catch (\RuntimeException $exception) {
        $forward = $exception->getMessage();
    }
    $skip = in_array($mode, ['missing', 'manager', 'api', 'forwarded'], true);
    if ($requestCalls !== ($skip ? 0 : 1)) { throw new \LogicException('Incorrect request access'); }
    $product = in_array($mode, ['product', 'language'], true);
    if ($forward !== ($product ? 'forward:1' : null)) { throw new \LogicException('Incorrect routing'); }
    if ($site->placeholders !== ($product ? ['product' => 42] : [])) { throw new \LogicException('Incorrect product'); }
    echo "PASS $mode\n";
}
