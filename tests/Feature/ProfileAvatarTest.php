<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_uploads_avatar_to_supabase_avatars_folder(): void
    {
        Storage::fake('supabase');

        $user = User::factory()->create([
            'avatar' => null,
        ]);

        Sanctum::actingAs($user, ['user']);

        $response = $this->post('/api/v1/profile/update', [
            'name' => 'Avatar User',
            'phone' => $user->phone,
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 400, 400),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $user->refresh();

        $this->assertStringStartsWith('avatars/', $user->avatar);
        Storage::disk('supabase')->assertExists($user->avatar);
        $this->assertNotEmpty($response->json('user.avatar_url'));
        $this->assertStringContainsString('avatars/', $response->json('user.avatar_url'));
    }

    public function test_profile_update_accepts_all_supported_avatar_image_types(): void
    {
        Storage::fake('supabase');

        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $extension) {
            $user = User::factory()->create([
                'avatar' => null,
            ]);

            Sanctum::actingAs($user, ['user']);

            $response = $this->post('/api/v1/profile/update', [
                'avatar' => UploadedFile::fake()->image("avatar.{$extension}", 200, 200),
            ], [
                'Accept' => 'application/json',
            ]);

            $response->assertOk()
                ->assertJsonPath('user.id', $user->id);

            $user->refresh();
            $this->assertStringStartsWith('avatars/', $user->avatar);
            Storage::disk('supabase')->assertExists($user->avatar);
        }
    }

    public function test_profile_update_deletes_previous_supabase_avatar(): void
    {
        Storage::fake('supabase');
        Storage::disk('supabase')->put('avatars/old-avatar.jpg', 'old-avatar');

        $user = User::factory()->create([
            'avatar' => 'avatars/old-avatar.jpg',
        ]);

        Sanctum::actingAs($user, ['user']);

        $response = $this->post('/api/v1/profile/update', [
            'avatar' => UploadedFile::fake()->image('new-avatar.png', 400, 400),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();

        $user->refresh();

        $this->assertStringStartsWith('avatars/', $user->avatar);
        $this->assertNotSame('avatars/old-avatar.jpg', $user->avatar);
        Storage::disk('supabase')->assertMissing('avatars/old-avatar.jpg');
        Storage::disk('supabase')->assertExists($user->avatar);
    }

    public function test_profile_update_persists_privacy_preferences(): void
    {
        $user = User::factory()->create([
            'show_phone_number' => true,
            'show_last_seen' => true,
            'allow_messages' => true,
            'accepts_notifications' => true,
        ]);

        Sanctum::actingAs($user, ['user']);

        $this->postJson('/api/v1/profile/update', [
            'show_phone_number' => false,
            'show_last_seen' => false,
            'allow_messages' => false,
            'accepts_notifications' => false,
        ])->assertOk()
            ->assertJsonPath('user.show_phone_number', false)
            ->assertJsonPath('user.show_last_seen', false)
            ->assertJsonPath('user.allow_messages', false)
            ->assertJsonPath('user.accepts_notifications', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'show_phone_number' => false,
            'show_last_seen' => false,
            'allow_messages' => false,
            'accepts_notifications' => false,
        ]);
    }

    public function test_public_profile_hides_last_seen_when_disabled(): void
    {
        $user = User::factory()->create([
            'show_last_seen' => false,
            'last_activity_at' => now(),
        ]);

        $this->getJson("/api/v1/users/{$user->id}/profile")
            ->assertOk()
            ->assertJsonPath('user.show_last_seen', false)
            ->assertJsonPath('user.is_online', false)
            ->assertJsonPath('user.last_activity_at', null);
    }
}
