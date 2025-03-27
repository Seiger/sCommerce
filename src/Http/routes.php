<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Seiger\sCommerce\Facades\sCart;
use Seiger\sCommerce\Facades\sCheckout;
use Seiger\sCommerce\Facades\sWishlist;

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
    Route::post('quick-order', fn() => tap(
        sCheckout::quickOrder(request()->all()),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 422)
    ))->name('quickOrder');
    Route::post('wishlist', fn() => tap(
        sWishlist::updateWishlist(request()->all()),
        fn($result) => response()->json($result, $result['success'] === true ? 200 : 422)
    ))->name('wishlist');
});
