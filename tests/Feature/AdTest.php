<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Ad;
use App\Models\AdImage;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class AdTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $this->otherUser = User::factory()->create(['role' => 'user', 'is_active' => true]);
        
        $this->category = Category::create([
            'title' => 'Test Category',
            'slug' => 'test-category',
            'is_active' => true,
        ]);
    }

    public function test_authenticated_user_can_create_ad(): void
    {
        Sanctum::actingAs($this->user, ['user']);

        $response = $this->postJson('/api/v1/ads', [
            'title'       => 'Test Ad',
            'description' => 'This is a test advertisement',
            'price'       => 100.00,
            'currency'    => 'SAR',
            'category_id' => $this->category->id,
            'location'    => 'Riyadh',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'title', 'price']]);
    }

    public function test_guest_user_cannot_create_ad(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        Sanctum::actingAs($guest, ['guest']);

        $response = $this->postJson('/api/v1/ads', [
            'title'       => 'Test Ad',
            'description' => 'Test',
            'price'       => 100.00,
            'currency'    => 'SAR',
            'category_id' => $this->category->id,
            'location'    => 'Riyadh',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_only_update_own_ad(): void
    {
        Sanctum::actingAs($this->user, ['user']);

        // Create ad as other user
        $ad = Ad::create([
            'user_id' => $this->otherUser->id,
            'category_id' => $this->category->id,
            'title' => 'Test Ad',
            'slug' => 'test-ad-' . uniqid(),
            'description' => 'Test description',
            'price' => 100.00,
            'currency' => 'SAR',
            'location' => 'Riyadh',
            'status' => 'active',
        ]);

        // Try to update other user's ad
        $response = $this->postJson("/api/v1/ads/{$ad->id}/update", [
            'title' => 'Hacked Title',
        ]);

        $response->assertStatus(404);
    }

    public function test_update_ad_syncs_images(): void
    {
        Sanctum::actingAs($this->user, ['user']);
        Storage::fake('supabase');
        Queue::fake();

        $ad = Ad::create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'title' => 'Test Ad With Images',
            'slug' => 'test-ad-with-images-' . uniqid(),
            'description' => 'Test description',
            'price' => 100.00,
            'currency' => 'SAR',
            'location' => 'Riyadh',
            'status' => 'active',
        ]);

        AdImage::create([
            'ad_id' => $ad->id,
            'image_path' => 'ads/old-main.jpg',
            'is_main' => true,
            'sort_order' => 0,
        ]);

        AdImage::create([
            'ad_id' => $ad->id,
            'image_path' => 'ads/old-second.jpg',
            'is_main' => false,
            'sort_order' => 1,
        ]);

        $response = $this->postJson("/api/v1/ads/{$ad->id}/update", [
            'title' => 'Updated Ad With Images',
            'images' => ['ads/old-second.jpg', 'ads/new-third.jpg'],
            'removed_images' => ['ads/old-main.jpg'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.images.0.image_path', 'ads/old-second.jpg')
            ->assertJsonPath('data.images.0.is_main', true)
            ->assertJsonPath('data.images.1.image_path', 'ads/new-third.jpg')
            ->assertJsonPath('data.images.1.is_main', false);

        $this->assertDatabaseMissing('ad_images', [
            'ad_id' => $ad->id,
            'image_path' => 'ads/old-main.jpg',
        ]);

        $this->assertDatabaseHas('ad_images', [
            'ad_id' => $ad->id,
            'image_path' => 'ads/old-second.jpg',
            'is_main' => true,
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('ad_images', [
            'ad_id' => $ad->id,
            'image_path' => 'ads/new-third.jpg',
            'is_main' => false,
            'sort_order' => 1,
        ]);
    }

    public function test_user_can_only_delete_own_ad(): void
    {
        Sanctum::actingAs($this->user, ['user']);

        $ad = Ad::create([
            'user_id' => $this->otherUser->id,
            'category_id' => $this->category->id,
            'title' => 'Test Ad 2',
            'slug' => 'test-ad-2-' . uniqid(),
            'description' => 'Test description 2',
            'price' => 200.00,
            'currency' => 'SAR',
            'location' => 'Riyadh',
            'status' => 'active',
        ]);

        $response = $this->deleteJson("/api/v1/ads/{$ad->id}");
        $response->assertStatus(404); // findOrFail with user_id scope throws 404
    }

    public function test_ad_views_are_counted_once_per_non_owner_account(): void
    {
        $ad = Ad::create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'title' => 'Unique views ad',
            'slug' => 'unique-views-ad-' . uniqid(),
            'description' => 'Test description',
            'price' => 100.00,
            'currency' => 'SAR',
            'location' => 'Riyadh',
            'status' => 'active',
            'views' => 0,
        ]);

        Sanctum::actingAs($this->user, ['user']);
        $this->getJson("/api/v1/ads/{$ad->id}")
            ->assertOk()
            ->assertJsonPath('data.views', 0);

        $this->assertDatabaseMissing('ad_views', [
            'ad_id' => $ad->id,
            'user_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->otherUser, ['user']);
        $this->getJson("/api/v1/ads/{$ad->id}")
            ->assertOk()
            ->assertJsonPath('data.views', 1);

        $this->getJson("/api/v1/ads/{$ad->id}")
            ->assertOk()
            ->assertJsonPath('data.views', 1);

        $thirdUser = User::factory()->create(['role' => 'user', 'is_active' => true]);
        Sanctum::actingAs($thirdUser, ['user']);
        $this->getJson("/api/v1/ads/{$ad->id}")
            ->assertOk()
            ->assertJsonPath('data.views', 2);

        $this->assertDatabaseCount('ad_views', 2);
        $this->assertSame(2, (int) $ad->fresh()->views);
    }

    public function test_owner_can_mark_ad_as_sold_and_dashboard_counts_it(): void
    {
        Sanctum::actingAs($this->user, ['user']);

        $ad = Ad::create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'title' => 'Sold from app',
            'slug' => 'sold-from-app-' . uniqid(),
            'description' => 'Test description',
            'price' => 100.00,
            'currency' => 'SAR',
            'location' => 'Riyadh',
            'status' => 'active',
        ]);

        $this->postJson("/api/v1/ads/{$ad->id}/mark-sold")
            ->assertOk()
            ->assertJsonPath('data.status', 'sold');

        $this->assertSame('sold', $ad->fresh()->status);

        $this->getJson('/api/v1/user/dashboard-stats')
            ->assertOk()
            ->assertJsonPath('stats.total_sold', 1);
    }

    public function test_buyer_can_review_seller_after_sold_ad_conversation(): void
    {
        $ad = Ad::create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'title' => 'Reviewable ad',
            'slug' => 'reviewable-ad-' . uniqid(),
            'description' => 'Test description',
            'price' => 100.00,
            'currency' => 'SAR',
            'location' => 'Riyadh',
            'status' => 'sold',
        ]);

        Conversation::create([
            'ad_id' => $ad->id,
            'sender_id' => $this->otherUser->id,
            'receiver_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user, ['user']);
        $this->postJson("/api/v1/ads/{$ad->id}/review", [
            'rating' => 5,
            'comment' => 'Great seller',
        ])->assertStatus(422);

        Sanctum::actingAs($this->otherUser, ['user']);
        $this->postJson("/api/v1/ads/{$ad->id}/review", [
            'rating' => 5,
            'comment' => 'Great seller',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5);

        $this->assertDatabaseHas('reviews', [
            'reviewer_id' => $this->otherUser->id,
            'reviewed_id' => $this->user->id,
            'ad_id' => $ad->id,
            'rating' => 5,
        ]);

        Sanctum::actingAs($this->user, ['user']);
        $this->getJson('/api/v1/user/dashboard-stats')
            ->assertOk()
            ->assertJsonPath('stats.rating', 5);
    }

    public function test_review_requires_conversation_on_sold_ad(): void
    {
        $ad = Ad::create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'title' => 'No conversation ad',
            'slug' => 'no-conversation-ad-' . uniqid(),
            'description' => 'Test description',
            'price' => 100.00,
            'currency' => 'SAR',
            'location' => 'Riyadh',
            'status' => 'sold',
        ]);

        Sanctum::actingAs($this->otherUser, ['user']);
        $this->postJson("/api/v1/ads/{$ad->id}/review", [
            'rating' => 4,
        ])->assertForbidden();

        $this->assertSame(0, Review::count());
    }
}
