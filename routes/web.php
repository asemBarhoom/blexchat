<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['ar', 'en'], true), 404);
    session(['admin_locale' => $locale]);

    return redirect()->back(fallback: '/admin');
})->middleware(['web', 'auth'])->name('admin.locale');
