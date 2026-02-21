<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
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

                // Ensure chat directory exists
                if (!\Illuminate\Support\Facades\Storage::disk('public')->exists('chat')) {
                    \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory('chat');
                }

                $path = $file->store('chat', 'public');
                $messageData['file_url'] = url('local-cdn/' . $path);
                $messageData['message_type'] = 'image';
            }

            if (empty($messageData['message']) && !isset($messageData['file_url'])) {
                return response()->json(['error' => 'Message or image is required'], 422);
            }

            // Find or create conversation
            $conversation = Conversation::where(function ($q) use ($request) {
                $q->where('sender_id', $request->user()->id)->where('receiver_id', $request->receiver_id);
            })->orWhere(function ($q) use ($request) {
                $q->where('sender_id', $request->receiver_id)->where('receiver_id', $request->user()->id);
            })->first();

            if (!$conversation) {
                $conversation = Conversation::create([
                    'sender_id' => $request->user()->id,
                    'receiver_id' => $request->receiver_id,
                    'ad_id' => $request->ad_id ?? null, // Now nullable
                ]);
            }

            $messageData['conversation_id'] = $conversation->id;
            $message = Message::create($messageData);

            $conversation->update([
                'last_message_id' => $message->id,
                'last_message_at' => now(),
                // Reset deletion flags if a new message is sent
                'sender_deleted_at' => null,
                'receiver_deleted_at' => null,
            ]);

            return response()->json($message);
        }
        catch (\Exception $e) {
            \Log::error('Error in MessageController@send: ' . $e->getMessage());
            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function fetch(Request $request, $userId, $otherUserId)
    {
        // Security Check: potentially vulnerable IDOR if we blindly trust $userId from URL
        // We should ensure the authenticated user is one of the participants.

        $authUserId = $request->user()->id;

        // Allow if auth user is $userId OR $otherUserId
        if ($authUserId != $userId && $authUserId != $otherUserId) {
            return response()->json(['error' => 'Unauthorized access to messages'], 403);
        }

        // Find conversation to check for deletion flags
        $conversation = Conversation::where(function ($q) use ($userId, $otherUserId) {
            $q->where('sender_id', $userId)->where('receiver_id', $otherUserId);
        })->orWhere(function ($q) use ($userId, $otherUserId) {
            $q->where('sender_id', $otherUserId)->where('receiver_id', $userId);
        })->first();

        $query = Message::where(function ($q) use ($userId, $otherUserId) {
            $q->where('sender_id', $userId)->where('receiver_id', $otherUserId);
        })->orWhere(function ($q) use ($userId, $otherUserId) {
            $q->where('sender_id', $otherUserId)->where('receiver_id', $userId);
        });

        // If conversation was deleted by this user, only show messages after deletion
        if ($conversation) {
            if ($conversation->sender_id == $authUserId && $conversation->sender_deleted_at) {
                $query->where('created_at', '>', $conversation->sender_deleted_at);
            }
            elseif ($conversation->receiver_id == $authUserId && $conversation->receiver_deleted_at) {
                $query->where('created_at', '>', $conversation->receiver_deleted_at);
            }
        }

        $messages = $query->orderBy('created_at', 'asc')->get();

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
            \Log::error('Error fetching conversations: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch conversations', 'message' => $e->getMessage()], 500);
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
            return response()->json(['error' => 'Failed to delete conversation', 'message' => $e->getMessage()], 500);
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
            return response()->json(['error' => 'Failed to block user', 'message' => $e->getMessage()], 500);
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
            return response()->json(['error' => 'Failed to unblock user', 'message' => $e->getMessage()], 500);
        }
    }
}
