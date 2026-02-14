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
            $table->index('price');
            $table->index('currency');
            $table->index('is_featured');
            $table->index('is_urgent');
            $table->index('is_premium');
            $table->index(['status', 'created_at']);
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
