<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'show_last_seen')) {
                $table->boolean('show_last_seen')->default(true)->after('show_phone_number');
            }

            if (!Schema::hasColumn('users', 'allow_messages')) {
                $table->boolean('allow_messages')->default(true)->after('show_last_seen');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'allow_messages')) {
                $table->dropColumn('allow_messages');
            }

            if (Schema::hasColumn('users', 'show_last_seen')) {
                $table->dropColumn('show_last_seen');
            }
        });
    }
};
