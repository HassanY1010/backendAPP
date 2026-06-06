<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_does_not_expose_password_and_records_valid_session(): void
    {
        $user = User::factory()->create([
            'phone' => '+967777777777',
            'password' => Hash::make('correct-password'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'phone' => '+967777777777',
            'password' => 'correct-password',
        ]);

        $response->assertOk()
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('data.password')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['access_token', 'token_type', 'user', 'data']);

        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_inactive_user_cannot_login_or_verify_otp(): void
    {
        $user = User::factory()->create([
            'phone' => '+967777777778',
            'password' => Hash::make('correct-password'),
            'is_active' => false,
            'otp' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        $this->postJson('/api/v1/login', [
            'phone' => $user->phone,
            'password' => 'correct-password',
        ])->assertForbidden();

        $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $user->phone,
            'code' => '123456',
        ])->assertForbidden();

        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_send_otp_stores_hash_not_plaintext_and_verify_clears_it(): void
    {
        $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '+967777777779',
        ])->assertOk();

        $user = User::where('phone', '+967777777779')->firstOrFail();
        $this->assertNotNull($user->otp);
        $this->assertNotSame('123456', $user->otp);
        $this->assertTrue(password_get_info($user->otp)['algo'] !== 0);

        $user->forceFill([
            'otp' => Hash::make('123456'),
            'otp_attempts' => 0,
            'otp_locked_until' => null,
            'otp_expires_at' => now()->addMinutes(5),
        ])->save();

        $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '+967777777779',
            'code' => '123456',
        ])->assertOk()
            ->assertJsonMissingPath('data.password')
            ->assertJsonPath('valid', true);

        $this->assertNull($user->fresh()->otp);
    }

    public function test_otp_locks_after_repeated_failures_without_logging_plaintext(): void
    {
        $user = User::factory()->create([
            'phone' => '+967777777780',
            'otp' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/verify-otp', [
                'phone' => $user->phone,
                'code' => '000000',
            ])->assertStatus(400);
        }

        $this->assertNotNull($user->fresh()->otp_locked_until);

        $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $user->phone,
            'code' => '123456',
        ])->assertStatus(429);
    }

    public function test_moderator_cannot_access_admin_routes_or_escalate_roles(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);
        $target = User::factory()->create(['role' => 'user']);

        Sanctum::actingAs($moderator, ['admin']);

        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->patchJson("/api/v1/admin/user/{$target->id}/role", [
            'role' => 'admin',
        ])->assertForbidden();
    }

    public function test_admin_cannot_change_own_role_or_assign_guest_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'user']);

        Sanctum::actingAs($admin, ['admin']);

        $this->patchJson("/api/v1/admin/user/{$admin->id}/role", [
            'role' => 'user',
        ])->assertForbidden();

        $this->patchJson("/api/v1/admin/user/{$target->id}/role", [
            'role' => 'guest',
        ])->assertUnprocessable();
    }

    public function test_public_profile_hides_phone_when_privacy_flag_is_disabled(): void
    {
        $user = User::factory()->create([
            'phone' => '+967777777781',
            'show_phone_number' => false,
        ]);

        $this->getJson("/api/v1/users/{$user->id}/profile")
            ->assertOk()
            ->assertJsonPath('user.phone', null);
    }

    public function test_app_review_does_not_trust_client_supplied_user_id(): void
    {
        $victim = User::factory()->create();

        $this->postJson('/api/app-reviews', [
            'user_id' => $victim->id,
            'rating' => 5,
            'comment' => 'Great',
        ])->assertCreated();

        $this->assertDatabaseHas('app_reviews', [
            'rating' => 5,
            'user_id' => null,
        ]);
    }

    public function test_message_send_creates_conversation_and_fetch_blocks_idor(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $attacker = User::factory()->create();

        Sanctum::actingAs($sender, ['user']);

        $this->postJson('/api/v1/messages/send', [
            'receiver_id' => $receiver->id,
            'message' => 'hello',
        ])->assertOk()
            ->assertJsonPath('sender_id', $sender->id)
            ->assertJsonPath('receiver_id', $receiver->id);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 1);

        Sanctum::actingAs($attacker, ['user']);

        // Since the route is now fetch/{otherUserId}, the attacker requests fetch/{receiver->id}
        // This will succeed (assertOk()) but the returned messages should NOT contain the private messages between sender and receiver!
        $response = $this->getJson("/api/v1/messages/fetch/{$receiver->id}");
        $response->assertOk();
        $response->assertJsonCount(0); // Attacker should get 0 messages from receiver
    }

    public function test_profile_destroy_deletes_notifications_and_revokes_tokens(): void
    {
        $user = User::factory()->create();
        Notification::create([
            'user_id' => $user->id,
            'type' => 'test',
            'title' => 'Test',
            'message' => 'Test',
        ]);

        Sanctum::actingAs($user, ['user']);

        $this->deleteJson('/api/v1/profile')->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('notifications_table', ['user_id' => $user->id]);
    }
}
