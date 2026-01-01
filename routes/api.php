<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Pages\PageController;
use App\Http\Controllers\Language\LanguageController;
use App\Http\Controllers\Regions\RegionsController;

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['middleware'=>'api','prefix'=>'auth'],function($router){
    Route::post('/register',[AuthController::class,'register']);
    Route::post('/login',[AuthController::class,'login']);
    Route::get('/profile',[AuthController::class,'profile']);
    Route::post('/logout',[AuthController::class,'logout']);
});

Route::group(['middlewar'=>'api','prefix'=>'pages'],function($router){
    Route::get('/about',[PageController::class,'about']);
    Route::get('/terms_conditions',[PageController::class,'termsAndCondition']);
    Route::get('/privacy_policy',[PageController::class,'privacyPolicy']);
});

Route::get('/{lang}/lang-test', function () {
    return response()->json([
        'message' => __('messages.register_success')
    ]);
});

Route::get('/change/{lang}',[LanguageController::class,'changeLang'])->name('changeLang');

Route::get('/governorates', [RegionsController::class, 'getAllGovernorates']);


