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
use Throwable;

class AuthController extends Controller
{
    public function register(
        RegisterRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        $existingUser = User::where(
            'email',
            $validated['email']
        )->first();

        if ($existingUser) {
            if (! $existingUser->hasVerifiedEmail()) {
                return response()->json([
                    'message' =>
                        'An account with this email already exists but has not been verified.',
                    'email_verification_required' => true,
                    'email' => $existingUser->email,
                ], 409);
            }

            return response()->json([
                'message' =>
                    'The email has already been taken.',
                'errors' => [
                    'email' => [
                        'The email has already been taken.',
                    ],
                ],
            ], 422);
        }

        $result = DB::transaction(
            function () use ($validated) {
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
            }
        );

        $verificationEmailSent = true;

        try {
            $result['user']
                ->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            $verificationEmailSent = false;

            report($exception);

            logger()->error(
                'Registration verification email failed.',
                [
                    'user_id' => $result['user']->id,
                    'email' => $result['user']->email,
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return response()->json([
            'message' => $verificationEmailSent
                ? 'Registration successful. Please verify your email address before logging in.'
                : 'Registration successful, but we could not send the verification email. Please use the resend verification option.',
            'business' => $result['business'],
            'user' => UserResource::make(
                $result['user']
            ),
            'email_verification_required' => true,
            'verification_email_sent' =>
                $verificationEmailSent,
        ], 201);
    }

    public function login(
        LoginRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        $user = User::where(
            'email',
            $validated['email']
        )->first();

        if (
            ! $user ||
            ! $user->password ||
            ! Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            return response()->json([
                'message' =>
                    'The provided credentials are incorrect.',
            ], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' =>
                    'Please verify your email address before logging in.',
                'email_verification_required' => true,
                'email' => $user->email,
            ], 403);
        }

        $token = $user
            ->createToken('inventory-api')
            ->plainTextToken;

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
            'email' => [
                'required',
                'email',
            ],
        ]);

        $user = User::where(
            'email',
            strtolower(trim($validated['email']))
        )->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (Throwable $exception) {
                report($exception);

                logger()->error(
                    'Verification resend email failed.',
                    [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'message' => $exception->getMessage(),
                    ]
                );
            }
        }

        return response()->json([
            'message' =>
                'If an account with that email requires verification, a new verification email has been sent.',
        ]);
    }

    public function logout(
        Request $request
    ): JsonResponse {
        $request->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }
}