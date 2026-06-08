<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;
    private User $userC;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userA = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $this->userB = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $this->userC = User::factory()->create(['role' => 'user', 'is_active' => true]);
    }

    public function test_user_can_send_message_to_other_user(): void
    {
        Sanctum::actingAs($this->userA, ['user']);

        $response = $this->postJson('/api/v1/messages/send', [
            'receiver_id' => $this->userB->id,
            'message'     => 'Hello User B',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'sender_id', 'receiver_id', 'message']);
    }

    public function test_conversation_appears_for_both_users_after_message_is_sent(): void
    {
        Sanctum::actingAs($this->userA, ['user']);

        $this->postJson('/api/v1/messages/send', [
            'receiver_id' => $this->userB->id,
            'message' => 'Hello User B',
        ])->assertOk();

        $senderResponse = $this->getJson('/api/v1/messages/conversations');
        $senderResponse->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.other_user_id', $this->userB->id)
            ->assertJsonPath('0.last_message', 'Hello User B');

        Sanctum::actingAs($this->userB, ['user']);

        $receiverResponse = $this->getJson('/api/v1/messages/conversations');
        $receiverResponse->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.other_user_id', $this->userA->id)
            ->assertJsonPath('0.last_message', 'Hello User B');
    }

    public function test_guest_user_cannot_send_messages(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        Sanctum::actingAs($guest, ['guest']);

        $response = $this->postJson('/api/v1/messages/send', [
            'receiver_id' => $this->userB->id,
            'message'     => 'Hello from Guest',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_fetch_messages_of_others(): void
    {
        // Setup conversation between A and B
        $conversation = Conversation::create([
            'sender_id' => $this->userA->id,
            'receiver_id' => $this->userB->id,
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->userA->id,
            'receiver_id' => $this->userB->id,
            'message' => 'Private message',
        ]);

        // acting as User C
        Sanctum::actingAs($this->userC, ['user']);

        // User C fetches conversation with User B (should not see A-B messages)
        $response = $this->getJson("/api/v1/messages/fetch/{$this->userB->id}");
        $response->assertStatus(200);
        $response->assertJsonCount(0); // Should return empty array, protecting private messages
    }
}
