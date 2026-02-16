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
        try {
            Schema::table('ads', function (Blueprint $table) {
                $table->fullText(['title', 'description']);
            });
        }
        catch (\Exception $e) {
        // Silently fail if DB doesn't support fulltext or index exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('ads', function (Blueprint $table) {
                $table->dropFullText(['title', 'description']);
            });
        }
        catch (\Exception $e) {
        }
    }
};
