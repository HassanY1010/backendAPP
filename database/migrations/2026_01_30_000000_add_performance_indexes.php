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
        // Use try-catch for each table to handle partial migration failure (DDL is not transactional in MySQL)
        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->index(['sender_id', 'receiver_id', 'created_at']);
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('ads', function (Blueprint $table) {
                $table->index(['status', 'category_id', 'created_at']);
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('favorites', function (Blueprint $table) {
                $table->index(['user_id', 'ad_id']);
            });
        } catch (\Exception $e) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['sender_id', 'receiver_id', 'created_at']);
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->dropIndex(['status', 'category_id', 'created_at']);
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'ad_id']);
        });
    }
};
