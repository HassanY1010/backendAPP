<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ad_views')) {
            Schema::create('ad_views', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ad_id')->constrained('ads')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['ad_id', 'user_id']);
                $table->index(['user_id', 'created_at']);
            });
        }

        DB::table('ads')->update(['views' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_views');
    }
};
