<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop email-related columns
            $table->dropUnique(['email']);
            $table->dropColumn(['email', 'email_verified_at']);

            // Make name nullable (users can update later)
            $table->string('name')->nullable()->change();

            // Ensure phone is not nullable and unique
            $table->string('phone')->nullable(false)->change();

            // Add guest role to enum if not exists (modify role column)
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['user', 'admin', 'moderator', 'guest'])->default('user')->after('avatar');
        });

        // Drop password_reset_tokens table as it's no longer needed
        Schema::dropIfExists('password_reset_tokens');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate password_reset_tokens
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            // Restore email and password columns
            $table->string('email')->unique()->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('phone');
            $table->string('password')->after('email_verified_at');

            // Make name required again
            $table->string('name')->nullable(false)->change();

            // Remove guest from role enum
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['user', 'admin', 'moderator'])->default('user')->after('avatar');
        });
    }
};
