<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlagsController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/flags', [FlagsController::class, 'index']);

Route::prefix('auth')->group(function () {
    Route::post('/device', [AuthController::class, 'device']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/social/{provider}', [AuthController::class, 'social']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
    Route::put('/me', [MeController::class, 'update']);
    Route::put('/me/locale', [MeController::class, 'updateLocale']);
    Route::post('/me/avatar', [MeController::class, 'avatar']);

    Route::get('/users/search', [UserController::class, 'search']);
    Route::get('/users/{user}', [UserController::class, 'show']);

    Route::get('/friends', [FriendController::class, 'index']);
    Route::get('/friends/requests', [FriendController::class, 'requests']);
    Route::get('/friends/suggestions', [FriendController::class, 'suggestions']);
    Route::post('/friends/request/{user}', [FriendController::class, 'sendRequest']);
    Route::post('/friends/respond/{friendRequest}', [FriendController::class, 'respond']);
});
