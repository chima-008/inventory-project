<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;


class UserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->select('id', 'name', 'email', 'role', 'created_at')
            ->orderBy('name')
            ->get();

        return UserResource::collection($users);
    }

   public function updateRole(
        UpdateUserRoleRequest $request,
        User $user
    )
    {
        $validated = $request->validated();

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