<?php

use App\Http\Controllers\Channels\FakeInstagramController;
use App\Http\Controllers\Payments\FakePaymentController;
use App\Http\Controllers\Payments\PaddleCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['ar', 'en'], true), 404);
    session(['admin_locale' => $locale]);

    return redirect()->back(fallback: '/admin');
})->middleware(['web', 'auth'])->name('admin.locale');

// PAYMENT_DRIVER=paddle: the default payment link set in Paddle; Paddle.js opens the checkout for ?_ptxn=
Route::get('payments/paddle', PaddleCheckoutController::class)->name('payments.paddle');

// PAYMENT_DRIVER=fake: the test payment page that stands in for Paddle's checkout. Signed links only.
Route::prefix('payments/fake/{session}')->name('payments.fake.')->group(function () {
    Route::get('/', [FakePaymentController::class, 'show'])->name('show');
    Route::post('pay', [FakePaymentController::class, 'pay'])->name('pay');
    Route::post('decline', [FakePaymentController::class, 'decline'])->name('decline');
});

// INSTAGRAM_DRIVER=fake: the test page that stands in for Instagram's sign-in. Signed links only.
Route::prefix('channels/fake/instagram')->name('channels.fake-instagram.')->group(function () {
    Route::get('/', [FakeInstagramController::class, 'show'])->name('show');
    Route::post('/', [FakeInstagramController::class, 'answer'])->name('answer');
});
