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

        /*
        |--------------------------------------------------------------------------
        | Existing account
        |--------------------------------------------------------------------------
        |
        | If the Google email already belongs to an account, we keep the
        | existing login flow. No business onboarding is required.
        |
        */

        $user = User::where('email', $email)->first();

        $rawCode = Str::random(64);

        if ($user) {
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            OAuthLoginCode::create([
                'user_id' => $user->id,
                'google_email' => null,
                'google_name' => null,
                'code_hash' => hash('sha256', $rawCode),
                'expires_at' => now()->addMinutes(2),
            ]);
        } else {
            /*
            |--------------------------------------------------------------------------
            | New Google account
            |--------------------------------------------------------------------------
            |
            | Do NOT create a Business or User yet.
            |
            | The short-lived OAuth code temporarily stores the verified
            | Google identity. The frontend will ask for the business name,
            | then the exchange endpoint will create both records atomically.
            |
            */

            OAuthLoginCode::create([
                'user_id' => null,
                'google_email' => $email,
                'google_name' => $googleUser->getName(),
                'code_hash' => hash('sha256', $rawCode),
                'expires_at' => now()->addMinutes(2),
            ]);
        }

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

            if (! $oauthCode) {
                return [
                    'status' => 'invalid',
                ];
            }

            if ($oauthCode->expires_at->isPast()) {
                $oauthCode->update([
                    'used_at' => now(),
                ]);

                return [
                    'status' => 'expired',
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Existing user
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | New Google user
            |--------------------------------------------------------------------------
            */

            if (! $oauthCode->google_email) {
                return [
                    'status' => 'invalid',
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | First exchange:
            | ask frontend for business name without consuming the code.
            |--------------------------------------------------------------------------
            */

            $businessName = trim(
                (string) ($validated['business_name'] ?? '')
            );

            if ($businessName === '') {
                return [
                    'status' => 'requires_business_name',
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Protect against the email being registered between the Google
            | callback and this exchange.
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Create business + user atomically
            |--------------------------------------------------------------------------
            */

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
                'message' => 'This authentication code has expired. Please try again.',
            ], 422);
        }

        if ($result['status'] === 'invalid') {
            return response()->json([
                'message' => 'This authentication code is invalid or has already been used.',
            ], 422);
        }

        if ($result['status'] === 'requires_business_name') {
            return response()->json([
                'message' => 'Please provide your business name to continue.',
                'requires_business_name' => true,
            ]);
        }

        return response()->json([
            'message' => 'Google authentication successful.',
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