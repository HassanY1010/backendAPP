<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user with plain password as requested
        User::create([
            'name' => 'Admin',
            'email' => 'admin@haraj.com',
            'password' => 'admin123', // Plain text password as requested
            'phone' => '0500000000',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
