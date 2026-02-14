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

            if (!in_array('ads_price_index', $existingIndices)) {
                $table->index('price');
            }
            if (!in_array('ads_currency_index', $existingIndices)) {
                $table->index('currency');
            }
            if (!in_array('ads_is_featured_index', $existingIndices)) {
                $table->index('is_featured');
            }
            if (!in_array('ads_is_urgent_index', $existingIndices)) {
                $table->index('is_urgent');
            }
            if (!in_array('ads_is_premium_index', $existingIndices)) {
                $table->index('is_premium');
            }
            if (!in_array('ads_status_created_at_index', $existingIndices)) {
                $table->index(['status', 'created_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropIndex(['price']);
            $table->dropIndex(['currency']);
            $table->dropIndex(['is_featured']);
            $table->dropIndex(['is_urgent']);
            $table->dropIndex(['is_premium']);
            $table->dropIndex(['status', 'created_at']);
        });
    }
};
