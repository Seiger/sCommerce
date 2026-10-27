<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Seiger\sCommerce\Facades\sCart;
use Seiger\sCommerce\Facades\sCheckout;
use Seiger\sCommerce\Facades\sWishlist;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Services\StorefrontGuard;
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
    // Orders are limited per client, and the customer gets the order without manager notes, history or meta.
    $placeOrder = static function (callable $place) {
        if (!StorefrontGuard::orderAllowed()) {
            return response()->json(['success' => false, 'message' => __('sCommerce::global.order_rate_limited'), 'error_code' => 'rate_limited'], 429);
        }
        $result = $place();
        if (($result['order'] ?? null) instanceof sOrder) {
            StorefrontGuard::orderPlaced();
            $result['order'] = $result['order']->toCustomerArray();
        }

        return $result;
    };
    $processOrderHandler = fn() => $placeOrder(static fn () => sCheckout::processOrder());
    $payOrderHandler = function ($method = null) {
        $result = sCheckout::payOrder($method, request()->all());
        if (request()->header('Accept') && strpos(request()->header('Accept'), 'application/json') !== false) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }
        unset($_SESSION['payOrderResponse']);
        $_SESSION['payOrderResponse'] = $result;
        return back();
    };
    $quickOrderHandler = fn() => $placeOrder(static fn () => sCheckout::quickOrder(request()->all()));
    // Display only: show prices with (gross) or without (net) VAT to this visitor.
    $vatDisplayHandler = function () {
        if (!\Seiger\sCommerce\Services\VatDisplay::enabled()) {
            return response()->json(['success' => false, 'message' => 'Prices without VAT are not enabled.'], 404);
        }
        $mode = request()->string('mode')->trim()->lower()->value();
        if (!in_array($mode, ['gross', 'net'], true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported VAT display mode.'], 422);
        }
        sCart::setVatDisplay($mode);

        return response()->json(['success' => true, 'vatDisplay' => sCart::getVatDisplay(), 'miniCart' => sCart::getMiniCart()]);
    };
    // Empty returnable containers the customer hands over for a cart line (no new deposit for them).
    $containerExchangeHandler = fn() => \Seiger\sCommerce\Services\ContainerDeposit::exchangeEnabled()
        ? tap(sCart::setContainerExchange(), fn($result) => response()->json($result, $result['success'] === true ? 200 : 422))
        : response()->json(['success' => false, 'message' => 'Container exchanges are not enabled.'], 404);
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
    Route::post('vat-display', $vatDisplayHandler)->name('vatDisplay');
    Route::post('container-exchange', $containerExchangeHandler)->name('containerExchange');

    // Routes with suffix (for frontend calls with friendly URLs)
    $suffix = sCommerce::config('basic.friendlyUrlSuffix', evo()->getConfig('friendly_url_suffix', ''));
    if (!empty($suffix) && $suffix != '/') {
        Route::post('add-to-cart' . $suffix, $addToCartHandler);
        Route::post('remove-from-cart' . $suffix, $removeFromCartHandler);
        Route::post('set-order-data' . $suffix, $setOrderDataHandler);
        Route::post('process-order' . $suffix, $processOrderHandler);
        Route::post('pay-order/{method?}' . $suffix, $payOrderHandler);
        Route::post('quick-order' . $suffix, $quickOrderHandler);
        Route::post('wishlist' . $suffix, $wishlistHandler);
        Route::post('vat-display' . $suffix, $vatDisplayHandler);
        Route::post('container-exchange' . $suffix, $containerExchangeHandler);
    }
});

Route::middleware('web')->match(['GET', 'POST'], 'payment-callback/{method?}', function (?string $method = null) {
    try {
        $service = app(sCommerce::class);
        if (!method_exists($service, 'handlePaymentCallback')) {
            return response('PAYMENT CALLBACK DISABLED', 503);
        }

        return $service->handlePaymentCallback($method);
    } catch (\Throwable) {
        return response('PAYMENT CALLBACK UNAVAILABLE', 503);
    }
})->name('payment.callback');

Route::middleware('web')->match(['GET', 'POST'], 'payment-callback/{method}/{identifier}', function (string $method, string $identifier) {
    try {
        $service = app(sCommerce::class);
        if (!method_exists($service, 'handleIdentifiedPaymentCallback')) {
            return response('PAYMENT CALLBACK DISABLED', 503);
        }

        return $service->handleIdentifiedPaymentCallback($method, $identifier);
    } catch (\Throwable) {
        return response('PAYMENT CALLBACK UNAVAILABLE', 503);
    }
})->name('payment.callback.identified');
