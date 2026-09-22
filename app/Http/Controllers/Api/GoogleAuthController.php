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
    logger()->error('Google account creation failed', [
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

        if (! $user) {
            try {
                $user = DB::transaction(function () use (
                    $googleUser,
                    $email
                ) {
                    $business = Business::create([
                        'name' => $this->businessName(
                            $googleUser->getName(),
                            $email
                        ),
                    ]);

                    return User::create([
                        'business_id' => $business->id,
                        'name' => $googleUser->getName()
                            ?: $this->nameFromEmail($email),
                        'email' => $email,
                        'password' => null,
                        'role' => UserRole::ADMIN->value,
                        'email_verified_at' => now(),
                    ]);
                });
            } catch (Throwable $exception) {
                report($exception);

                return redirect()->away(
                    $this->frontendUrl('/login?error=google_account_failed')
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create a short-lived, one-time OAuth handoff code
        |--------------------------------------------------------------------------
        */

        $rawCode = Str::random(64);

        OAuthLoginCode::create([
            'user_id' => $user->id,
            'code_hash' => hash('sha256', $rawCode),
            'expires_at' => now()->addMinutes(2),
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
    ]);

    $codeHash = hash(
        'sha256',
        $validated['code']
    );

    $result = DB::transaction(function () use ($codeHash) {
        $oauthCode = OAuthLoginCode::with('user')
            ->where('code_hash', $codeHash)
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

        $oauthCode->update([
            'used_at' => now(),
        ]);

        $token = $oauthCode->user
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

    private function businessName(
        ?string $googleName,
        string $email
    ): string {
        $name = trim((string) $googleName);

        if ($name !== '') {
            return $name . "'s Business";
        }

        return $this->nameFromEmail($email) . "'s Business";
    }

    private function nameFromEmail(string $email): string
    {
        $localPart = strstr($email, '@', true);

        return $localPart !== false
            ? $localPart
            : 'Google User';
    }
}