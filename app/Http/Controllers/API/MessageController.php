<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
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

            $messageData = [
                'sender_id' => $request->user()->id,
                'receiver_id' => $request->receiver_id,
                'message' => $request->message ?? '',
                'message_type' => 'text',
            ];

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $path = $file->store('chat', 'supabase');
                $messageData['file_url'] = Storage::disk('supabase')->url($path);
                $messageData['message_type'] = 'image';
            }

            if (empty($messageData['message']) && !isset($messageData['file_url'])) {
                return response()->json(['error' => 'Message or image is required'], 422);
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

            return response()->json($message);
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
        $messages = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        return response()->json($messages);
    }

    public function conversations(Request $request)
    {
        try {
            $userId = $request->user()->id;

            // Get conversations where the user is either sender or receiver and hasn't deleted it
            $conversations = Conversation::where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->whereNull('sender_deleted_at');
            })->orWhere(function ($q) use ($userId) {
                $q->where('receiver_id', $userId)->whereNull('receiver_deleted_at');
            })
                ->with(['sender:id,name,avatar', 'receiver:id,name,avatar', 'lastMessage'])
                ->orderBy('last_message_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($conv) use ($userId) {
                $otherUser = $conv->sender_id == $userId ? $conv->receiver : $conv->sender;
                return [
                'id' => $conv->id,
                'other_user_id' => $otherUser->id,
                'name' => $otherUser->name ?? 'Unknown User',
                'avatar' => $otherUser->avatar_url ?? null,
                'last_message' => $conv->lastMessage->message ?? '',
                'date' => $conv->last_message_at,
                // Add these for frontend compatibility with _getOtherUser
                'sender_id' => $conv->sender_id,
                'receiver_id' => $conv->receiver_id,
                'sender_name' => $conv->sender->name ?? null,
                'receiver_name' => $conv->receiver->name ?? null,
                'sender_avatar' => $conv->sender->avatar_url ?? null,
                'receiver_avatar' => $conv->receiver->avatar_url ?? null,
                ];
            });

            return response()->json($conversations);
        }
        catch (\Exception $e) {
            Log::error('Error fetching conversations', ['exception' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch conversations'], 500);
        }
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
}
