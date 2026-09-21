<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Api\PasswordResetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'inventory-api',
    ]);
});

// Authentication routes
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:register');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/email/verification-notification', [
    AuthController::class,
    'resendVerification',
])->middleware('throttle:verification');

Route::post(
    '/forgot-password',
    [PasswordResetController::class, 'forgotPassword']
)->middleware('throttle:5,1');

Route::post(
    '/reset-password',
    [PasswordResetController::class, 'resetPassword']
)->middleware('throttle:10,1');

// Signed email verification link
Route::get('/email/verify/{id}/{hash}', [
    EmailVerificationController::class,
    'verify',
])
    ->middleware(['signed', 'throttle:verification'])
    ->name('verification.verify');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(['auth:sanctum', 'verified']);

Route::get('/user', function (Request $request) {
    return \App\Http\Resources\UserResource::make($request->user());
})->middleware(['auth:sanctum', 'verified']);

// Category routes
Route::get('/categories', [CategoryController::class, 'index'])
    ->middleware(['auth:sanctum', 'verified']);

Route::post('/categories', [CategoryController::class, 'store'])
    ->middleware(['auth:sanctum', 'verified']);

Route::get('/categories/{category}', [CategoryController::class, 'show'])
    ->middleware(['auth:sanctum', 'verified']);

Route::put('/categories/{category}', [CategoryController::class, 'update'])
    ->middleware(['auth:sanctum', 'verified']);

Route::patch('/categories/{category}', [CategoryController::class, 'update'])
    ->middleware(['auth:sanctum', 'verified']);

Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'verified', 'admin']);

// Product routes
Route::get('/products', [ProductController::class, 'index'])
    ->middleware(['auth:sanctum', 'verified']);

Route::post('/products', [ProductController::class, 'store'])
    ->middleware(['auth:sanctum', 'verified']);

Route::get('/products/low-stock', [ProductController::class, 'lowStock'])
    ->middleware(['auth:sanctum', 'verified']);

Route::get('/products/{product}', [ProductController::class, 'show'])
    ->middleware(['auth:sanctum', 'verified']);

Route::put('/products/{product}', [ProductController::class, 'update'])
    ->middleware(['auth:sanctum', 'verified']);

Route::patch('/products/{product}', [ProductController::class, 'update'])
    ->middleware(['auth:sanctum', 'verified']);

Route::delete('/products/{product}', [ProductController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'verified', 'admin']);

// Stock movement routes
Route::get('/products/{product}/stock-movements', [
    StockMovementController::class,
    'index',
])->middleware(['auth:sanctum', 'verified']);

Route::post('/products/{product}/stock-movements', [
    StockMovementController::class,
    'store',
])->middleware(['auth:sanctum', 'verified']);

// Dashboard routes
Route::get('/dashboard/summary', [
    DashboardController::class,
    'summary',
])->middleware(['auth:sanctum', 'verified']);

// User management routes
Route::get('/users', [UserController::class, 'index'])
    ->middleware(['auth:sanctum', 'verified', 'admin']);

Route::patch('/users/{user}/role', [
    UserController::class,
    'updateRole',
])->middleware(['auth:sanctum', 'verified', 'admin']);