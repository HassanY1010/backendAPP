<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {--phone= : Admin phone number}
                            {--password= : Admin password}
                            {--name=Admin : Admin display name}
                            {--force : Update existing user to admin role}';

    protected $description = 'Create or update an admin user';

    public function handle(): int
    {
        $phone    = $this->option('phone')    ?? env('ADMIN_PHONE');
        $password = $this->option('password') ?? env('ADMIN_PASSWORD');
        $name     = $this->option('name');
        $force    = $this->option('force');

        if (!$phone || !$password) {
            $this->error('Phone and password are required. Use --phone and --password options or set ADMIN_PHONE/ADMIN_PASSWORD env vars.');
            return self::FAILURE;
        }

        $existing = User::where('phone', $phone)->first();

        if ($existing) {
            if (!$force) {
                $this->warn("User with phone [{$phone}] already exists (role: {$existing->role}). Use --force to promote to admin.");
                return self::SUCCESS;
            }

            $existing->forceFill([
                'role'     => 'admin',
                'password' => Hash::make($password),
                'name'     => $name,
                'is_active' => true,
            ])->save();

            $this->info("User [{$phone}] has been promoted to admin successfully.");
            return self::SUCCESS;
        }

        User::forceCreate([
            'name'       => $name,
            'phone'      => $phone,
            'password'   => Hash::make($password),
            'role'       => 'admin',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("Admin user [{$phone}] created successfully!");
        return self::SUCCESS;
    }
}
