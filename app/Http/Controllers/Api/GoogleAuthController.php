<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\OAuthLoginCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();
        } catch (Throwable $exception) {
            logger()->error('Google authentication failed', [
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
            ]);

            report($exception);

            return redirect()->away(
                $this->frontendUrl('/login?error=google_account_failed')
            );
        }

        $rawGoogleUser = $googleUser->getRaw();

        $emailVerified = filter_var(
            $rawGoogleUser['email_verified'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if (! $emailVerified) {
            return redirect()->away(
                $this->frontendUrl('/login?error=google_email_unverified')
            );
        }

        $email = $googleUser->getEmail();

        if (! $email) {
            return redirect()->away(
                $this->frontendUrl('/login?error=google_email_required')
            );
        }

        $email = strtolower(trim($email));

        $user = User::where('email', $email)->first();

        $rawCode = Str::random(64);

        $codeHash = hash('sha256', $rawCode);

        if ($user) {
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $oauthCode = OAuthLoginCode::create([
                'user_id' => $user->id,
                'google_email' => null,
                'google_name' => null,
                'code_hash' => $codeHash,
                'expires_at' => now()->addMinutes(2),
            ]);

            logger()->info('Google OAuth code created', [
                'oauth_code_id' => $oauthCode->id,
                'user_id' => $user->id,
                'code_hash' => $codeHash,
                'expires_at' => $oauthCode->expires_at?->toIso8601String(),
            ]);
        } else {
            $oauthCode = OAuthLoginCode::create([
                'user_id' => null,
                'google_email' => $email,
                'google_name' => $googleUser->getName(),
                'code_hash' => $codeHash,
                'expires_at' => now()->addMinutes(2),
            ]);

            logger()->info('Google OAuth code created for new user', [
                'oauth_code_id' => $oauthCode->id,
                'google_email' => $email,
                'code_hash' => $codeHash,
                'expires_at' => $oauthCode->expires_at?->toIso8601String(),
            ]);
        }

        logger()->info('Google OAuth callback redirecting', [
            'oauth_code_id' => $oauthCode->id,
            'code_hash' => $codeHash,
        ]);

        return redirect()->away(
            $this->frontendUrl(
                '/auth/callback?code=' . urlencode($rawCode)
            )
        );
    }

    public function exchangeCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'size:64',
            ],
            'business_name' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $codeHash = hash(
            'sha256',
            $validated['code']
        );

        logger()->info('Google OAuth exchange started', [
            'code_hash' => $codeHash,
            'code_length' => strlen($validated['code']),
        ]);

        $result = DB::transaction(function () use (
            $codeHash,
            $validated
        ) {
            $oauthCode = OAuthLoginCode::where(
                'code_hash',
                $codeHash
            )
                ->whereNull('used_at')
                ->lockForUpdate()
                ->first();

            logger()->info('Google OAuth exchange lookup', [
                'code_hash' => $codeHash,
                'found' => (bool) $oauthCode,
                'oauth_code_id' => $oauthCode?->id,
                'user_id' => $oauthCode?->user_id,
                'used_at' => $oauthCode?->used_at?->toIso8601String(),
                'expires_at' => $oauthCode?->expires_at?->toIso8601String(),
            ]);

            if (! $oauthCode) {
                $matchingCode = OAuthLoginCode::where(
                    'code_hash',
                    $codeHash
                )->first();

                logger()->warning('Google OAuth code not usable', [
                    'code_hash' => $codeHash,
                    'matching_record_exists' => (bool) $matchingCode,
                    'matching_record_id' => $matchingCode?->id,
                    'matching_record_user_id' => $matchingCode?->user_id,
                    'matching_record_used_at' => $matchingCode?->used_at?->toIso8601String(),
                    'matching_record_expires_at' => $matchingCode?->expires_at?->toIso8601String(),
                ]);

                return [
                    'status' => 'invalid',
                ];
            }

            if ($oauthCode->expires_at->isPast()) {
                logger()->warning('Google OAuth code expired', [
                    'oauth_code_id' => $oauthCode->id,
                    'expires_at' => $oauthCode->expires_at->toIso8601String(),
                ]);

                $oauthCode->update([
                    'used_at' => now(),
                ]);

                return [
                    'status' => 'expired',
                ];
            }

            if ($oauthCode->user_id) {
                $user = $oauthCode->user;

                if (! $user) {
                    return [
                        'status' => 'invalid',
                    ];
                }

                $oauthCode->update([
                    'used_at' => now(),
                ]);

                $token = $user
                    ->createToken('inventory-api')
                    ->plainTextToken;

                return [
                    'status' => 'success',
                    'token' => $token,
                ];
            }

            if (! $oauthCode->google_email) {
                return [
                    'status' => 'invalid',
                ];
            }

            $businessName = trim(
                (string) ($validated['business_name'] ?? '')
            );

            if ($businessName === '') {
                return [
                    'status' => 'requires_business_name',
                ];
            }

            $existingUser = User::where(
                'email',
                $oauthCode->google_email
            )
                ->lockForUpdate()
                ->first();

            if ($existingUser) {
                if (! $existingUser->hasVerifiedEmail()) {
                    $existingUser->markEmailAsVerified();
                }

                $oauthCode->user_id = $existingUser->id;
                $oauthCode->google_email = null;
                $oauthCode->google_name = null;
                $oauthCode->used_at = now();
                $oauthCode->save();

                $token = $existingUser
                    ->createToken('inventory-api')
                    ->plainTextToken;

                return [
                    'status' => 'success',
                    'token' => $token,
                ];
            }

            $business = Business::create([
                'name' => $businessName,
            ]);

            $user = User::create([
                'business_id' => $business->id,
                'name' => $oauthCode->google_name
                    ?: $this->nameFromEmail(
                        $oauthCode->google_email
                    ),
                'email' => $oauthCode->google_email,
                'password' => null,
                'role' => UserRole::ADMIN->value,
                'email_verified_at' => now(),
            ]);

            $oauthCode->update([
                'user_id' => $user->id,
                'google_email' => null,
                'google_name' => null,
                'used_at' => now(),
            ]);

            $token = $user
                ->createToken('inventory-api')
                ->plainTextToken;

            return [
                'status' => 'success',
                'token' => $token,
            ];
        });

        if ($result['status'] === 'expired') {
            return response()->json([
                'message' =>
                    'This authentication code has expired. Please try again.',
            ], 422);
        }

        if ($result['status'] === 'invalid') {
            return response()->json([
                'message' =>
                    'This authentication code is invalid or has already been used.',
            ], 422);
        }

        if ($result['status'] === 'requires_business_name') {
            return response()->json([
                'message' =>
                    'Please provide your business name to continue.',
                'requires_business_name' => true,
            ]);
        }

        return response()->json([
            'message' =>
                'Google authentication successful.',
            'token' => $result['token'],
        ]);
    }

    private function frontendUrl(string $path): string
    {
        return rtrim(
            env('FRONTEND_URL', 'http://localhost:3000'),
            '/'
        ) . $path;
    }

    private function nameFromEmail(string $email): string
    {
        $localPart = strstr($email, '@', true);

        return $localPart !== false
            ? $localPart
            : 'Google User';
    }
}