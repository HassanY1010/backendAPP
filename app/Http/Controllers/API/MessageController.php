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

            $messageData = [
                'sender_id' => $request->user()->id,
                'receiver_id' => $request->receiver_id,
                'message' => $request->message ?? '',
                'message_type' => 'text',
            ];

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $path = $file->store('chat', 'public');
                $messageData['file_url'] = url('local-cdn/' . $path);
                $messageData['message_type'] = 'image';
            }

            if (empty($messageData['message']) && !isset($messageData['file_url'])) {
                return response()->json(['error' => 'Message or image is required'], 422);
            }

            $message = Message::create($messageData);

            return response()->json($message);
        } catch (\Exception $e) {
            \Log::error('Error in MessageController@send: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
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

        $messages = Message::where(function($q) use ($userId, $otherUserId){
            $q->where('sender_id', $userId)->where('receiver_id', $otherUserId);
        })->orWhere(function($q) use ($userId, $otherUserId){
            $q->where('sender_id', $otherUserId)->where('receiver_id', $userId);
        })->orderBy('created_at', 'asc')->get();

        return response()->json($messages);
    }

    public function conversations(Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            // Get the last message for each conversation involving the user
            // We use a subquery to find the latest message ID for each unique contact
            $latestMessagesIds = Message::where('sender_id', $userId)
                ->orWhere('receiver_id', $userId)
                ->select(\DB::raw('MAX(id) as id'))
                ->groupBy(\DB::raw('CASE WHEN sender_id = ' . $userId . ' THEN receiver_id ELSE sender_id END'))
                ->pluck('id');

            $conversations = Message::whereIn('id', $latestMessagesIds)
                ->with(['sender:id,name,avatar', 'receiver:id,name,avatar'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($msg) use ($userId) {
                    $otherUser = $msg->sender_id == $userId ? $msg->receiver : $msg->sender;
                    return [
                        'other_user_id' => $otherUser->id,
                        'name' => $otherUser->name ?? 'Unknown User',
                        'avatar' => $otherUser->avatar_url ?? null,
                        'last_message' => $msg->message ?? '',
                        'date' => $msg->created_at,
                    ];
                });
            
            return response()->json($conversations);
        } catch (\Exception $e) {
            \Log::error('Error fetching conversations: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch conversations', 'message' => $e->getMessage()], 500);
        }
    }
}
