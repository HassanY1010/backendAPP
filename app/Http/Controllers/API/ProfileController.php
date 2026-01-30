<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'avatar' => 'sometimes|image|max:2048',
            'accepts_notifications' => 'sometimes|boolean',
            'show_phone_number' => 'sometimes|boolean',
        ]);

        if ($request->has('accepts_notifications')) {
            $user->accepts_notifications = $request->boolean('accepts_notifications');
        }

        if ($request->has('show_phone_number')) {
            $user->show_phone_number = $request->boolean('show_phone_number');
        }

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Ensure avatars directory exists
            if (!Storage::disk('public')->exists('avatars')) {
                Storage::disk('public')->makeDirectory('avatars');
            }

            // Store new avatar
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => new \App\Http\Resources\UserResource($user)
        ]);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
                // 1. Detach many-to-many relationships
                $user->favorites()->detach();
                $user->followers()->detach();
                $user->following()->detach();

                // 2. Delete polymorphic or simple hasMany relationships
                $user->notifications()->delete();
                // reports where user is reporter
                // If there's a Report model, delete them: \App\Models\Report::where('reporter_id', $user->id)->delete();

                // 3. Delete Ads (This usually triggers observers/events if set up, but we can allow cascade)
                // The User model's boot method already handles $user->ads(), comments, messages, avatar.

                // 4. Revoke tokens
                $user->tokens()->delete();

                // 5. Finally delete the user
                $user->delete();
            });

            return response()->json([
                'message' => 'Account and all associated data deleted successfully',
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete account: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $user = $request->user();

            // Eager load everything needed for the report
            $user->load([
                'ads' => function ($q) {
                    $q->orderBy('created_at', 'desc');
                },
                'reviewsReceived',
                'favorites'
            ]);

            // Fetch stats if available via a service or recalculate
            $stats = [
                'total_ads' => $user->ads->count(),
                'active_ads' => $user->ads->where('status', 'active')->count(),
                'sold_ads' => $user->ads->where('status', 'sold')->count(),
                'total_favorites' => $user->favorites->count(),
                'reviews_count' => $user->reviewsReceived->count(),
                'average_rating' => $user->reviewsReceived->avg('rating') ?? 0,
                'joined_date' => $user->created_at->format('Y-m-d'),
            ];

            return response()->json([
                'user' => [
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'created_at' => $user->created_at->toIso8601String(),
                    'avatar_url' => $user->avatar_url,
                ],
                'stats' => $stats,
                'ads' => $user->ads->map(function ($ad) {
                    return [
                        'id' => $ad->id,
                        'title' => $ad->title,
                        'price' => $ad->price,
                        'currency' => $ad->currency,
                        'status' => $ad->status,
                        'created_at' => $ad->created_at->toIso8601String(),
                        'views' => $ad->views,
                    ];
                }),
                'reviews' => $user->reviewsReceived->map(function ($review) {
                    return [
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'created_at' => $review->created_at->toIso8601String(),
                    ];
                }),
                'exported_at' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to export data: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
}
