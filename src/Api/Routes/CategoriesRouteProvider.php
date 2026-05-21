<?php namespace Seiger\sCommerce\Api\Routes;

use Illuminate\Routing\Router;
use Seiger\sApi\Contracts\RouteProviderInterface;
use Seiger\sCommerce\Api\Controllers\CategoriesController;

final class CategoriesRouteProvider implements RouteProviderInterface
{
    public function register(Router $router): void
    {
        $router->group(['prefix' => 'categories'], function () use ($router) {
            $router->get('', [CategoriesController::class, 'index'])->name('index');
        });
    }
}
