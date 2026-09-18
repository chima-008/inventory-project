<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test Manager',
            'email' => 'manager@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Registration successful.')
            ->assertJsonPath('user.name', 'Test Manager')
            ->assertJsonPath('user.email', 'manager@example.com')
            ->assertJsonPath('user.role', 'manager')
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'created_at',
                ],
                'token',
            ]);

        $response->assertJsonMissingPath('user.password');
        $response->assertJsonMissingPath('user.remember_token');

        $this->assertDatabaseHas('users', [
            'email' => 'manager@example.com',
            'role' => 'manager',
        ]);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'manager@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'manager@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'manager@example.com')
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'created_at',
                ],
                'token',
            ]);

        $response->assertJsonMissingPath('user.password');
        $response->assertJsonMissingPath('user.remember_token');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'manager@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'manager@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'The provided credentials are incorrect.',
            ]);
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/register', [
                'name' => "Test User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $response->assertCreated();
        }

        $response = $this->postJson('/api/register', [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(429)
            ->assertJson([
                'message' => 'Too Many Attempts.',
            ]);
    }

    public function test_authenticated_user_can_fetch_current_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Current User',
            'email' => 'current@example.com',
            'role' => 'manager',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->getJson('/api/user');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Current User')
            ->assertJsonPath('data.email', 'current@example.com')
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'created_at',
                ],
            ]);

        $response->assertJsonMissingPath('data.password');
        $response->assertJsonMissingPath('data.remember_token');
    }
}