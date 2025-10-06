<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Seiger\sCommerce\Facades\sCart;
use Seiger\sCommerce\Facades\sCheckout;
use Seiger\sCommerce\Facades\sWishlist;
use Seiger\sCommerce\Integration\IntegrationActionController;
use Seiger\sCommerce\sCommerce;

Route::middleware('web')->prefix('scommerce/')->name('sCommerce.')->group(function () {
    $addToCartHandler = fn() => tap(
        sCart::addProduct(),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 400)
    );
    $removeFromCartHandler = fn() => tap(
        sCart::removeProduct(),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 400)
    );
    $setOrderDataHandler = fn() => tap(
        sCheckout::setOrderData(request()->all()),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 422)
    );
    $processOrderHandler = fn() => tap(
        sCheckout::processOrder(),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 422)
    );
    $payOrderHandler = function ($method = null) {
        $result = sCheckout::payOrder($method, request()->all());
        if (request()->header('Accept') && strpos(request()->header('Accept'), 'application/json') !== false) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }
        unset($_SESSION['payOrderResponse']);
        $_SESSION['payOrderResponse'] = $result;
        return back();
    };
    $quickOrderHandler = fn() => tap(
        sCheckout::quickOrder(request()->all()),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 422)
    );
    $wishlistHandler = fn() => tap(
        sWishlist::updateWishlist(request()->all()),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 422)
    );

    // Routes without suffix (for API calls)
    Route::post('add-to-cart', $addToCartHandler)->name('addToCart');
    Route::post('remove-from-cart', $removeFromCartHandler)->name('removeFromCart');
    Route::post('set-order-data', $setOrderDataHandler)->name('setOrderData');
    Route::post('process-order', $processOrderHandler)->name('processOrder');
    Route::post('pay-order/{method?}', $payOrderHandler)->name('payOrder');
    Route::post('quick-order', $quickOrderHandler)->name('quickOrder');
    Route::post('wishlist', $wishlistHandler)->name('wishlist');

    // Routes with suffix (for frontend calls with friendly URLs)
    $suffix = sCommerce::config('basic.friendlyUrlSuffix', evo()->getConfig('friendly_url_suffix', ''));
    if ($suffix) {
        Route::post('add-to-cart' . $suffix, $addToCartHandler);
        Route::post('remove-from-cart' . $suffix, $removeFromCartHandler);
        Route::post('set-order-data' . $suffix, $setOrderDataHandler);
        Route::post('process-order' . $suffix, $processOrderHandler);
        Route::post('pay-order/{method?}' . $suffix, $payOrderHandler);
        Route::post('quick-order' . $suffix, $quickOrderHandler);
        Route::post('wishlist' . $suffix, $wishlistHandler);
    }
});

Route::middleware('mgr')->prefix('scommerce/integrations')->name('sCommerce.integrations.')->group(function () {
    Route::get('servlimits', [IntegrationActionController::class, 'serverLimits'])->name('serverLimits');
    Route::post('{key}/tasks/{action}', [IntegrationActionController::class, 'start'])->whereAlpha('action')->name('task.start');
    Route::post('{key}/upload', [IntegrationActionController::class, 'upload'])->name('upload');
    Route::get('tasks/{id}/progress', [IntegrationActionController::class, 'progress'])->name('progress');
    Route::get('tasks/{id}/download', [IntegrationActionController::class, 'download'])->name('download');
});