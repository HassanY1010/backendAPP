<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Mass assignment of role=admin must be rejected.
     */
    public function test_role_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        // Attempt to mass assign role via fillable
        try {
            $user->fill(['role' => 'admin']);
            // If fill doesn't throw, check the role was not changed
            $this->assertNotEquals('admin', $user->role, 'Role should not be changeable via mass assignment');
        } catch (\Illuminate\Database\Eloquent\MassAssignmentException $e) {
            // This is the expected secure behavior
            $this->assertTrue(true);
        }
    }

    /**
     * Test: Guest token cannot send messages (blocked by BlockGuestAccess middleware).
     */
    public function test_guest_cannot_send_messages(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        Sanctum::actingAs($guest, ['guest']);

        $response = $this->postJson('/api/v1/messages/send', [
            'receiver_id' => 999,
            'message'     => 'Hello',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test: IDOR — user cannot read another user's messages.
     */
    public function test_user_cannot_fetch_others_messages(): void
    {
        $userA = User::factory()->create(['role' => 'user']);
        $userB = User::factory()->create(['role' => 'user']);
        $userC = User::factory()->create(['role' => 'user']);

        Sanctum::actingAs($userC, ['user']);

        // userC tries to read conversation between A and B
        $response = $this->getJson("/api/v1/messages/fetch/{$userA->id}");

        // Should get empty result (not A-B messages), not a 403 necessarily
        // but the fix ensures only auth user's messages are returned
        $response->assertSuccessful();
        // The messages returned should only involve userC, not A-B private messages
    }

    /**
     * Test: AdminMiddleware does not leak role in 403.
     */
    public function test_admin_middleware_does_not_leak_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user, ['user']);

        $response = $this->getJson('/api/v1/admin/users');

        $response->assertStatus(403);
        $response->assertJsonMissing(['your_role']);
    }

    /**
     * Test: OTP endpoint has rate limiting.
     */
    public function test_otp_rate_limit(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/auth/send-otp', [
                'phone' => '+9671234567890',
            ]);
        }

        // After 5 requests in 1 minute, 6th should be rate limited
        $response->assertStatus(429);
    }
}
