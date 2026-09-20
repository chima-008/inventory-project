<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Services\BrevoMailService;

class AuthTest extends TestCase
{
    use RefreshDatabase;

      protected function setUp(): void
    {
        parent::setUp();

        $this->mock(BrevoMailService::class, function ($mock) {
            $mock->shouldReceive('sendVerificationEmail')
                ->andReturnNull();
        });
    }


    public function test_business_owner_can_register_and_become_admin(): void
    {
        $response = $this->postJson('/api/register', [
            'business_name' => 'Owner Business',
            'name' => 'Business Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Registration successful. Please verify your email address before logging in.'
            )
            ->assertJsonPath('business.name', 'Owner Business')
            ->assertJsonPath('user.name', 'Business Owner')
            ->assertJsonPath('user.email', 'owner@example.com')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('email_verification_required', true)
            ->assertJsonMissingPath('token');

        $this->assertDatabaseHas('businesses', [
            'name' => 'Owner Business',
        ]);

        $business = Business::where('name', 'Owner Business')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.com',
            'business_id' => $business->id,
            'role' => UserRole::ADMIN->value,
            'email_verified_at' => null,
        ]);
    }

    public function test_another_business_owner_gets_a_separate_business(): void
    {
        $firstResponse = $this->postJson('/api/register', [
            'business_name' => 'First Business',
            'name' => 'First Owner',
            'email' => 'first@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $firstResponse->assertCreated();

        $secondResponse = $this->postJson('/api/register', [
            'business_name' => 'Second Business',
            'name' => 'Second Owner',
            'email' => 'second@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $secondResponse
            ->assertCreated()
            ->assertJsonPath('business.name', 'Second Business')
            ->assertJsonPath('user.name', 'Second Owner')
            ->assertJsonPath('user.email', 'second@example.com')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('email_verification_required', true);

        $this->assertDatabaseHas('businesses', [
            'name' => 'First Business',
        ]);

        $this->assertDatabaseHas('businesses', [
            'name' => 'Second Business',
        ]);

        $firstOwner = User::where('email', 'first@example.com')->firstOrFail();
        $secondOwner = User::where('email', 'second@example.com')->firstOrFail();

        $this->assertNotNull($firstOwner->business_id);
        $this->assertNotNull($secondOwner->business_id);

        $this->assertNotSame(
            $firstOwner->business_id,
            $secondOwner->business_id
        );
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'password123',
            'role' => UserRole::ADMIN->value,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('user.email', 'owner@example.com')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure([
                'token',
                'user',
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_unverified_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => 'password123',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'unverified@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Please verify your email address before logging in.'
            )
            ->assertJsonPath('email_verification_required', true)
            ->assertJsonMissingPath('token');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath(
                'message',
                'The provided credentials are incorrect.'
            );
    }

    public function test_unverified_user_cannot_access_protected_api_routes(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Your email address is not verified.'
            );
    }

    public function test_verified_user_can_access_protected_api_routes(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/register', [
                'business_name' => "Business {$i}",
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $response->assertCreated();
        }

        $response = $this->postJson('/api/register', [
            'business_name' => 'Blocked Business',
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(429);
    }

    public function test_authenticated_user_can_fetch_current_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Authenticated User',
            'email' => 'authenticated@example.com',
            'role' => UserRole::ADMIN->value,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Authenticated User')
            ->assertJsonPath('data.email', 'authenticated@example.com')
            ->assertJsonPath('data.role', 'admin');
    }

}