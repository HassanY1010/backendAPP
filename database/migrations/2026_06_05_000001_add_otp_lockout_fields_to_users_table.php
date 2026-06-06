<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'otp_attempts')) {
                $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_expires_at');
            }

            if (!Schema::hasColumn('users', 'otp_locked_until')) {
                $table->timestamp('otp_locked_until')->nullable()->after('otp_attempts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'otp_locked_until')) {
                $table->dropColumn('otp_locked_until');
            }

            if (Schema::hasColumn('users', 'otp_attempts')) {
                $table->dropColumn('otp_attempts');
            }
        });
    }
};
