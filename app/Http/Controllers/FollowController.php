<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function follow(Request $request, $id)
    {
        try {
            $userToFollow = User::findOrFail($id);
            $currentUser = $request->user();

            if ($currentUser->id === $userToFollow->id) {
                return response()->json(['message' => 'You cannot follow yourself'], 400);
            }

            $isFollowing = $currentUser->following()->where('following_id', $id)->exists();

            if (!$isFollowing) {
                $currentUser->following()->attach($id);
                
                // Trigger Notification
                \App\Models\Notification::create([
                    'user_id' => $userToFollow->id,
                    'type' => 'follow',
                    'title' => 'متابع جديد',
                    'message' => 'قام ' . $currentUser->name . ' بمتابعتك',
                    'data' => [
                        'follower_id' => $currentUser->id,
                    ],
                ]);
            }

            return response()->json(['message' => 'Followed successfully', 'is_following' => true]);
        } catch (\Exception $e) {
            \Log::error('Error in follow: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    public function unfollow(Request $request, $id)
    {
        try {
            $currentUser = $request->user();
            $currentUser->following()->detach($id);
            return response()->json(['message' => 'Unfollowed successfully', 'is_following' => false]);
        } catch (\Exception $e) {
            \Log::error('Error in unfollow: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    public function toggleFollow(Request $request, $id)
    {
        try {
            $userToFollow = User::findOrFail($id);
            $currentUser = $request->user();

            if ($currentUser->id === $userToFollow->id) {
                return response()->json(['message' => 'You cannot follow yourself'], 400);
            }

            $isFollowing = $currentUser->following()->where('following_id', $id)->exists();

            if ($isFollowing) {
                return $this->unfollow($request, $id);
            } else {
                return $this->follow($request, $id);
            }
        } catch (\Exception $e) {
            \Log::error('Error in toggleFollow: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    public function followers($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user->followers()->select('id', 'name', 'avatar')->get());
    }

    public function following($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user->following()->select('id', 'name', 'avatar')->get());
    }
}
