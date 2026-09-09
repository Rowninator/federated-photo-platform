<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', HealthController::class);

Route::post('/register', RegisterController::class);

Route::post('/login', [SessionController::class, 'store']);
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth');

Route::get('/account', AccountController::class)->middleware('auth');

Route::patch('/profile', ProfileController::class)->middleware('auth');
