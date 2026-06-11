<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\QueryException;
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

    public function test_ad_conversation_can_be_created_before_first_message(): void
    {
        $ad = $this->createAdFor($this->userB);
        Sanctum::actingAs($this->userA, ['user']);

        $createResponse = $this->postJson('/api/v1/messages/conversations', [
            'receiver_id' => $this->userB->id,
            'ad_id' => $ad->id,
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('ad_id', $ad->id)
            ->assertJsonPath('other_user_id', $this->userB->id);

        $listResponse = $this->getJson('/api/v1/messages/conversations');
        $listResponse->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.ad_id', $ad->id)
            ->assertJsonPath('0.other_user_id', $this->userB->id);
    }

    public function test_messages_for_same_ad_reuse_the_same_conversation(): void
    {
        $ad = $this->createAdFor($this->userB);
        Sanctum::actingAs($this->userA, ['user']);

        $first = $this->postJson('/api/v1/messages/send', [
            'receiver_id' => $this->userB->id,
            'ad_id' => $ad->id,
            'message' => 'Is this available?',
        ]);

        $second = $this->postJson('/api/v1/messages/send', [
            'receiver_id' => $this->userB->id,
            'ad_id' => $ad->id,
            'message' => 'I am interested.',
        ]);

        $first->assertOk();
        $second->assertOk();

        $this->assertSame($first['conversation_id'], $second['conversation_id']);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);

        $this->getJson('/api/v1/messages/conversations')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.last_message', 'I am interested.')
            ->assertJsonPath('0.ad_id', $ad->id);
    }

    public function test_seller_reply_reuses_existing_ad_conversation(): void
    {
        $ad = $this->createAdFor($this->userB);
        Sanctum::actingAs($this->userA, ['user']);

        $buyerMessage = $this->postJson('/api/v1/messages/send', [
            'receiver_id' => $this->userB->id,
            'ad_id' => $ad->id,
            'message' => 'Is this available?',
        ]);
        $buyerMessage->assertOk();

        Sanctum::actingAs($this->userB, ['user']);

        $sellerMessage = $this->postJson('/api/v1/messages/send', [
            'receiver_id' => $this->userA->id,
            'ad_id' => $ad->id,
            'message' => 'Yes, it is available.',
        ]);
        $sellerMessage->assertOk();

        $this->assertSame($buyerMessage['conversation_id'], $sellerMessage['conversation_id']);
        $this->assertDatabaseCount('conversations', 1);

        $this->getJson('/api/v1/messages/conversations')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.other_user_id', $this->userA->id)
            ->assertJsonPath('0.last_message', 'Yes, it is available.');
    }

    public function test_database_rejects_duplicate_ad_conversation_with_reversed_participants(): void
    {
        $ad = $this->createAdFor($this->userB);

        Conversation::create([
            'sender_id' => $this->userA->id,
            'receiver_id' => $this->userB->id,
            'ad_id' => $ad->id,
        ]);

        $this->expectException(QueryException::class);

        Conversation::create([
            'sender_id' => $this->userB->id,
            'receiver_id' => $this->userA->id,
            'ad_id' => $ad->id,
        ]);
    }

    public function test_delete_conversation_only_hides_it_for_deleting_user(): void
    {
        Sanctum::actingAs($this->userA, ['user']);

        $messageResponse = $this->postJson('/api/v1/messages/send', [
            'receiver_id' => $this->userB->id,
            'message' => 'Keep this conversation in storage',
        ]);
        $messageResponse->assertOk();

        $conversationId = $messageResponse['conversation_id'];

        $this->deleteJson("/api/v1/messages/conversations/{$conversationId}")
            ->assertOk();

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
        ]);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'message' => 'Keep this conversation in storage',
        ]);

        $this->getJson('/api/v1/messages/conversations')
            ->assertOk()
            ->assertJsonCount(0);

        Sanctum::actingAs($this->userB, ['user']);

        $this->getJson('/api/v1/messages/conversations')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $conversationId);
    }

    public function test_fetch_messages_is_capped_to_one_hundred_messages(): void
    {
        $conversation = Conversation::create([
            'sender_id' => $this->userA->id,
            'receiver_id' => $this->userB->id,
        ]);

        for ($i = 1; $i <= 120; $i++) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $i % 2 === 0 ? $this->userA->id : $this->userB->id,
                'receiver_id' => $i % 2 === 0 ? $this->userB->id : $this->userA->id,
                'message' => "Message {$i}",
                'created_at' => now()->addSeconds($i),
            ]);
        }

        Sanctum::actingAs($this->userA, ['user']);

        $this->getJson("/api/v1/messages/fetch/{$this->userB->id}?limit=500")
            ->assertOk()
            ->assertJsonCount(100)
            ->assertJsonPath('0.message', 'Message 21')
            ->assertJsonPath('99.message', 'Message 120');
    }

    public function test_conversations_endpoint_repairs_messages_without_conversation_id(): void
    {
        $message = Message::create([
            'sender_id' => $this->userA->id,
            'receiver_id' => $this->userB->id,
            'message' => 'Legacy private message',
        ]);

        Sanctum::actingAs($this->userA, ['user']);

        $response = $this->getJson('/api/v1/messages/conversations');
        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.other_user_id', $this->userB->id)
            ->assertJsonPath('0.last_message', 'Legacy private message');

        $message->refresh();
        $this->assertNotNull($message->conversation_id);
        $this->assertDatabaseHas('conversations', [
            'id' => $message->conversation_id,
            'last_message_id' => $message->id,
        ]);
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

    public function test_user_cannot_send_message_when_receiver_disallows_messages(): void
    {
        $this->userB->forceFill(['allow_messages' => false])->save();
        Sanctum::actingAs($this->userA, ['user']);

        $this->postJson('/api/v1/messages/send', [
            'receiver_id' => $this->userB->id,
            'message' => 'Are you available?',
        ])->assertForbidden()
            ->assertJsonPath('error', 'This user is not accepting messages');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_user_cannot_create_conversation_when_receiver_disallows_messages(): void
    {
        $this->userB->forceFill(['allow_messages' => false])->save();
        Sanctum::actingAs($this->userA, ['user']);

        $this->postJson('/api/v1/messages/conversations', [
            'receiver_id' => $this->userB->id,
        ])->assertForbidden()
            ->assertJsonPath('error', 'This user is not accepting messages');

        $this->assertDatabaseCount('conversations', 0);
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

    private function createAdFor(User $seller): Ad
    {
        $category = Category::create([
            'title' => 'Test Category',
            'slug' => 'test-category-' . uniqid(),
        ]);

        return Ad::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Test Ad',
            'slug' => 'test-ad-' . uniqid(),
            'description' => 'Test ad description',
            'price' => 2000,
            'currency' => 'SAR',
            'location' => 'Riyadh',
            'status' => 'active',
        ]);
    }
}
