<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ImageStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class AdImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_ad_image_upload_stores_file_in_supabase_ads_folder(): void
    {
        Storage::fake('supabase');

        $user = User::factory()->create();

        Sanctum::actingAs($user, ['user']);

        $response = $this->post('/api/v1/ads/upload-image', [
            'image' => UploadedFile::fake()->image('ad-photo.jpg', 900, 700),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['path', 'url']);

        $path = $response->json('path');

        $this->assertStringStartsWith('ads/', $path);
        Storage::disk('supabase')->assertExists($path);
        $this->assertStringContainsString('ads/', $response->json('url'));
    }

    public function test_ad_image_upload_returns_friendly_error_when_storage_fails(): void
    {
        $user = User::factory()->create();

        $this->mock(ImageStorageService::class, function ($mock) {
            $mock->shouldReceive('uploadPublicImage')
                ->once()
                ->andThrow(new RuntimeException('Storage failed'));
        });

        Sanctum::actingAs($user, ['user']);

        $this->post('/api/v1/ads/upload-image', [
            'image' => UploadedFile::fake()->image('ad-photo.jpg', 900, 700),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertStatus(500)
            ->assertJsonPath('message', 'فشل رفع صورة الإعلان. تحقق من إعدادات التخزين ثم حاول مرة أخرى');
    }
}
