<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandAddressController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CoffeeMenuController;
use App\Http\Controllers\EstablishmentController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FoodCourtController;
use App\Http\Controllers\HeaderImageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IceTicketController;
use App\Http\Controllers\LocalizationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderFeedbackController;
use App\Http\Controllers\OrderQuoteController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromoTileController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\TipTopWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/home', HomeController::class)->name('api.home');
Route::get('/localizations', [LocalizationController::class, 'index'])->name('api.localizations.index');
Route::get('/localizations/{locale}', [LocalizationController::class, 'show'])->name('api.localizations.show');
Route::get('/header-images', HeaderImageController::class)->name('api.header-images');
Route::get('/promo-tiles', PromoTileController::class)->name('api.promo-tiles');
Route::get('/privacy-policy', PrivacyPolicyController::class)->name('api.privacy-policy');
Route::get('/offer', OfferController::class)->name('api.offer');
Route::get('/brands/{brand}/addresses', [BrandAddressController::class, 'index'])
    ->name('api.brands.addresses.index');
Route::get('/brands/{brand}/addresses/{establishment}', [BrandAddressController::class, 'show'])
    ->name('api.brands.addresses.show');
Route::get('/establishments/{establishment}', [EstablishmentController::class, 'show'])
    ->name('api.establishments.show');
Route::get('/establishments/{establishment}/rentals', [RentalController::class, 'show'])
    ->name('api.establishments.rentals');
Route::get('/establishments/{establishment}/foodcourt', [FoodCourtController::class, 'show'])
    ->name('api.establishments.foodcourt');
Route::get('/establishments/{establishment}/foodcourt/products/{product}', [FoodCourtController::class, 'product'])
    ->name('api.establishments.foodcourt.products.show');
Route::get('/establishments/{establishment}/menu', [CoffeeMenuController::class, 'index'])
    ->name('api.establishments.menu');
Route::get('/establishments/{establishment}/menu/products/{product}', [CoffeeMenuController::class, 'show'])
    ->name('api.establishments.menu.products.show');
Route::post('/establishments/{establishment}/menu/quote', [CoffeeMenuController::class, 'quoteItem'])
    ->name('api.establishments.menu.quote');
Route::post('/orders/quote', OrderQuoteController::class)
    ->name('api.orders.quote');
Route::get('/establishments/{establishment}/promo-codes/{code}', [IceTicketController::class, 'previewPromo'])
    ->name('api.establishments.promo-codes.show');

Route::prefix('payments/tiptop')->group(function () {
    Route::post('/check', [TipTopWebhookController::class, 'check'])->name('api.payments.tiptop.check');
    Route::post('/pay', [TipTopWebhookController::class, 'pay'])->name('api.payments.tiptop.pay');
    Route::post('/fail', [TipTopWebhookController::class, 'fail'])->name('api.payments.tiptop.fail');
    Route::post('/refund', [TipTopWebhookController::class, 'refund'])->name('api.payments.tiptop.refund');
});

Route::prefix('auth')->group(function () {
    Route::post('/send-code', [AuthController::class, 'sendCode'])
        ->middleware('throttle:5,1')
        ->name('api.auth.send-code');

    Route::post('/verify-code', [AuthController::class, 'verifyCode'])
        ->middleware('throttle:10,1')
        ->name('api.auth.verify-code');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('api.auth.logout');
        Route::delete('/account', [AuthController::class, 'destroy'])
            ->name('api.auth.account.destroy');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [ProfileController::class, 'me'])->name('api.me');

    Route::get('/profile', [ProfileController::class, 'show'])->name('api.profile.show');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('api.profile.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'readAll'])->name('api.notifications.read-all');
    Route::post('/notifications/{userNotification}/read', [NotificationController::class, 'read'])->name('api.notifications.read');

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('api.favorites.index');
    Route::post('/favorites', [FavoriteController::class, 'store'])->name('api.favorites.store');
    Route::delete('/favorites/{establishment}', [FavoriteController::class, 'destroy'])->name('api.favorites.destroy');

    Route::get('/establishments/{establishment}/cart', [CartController::class, 'show'])
        ->name('api.establishments.cart.show');
    Route::patch('/establishments/{establishment}/cart', [CartController::class, 'update'])
        ->name('api.establishments.cart.update');
    Route::delete('/establishments/{establishment}/cart', [CartController::class, 'clear'])
        ->name('api.establishments.cart.clear');
    Route::post('/establishments/{establishment}/cart/items', [CartController::class, 'addItem'])
        ->name('api.establishments.cart.items.store');
    Route::patch('/establishments/{establishment}/cart/items/{cartItem}', [CartController::class, 'updateItem'])
        ->name('api.establishments.cart.items.update');
    Route::delete('/establishments/{establishment}/cart/items/{cartItem}', [CartController::class, 'removeItem'])
        ->name('api.establishments.cart.items.destroy');
    Route::post('/establishments/{establishment}/cart/checkout', [CartController::class, 'checkout'])
        ->name('api.establishments.cart.checkout');

    Route::get('/orders', [OrderController::class, 'index'])->name('api.orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->name('api.orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('api.orders.show');
    Route::get('/orders/{order}/coffee-screen', [OrderFeedbackController::class, 'screen'])
        ->name('api.orders.coffee-screen');
    Route::post('/orders/{order}/feedback', [OrderFeedbackController::class, 'store'])
        ->name('api.orders.feedback');
    Route::post('/orders/{order}/payments/charge', [PaymentController::class, 'charge'])
        ->name('api.orders.payments.charge');
    Route::post('/orders/{order}/payments/3ds', [PaymentController::class, 'complete3ds'])
        ->name('api.orders.payments.3ds');

    Route::post('/orders/{order}/passes/{barcode}/refund', [IceTicketController::class, 'refund'])
        ->name('api.orders.passes.refund');
    Route::post('/orders/{order}/passes/{barcode}/move', [IceTicketController::class, 'move'])
        ->name('api.orders.passes.move');
});
