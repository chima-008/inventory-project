<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
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

    public function updateRole(
        Request $request,
        UpdateUserRoleRequest $roleRequest,
        User $user
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

        return UserResource::make($user)
            ->additional([
                'message' => 'User role updated successfully.',
            ]);
    }
}