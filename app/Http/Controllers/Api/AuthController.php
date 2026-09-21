<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $existingUser = User::where(
            'email',
            $validated['email']
        )->first();

        if ($existingUser) {
            if (! $existingUser->hasVerifiedEmail()) {
                return response()->json([
                    'message' => 'An account with this email already exists but has not been verified.',
                    'email_verification_required' => true,
                    'email' => $existingUser->email,
                ], 409);
            }

            return response()->json([
                'message' => 'The email has already been taken.',
                'errors' => [
                    'email' => [
                        'The email has already been taken.',
                    ],
                ],
            ], 422);
        }

        $result = DB::transaction(function () use ($validated) {
            $business = Business::create([
                'name' => $validated['business_name'],
            ]);

            $user = User::create([
                'business_id' => $business->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => UserRole::ADMIN->value,
            ]);

            return [
                'business' => $business,
                'user' => $user,
            ];
        });

        $result['user']->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Registration successful. Please verify your email address before logging in.',
            'business' => $result['business'],
            'user' => UserResource::make($result['user']),
            'email_verification_required' => true,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where(
            'email',
            $validated['email']
        )->first();

        if (
            ! $user ||
            ! Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email address before logging in.',
                'email_verification_required' => true,
                'email' => $user->email,
            ], 403);
        }

        $token = $user->createToken(
            'inventory-api'
        )->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => UserResource::make($user),
            'token' => $token,
        ]);
    }

    public function resendVerification(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where(
            'email',
            $validated['email']
        )->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'If an account with that email requires verification, a new verification email has been sent.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }
}