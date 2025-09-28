<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Seiger\sCommerce\Facades\sCart;
use Seiger\sCommerce\Facades\sCheckout;
use Seiger\sCommerce\Facades\sWishlist;
use Seiger\sCommerce\Integration\IntegrationActionController;

Route::middleware('web')->prefix('scommerce/')->name('sCommerce.')->group(function () {
    Route::post('add-to-cart', fn() => tap(
        sCart::addProduct(),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 400)
    ))->name('addToCart');
    Route::post('remove-from-cart', fn() => tap(
        sCart::removeProduct(),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 400)
    ))->name('removeFromCart');
    Route::post('set-order-data', fn() => tap(
        sCheckout::setOrderData(request()->all()),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 422)
    ))->name('setOrderData');
    Route::post('process-order', fn() => tap(
        sCheckout::processOrder(),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 422)
    ))->name('processOrder');
    Route::post('pay-order/{method?}', function ($method = null) {
        $result = sCheckout::payOrder($method, request()->all());
        if (request()->header('Accept') && strpos(request()->header('Accept'), 'application/json') !== false) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }
        unset($_SESSION['payOrderResponse']);
        $_SESSION['payOrderResponse'] = $result;
        return back();
    })->name('payOrder');
    Route::post('quick-order', fn() => tap(
        sCheckout::quickOrder(request()->all()),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 422)
    ))->name('quickOrder');
    Route::post('wishlist', fn() => tap(
        sWishlist::updateWishlist(request()->all()),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 422)
    ))->name('wishlist');
});

Route::middleware('mgr')->prefix('scommerce/integrations')->name('sCommerce.integrations.')->group(function () {
    Route::get('servlimits', [IntegrationActionController::class, 'serverLimits'])->name('serverLimits');
    Route::post('{key}/tasks/{action}', [IntegrationActionController::class, 'start'])->whereAlpha('action')->name('task.start');
    Route::post('{key}/upload', [IntegrationActionController::class, 'upload'])->name('upload');
    Route::get('tasks/{id}/progress', [IntegrationActionController::class, 'progress'])->name('progress');
    Route::get('tasks/{id}/download', [IntegrationActionController::class, 'download'])->name('download');
});