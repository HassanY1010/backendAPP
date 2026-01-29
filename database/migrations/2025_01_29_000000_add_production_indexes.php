<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // Add performance indexes for users (skip if exists)
        Schema::table('users', function (Blueprint $table) use ($driver) {
            // Skip email index if it already exists (created in default migration)
            if (!$this->indexExists('users', 'users_email_index')) {
                $table->index('email');
            }
            if (!$this->indexExists('users', 'users_phone_index')) {
                $table->index('phone');
            }
        });

        // Add indexes for ads
        if (Schema::hasTable('ads')) {
            Schema::table('ads', function (Blueprint $table) use ($driver) {
                if (!$this->indexExists('ads', 'ads_category_id_index')) {
                    $table->index('category_id');
                }
                if (!$this->indexExists('ads', 'ads_status_index')) {
                    $table->index('status');
                }
                if (!$this->indexExists('ads', 'ads_user_id_index')) {
                    $table->index('user_id');
                }

                // Only add fulltext for MySQL/MariaDB
                if (in_array($driver, ['mysql', 'mariadb'])) {
                    if (!$this->indexExists('ads', 'ads_title_description_fulltext')) {
                        $table->fullText(['title', 'description']);
                    }
                }
            });
        }

        // Add indexes for messages
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (!$this->indexExists('messages', 'messages_sender_id_receiver_id_index')) {
                    $table->index(['sender_id', 'receiver_id']);
                }
                if (!$this->indexExists('messages', 'messages_conversation_id_index')) {
                    $table->index('conversation_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'users_phone_index')) {
                $table->dropIndex(['phone']);
            }
        });

        if (Schema::hasTable('ads')) {
            Schema::table('ads', function (Blueprint $table) use ($driver) {
                if ($this->indexExists('ads', 'ads_category_id_index')) {
                    $table->dropIndex(['category_id']);
                }
                if ($this->indexExists('ads', 'ads_status_index')) {
                    $table->dropIndex(['status']);
                }
                if ($this->indexExists('ads', 'ads_user_id_index')) {
                    $table->dropIndex(['user_id']);
                }

                if (in_array($driver, ['mysql', 'mariadb']) && $this->indexExists('ads', 'ads_title_description_fulltext')) {
                    $table->dropFullText(['title', 'description']);
                }
            });
        }

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if ($this->indexExists('messages', 'messages_sender_id_receiver_id_index')) {
                    $table->dropIndex(['sender_id', 'receiver_id']);
                }
                if ($this->indexExists('messages', 'messages_conversation_id_index')) {
                    $table->dropIndex(['conversation_id']);
                }
            });
        }
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
        $doctrineTable = $doctrineSchemaManager->introspectTable($table);

        return $doctrineTable->hasIndex($index);
    }
};
