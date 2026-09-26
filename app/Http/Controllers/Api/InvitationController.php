<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\CompleteInvitationRequest;
use App\Http\Requests\InviteManagerRequest;
use App\Http\Resources\UserResource;
use App\Models\Invitation;
use App\Models\User;
use App\Services\BrevoMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class InvitationController extends Controller
{
    public function store(
        InviteManagerRequest $request,
        BrevoMailService $mailService
    ): JsonResponse {
        $admin = $request->user();

        abort_unless(
            $admin->business_id !== null,
            404
        );

        $email = strtolower(
            trim($request->validated()['email'])
        );

        if ($email === strtolower($admin->email)) {
            return response()->json([
                'message' => 'You cannot invite yourself.',
            ], 422);
        }

        $existingUser = User::whereRaw(
            'LOWER(email) = ?',
            [$email]
        )->first();

        if ($existingUser) {
            if (
                $existingUser->business_id ===
                $admin->business_id
            ) {
                return response()->json([
                    'message' =>
                        'This user is already a member of your business.',
                ], 422);
            }

            if ($existingUser->business_id !== null) {
                return response()->json([
                    'message' =>
                        'This email is already associated with another account.',
                ], 422);
            }
        }

        $pendingInvitation = Invitation::query()
            ->where(
                'business_id',
                $admin->business_id
            )
            ->whereRaw(
                'LOWER(email) = ?',
                [$email]
            )
            ->whereNull('accepted_at')
            ->where(
                'expires_at',
                '>',
                now()
            )
            ->first();

        if ($pendingInvitation) {
            return response()->json([
                'message' =>
                    'A pending invitation already exists for this email address.',
            ], 422);
        }

        $token = Str::random(64);

        $invitation = DB::transaction(
            function () use (
                $admin,
                $email,
                $token
            ) {
                return Invitation::create([
                    'business_id' =>
                        $admin->business_id,

                    'invited_by' =>
                        $admin->id,

                    'email' =>
                        $email,

                    'role' =>
                        UserRole::MANAGER->value,

                    'token_hash' =>
                        hash('sha256', $token),

                    'expires_at' =>
                        now()->addDays(7),
                ]);
            }
        );

        $business = $admin->business;

        if (! $business) {
            $invitation->delete();

            throw new RuntimeException(
                'The authenticated user does not have a valid business.'
            );
        }

        $frontendUrl = rtrim(
            env(
                'FRONTEND_URL',
                'http://localhost:3000'
            ),
            '/'
        );

        $invitationUrl =
            $frontendUrl .
            '/invite/' .
            urlencode($token);

        try {
            $mailService->sendManagerInvitationEmail(
                $email,
                $email,
                $business->name,
                $invitationUrl,
                $admin->name
            );
        } catch (\Throwable $exception) {
            $invitation->delete();

            throw $exception;
        }

        return response()->json([
            'message' =>
                'Manager invitation sent successfully.',
        ], 201);
    }

    public function show(
        string $token
    ): JsonResponse {
        $invitation = $this->findValidInvitation(
            $token
        );

        if (! $invitation) {
            return response()->json([
                'message' =>
                    'This invitation is invalid, expired, or has already been accepted.',
            ], 404);
        }

        $existingUser = User::whereRaw(
            'LOWER(email) = ?',
            [strtolower($invitation->email)]
        )->first();

        if (
            $existingUser &&
            $existingUser->business_id !== null
        ) {
            return response()->json([
                'message' =>
                    'This invitation cannot be accepted because this account already belongs to a business.',
            ], 422);
        }

        return response()->json([
            'data' => [
                'email' =>
                    $invitation->email,

                'business' => [
                    'id' =>
                        $invitation->business->id,

                    'name' =>
                        $invitation->business->name,
                ],

                'role' =>
                    $invitation->role,

                'expires_at' =>
                    $invitation->expires_at,

                'existing_account' =>
                    $existingUser !== null,
            ],
        ]);
    }

    public function accept(
        AcceptInvitationRequest $request
    ) {
        $invitation = $this->findValidInvitation(
            $request->validated()['token']
        );

        if (! $invitation) {
            return response()->json([
                'message' =>
                    'This invitation is invalid, expired, or has already been accepted.',
            ], 404);
        }

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' =>
                    'You must be logged in to accept this invitation.',
            ], 401);
        }

        if (
            strtolower($user->email) !==
            strtolower($invitation->email)
        ) {
            return response()->json([
                'message' =>
                    'This invitation was issued for a different email address.',
            ], 403);
        }

        if ($user->business_id !== null) {
            return response()->json([
                'message' =>
                    'Your account already belongs to a business.',
            ], 422);
        }

        DB::transaction(function () use (
            $invitation,
            $user
        ) {
            $user->update([
                'business_id' =>
                    $invitation->business_id,

                'role' =>
                    UserRole::MANAGER,
            ]);

            $invitation->update([
                'accepted_at' =>
                    now(),
            ]);
        });

        $user->load('business');

        return UserResource::make($user)
            ->additional([
                'message' =>
                    'Invitation accepted successfully.',
            ]);
    }

    public function complete(
        CompleteInvitationRequest $request
    ) {
        $validated = $request->validated();

        $invitation = $this->findValidInvitation(
            $validated['token']
        );

        if (! $invitation) {
            return response()->json([
                'message' =>
                    'This invitation is invalid, expired, or has already been accepted.',
            ], 404);
        }

        $email = strtolower(
            $invitation->email
        );

        $existingUser = User::whereRaw(
            'LOWER(email) = ?',
            [$email]
        )->first();

        if ($existingUser) {
            if ($existingUser->business_id !== null) {
                return response()->json([
                    'message' =>
                        'An account with this email already belongs to a business.',
                ], 422);
            }

            return response()->json([
                'message' =>
                    'An account already exists for this invitation. Please log in and accept the invitation.',
            ], 422);
        }

        $user = DB::transaction(
            function () use (
                $validated,
                $invitation,
                $email
            ) {
                $user = User::create([
                    'business_id' =>
                        $invitation->business_id,

                    'name' =>
                        trim($validated['name']),

                    'email' =>
                        $email,

                    'password' =>
                        Hash::make(
                            $validated['password']
                        ),

                    'role' =>
                        UserRole::MANAGER,

                    'email_verified_at' =>
                        now(),
                ]);

                $invitation->update([
                    'accepted_at' =>
                        now(),
                ]);

                return $user;
            }
        );

        $token = $user->createToken(
            'invitation-login'
        )->plainTextToken;

        $user->load('business');

        return UserResource::make($user)
            ->additional([
                'message' =>
                    'Invitation accepted and account created successfully.',

                'token' =>
                    $token,
            ]);
    }

    private function findValidInvitation(
        string $token
    ): ?Invitation {
        return Invitation::query()
            ->with('business')
            ->where(
                'token_hash',
                hash('sha256', $token)
            )
            ->whereNull('accepted_at')
            ->where(
                'expires_at',
                '>',
                now()
            )
            ->first();
    }
}