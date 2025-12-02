<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Pages\PageController;
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['middleware'=>'api','prefix'=>'auth'],function($router){
    Route::post('/register',[AuthController::class,'register']);
    Route::post('/login',[AuthController::class,'login']);
    Route::get('/profile',[AuthController::class,'profile']);
    Route::post('/logout',[AuthController::class,'logout']);
});

Route::group(['middleware'=>'api','prefix'=>'pages'],function($router){
    Route::get('/about',[PageController::class,'about']);
    Route::get('/terms_conditions',[PageController::class,'termsAndCondition']);
    Route::get('/privacy_policy',[PageController::class,'privacyPolicy']);
});
