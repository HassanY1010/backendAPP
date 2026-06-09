<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Ad;
use App\Models\AdImage;
use App\Models\Category;
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
}
