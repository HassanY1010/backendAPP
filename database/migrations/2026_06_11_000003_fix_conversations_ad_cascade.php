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
        Schema::table('conversations', function (Blueprint $table) {
            // Drop old foreign key constraint
            $table->dropForeign(['ad_id']);

            // Re-create it with ON DELETE SET NULL
            $table->foreign('ad_id')
                ->references('id')
                ->on('ads')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Drop new foreign key constraint
            $table->dropForeign(['ad_id']);

            // Re-create it with ON DELETE CASCADE
            $table->foreign('ad_id')
                ->references('id')
                ->on('ads')
                ->onDelete('cascade');
        });
    }
};
