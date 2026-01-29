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
            
            if (!in_array('ads_status_index', $existingIndices)) {
                $table->index('status');
            }
            if (!in_array('ads_created_at_index', $existingIndices)) {
                $table->index('created_at');
            }
            if (!in_array('ads_status_created_at_index', $existingIndices)) {
                $table->index(['status', 'created_at']);
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            $existingIndices = collect(Schema::getIndexes('messages'))->pluck('name')->toArray();
            
            if (!in_array('messages_created_at_index', $existingIndices)) {
                $table->index('created_at');
            }
        });

        Schema::table('notifications_table', function (Blueprint $table) {
            $existingIndices = collect(Schema::getIndexes('notifications_table'))->pluck('name')->toArray();
            
            if (!in_array('notifications_table_is_read_index', $existingIndices)) {
                $table->index('is_read');
            }
            if (!in_array('notifications_table_created_at_index', $existingIndices)) {
                $table->index('created_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $existingIndices = collect(Schema::getIndexes('ads'))->pluck('name')->toArray();
            
            if (in_array('ads_status_index', $existingIndices)) $table->dropIndex(['status']);
            if (in_array('ads_created_at_index', $existingIndices)) $table->dropIndex(['created_at']);
            if (in_array('ads_status_created_at_index', $existingIndices)) $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $existingIndices = collect(Schema::getIndexes('messages'))->pluck('name')->toArray();
            if (in_array('messages_created_at_index', $existingIndices)) $table->dropIndex(['created_at']);
        });

        Schema::table('notifications_table', function (Blueprint $table) {
            $existingIndices = collect(Schema::getIndexes('notifications_table'))->pluck('name')->toArray();
            if (in_array('notifications_table_is_read_index', $existingIndices)) $table->dropIndex(['is_read']);
            if (in_array('notifications_table_created_at_index', $existingIndices)) $table->dropIndex(['created_at']);
        });
    }
};
