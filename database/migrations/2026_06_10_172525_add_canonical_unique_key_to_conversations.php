<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('conversations', 'conversation_scope')) {
                $table->string('conversation_scope', 64)->nullable()->after('ad_id');
            }

            if (!Schema::hasColumn('conversations', 'participant_min_id')) {
                $table->foreignId('participant_min_id')->nullable()->after('receiver_id')->constrained('users')->cascadeOnDelete();
            }

            if (!Schema::hasColumn('conversations', 'participant_max_id')) {
                $table->foreignId('participant_max_id')->nullable()->after('participant_min_id')->constrained('users')->cascadeOnDelete();
            }
        });

        $this->backfillCanonicalConversationKeys();
        $this->mergeDuplicateConversations();

        Schema::table('conversations', function (Blueprint $table) {
            $existingIndexes = collect(Schema::getIndexes('conversations'))->pluck('name')->toArray();

            if (!in_array('conversations_scope_participants_unique', $existingIndexes, true)) {
                $table->unique(
                    ['conversation_scope', 'participant_min_id', 'participant_max_id'],
                    'conversations_scope_participants_unique'
                );
            }

            if (!in_array('conversations_last_message_at_index', $existingIndexes, true)) {
                $table->index('last_message_at', 'conversations_last_message_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $existingIndexes = collect(Schema::getIndexes('conversations'))->pluck('name')->toArray();

            if (in_array('conversations_scope_participants_unique', $existingIndexes, true)) {
                $table->dropUnique('conversations_scope_participants_unique');
            }

            if (in_array('conversations_last_message_at_index', $existingIndexes, true)) {
                $table->dropIndex('conversations_last_message_at_index');
            }
        });

        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'participant_max_id')) {
                $table->dropConstrainedForeignId('participant_max_id');
            }

            if (Schema::hasColumn('conversations', 'participant_min_id')) {
                $table->dropConstrainedForeignId('participant_min_id');
            }

            if (Schema::hasColumn('conversations', 'conversation_scope')) {
                $table->dropColumn('conversation_scope');
            }
        });
    }

    private function backfillCanonicalConversationKeys(): void
    {
        DB::table('conversations')
            ->select(['id', 'ad_id', 'sender_id', 'receiver_id'])
            ->orderBy('id')
            ->chunkById(200, function ($conversations) {
                foreach ($conversations as $conversation) {
                    $senderId = (int) $conversation->sender_id;
                    $receiverId = (int) $conversation->receiver_id;

                    DB::table('conversations')
                        ->where('id', $conversation->id)
                        ->update([
                            'conversation_scope' => $conversation->ad_id ? 'ad:' . $conversation->ad_id : 'direct',
                            'participant_min_id' => min($senderId, $receiverId),
                            'participant_max_id' => max($senderId, $receiverId),
                        ]);
                }
            });
    }

    private function mergeDuplicateConversations(): void
    {
        $groups = DB::table('conversations')
            ->select(['conversation_scope', 'participant_min_id', 'participant_max_id'])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('conversation_scope', 'participant_min_id', 'participant_max_id')
            ->having('total', '>', 1)
            ->get();

        foreach ($groups as $group) {
            $duplicates = DB::table('conversations')
                ->where('conversation_scope', $group->conversation_scope)
                ->where('participant_min_id', $group->participant_min_id)
                ->where('participant_max_id', $group->participant_max_id)
                ->orderByDesc('last_message_at')
                ->orderBy('id')
                ->get();

            $keeper = $duplicates->first();
            $duplicateIds = $duplicates->pluck('id')->slice(1)->values();

            if ($duplicateIds->isEmpty()) {
                continue;
            }

            DB::table('messages')
                ->whereIn('conversation_id', $duplicateIds)
                ->update(['conversation_id' => $keeper->id]);

            $latestMessage = DB::table('messages')
                ->where('conversation_id', $keeper->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($latestMessage) {
                DB::table('conversations')
                    ->where('id', $keeper->id)
                    ->update([
                        'last_message_id' => $latestMessage->id,
                        'last_message_at' => $latestMessage->created_at,
                    ]);
            }

            DB::table('conversations')
                ->whereIn('id', $duplicateIds)
                ->delete();
        }
    }
};
