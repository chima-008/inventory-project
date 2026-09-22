<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBusinessRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Enums\UserRole;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = \App\Models\User::query()
            ->where('business_id', $request->user()->business_id)
            ->select(
                'id',
                'business_id',
                'name',
                'email',
                'role',
                'created_at'
            )
            ->orderBy('name')
            ->get();

        return UserResource::collection($users);
    }

    public function updateProfile(
        UpdateProfileRequest $request
    ) {
        $user = $request->user();

        $user->update([
            'name' => $request->validated()['name'],
        ]);

        $user->load('business');

        return UserResource::make($user)
            ->additional([
                'message' => 'Profile updated successfully.',
            ]);
    }

    public function updateBusiness(
        UpdateBusinessRequest $request
    ) {
        $user = $request->user();

        abort_unless(
            $user->business_id !== null,
            404
        );

        abort_unless(
            $user->role === UserRole::ADMIN,
            403
        );

        $business = $user->business;

        if (! $business) {
            abort(404);
        }

        $business->update([
            'name' => $request->validated()['name'],
        ]);

        $user->load('business');

        return UserResource::make($user)
            ->additional([
                'message' => 'Business name updated successfully.',
            ]);
    }

    public function updateRole(
        Request $request,
        UpdateUserRoleRequest $roleRequest,
        \App\Models\User $user
    ) {
        abort_unless(
            $user->business_id === $request->user()->business_id,
            404
        );

        $validated = $roleRequest->validated();

        if ($request->user()->is($user)) {
            return response()->json([
                'message' => 'You cannot change your own role.',
            ], 403);
        }

        $user->update([
            'role' => $validated['role'],
        ]);

        $user->load('business');

        return UserResource::make($user)
            ->additional([
                'message' => 'User role updated successfully.',
            ]);
    }
}