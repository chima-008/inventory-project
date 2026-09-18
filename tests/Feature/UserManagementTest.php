<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $manager = User::factory()->create([
            'name' => 'Test Manager',
            'email' => 'manager@example.com',
            'role' => 'manager',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'created_at',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'name' => 'Test Manager',
                'email' => 'manager@example.com',
                'role' => 'manager',
            ]);

        $response->assertJsonMissingPath('data.0.password');
        $response->assertJsonMissingPath('data.0.remember_token');
    }

    public function test_manager_cannot_list_users(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/users');

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'You do not have permission to perform this action.',
            ]);
    }

    public function test_admin_can_change_user_role_to_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson(
            "/api/users/{$manager->id}/role",
            [
                'role' => 'admin',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $manager->id)
            ->assertJsonPath('data.role', 'admin');

        $this->assertDatabaseHas('users', [
            'id' => $manager->id,
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_change_user_role_to_manager(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $targetUser = User::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson(
            "/api/users/{$targetUser->id}/role",
            [
                'role' => 'manager',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $targetUser->id)
            ->assertJsonPath('data.role', 'manager');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'role' => 'manager',
        ]);
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson(
            "/api/users/{$admin->id}/role",
            [
                'role' => 'manager',
            ]
        );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'You cannot change your own role.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
        ]);
    }

    public function test_manager_cannot_change_user_role(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        $targetUser = User::factory()->create([
            'role' => 'manager',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->patchJson(
            "/api/users/{$targetUser->id}/role",
            [
                'role' => 'admin',
            ]
        );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'You do not have permission to perform this action.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'role' => 'manager',
        ]);
    }

    public function test_invalid_role_is_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $targetUser = User::factory()->create([
            'role' => 'manager',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson(
            "/api/users/{$targetUser->id}/role",
            [
                'role' => 'superuser',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'role' => 'manager',
        ]);
    }

    public function test_unauthenticated_user_cannot_list_users(): void
    {
        $response = $this->getJson('/api/users');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_unauthenticated_user_cannot_change_user_role(): void
    {
        $targetUser = User::factory()->create([
            'role' => 'manager',
        ]);

        $response = $this->patchJson(
            "/api/users/{$targetUser->id}/role",
            [
                'role' => 'admin',
            ]
        );

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'role' => 'manager',
        ]);
    }
}