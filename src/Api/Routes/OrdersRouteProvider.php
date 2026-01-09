<?php namespace Seiger\sCommerce\Api\Routes;

use Illuminate\Routing\Router;
use Seiger\sApi\Contracts\RouteProviderInterface;
use Seiger\sCommerce\Api\Controllers\OrdersController;

final class OrdersRouteProvider implements RouteProviderInterface
{
    public function register(Router $router): void
    {
        $router->group(['prefix' => 'orders'], function () use ($router) {
            $router->get('', [OrdersController::class, 'index'])->name('index');
            $router->put('{order_id}', [OrdersController::class, 'update'])->whereNumber('order_id')->name('update');
        });
    }
}
