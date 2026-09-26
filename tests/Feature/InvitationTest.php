<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use App\Services\BrevoMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_manager_invitation(): void
    {
    

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $this->mock(BrevoMailService::class, function ($mock) {
    $mock->shouldReceive('sendManagerInvitationEmail')
        ->once();
});

        $response = $this->postJson('/api/invitations', [
            'email' => 'manager@example.com',
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'message' =>
                    'Manager invitation sent successfully.',
            ]);

        $this->assertDatabaseHas('invitations', [
            'business_id' => $admin->business_id,
            'invited_by' => $admin->id,
            'email' => 'manager@example.com',
            'role' => 'manager',
            'accepted_at' => null,
        ]);
    }

    public function test_manager_cannot_send_invitation(): void
    {
     

        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/invitations', [
            'email' => 'manager@example.com',
        ]);

        $response
            ->assertForbidden()
            ->assertJson([
                'message' =>
                    'You do not have permission to perform this action.',
            ]);
    }

    public function test_admin_cannot_invite_themselves(): void
    {
        

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/invitations', [
            'email' => $admin->email,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' =>
                    'You cannot invite yourself.',
            ]);

        $this->assertDatabaseCount('invitations', 0);
    }

    public function test_admin_cannot_invite_existing_member(): void
    {
        

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $member = User::factory()->create([
            'business_id' => $admin->business_id,
            'role' => 'manager',
            'email' => 'member@example.com',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/invitations', [
            'email' => $member->email,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' =>
                    'This user is already a member of your business.',
            ]);

        $this->assertDatabaseCount('invitations', 0);
    }

    public function test_admin_cannot_invite_user_from_another_business(): void
    {
        

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $otherUser = User::factory()->create([
            'role' => 'manager',
            'email' => 'other@example.com',
        ]);

        $this->assertNotSame(
            $admin->business_id,
            $otherUser->business_id
        );

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/invitations', [
            'email' => $otherUser->email,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' =>
                    'This email is already associated with another account.',
            ]);

        $this->assertDatabaseCount('invitations', 0);
    }

    public function test_duplicate_pending_invitation_is_rejected(): void
    {


        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Invitation::create([
            'business_id' => $admin->business_id,
            'invited_by' => $admin->id,
            'email' => 'manager@example.com',
            'role' => 'manager',
            'token_hash' => hash(
                'sha256',
                'existing-token'
            ),
            'expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/invitations', [
            'email' => 'manager@example.com',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' =>
                    'A pending invitation already exists for this email address.',
            ]);

        $this->assertDatabaseCount('invitations', 1);
    }

    public function test_invitation_details_can_be_viewed(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = 'valid-invitation-token';

        Invitation::create([
            'business_id' => $admin->business_id,
            'invited_by' => $admin->id,
            'email' => 'manager@example.com',
            'role' => 'manager',
            'token_hash' => hash(
                'sha256',
                $token
            ),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->getJson(
            "/api/invitations/{$token}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.email',
                'manager@example.com'
            )
            ->assertJsonPath(
                'data.role',
                'manager'
            )
            ->assertJsonPath(
                'data.business.id',
                $admin->business_id
            )
            ->assertJsonPath(
                'data.existing_account',
                false
            );
    }

    public function test_invalid_invitation_token_is_rejected(): void
    {
        $response = $this->getJson(
            '/api/invitations/invalid-token'
        );

        $response
            ->assertNotFound()
            ->assertJson([
                'message' =>
                    'This invitation is invalid, expired, or has already been accepted.',
            ]);
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = 'expired-invitation-token';

        Invitation::create([
            'business_id' => $admin->business_id,
            'invited_by' => $admin->id,
            'email' => 'manager@example.com',
            'role' => 'manager',
            'token_hash' => hash(
                'sha256',
                $token
            ),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/invitations/{$token}"
        );

        $response
            ->assertNotFound()
            ->assertJson([
                'message' =>
                    'This invitation is invalid, expired, or has already been accepted.',
            ]);
    }

    public function test_new_user_can_complete_invitation(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = 'new-manager-invitation-token';

        Invitation::create([
            'business_id' => $admin->business_id,
            'invited_by' => $admin->id,
            'email' => 'newmanager@example.com',
            'role' => 'manager',
            'token_hash' => hash(
                'sha256',
                $token
            ),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson(
            '/api/invitations/complete',
            [
                'token' => $token,
                'name' => 'New Manager',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.email',
                'newmanager@example.com'
            )
            ->assertJsonPath(
                'data.name',
                'New Manager'
            )
            ->assertJsonPath(
                'data.role',
                'manager'
            )
            ->assertJsonPath(
                'data.business.id',
                $admin->business_id
            )
            ->assertJsonStructure([
                'data',
                'message',
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newmanager@example.com',
            'business_id' => $admin->business_id,
            'role' => 'manager',
        ]);

        $this->assertDatabaseHas('invitations', [
            'email' => 'newmanager@example.com',
        ]);

        $invitation = Invitation::where(
            'email',
            'newmanager@example.com'
        )->first();

        $this->assertNotNull(
            $invitation->accepted_at
        );
    }

    public function test_completed_invitation_cannot_be_used_again(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = 'single-use-invitation-token';

        Invitation::create([
            'business_id' => $admin->business_id,
            'invited_by' => $admin->id,
            'email' => 'manager@example.com',
            'role' => 'manager',
            'token_hash' => hash(
                'sha256',
                $token
            ),
            'expires_at' => now()->addDays(7),
        ]);

        $firstResponse = $this->postJson(
            '/api/invitations/complete',
            [
                'token' => $token,
                'name' => 'Manager',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]
        );

        $firstResponse->assertCreated();

        $secondResponse = $this->postJson(
            '/api/invitations/complete',
            [
                'token' => $token,
                'name' => 'Another Manager',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]
        );

        $secondResponse
            ->assertNotFound()
            ->assertJson([
                'message' =>
                    'This invitation is invalid, expired, or has already been accepted.',
            ]);
    }

    public function test_existing_unassigned_user_can_accept_invitation(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'business_id' => null,
            'role' => 'manager',
            'email' => 'existing@example.com',
        ]);

        $token = 'existing-user-invitation-token';

        Invitation::create([
            'business_id' => $admin->business_id,
            'invited_by' => $admin->id,
            'email' => $user->email,
            'role' => 'manager',
            'token_hash' => hash(
                'sha256',
                $token
            ),
            'expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/invitations/accept',
            [
                'token' => $token,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $user->id
            )
            ->assertJsonPath(
                'data.role',
                'manager'
            )
            ->assertJsonPath(
                'data.business.id',
                $admin->business_id
            );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'business_id' => $admin->business_id,
            'role' => 'manager',
        ]);

        $this->assertDatabaseHas('invitations', [
            'email' => $user->email,
        ]);

        $invitation = Invitation::where(
            'email',
            $user->email
        )->first();

        $this->assertNotNull(
            $invitation->accepted_at
        );
    }

    public function test_user_cannot_accept_invitation_for_another_email(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'business_id' => null,
            'role' => 'manager',
            'email' => 'different@example.com',
        ]);

        $token = 'wrong-email-invitation-token';

        Invitation::create([
            'business_id' => $admin->business_id,
            'invited_by' => $admin->id,
            'email' => 'invited@example.com',
            'role' => 'manager',
            'token_hash' => hash(
                'sha256',
                $token
            ),
            'expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/invitations/accept',
            [
                'token' => $token,
            ]
        );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' =>
                    'This invitation was issued for a different email address.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'business_id' => $user->business_id,
        ]);
    }

    public function test_user_from_another_business_cannot_accept_invitation(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $otherAdmin = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = 'cross-business-invitation-token';

        Invitation::create([
            'business_id' => $admin->business_id,
            'invited_by' => $admin->id,
            'email' => $otherAdmin->email,
            'role' => 'manager',
            'token_hash' => hash(
                'sha256',
                $token
            ),
            'expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($otherAdmin);

        $response = $this->postJson(
            '/api/invitations/accept',
            [
                'token' => $token,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' =>
                    'Your account already belongs to a business.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'business_id' => $otherAdmin->business_id,
            'role' => 'admin',
        ]);
    }

    public function test_existing_account_cannot_complete_new_account_invitation(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        User::factory()->create([
            'business_id' => null,
            'email' => 'existing@example.com',
            'role' => 'manager',
        ]);

        $token = 'existing-account-complete-token';

        Invitation::create([
            'business_id' => $admin->business_id,
            'invited_by' => $admin->id,
            'email' => 'existing@example.com',
            'role' => 'manager',
            'token_hash' => hash(
                'sha256',
                $token
            ),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson(
            '/api/invitations/complete',
            [
                'token' => $token,
                'name' => 'Existing User',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' =>
                    'An account already exists for this invitation. Please log in and accept the invitation.',
            ]);
    }

    public function test_invitation_requires_valid_registration_data(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = 'validation-invitation-token';

        Invitation::create([
            'business_id' => $admin->business_id,
            'invited_by' => $admin->id,
            'email' => 'manager@example.com',
            'role' => 'manager',
            'token_hash' => hash(
                'sha256',
                $token
            ),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson(
            '/api/invitations/complete',
            [
                'token' => $token,
                'name' => '',
                'password' => 'short',
                'password_confirmation' => 'different',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'password',
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'manager@example.com',
        ]);
    }
}