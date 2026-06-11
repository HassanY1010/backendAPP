<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairChatConversations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:repair {--user= : Repair for a specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repairs chat conversations by linking orphaned messages and updating last message metadata';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');

        if ($userId) {
            $this->info("Starting chat repair for user ID: {$userId}...");
        } else {
            $this->info("Starting chat repair for ALL users...");
        }

        DB::transaction(function () use ($userId) {
            // 1. Fetch messages without a valid conversation_id
            $query = Message::query();
            
            if ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
                });
            }

            $messagesWithoutConversation = $query->where(function ($q) {
                    $q->whereNull('conversation_id')
                        ->orWhereDoesntHave('conversation');
                })
                ->orderBy('created_at')
                ->get();

            $this->info("Found {$messagesWithoutConversation->count()} messages without a valid conversation.");

            $repairedCount = 0;
            foreach ($messagesWithoutConversation as $message) {
                $senderId = (int) $message->sender_id;
                $receiverId = (int) $message->receiver_id;

                if ($senderId === $receiverId) {
                    continue;
                }

                // Search for a direct conversation (ad_id is null) between these two users
                $conversation = Conversation::where(function ($participants) use ($senderId, $receiverId) {
                    $participants->where(function ($q) use ($senderId, $receiverId) {
                        $q->where('sender_id', $senderId)->where('receiver_id', $receiverId);
                    })->orWhere(function ($q) use ($senderId, $receiverId) {
                        $q->where('sender_id', $receiverId)->where('receiver_id', $senderId);
                    });
                })->whereNull('ad_id')->lockForUpdate()->first();

                if (!$conversation) {
                    $conversation = Conversation::create([
                        'sender_id' => $senderId,
                        'receiver_id' => $receiverId,
                        'ad_id' => null,
                    ]);
                    $this->info("Created new direct conversation ID: {$conversation->id} between {$senderId} and {$receiverId}");
                }

                $message->conversation_id = $conversation->id;
                $message->save();
                $repairedCount++;
            }

            $this->info("Successfully linked {$repairedCount} messages to conversations.");

            // 2. Update last message metadata for all conversations that are affected
            $convQuery = Conversation::query();
            if ($userId) {
                $convQuery->where(function ($q) use ($userId) {
                    $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
                });
            }

            $conversationsToUpdate = $convQuery->lockForUpdate()->get();
            $updatedConvCount = 0;

            foreach ($conversationsToUpdate as $conversation) {
                $latestMessage = Message::where('conversation_id', $conversation->id)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->first();

                if (!$latestMessage) {
                    continue;
                }

                if (
                    (int) $conversation->last_message_id !== (int) $latestMessage->id ||
                    !$conversation->last_message_at
                ) {
                    $conversation->update([
                        'last_message_id' => $latestMessage->id,
                        'last_message_at' => $latestMessage->created_at,
                    ]);
                    $updatedConvCount++;
                }
            }

            $this->info("Successfully updated last message metadata for {$updatedConvCount} conversations.");
        });

        $this->info('Chat repair completed successfully!');
        return self::SUCCESS;
    }
}
