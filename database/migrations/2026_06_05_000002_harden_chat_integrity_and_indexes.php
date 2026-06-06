<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $existingIndexes = collect(Schema::getIndexes('conversations'))->pluck('name')->toArray();
            $existingForeignKeys = collect(Schema::getForeignKeys('conversations'))->pluck('name')->toArray();

            if (!in_array('conversations_participants_last_message_index', $existingIndexes, true)) {
                $table->index(['sender_id', 'receiver_id', 'last_message_at'], 'conversations_participants_last_message_index');
            }

            if (!in_array('conversations_receiver_last_message_index', $existingIndexes, true)) {
                $table->index(['receiver_id', 'last_message_at'], 'conversations_receiver_last_message_index');
            }

            if (!in_array('conversations_last_message_id_index', $existingIndexes, true)) {
                $table->index('last_message_id', 'conversations_last_message_id_index');
            }

            if (!in_array('conversations_last_message_id_foreign', $existingForeignKeys, true)) {
                $table->foreign('last_message_id', 'conversations_last_message_id_foreign')
                    ->references('id')
                    ->on('messages')
                    ->nullOnDelete();
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            $existingIndexes = collect(Schema::getIndexes('messages'))->pluck('name')->toArray();

            if (!in_array('messages_conversation_created_at_index', $existingIndexes, true)) {
                $table->index(['conversation_id', 'created_at'], 'messages_conversation_created_at_index');
            }

            if (!in_array('messages_receiver_is_read_index', $existingIndexes, true)) {
                $table->index(['receiver_id', 'is_read'], 'messages_receiver_is_read_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $existingIndexes = collect(Schema::getIndexes('messages'))->pluck('name')->toArray();

            if (in_array('messages_receiver_is_read_index', $existingIndexes, true)) {
                $table->dropIndex('messages_receiver_is_read_index');
            }

            if (in_array('messages_conversation_created_at_index', $existingIndexes, true)) {
                $table->dropIndex('messages_conversation_created_at_index');
            }
        });

        Schema::table('conversations', function (Blueprint $table) {
            $existingIndexes = collect(Schema::getIndexes('conversations'))->pluck('name')->toArray();
            $existingForeignKeys = collect(Schema::getForeignKeys('conversations'))->pluck('name')->toArray();

            if (in_array('conversations_last_message_id_foreign', $existingForeignKeys, true)) {
                $table->dropForeign('conversations_last_message_id_foreign');
            }

            foreach ([
                'conversations_last_message_id_index',
                'conversations_receiver_last_message_index',
                'conversations_participants_last_message_index',
            ] as $indexName) {
                if (in_array($indexName, $existingIndexes, true)) {
                    $table->dropIndex($indexName);
                }
            }
        });
    }
};
