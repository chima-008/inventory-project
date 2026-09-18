<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\UserController;

//Authentication routes
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:register');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return \App\Http\Resources\UserResource::make($request->user());
})->middleware('auth:sanctum');

//Category routes
Route::get('/categories', [CategoryController::class, 'index'])
    ->middleware('auth:sanctum');

Route::post('/categories', [CategoryController::class, 'store'])
    ->middleware('auth:sanctum');

Route::get('/categories/{category}', [CategoryController::class, 'show'])
    ->middleware('auth:sanctum');

Route::put('/categories/{category}', [CategoryController::class, 'update'])
    ->middleware('auth:sanctum');

Route::patch('/categories/{category}', [CategoryController::class, 'update'])
    ->middleware('auth:sanctum');

Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'admin']);

//Product routes
Route::get('/products', [ProductController::class, 'index'])
    ->middleware('auth:sanctum');

Route::post('/products', [ProductController::class, 'store'])
    ->middleware('auth:sanctum');

Route::get('/products/low-stock', [ProductController::class, 'lowStock'])
    ->middleware('auth:sanctum');    

Route::get('/products/{product}', [ProductController::class, 'show'])
    ->middleware('auth:sanctum');

Route::put('/products/{product}', [ProductController::class, 'update'])
    ->middleware('auth:sanctum');

Route::patch('/products/{product}', [ProductController::class, 'update'])
    ->middleware('auth:sanctum');

Route::delete('/products/{product}', [ProductController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'admin']);

//Stock Movement routes
Route::get('/products/{product}/stock-movements', [StockMovementController::class, 'index'])
    ->middleware('auth:sanctum');

Route::post('/products/{product}/stock-movements', [StockMovementController::class, 'store'])
    ->middleware('auth:sanctum');

//Dashboard routes
Route::get('/dashboard/summary', [DashboardController::class, 'summary'])
    ->middleware('auth:sanctum');

//User management routes    
Route::get('/users', [UserController::class, 'index'])
    ->middleware(['auth:sanctum', 'admin']);

Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])
    ->middleware(['auth:sanctum', 'admin']);
