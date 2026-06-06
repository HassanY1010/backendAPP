<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\UserResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
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
            $file = $request->file('avatar');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $filename;
            $oldAvatar = $user->avatar;

            $contents = file_get_contents($file->getRealPath());
            if ($contents === false) {
                return response()->json(['message' => 'Unable to read uploaded avatar'], 422);
            }

            $uploaded = Storage::disk('supabase_avatars')->put($path, $contents, [
                'visibility' => 'public',
                'ContentType' => $file->getMimeType() ?: 'image/jpeg',
            ]);

            if (!$uploaded) {
                Log::error('Avatar upload failed', [
                    'user_id' => $user->id,
                    'filename' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                ]);

                return response()->json(['message' => 'Failed to upload avatar'], 500);
            }

            if ($oldAvatar && !Str::startsWith($oldAvatar, ['http://', 'https://']) && $oldAvatar !== $path) {
                Storage::disk('supabase_avatars')->delete($oldAvatar);
            }

            $user->avatar = $path;
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('phone')) {
            if ($user->phone !== $request->phone) {
                $user->phone = $request->phone;
                $user->phone_verified_at = null;
            }
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => new UserResource($user->fresh())
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
            Log::error('Failed to delete account', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to delete account',
                'status' => 'error'
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $user = $request->user();

            $ads = $user->ads()
                ->with(['category:id,title', 'images'])
                ->withCount(['favoritedBy', 'comments'])
                ->latest()
                ->get();

            $favorites = $user->favorites()
                ->with(['category:id,title', 'mainImage'])
                ->orderByPivot('created_at', 'desc')
                ->get();

            $reviews = $user->reviewsReceived()
                ->with(['reviewer:id,name', 'ad:id,title'])
                ->latest()
                ->get();

            $stats = [
                'total_ads' => $ads->count(),
                'active_ads' => $ads->where('status', 'active')->count(),
                'pending_ads' => $ads->where('status', 'pending')->count(),
                'sold_ads' => $ads->where('status', 'sold')->count(),
                'rejected_ads' => $ads->where('status', 'rejected')->count(),
                'expired_ads' => $ads->where('status', 'expired')->count(),
                'inactive_ads' => $ads->where('status', 'inactive')->count(),
                'featured_ads' => $ads->where('is_featured', true)->count(),
                'total_views' => (int) $ads->sum('views'),
                'total_favorites' => $favorites->count(),
                'followers_count' => $user->followers()->count(),
                'following_count' => $user->following()->count(),
                'reviews_count' => $reviews->count(),
                'average_rating' => round((float) ($reviews->avg('rating') ?? 0), 2),
                'notifications_total' => $user->notifications()->count(),
                'notifications_unread' => $user->notifications()->where('is_read', false)->count(),
                'joined_date' => $user->created_at->format('Y-m-d'),
            ];

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'show_phone_number' => $user->show_phone_number,
                    'accepts_notifications' => $user->accepts_notifications,
                    'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
                    'created_at' => $user->created_at->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                    'last_login_at' => $user->last_login_at?->toIso8601String(),
                    'avatar_url' => $user->avatar_url,
                ],
                'stats' => $stats,
                'ads' => $ads->map(function ($ad) {
                    return [
                        'id' => $ad->id,
                        'title' => $ad->title,
                        'description' => $ad->description,
                        'price' => $ad->price,
                        'currency' => $ad->currency,
                        'status' => $ad->status,
                        'category' => $ad->category?->title,
                        'location' => $ad->location,
                        'address' => $ad->address,
                        'views' => $ad->views,
                        'favorites_count' => $ad->favorited_by_count,
                        'comments_count' => $ad->comments_count,
                        'images_count' => $ad->images->count(),
                        'is_featured' => $ad->is_featured,
                        'is_urgent' => $ad->is_urgent,
                        'is_premium' => $ad->is_premium,
                        'is_negotiable' => $ad->is_negotiable,
                        'condition' => $ad->condition,
                        'contact_phone' => $ad->contact_phone,
                        'contact_whatsapp' => $ad->contact_whatsapp,
                        'expires_at' => $ad->expires_at?->toIso8601String(),
                        'featured_until' => $ad->featured_until?->toIso8601String(),
                        'created_at' => $ad->created_at->toIso8601String(),
                        'updated_at' => $ad->updated_at?->toIso8601String(),
                    ];
                }),
                'favorites' => $favorites->map(function ($ad) {
                    return [
                        'id' => $ad->id,
                        'title' => $ad->title,
                        'price' => $ad->price,
                        'currency' => $ad->currency,
                        'status' => $ad->status,
                        'category' => $ad->category?->title,
                        'location' => $ad->location,
                        'favorited_at' => $ad->pivot?->created_at,
                    ];
                }),
                'reviews' => $reviews->map(function ($review) {
                    return [
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'reviewer_name' => $review->reviewer?->name,
                        'ad_title' => $review->ad?->title,
                        'is_approved' => $review->is_approved,
                        'created_at' => $review->created_at->toIso8601String(),
                    ];
                }),
                'exported_at' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to export profile data', [
                'user_id' => $request->user()?->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to export data',
                'status' => 'error'
            ], 500);
        }
    }
}
