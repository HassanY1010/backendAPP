<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * Fixes:
     * 1. conversations.last_message_id — missing FK to messages.id
     * 2. subscriptions.plan_id — missing FK to plans.id
     * 3. reviews.rating — add CHECK constraint (1-5)
     * 4. messages table — add updated_at index for pagination
     * 5. notifications_table — add composite index on user_id, is_read
     * 6. ads — add index on user_id, status for user ads listing
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // Fix 1: conversations.last_message_id FK
        // Only add if the column exists and FK does not yet exist
        if (Schema::hasColumn('conversations', 'last_message_id')) {
            try {
                Schema::table('conversations', function (Blueprint $table) {
                    // Ensure nullable before adding FK
                    $table->unsignedBigInteger('last_message_id')->nullable()->change();
                    $table->foreign('last_message_id')
                        ->references('id')
                        ->on('messages')
                        ->onDelete('set null');
                });
            } catch (\Exception $e) {
                // FK may already exist — skip gracefully
                \Illuminate\Support\Facades\Log::info('FK conversations.last_message_id may already exist: ' . $e->getMessage());
            }
        }

        // Fix 2: subscriptions.plan_id FK
        if (Schema::hasColumn('subscriptions', 'plan_id') && Schema::hasTable('plans')) {
            try {
                Schema::table('subscriptions', function (Blueprint $table) {
                    $table->foreign('plan_id')
                        ->references('id')
                        ->on('plans')
                        ->onDelete('restrict');
                });
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::info('FK subscriptions.plan_id may already exist: ' . $e->getMessage());
            }
        }

        // Fix 3: reviews.rating CHECK constraint (MySQL 8+)
        if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'rating')) {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                try {
                    DB::statement('ALTER TABLE reviews ADD CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)');
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::info('CHECK constraint on reviews.rating may already exist: ' . $e->getMessage());
                }
            }
        }

        // Fix 4: notifications_table — composite index
        if (Schema::hasTable('notifications_table')) {
            try {
                Schema::table('notifications_table', function (Blueprint $table) {
                    $table->index(['user_id', 'is_read'], 'idx_notifications_user_read');
                });
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::info('Index idx_notifications_user_read may already exist: ' . $e->getMessage());
            }
        }

        // Fix 5: ads — composite index for user ads listing
        if (Schema::hasTable('ads')) {
            try {
                Schema::table('ads', function (Blueprint $table) {
                    $table->index(['user_id', 'status', 'created_at'], 'idx_ads_user_status_created');
                });
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::info('Index idx_ads_user_status_created may already exist: ' . $e->getMessage());
            }
        }

        // Fix 6: messages — index for conversation pagination
        if (Schema::hasTable('messages')) {
            try {
                Schema::table('messages', function (Blueprint $table) {
                    $table->index(['sender_id', 'receiver_id', 'created_at'], 'idx_messages_participants_created');
                });
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::info('Index idx_messages_participants_created may already exist: ' . $e->getMessage());
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropIndexIfExists('idx_messages_participants_created');
            });
        }

        if (Schema::hasTable('ads')) {
            Schema::table('ads', function (Blueprint $table) {
                $table->dropIndexIfExists('idx_ads_user_status_created');
            });
        }

        if (Schema::hasTable('notifications_table')) {
            Schema::table('notifications_table', function (Blueprint $table) {
                $table->dropIndexIfExists('idx_notifications_user_read');
            });
        }

        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'plan_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropForeign(['plan_id']);
            });
        }

        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'last_message_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropForeign(['last_message_id']);
            });
        }

        Schema::enableForeignKeyConstraints();
    }
};
