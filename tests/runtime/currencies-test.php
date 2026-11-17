<?php

/** Return isolated project settings without bootstrapping a site. @since 1.5.0 */
function config($key, $default = null)
{
    return Illuminate\Support\Arr::get($GLOBALS['currencySettings'] ?? [], $key, $default);
}

require __DIR__ . '/bootstrap.php';

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Seiger\sCommerce\sCommerce;

$app = new Container();
$cache = new Repository(new ArrayStore());
$events = new Dispatcher($app);
$app->instance('cache', $cache);
$app->instance('events', $events);
Facade::setFacadeApplication($app);
$newCommerce = static fn () => (new ReflectionClass(sCommerce::class))->newInstanceWithoutConstructor();
$commerce = $newCommerce();
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$calls = 0;
$events->listen('sCommerce.ResolveCurrencies', static function (array $payload) use (&$calls): Collection {
    ++$calls;
    $rows = $payload['currencies'];
    if (!$rows->contains('alpha', 'EUR')) {
        throw new RuntimeException('ISO list must precede listeners.');
    }
    // Return a new collection so the next listener must receive the replacement.
    return $rows->concat([[
        'alpha' => 'EUR_WAL', 'numeric' => null, 'name' => 'Supplier euro',
        'symbol' => 'EUR', 'exp' => 2,
    ]]);
});
$events->listen('sCommerce.ResolveCurrencies', static function (array $payload) use ($check): ?Collection {
    $row = $payload['currencies']->firstWhere('alpha', 'EUR_WAL');
    $check($row['symbol'] === 'EUR', 'Listeners run sequentially before settings.');
    return null;
});
$GLOBALS['currencySettings'] = ['seiger' => ['settings' => ['sCommerce' => ['currencies' => [
    'EUR_WAL' => ['symbol' => 'WAL', 'exp' => 3],
]]]]];
$filtered = $commerce->getCurrencies(['EUR_WAL']);
$check($filtered->count() === 1 && $filtered->first()['symbol'] === 'WAL', 'Settings apply to custom rows before filtering.');
$check($commerce->getCurrencies()->count() === 179, 'Filtering does not truncate the cached list.');
$commerce->getCurrencyFormat('EUR_WAL');
$check($newCommerce()->getCurrencies()->count() === 179 && $calls === 1, 'Persistent cache avoids dispatch across instances.');
$events->forget('sCommerce.ResolveCurrencies');
$check($newCommerce()->getCurrencies()->contains('alpha', 'EUR_WAL'), 'Listener changes require invalidation.');
$cache->flush();
$commerce = $newCommerce();
$check(!$cache->has('currencies'), 'System cache flush forgets the shared directory.');
$check(!$commerce->getCurrencies()->contains('alpha', 'EUR_WAL'), 'A new request rebuilds the directory after system cache cleanup.');
$check($commerce->getCurrencies()->firstWhere('alpha', 'XSU')['numeric'] === '994', 'ISO numeric 994 remains XSU.');

$events->listen('sCommerce.Resolve*', static function (string $name, array $payload) use ($check): ?Collection {
    $check($name === 'sCommerce.ResolveCurrencies' && $payload[0]['currencies'] instanceof Collection, 'Wildcard listeners receive the standard dispatcher payload.');
    return null;
});
$check($commerce->getCurrencies()->count() === 178, 'Wildcard listener preserves the directory.');
echo "$checks currency event/cache checks passed\n";
