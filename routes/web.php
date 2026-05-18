<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\LandingController;

Route::get('/', function () {
    return view('landing');
});

Route::get('/', [LandingController::class, 'index'])->name('landing');


Route::get('/lang/{lang}', function ($lang) {
    if (!in_array($lang, ['en', 'ar'])) $lang = 'en';

    session()->put('app_locale', $lang);   // ✅ يخزن في session
    app()->setLocale($lang);              // ✅ فوري للطلب الحالي

    return redirect()->back();
})->name('web.lang.switch');

Route::view('/about', 'pages.about')->name('web.about');
Route::view('/terms', 'pages.terms')->name('web.terms');
Route::view('/privacy', 'pages.privacy')->name('web.privacy');
