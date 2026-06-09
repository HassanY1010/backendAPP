<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function send(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'receiver_id' => 'required|exists:users,id',
                'message' => 'nullable|string|max:1000',
                'image' => 'nullable|image|max:10240', // Max 10MB
                'file' => 'nullable|file|max:20480',
                'reply_to_id' => 'nullable|exists:messages,id',
                'ad_id' => 'nullable|exists:ads,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            // Prevent sending message to self
            if ($request->user()->id == $request->receiver_id) {
                return response()->json(['error' => 'Cannot send message to self'], 400);
            }

            // Prevent sending message if either user is blocked
            $isBlocked = \App\Models\BlockedUser::where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)->where('blocked_id', $request->receiver_id);
            })->orWhere(function ($q) use ($request) {
                $q->where('user_id', $request->receiver_id)->where('blocked_id', $request->user()->id);
            })->exists();

            if ($isBlocked) {
                return response()->json(['error' => 'Cannot send message to blocked user or if you are blocked'], 403);
            }

            $recentMessages = Message::where('sender_id', $request->user()->id)
                ->where('created_at', '>=', now()->subSeconds(10))
                ->count();

            if ($recentMessages >= 5) {
                return response()->json([
                    'error' => 'Too many messages. Please slow down.',
                ], 429);
            }

            $messageData = [
                'sender_id' => $request->user()->id,
                'receiver_id' => $request->receiver_id,
                'reply_to_id' => $request->reply_to_id,
                'message' => $request->message ?? '',
                'message_type' => 'text',
            ];

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $path = $file->store('chat', 'supabase');
                $messageData['file_url'] = Storage::disk('supabase')->url($path);
                $messageData['message_type'] = 'image';
            }

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = $file->store('chat', 'supabase');
                $messageData['file_url'] = Storage::disk('supabase')->url($path);
                $messageData['file_name'] = $file->getClientOriginalName();
                $messageData['message_type'] = 'file';
            }

            if (empty($messageData['message']) && !isset($messageData['file_url'])) {
                return response()->json(['error' => 'Message, image, or file is required'], 422);
            }

            $message = DB::transaction(function () use ($request, $messageData) {
                $authUserId = $request->user()->id;
                $receiverId = (int) $request->receiver_id;

                $conversation = Conversation::where(function ($q) use ($authUserId, $receiverId) {
                    $q->where('sender_id', $authUserId)->where('receiver_id', $receiverId);
                })->orWhere(function ($q) use ($authUserId, $receiverId) {
                    $q->where('sender_id', $receiverId)->where('receiver_id', $authUserId);
                })->lockForUpdate()->first();

                if (!$conversation) {
                    $conversation = Conversation::create([
                        'sender_id' => $authUserId,
                        'receiver_id' => $receiverId,
                        'ad_id' => $request->ad_id ?? null,
                    ]);
                }

                $messageData['conversation_id'] = $conversation->id;
                $message = Message::create($messageData);

                $conversation->update([
                    'last_message_id' => $message->id,
                    'last_message_at' => now(),
                    'sender_deleted_at' => null,
                    'receiver_deleted_at' => null,
                ]);

                return $message;
            });

            return response()->json($this->serializeMessage($message->load('replyTo:id,message,message_type,file_url,file_name')));
        }
        catch (\Exception $e) {
            Log::error('Error in MessageController@send', ['exception' => $e->getMessage()]);
            return response()->json([
                'error' => 'Internal Server Error'
            ], 500);
        }
    }

    public function fetch(Request $request, $otherUserId)
    {
        $authUserId = $request->user()->id;

        // Enforce: auth user is always one participant; derive the pair from auth context only
        if ($authUserId == $otherUserId) {
            return response()->json(['error' => 'Invalid conversation'], 400);
        }

        // Find conversation to check for deletion flags
        $conversation = Conversation::where(function ($q) use ($authUserId, $otherUserId) {
            $q->where('sender_id', $authUserId)->where('receiver_id', $otherUserId);
        })->orWhere(function ($q) use ($authUserId, $otherUserId) {
            $q->where('sender_id', $otherUserId)->where('receiver_id', $authUserId);
        })->first();

        $query = Message::where(function ($q) use ($authUserId, $otherUserId) {
            $q->where('sender_id', $authUserId)->where('receiver_id', $otherUserId);
        })->orWhere(function ($q) use ($authUserId, $otherUserId) {
            $q->where('sender_id', $otherUserId)->where('receiver_id', $authUserId);
        });

        // If conversation was deleted by this user, only show messages after deletion
        if ($conversation) {
            if ($conversation->sender_id == $authUserId && $conversation->sender_deleted_at) {
                $query->where('created_at', '>', $conversation->sender_deleted_at);
            } elseif ($conversation->receiver_id == $authUserId && $conversation->receiver_deleted_at) {
                $query->where('created_at', '>', $conversation->receiver_deleted_at);
            }
        }

        $limit = min(max((int) $request->query('limit', 50), 1), 100);
        $messages = $query->with('replyTo:id,message,message_type,file_url,file_name')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        Message::where('sender_id', $otherUserId)
            ->where('receiver_id', $authUserId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json($messages->map(fn ($message) => $this->serializeMessage($message)));
    }

    public function conversations(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $this->repairConversationsForUser($userId);

            // Get conversations where the user is either sender or receiver and hasn't deleted it
            $conversations = Conversation::where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->whereNull('sender_deleted_at');
            })->orWhere(function ($q) use ($userId) {
                $q->where('receiver_id', $userId)->whereNull('receiver_deleted_at');
            })
                ->with([
                    'sender:id,name,avatar,last_activity_at,is_online',
                    'receiver:id,name,avatar,last_activity_at,is_online',
                    'lastMessage',
                    'ad:id,title,price,currency,location',
                    'ad.mainImage:id,ad_id,image_path,url,is_main',
                ])
                ->orderBy('last_message_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($conv) use ($userId) {
                $otherUser = $conv->sender_id == $userId ? $conv->receiver : $conv->sender;
                if (!$otherUser) {
                    return null;
                }

                return [
                'id' => $conv->id,
                'ad_id' => $conv->ad_id,
                'ad' => $conv->ad ? [
                    'id' => $conv->ad->id,
                    'title' => $conv->ad->title,
                    'price' => $conv->ad->price,
                    'currency' => $conv->ad->currency,
                    'location' => $conv->ad->location,
                    'image' => $conv->ad->mainImage->url ?? $conv->ad->mainImage->image_path ?? null,
                ] : null,
                'other_user_id' => $otherUser->id,
                'name' => $otherUser->name ?? 'Unknown User',
                'avatar' => $otherUser->avatar_url ?? null,
                'is_online' => (bool) ($otherUser->is_online ?? false),
                'last_activity_at' => $otherUser->last_activity_at,
                'last_message' => $conv->lastMessage->message ?? '',
                'last_message_type' => $conv->lastMessage->message_type ?? 'text',
                'date' => $conv->last_message_at,
                'unread_count' => Message::where('conversation_id', $conv->id)
                    ->where('receiver_id', $userId)
                    ->where('is_read', false)
                    ->count(),
                // Add these for frontend compatibility with _getOtherUser
                'sender_id' => $conv->sender_id,
                'receiver_id' => $conv->receiver_id,
                'sender_name' => $conv->sender->name ?? null,
                'receiver_name' => $conv->receiver->name ?? null,
                'sender_avatar' => $conv->sender->avatar_url ?? null,
                'receiver_avatar' => $conv->receiver->avatar_url ?? null,
                ];
            })
                ->filter()
                ->values();

            return response()->json($conversations);
        }
        catch (\Exception $e) {
            Log::error('Error fetching conversations', ['exception' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch conversations'], 500);
        }
    }

    private function repairConversationsForUser(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $messagesWithoutConversation = Message::where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
                ->whereNull('conversation_id')
                ->orderBy('created_at')
                ->get();

            foreach ($messagesWithoutConversation as $message) {
                $senderId = (int) $message->sender_id;
                $receiverId = (int) $message->receiver_id;

                if ($senderId === $receiverId) {
                    continue;
                }

                $conversation = $this->conversationBetween($senderId, $receiverId, true);

                if (!$conversation) {
                    $conversation = Conversation::create([
                        'sender_id' => $senderId,
                        'receiver_id' => $receiverId,
                        'ad_id' => null,
                    ]);
                }

                $message->conversation_id = $conversation->id;
                $message->save();
            }

            Conversation::where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
                ->lockForUpdate()
                ->get()
                ->each(function (Conversation $conversation) {
                    $latestMessage = Message::where('conversation_id', $conversation->id)
                        ->latest('created_at')
                        ->first();

                    if (!$latestMessage) {
                        return;
                    }

                    if (
                        (int) $conversation->last_message_id !== (int) $latestMessage->id ||
                        !$conversation->last_message_at
                    ) {
                        $conversation->update([
                            'last_message_id' => $latestMessage->id,
                            'last_message_at' => $latestMessage->created_at,
                        ]);
                    }
                });
        });
    }

    private function conversationBetween(int $firstUserId, int $secondUserId, bool $lock = false): ?Conversation
    {
        $query = Conversation::where(function ($q) use ($firstUserId, $secondUserId) {
            $q->where('sender_id', $firstUserId)->where('receiver_id', $secondUserId);
        })->orWhere(function ($q) use ($firstUserId, $secondUserId) {
            $q->where('sender_id', $secondUserId)->where('receiver_id', $firstUserId);
        });

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function deleteConversation(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;
            $conversation = Conversation::findOrFail($id);

            if ($conversation->sender_id == $userId) {
                $conversation->update(['sender_deleted_at' => now()]);
            }
            elseif ($conversation->receiver_id == $userId) {
                $conversation->update(['receiver_deleted_at' => now()]);
            }
            else {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return response()->json(['message' => 'Conversation deleted successfully']);
        }
        catch (\Exception $e) {
            Log::error('Failed to delete conversation', ['exception' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to delete conversation'], 500);
        }
    }

    public function deleteMessage(Request $request, $id)
    {
        $message = Message::findOrFail($id);
        $userId = $request->user()->id;

        if ($message->sender_id == $userId) {
            $message->update(['deleted_by_sender' => true]);
        } elseif ($message->receiver_id == $userId) {
            $message->update(['deleted_by_receiver' => true]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(['message' => 'Message deleted successfully']);
    }

    public function markRead(Request $request, $otherUserId)
    {
        $authUserId = $request->user()->id;

        Message::where('sender_id', $otherUserId)
            ->where('receiver_id', $authUserId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'Messages marked as read']);
    }

    public function reportUser(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ((int) $id === (int) $request->user()->id) {
            return response()->json(['error' => 'Cannot report self'], 400);
        }

        $conversation = Conversation::where(function ($q) use ($request, $id) {
            $q->where('sender_id', $request->user()->id)->where('receiver_id', $id);
        })->orWhere(function ($q) use ($request, $id) {
            $q->where('sender_id', $id)->where('receiver_id', $request->user()->id);
        })->first();

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'ad_id' => $conversation?->ad_id,
            'reported_user_id' => $id,
            'reason' => $request->reason ?? 'other',
            'description' => $request->description,
            'type' => 'user',
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Report submitted successfully', 'data' => $report], 201);
    }

    public function blockUser(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;

            if ($userId == $id) {
                return response()->json(['error' => 'Cannot block self'], 400);
            }

            \App\Models\BlockedUser::firstOrCreate([
                'user_id' => $userId,
                'blocked_id' => $id
            ]);

            return response()->json(['message' => 'User blocked successfully']);
        }
        catch (\Exception $e) {
            Log::error('Failed to block user', ['exception' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to block user'], 500);
        }
    }

    public function unblockUser(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;

            \App\Models\BlockedUser::where('user_id', $userId)
                ->where('blocked_id', $id)
                ->delete();

            return response()->json(['message' => 'User unblocked successfully']);
        }
        catch (\Exception $e) {
            Log::error('Failed to unblock user', ['exception' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to unblock user'], 500);
        }
    }

    private function serializeMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'receiver_id' => $message->receiver_id,
            'reply_to_id' => $message->reply_to_id,
            'reply_to' => $message->replyTo ? [
                'id' => $message->replyTo->id,
                'message' => $message->replyTo->message,
                'message_type' => $message->replyTo->message_type,
                'file_url' => $message->replyTo->file_url,
                'file_name' => $message->replyTo->file_name,
            ] : null,
            'message' => $message->message,
            'message_type' => $message->message_type,
            'file_url' => $message->file_url,
            'file_name' => $message->file_name,
            'is_read' => (bool) $message->is_read,
            'read_at' => $message->read_at,
            'status' => $message->is_read ? 'read' : 'sent',
            'created_at' => $message->created_at,
            'updated_at' => $message->updated_at,
        ];
    }
}
