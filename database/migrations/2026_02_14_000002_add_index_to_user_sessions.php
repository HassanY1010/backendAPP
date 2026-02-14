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
        Schema::table('user_sessions', function (Blueprint $table) {
            $existingIndices = collect(Schema::getIndexes('user_sessions'))->pluck('name')->toArray();
            if (!in_array('user_sessions_user_id_logout_at_index', $existingIndices)) {
                $table->index(['user_id', 'logout_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'logout_at']);
        });
    }
};
