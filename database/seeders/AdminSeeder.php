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
        $phone = env('ADMIN_PHONE');
        $password = env('ADMIN_PASSWORD');

        if (!$phone || !$password) {
            $this->command?->warn('ADMIN_PHONE and ADMIN_PASSWORD are required to seed an admin user.');
            return;
        }

        // Check if admin already exists
        $adminExists = DB::table('users')
            ->where('phone', $phone)
            ->exists();

        if (!$adminExists) {
            DB::table('users')->insert([
                'name' => 'Admin',
                'phone' => $phone,
                'password' => Hash::make($password),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('Admin user created successfully!');
        } else {
            $this->command->info('Admin user already exists.');
        }
    }
}
