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
        Schema::table('ads', function (Blueprint $table) {
            $existingIndices = collect(Schema::getIndexes('ads'))->pluck('name')->toArray();
            
            // Composite index for category browsing
            if (!in_array('ads_category_status_created_at_index', $existingIndices)) {
                $table->index(['category_id', 'status', 'created_at']);
            }
            
            // Composite index for user profile/dashboard
            if (!in_array('ads_user_status_created_at_index', $existingIndices)) {
                $table->index(['user_id', 'status', 'created_at']);
            }
            
            // Index for location search
            if (!in_array('ads_location_index', $existingIndices)) {
                $table->index('location');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            $existingIndices = collect(Schema::getIndexes('categories'))->pluck('name')->toArray();
            
            // Optimization for category tree retrieval
            if (!in_array('categories_active_sort_parent_index', $existingIndices)) {
                $table->index(['is_active', 'sort_order', 'parent_id']);
            }
        });

        Schema::table('favorites', function (Blueprint $table) {
            $existingIndices = collect(Schema::getIndexes('favorites'))->pluck('name')->toArray();
            
            // Speed up "is favorited" checks
            if (!in_array('favorites_user_ad_index', $existingIndices)) {
                $table->index(['user_id', 'ad_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'status', 'created_at']);
            $table->dropIndex(['user_id', 'status', 'created_at']);
            $table->dropIndex(['location']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'sort_order', 'parent_id']);
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'ad_id']);
        });
    }
};
