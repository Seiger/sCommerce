<?php namespace Seiger\sCommerce\Api\Routes;

use Illuminate\Routing\Router;
use Seiger\sApi\Contracts\RouteProviderInterface;
use Seiger\sCommerce\Api\Controllers\StatusesController;

final class StatusesRouteProvider implements RouteProviderInterface
{
    public function register(Router $router): void
    {
        $router->group(['prefix' => 'statuses'], function () use ($router) {
            $router->get('', [StatusesController::class, 'index'])->name('index');
        });
    }
}
