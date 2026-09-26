<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'inventory-api',
    ]);
});

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::post('/register', [
    AuthController::class,
    'register',
])->middleware('throttle:registration');

Route::post('/login', [
    AuthController::class,
    'login',
])->middleware('throttle:login');

Route::post('/forgot-password', [
    PasswordResetController::class,
    'sendResetLink',
])->middleware('throttle:password-reset');

Route::post('/reset-password', [
    PasswordResetController::class,
    'reset',
])->middleware('throttle:password-reset');

/*
|--------------------------------------------------------------------------
| Email Verification
|--------------------------------------------------------------------------
*/
Route::get('/email/verify/{id}/{hash}', [
    EmailVerificationController::class,
    'verify',
])->middleware([
    'signed',
    'throttle:verification',
])->name('verification.verify');

Route::post('/email/verification-notification', [
    EmailVerificationController::class,
    'send',
])->middleware([
    'auth:sanctum',
    'throttle:verification',
]);

/*
|--------------------------------------------------------------------------
| Google OAuth
|--------------------------------------------------------------------------
*/
Route::get('/auth/google', [
    GoogleAuthController::class,
    'redirect',
]);

Route::get('/auth/google/callback', [
    GoogleAuthController::class,
    'callback',
]);

Route::post('/auth/google/exchange', [
    GoogleAuthController::class,
]);

/*
|--------------------------------------------------------------------------
| Authenticated User
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth:sanctum',
    'verified',
])->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json([
            'data' => new \App\Http\Resources\UserResource(
                $request->user()->load('business')
            ),
        ]);
    });

    Route::patch('/user/profile', [
        UserController::class,
        'updateProfile',
    ]);

    Route::get('/dashboard/summary', [
        DashboardController::class,
        'summary',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */
    Route::get('/categories', [
        CategoryController::class,
        'index',
    ]);

    Route::post('/categories', [
        CategoryController::class,
        'store',
    ]);

    Route::get('/categories/{category}', [
        CategoryController::class,
        'show',
    ]);

    Route::put('/categories/{category}', [
        CategoryController::class,
        'update',
    ]);

    Route::patch('/categories/{category}', [
        CategoryController::class,
        'update',
    ]);

    Route::delete('/categories/{category}', [
        CategoryController::class,
        'destroy',
    ])->middleware('admin');

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */
    Route::get('/products', [
        ProductController::class,
        'index',
    ]);

    Route::post('/products', [
        ProductController::class,
        'store',
    ]);

    Route::get('/products/{product}', [
        ProductController::class,
        'show',
    ]);

    Route::put('/products/{product}', [
        ProductController::class,
        'update',
    ]);

    Route::patch('/products/{product}', [
        ProductController::class,
        'update',
    ]);

    Route::delete('/products/{product}', [
        ProductController::class,
        'destroy',
    ])->middleware('admin');

    Route::get('/products/{product}/stock-movements', [
        StockMovementController::class,
        'index',
    ]);

    Route::post('/products/{product}/stock-movements', [
        StockMovementController::class,
        'store',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Business
    |--------------------------------------------------------------------------
    */
    Route::patch('/business', [
        UserController::class,
        'updateBusiness',
    ])->middleware('admin');

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */
    Route::get('/users', [
        UserController::class,
        'index',
    ])->middleware('admin');

    Route::patch('/users/{user}/role', [
        UserController::class,
        'updateRole',
    ])->middleware('admin');

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    */
    Route::post('/invitations', [
        InvitationController::class,
        'store',
    ])->middleware('admin');

    Route::post('/invitations/accept', [
        InvitationController::class,
        'accept',
    ]);
});

/*
|--------------------------------------------------------------------------
| Public Invitations
|--------------------------------------------------------------------------
*/
Route::get('/invitations/{token}', [
    InvitationController::class,
    'show',
]);

Route::post('/invitations/complete', [
    InvitationController::class,
    'complete',
]);