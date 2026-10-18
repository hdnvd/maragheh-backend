<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
 * All routes are served under https://api.maraghecity.ir/v1/...
 */

// ---- Public (Android app) ----
Route::middleware('throttle:public')->group(function () {
    Route::get('home', [Api\HomeController::class, 'index']);
    Route::get('weather', [Api\HomeController::class, 'weather']);
    Route::get('news', [Api\NewsController::class, 'index']);
    Route::get('news/{id}', [Api\NewsController::class, 'show'])->whereNumber('id');
    Route::get('sources', [Api\NewsController::class, 'sources']);
    Route::get('posts', [Api\PostController::class, 'index']);
    Route::get('posts/{id}', [Api\PostController::class, 'show'])->whereNumber('id');
    Route::get('photos', [Api\PhotoController::class, 'index']);
});

// ---- App user auth (phone + SMS code) ----
Route::prefix('auth')->group(function () {
    Route::post('otp/request', [Api\AuthController::class, 'requestOtp'])->middleware('throttle:otp-request');
    Route::post('otp/verify', [Api\AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify');
    Route::post('logout', [Api\AuthController::class, 'logout'])->middleware(['auth:sanctum', 'app-user']);
});

Route::middleware(['auth:sanctum', 'app-user', 'throttle:app-user'])->group(function () {
    Route::get('me', [Api\AuthController::class, 'me']);
    Route::get('me/photos', [Api\PhotoController::class, 'mine']);
    Route::post('me/photos', [Api\PhotoController::class, 'store']);
});

// ---- Admin panel ----
Route::prefix('admin')->group(function () {
    Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:admin-login');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('me', [Admin\AuthController::class, 'me']);
        Route::post('logout', [Admin\AuthController::class, 'logout']);
        Route::put('password', [Admin\AuthController::class, 'changePassword']);
        Route::get('dashboard', [Admin\DashboardController::class, 'index']);

        Route::get('news', [Admin\NewsController::class, 'index']);
        Route::get('news/{news}', [Admin\NewsController::class, 'show']);
        Route::patch('news/{news}', [Admin\NewsController::class, 'update']);
        Route::delete('news/{news}', [Admin\NewsController::class, 'destroy']);

        Route::get('sources', [Admin\SourceController::class, 'index']);
        Route::post('sources', [Admin\SourceController::class, 'store']);
        Route::put('sources/{source}', [Admin\SourceController::class, 'update']);
        Route::delete('sources/{source}', [Admin\SourceController::class, 'destroy']);
        Route::post('sources/{source}/crawl', [Admin\SourceController::class, 'crawl']);

        Route::get('posts', [Admin\PostController::class, 'index']);
        Route::post('posts', [Admin\PostController::class, 'store']);
        Route::get('posts/{post}', [Admin\PostController::class, 'show']);
        Route::match(['put', 'post'], 'posts/{post}', [Admin\PostController::class, 'update']);
        Route::delete('posts/{post}', [Admin\PostController::class, 'destroy']);
        Route::post('posts/{post}/images', [Admin\PostController::class, 'addImages']);
        Route::delete('posts/{post}/images/{image}', [Admin\PostController::class, 'deleteImage']);

        Route::get('photos', [Admin\PhotoController::class, 'index']);
        Route::post('photos', [Admin\PhotoController::class, 'store']);
        Route::patch('photos/{photo}', [Admin\PhotoController::class, 'update']);
        Route::post('photos/{photo}/approve', [Admin\PhotoController::class, 'approve']);
        Route::post('photos/{photo}/reject', [Admin\PhotoController::class, 'reject']);
        Route::delete('photos/{photo}', [Admin\PhotoController::class, 'destroy']);

        Route::get('app-users', [Admin\AppUserController::class, 'index']);
        Route::patch('app-users/{appUser}', [Admin\AppUserController::class, 'update']);
    });
});
