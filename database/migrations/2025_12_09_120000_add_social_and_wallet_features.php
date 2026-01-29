<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update users table in a separate schema call or here.
        // It's safer to do Schema::table('users'...)
        Schema::table('users', function (Blueprint $table) {
            // Check if columns exist before adding (optional but good practice)
            // But since we are certain they don't from previous check:
            if (!Schema::hasColumn('users', 'show_phone_number')) {
                $table->boolean('show_phone_number')->default(true)->after('phone');
            }
            if (!Schema::hasColumn('users', 'wallet_balance')) {
                $table->decimal('wallet_balance', 10, 2)->default(0.00)->after('show_phone_number');
            }
            if (!Schema::hasColumn('users', 'qr_code')) {
                 $table->string('qr_code')->nullable()->after('wallet_balance');
            }
        });

        // Create comments table
        if (!Schema::hasTable('comments')) {
            Schema::create('comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('ad_id')->constrained('ads')->onDelete('cascade');
                $table->text('content');
                $table->timestamps();
            });
        }

        // Create followers table
        if (!Schema::hasTable('followers')) {
            Schema::create('followers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('follower_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('following_id')->constrained('users')->onDelete('cascade');
                $table->timestamps();

                $table->unique(['follower_id', 'following_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('followers');
        Schema::dropIfExists('comments');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['show_phone_number', 'wallet_balance', 'qr_code']);
        });
    }
};
