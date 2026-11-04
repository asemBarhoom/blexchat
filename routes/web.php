<?php

use App\Http\Controllers\Payments\FakePaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['ar', 'en'], true), 404);
    session(['admin_locale' => $locale]);

    return redirect()->back(fallback: '/admin');
})->middleware(['web', 'auth'])->name('admin.locale');

// PAYMENT_DRIVER=fake: the test payment page that stands in for Stripe Checkout. Signed links only.
Route::prefix('payments/fake/{session}')->name('payments.fake.')->group(function () {
    Route::get('/', [FakePaymentController::class, 'show'])->name('show');
    Route::post('pay', [FakePaymentController::class, 'pay'])->name('pay');
    Route::post('decline', [FakePaymentController::class, 'decline'])->name('decline');
});
