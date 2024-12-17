<?php

use Illuminate\Support\Facades\Route;
use Seiger\sCommerce\Facades\sCart;

Route::middleware('web')->prefix('scommerce/')->name('sCommerce.')->group(function () {
    Route::post('add-to-cart', fn() => tap(sCart::addProduct(), fn($result) => response()->json($result, $result['success'] === true ? 200 : 404)))->name('addToCart');
    Route::post('remove-from-cart', fn() => tap(sCart::removeProduct(), fn($result) => response()->json($result, $result['success'] === true ? 200 : 404)))->name('removeFromCart');
});
