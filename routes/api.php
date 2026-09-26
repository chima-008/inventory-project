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
    /*
    |--------------------------------------------------------------------------
    | Current User
    |--------------------------------------------------------------------------
    */

    Route::get('/user', function (Request $request) {
        return response()->json([
            'data' => new \App\Http\Resources\UserResource(
                $request->user()->load('business')
            ),
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::patch('/user/profile', [
        UserController::class,
        'updateProfile',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard/summary', [
        DashboardController::class,
        'summary',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */

    Route::apiResource('categories', CategoryController::class);

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */

    Route::apiResource('products', ProductController::class);

    /*
    |--------------------------------------------------------------------------
    | Stock Movements
    |--------------------------------------------------------------------------
    */

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
    |
    | Business name can only be changed by the admin.
    |
    */

    Route::patch('/business', [
        UserController::class,
        'updateBusiness',
    ])->middleware('admin');

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    |
    | Only admins can view users and change roles.
    |
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
    | Manager Invitations
    |--------------------------------------------------------------------------
    |
    | Admins can send invitations.
    |
    */

    Route::post('/invitations', [
        InvitationController::class,
        'store',
    ])->middleware('admin');

    /*
    |--------------------------------------------------------------------------
    | Accept Existing Account Invitation
    |--------------------------------------------------------------------------
    |
    | The invited user must already be authenticated and verified.
    |
    */

    Route::post('/invitations/accept', [
        InvitationController::class,
        'accept',
    ]);

});

/*
|--------------------------------------------------------------------------
| Invitation Details
|--------------------------------------------------------------------------
|
| This endpoint is public because an invited person may not have
| an account yet. The token itself identifies the invitation.
|
*/

Route::get('/invitations/{token}', [
    InvitationController::class,
    'show',
]);

/*
|--------------------------------------------------------------------------
| Complete New Manager Account
|--------------------------------------------------------------------------
|
| A person without an existing account can create their account
| directly from an invitation.
|
*/

Route::post('/invitations/complete', [
    InvitationController::class,
    'complete',
]);