<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    public function index($adId)
    {
        $comments = Comment::where('ad_id', $adId)
            ->with(['user:id,name,avatar'])
            ->latest()
            ->get();

        return response()->json($comments);
    }

    public function store(Request $request, $adId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $ad = Ad::findOrFail($adId);

            $comment = $ad->comments()->create([
                'user_id' => $request->user()->id,
                'content' => $request->content,
            ]);

            // Trigger Notification
            if ($ad->user_id !== $request->user()->id) {
                \App\Models\Notification::create([
                    'user_id' => $ad->user_id,
                    'type' => 'comment',
                    'title' => 'تعليق جديد',
                    'message' => 'علق ' . $request->user()->name . ' على إعلانك: ' . $ad->title,
                    'data' => [
                        'ad_id' => $ad->id,
                        'comment_id' => $comment->id,
                        'commenter_id' => $request->user()->id,
                    ],
                ]);
            }

            return response()->json([
                'message' => 'Comment added successfully',
                'comment' => $comment->load('user:id,name,avatar'),
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error in CommentController@store: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }
}
