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
        // Add performance indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index('email');
            $table->index('phone');
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->index('category_id');
            $table->index('status');
            $table->index('user_id');
            $table->fullText(['title', 'description']); // Full text search
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['sender_id', 'receiver_id']);
            $table->index('conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['phone']);
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id']);
            $table->dropFullText(['title', 'description']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['sender_id', 'receiver_id']);
            $table->dropIndex(['conversation_id']);
        });
    }
};
