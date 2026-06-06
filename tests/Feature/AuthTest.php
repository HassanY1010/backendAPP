<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'phone'    => '+96712345678',
            'password' => Hash::make('password123'),
            'role'     => 'user',
            'is_active'=> true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'phone'    => '+96712345678',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'token_type', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'phone'    => '+96712345678',
            'password' => Hash::make('correct_password'),
            'role'     => 'user',
            'is_active'=> true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'phone'    => '+96712345678',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'phone'     => '+96712345678',
            'password'  => Hash::make('password123'),
            'role'      => 'user',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'phone'    => '+96712345678',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        Sanctum::actingAs($user, ['user']);

        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(200);
    }

    public function test_admin_cannot_login_with_user_route_to_admin_panel(): void
    {
        $user = User::factory()->create([
            'phone'    => '+96712345678',
            'password' => Hash::make('password123'),
            'role'     => 'user',
            'is_active'=> true,
        ]);

        // Regular user tries admin login endpoint
        $response = $this->postJson('/api/v1/admin/login', [
            'phone'    => '+96712345678',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }
}
