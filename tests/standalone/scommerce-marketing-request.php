<?php

/** Return the isolated container used by CMS helpers in this regression. */
function evo() { return $GLOBALS['marketingTestContainer']; }

require __DIR__ . '/bootstrap.php';

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

$container = new Container();
$GLOBALS['marketingTestContainer'] = $container;
$events = new Dispatcher($container);
$container->instance('events', $events);
Facade::setFacadeApplication($container);
require dirname(__DIR__, 2) . '/plugins/sCommercePlugin.php';

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
};

$marketingResolutions = 0;
$container->bind('sCommerce', static function () use (&$marketingResolutions) {
    $marketingResolutions++;
    throw new RuntimeException('Marketing service must not boot without a request');
});
$events->dispatch('evolution.OnWebPageInit');
$check($marketingResolutions === 0, 'Unbound request must skip marketing service');
$check(!$container->bound('request'), 'Listener must not synthesize a request');

/** Record listener payloads without writing cookies or loading commerce state. */
$receiver = new class {
    public array $queries = [];
    /** Record the unchanged query passed to the existing marketing API. */
    public function captureMarketingTags(array $query): void { $this->queries[] = $query; }
};
$container->instance('sCommerce', $receiver);
$query = ['utm_source' => 'newsletter', 'gclid' => 'test-click', 'other' => 'preserve'];
$container->instance('request', Request::create('/baltics/', 'GET', $query));
$events->dispatch('evolution.OnWebPageInit');
$check($receiver->queries === [$query], 'Bound HTTP request must preserve the entire query');
$container->instance('request', Request::create('/baltics/'));
$events->dispatch('evolution.OnWebPageInit');
$check($receiver->queries === [$query, []], 'Empty query must retain existing capture behavior');

$container->forgetInstance('request');
$events->dispatch('evolution.OnWebPageInit');
$check(count($receiver->queries) === 2, 'Missing request must remain safe after earlier HTTP events');
echo "Marketing request checks passed: {$checks}\n";
