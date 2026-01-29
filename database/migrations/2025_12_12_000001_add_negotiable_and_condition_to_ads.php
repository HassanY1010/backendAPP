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
            // Add is_negotiable column
            $table->boolean('is_negotiable')->default(false)->after('price');
            
            // Add condition column
            $table->enum('condition', ['new', 'used', 'refurbished'])->default('used')->after('is_negotiable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn(['is_negotiable', 'condition']);
        });
    }
};
