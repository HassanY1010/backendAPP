<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'reply_to_id')) {
                $table->foreignId('reply_to_id')
                    ->nullable()
                    ->after('receiver_id')
                    ->constrained('messages')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('messages', 'file_name')) {
                $table->string('file_name', 255)->nullable()->after('file_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'reply_to_id')) {
                $table->dropConstrainedForeignId('reply_to_id');
            }

            if (Schema::hasColumn('messages', 'file_name')) {
                $table->dropColumn('file_name');
            }
        });
    }
};
