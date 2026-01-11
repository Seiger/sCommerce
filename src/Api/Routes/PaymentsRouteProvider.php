<?php namespace Seiger\sCommerce\Api\Routes;

use Illuminate\Routing\Router;
use Seiger\sApi\Contracts\RouteProviderInterface;
use Seiger\sCommerce\Api\Controllers\PaymentsController;

final class PaymentsRouteProvider implements RouteProviderInterface
{
    public function register(Router $router): void
    {
        $router->group(['prefix' => 'payments'], function () use ($router) {
            $router->get('', [PaymentsController::class, 'index'])->name('index');
        });
    }
}
