<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin already exists
        $adminExists = DB::table('users')
            ->where('phone', '782305677')
            ->exists();

        if (!$adminExists) {
            DB::table('users')->insert([
                'name' => 'Admin',
                'phone' => '782305677',
                'password' => Hash::make('abc098abc123'),
                'role' => 'admin',
                'is_verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('Admin user created successfully!');
        } else {
            $this->command->info('Admin user already exists.');
        }
    }
}
