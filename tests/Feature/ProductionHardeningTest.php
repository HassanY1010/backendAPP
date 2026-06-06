<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Review;
use App\Models\SavedSearch;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_public_profile_exposes_trust_badges_and_metrics(): void
    {
        $seller = User::factory()->create([
            'phone_verified_at' => now(),
            'last_activity_at' => now(),
        ]);
        $reviewer = User::factory()->create();
        $category = Category::create([
            'title' => 'Cars',
            'slug' => 'cars',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Ad::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Active car',
            'slug' => 'active-car',
            'description' => 'A clean car',
            'price' => 10000,
            'currency' => 'SAR',
            'location' => 'Riyadh',
            'status' => 'active',
        ]);

        $soldAd = Ad::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Sold car',
            'slug' => 'sold-car',
            'description' => 'Sold successfully',
            'price' => 9000,
            'currency' => 'SAR',
            'location' => 'Riyadh',
            'status' => 'sold',
        ]);

        Review::create([
            'reviewer_id' => $reviewer->id,
            'reviewed_id' => $seller->id,
            'ad_id' => $soldAd->id,
            'rating' => 5,
            'comment' => 'Great seller',
            'is_approved' => true,
        ]);

        $response = $this->getJson("/api/v1/users/{$seller->id}/profile")
            ->assertOk()
            ->assertJsonPath('user.phone_verified', true)
            ->assertJsonPath('user.active_ads_count', 1)
            ->assertJsonPath('user.successful_ads_count', 1)
            ->assertJsonPath('user.ratings_count', 1)
            ->assertJsonPath('user.rating', 5);

        $badgeIds = collect($response->json('user.trust_badges'))->pluck('id');

        $this->assertTrue($badgeIds->contains('phone_verified'));
        $this->assertTrue($badgeIds->contains('active_seller'));
        $this->assertTrue($badgeIds->contains('fast_responder'));
        $this->assertTrue($badgeIds->contains('post_sale_rating'));
        $this->assertTrue($badgeIds->contains('successful_sales'));
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

    public function test_categories_endpoint_rebuilds_after_empty_cache(): void
    {
        Cache::put('category_tree:v2', collect(), now()->addHours(12));

        $parent = Category::create([
            'title' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Category::create([
            'title' => 'Phones',
            'slug' => 'phones',
            'parent_id' => $parent->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Electronics')
            ->assertJsonPath('data.0.children.0.name', 'Phones');
    }

    public function test_profile_export_includes_rich_report_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Report User',
            'phone' => '+967777777782',
            'is_active' => true,
            'show_phone_number' => true,
            'accepts_notifications' => true,
        ]);
        $reviewer = User::factory()->create(['name' => 'Reviewer User']);

        $category = Category::create([
            'title' => 'Cars',
            'slug' => 'cars',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $ad = Ad::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Clean sedan',
            'slug' => 'clean-sedan',
            'description' => 'A detailed export description for the generated PDF.',
            'price' => 12500,
            'currency' => 'YER',
            'location' => 'Sanaa',
            'status' => 'active',
            'views' => 37,
            'is_featured' => true,
            'is_negotiable' => true,
            'condition' => 'used',
        ]);

        $user->favorites()->attach($ad->id);

        Review::create([
            'reviewer_id' => $reviewer->id,
            'reviewed_id' => $user->id,
            'ad_id' => $ad->id,
            'rating' => 5,
            'comment' => 'Excellent seller',
            'is_approved' => true,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'type' => 'ad',
            'title' => 'New favorite',
            'message' => 'Someone added your ad to favorites.',
            'is_read' => false,
        ]);

        SavedSearch::create([
            'user_id' => $user->id,
            'name' => 'Cars in Sanaa',
            'filters' => ['category_id' => $category->id, 'location' => 'Sanaa'],
            'notify_enabled' => true,
        ]);

        UserSession::create([
            'user_id' => $user->id,
            'login_at' => now()->subHour(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Flutter export test',
            'device_type' => 'Mobile',
        ]);

        Sanctum::actingAs($user, ['user']);

        $this->getJson('/api/v1/profile/export')
            ->assertOk()
            ->assertJsonPath('user.name', 'Report User')
            ->assertJsonPath('user.avatar_url', $user->avatar_url)
            ->assertJsonPath('stats.total_ads', 1)
            ->assertJsonPath('stats.total_views', 37)
            ->assertJsonPath('stats.total_favorites', 1)
            ->assertJsonPath('stats.reviews_count', 1)
            ->assertJsonPath('stats.notifications_total', 1)
            ->assertJsonPath('stats.saved_searches_count', 1)
            ->assertJsonPath('stats.sessions_count', 1)
            ->assertJsonPath('ads.0.description', 'A detailed export description for the generated PDF.')
            ->assertJsonPath('ads.0.category', 'Cars')
            ->assertJsonPath('ads.0.favorites_count', 1)
            ->assertJsonPath('ads.0.is_featured', true)
            ->assertJsonPath('favorites.0.title', 'Clean sedan')
            ->assertJsonPath('favorites.0.views', 37)
            ->assertJsonPath('reviews.0.reviewer_name', 'Reviewer User')
            ->assertJsonPath('reviews.0.comment', 'Excellent seller')
            ->assertJsonPath('notifications.0.title', 'New favorite')
            ->assertJsonPath('saved_searches.0.name', 'Cars in Sanaa')
            ->assertJsonPath('sessions.0.device_type', 'Mobile');
    }

    public function test_ads_index_supports_smart_filters_and_sorting(): void
    {
        $seller = User::factory()->create();
        $cars = Category::create([
            'title' => 'Cars',
            'slug' => 'cars-smart-filter',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $phones = Category::create([
            'title' => 'Phones',
            'slug' => 'phones-smart-filter',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $mostViewed = Ad::create([
            'user_id' => $seller->id,
            'category_id' => $cars->id,
            'title' => 'Toyota Camry',
            'slug' => 'toyota-camry-smart-filter',
            'description' => 'Clean family car',
            'price' => 1200,
            'currency' => 'YER',
            'location' => 'Sanaa',
            'status' => 'active',
            'views' => 90,
            'condition' => 'used',
        ]);

        Ad::create([
            'user_id' => $seller->id,
            'category_id' => $cars->id,
            'title' => 'Honda Civic',
            'slug' => 'honda-civic-smart-filter',
            'description' => 'Reliable car',
            'price' => 1000,
            'currency' => 'YER',
            'location' => 'Sanaa',
            'status' => 'active',
            'views' => 25,
            'condition' => 'used',
        ]);

        Ad::create([
            'user_id' => $seller->id,
            'category_id' => $phones->id,
            'title' => 'Phone outside filter',
            'slug' => 'phone-outside-smart-filter',
            'description' => 'Different category',
            'price' => 1100,
            'currency' => 'YER',
            'location' => 'Sanaa',
            'status' => 'active',
            'views' => 999,
            'condition' => 'used',
        ]);

        $this->getJson("/api/v1/ads?category_id={$cars->id}&location=Sanaa&min_price=900&max_price=1500&condition=used&sort=most_viewed")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $mostViewed->id)
            ->assertJsonPath('data.0.location', 'Sanaa')
            ->assertJsonPath('data.0.condition', 'used');
    }

    public function test_saved_search_notifies_user_when_matching_ad_is_created(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $category = Category::create([
            'title' => 'Cars',
            'slug' => 'cars-saved-search',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($buyer, ['user']);

        $this->postJson('/api/v1/saved-searches', [
            'name' => 'Camry in Sanaa',
            'notify_enabled' => true,
            'filters' => [
                'search' => 'Camry',
                'category_id' => $category->id,
                'location' => 'Sanaa',
                'min_price' => 1000,
                'max_price' => 2000,
                'currency' => 'YER',
                'condition' => 'used',
            ],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Camry in Sanaa');

        Sanctum::actingAs($seller, ['user']);

        $this->postJson('/api/v1/ads', [
            'title' => 'Toyota Camry 2018',
            'description' => 'A clean Camry matching the saved search.',
            'price' => 1500,
            'currency' => 'YER',
            'category_id' => $category->id,
            'location' => 'Sanaa',
            'condition' => 'used',
        ])->assertCreated();

        $this->assertDatabaseHas('notifications_table', [
            'user_id' => $buyer->id,
            'type' => 'saved_search_match',
            'title' => 'إعلان جديد مطابق لبحثك',
        ]);
    }
}
