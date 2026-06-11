<?php

namespace App\Http\Controllers\API;

use App\Models\UserSession;
use App\Http\Resources\UserResource;
use App\Http\Controllers\Controller;
use App\Services\ImageStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request, ImageStorageService $imageStorage)
    {
        $user = $request->user();
        $oldAvatarToDelete = null;
        $newAvatarPath = null;

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'avatar' => 'sometimes|image|mimes:jpg,jpeg,png,webp,gif|max:10240',
            'accepts_notifications' => 'sometimes|boolean',
            'show_phone_number' => 'sometimes|boolean',
            'show_last_seen' => 'sometimes|boolean',
            'allow_messages' => 'sometimes|boolean',
        ]);

        if ($request->has('accepts_notifications')) {
            $user->accepts_notifications = $request->boolean('accepts_notifications');
        }

        if ($request->has('show_phone_number')) {
            $user->show_phone_number = $request->boolean('show_phone_number');
        }

        if ($request->has('show_last_seen')) {
            $user->show_last_seen = $request->boolean('show_last_seen');
        }

        if ($request->has('allow_messages')) {
            $user->allow_messages = $request->boolean('allow_messages');
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');

            try {
                $path = $imageStorage->uploadPublicImage($file, 'avatars', [
                    'user_id' => $user->id,
                    'type' => 'avatar',
                ]);

                $oldAvatarToDelete = $user->avatar;
                $newAvatarPath = $path;
                $user->avatar = $path;
            } catch (\Throwable $exception) {
                Log::error('Avatar upload exception', [
                    'user_id' => $user->id,
                    'filename' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'disk' => 'supabase',
                    'exception' => $exception->getMessage(),
                ]);

                return response()->json(['message' => 'فشل رفع الصورة. تحقق من إعدادات التخزين ثم حاول مرة أخرى'], 500);
            }
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

        if ($newAvatarPath !== null) {
            $this->deleteStoredAvatar($oldAvatarToDelete, $newAvatarPath, $user->id);
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => new UserResource($user->fresh())
        ]);
    }

    private function deleteStoredAvatar(?string $avatar, string $currentAvatar, int $userId): void
    {
        if (
            !$avatar ||
            $avatar === $currentAvatar ||
            Str::startsWith($avatar, ['http://', 'https://'])
        ) {
            return;
        }

        $path = ltrim(Str::replaceStart('public/', '', $avatar), '/');

        try {
            if (Str::startsWith($path, 'avatars/')) {
                Storage::disk('supabase')->delete($path);
                return;
            }

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                return;
            }

            Storage::disk('supabase_avatars')->delete($path);
        } catch (\Throwable $exception) {
            Log::warning('Failed to delete old avatar', [
                'user_id' => $userId,
                'avatar' => $avatar,
                'exception' => $exception->getMessage(),
            ]);
        }
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
            $recordLimit = 35;

            $adsBaseQuery = $user->ads();
            $favoritesBaseQuery = $user->favorites();
            $reviewsBaseQuery = $user->reviewsReceived();
            $notificationsBaseQuery = $user->notifications();
            $savedSearchesBaseQuery = $user->savedSearches();
            $sessionsBaseQuery = UserSession::where('user_id', $user->id);

            $ads = (clone $adsBaseQuery)
                ->with(['category:id,title', 'images'])
                ->withCount(['favoritedBy', 'comments'])
                ->latest()
                ->limit($recordLimit)
                ->get();

            $favorites = (clone $favoritesBaseQuery)
                ->with(['category:id,title', 'mainImage'])
                ->withCount(['favoritedBy', 'comments'])
                ->orderByPivot('created_at', 'desc')
                ->limit($recordLimit)
                ->get();

            $reviews = (clone $reviewsBaseQuery)
                ->with(['reviewer:id,name', 'ad:id,title'])
                ->latest()
                ->limit($recordLimit)
                ->get();

            $notifications = (clone $notificationsBaseQuery)
                ->latest()
                ->limit($recordLimit)
                ->get();

            $savedSearches = (clone $savedSearchesBaseQuery)
                ->latest()
                ->limit($recordLimit)
                ->get();

            $sessions = (clone $sessionsBaseQuery)
                ->orderByDesc('login_at')
                ->limit($recordLimit)
                ->get();

            $stats = [
                'total_ads' => (clone $adsBaseQuery)->count(),
                'active_ads' => (clone $adsBaseQuery)->where('status', 'active')->count(),
                'pending_ads' => (clone $adsBaseQuery)->where('status', 'pending')->count(),
                'sold_ads' => (clone $adsBaseQuery)->where('status', 'sold')->count(),
                'rejected_ads' => (clone $adsBaseQuery)->where('status', 'rejected')->count(),
                'expired_ads' => (clone $adsBaseQuery)->where('status', 'expired')->count(),
                'inactive_ads' => (clone $adsBaseQuery)->where('status', 'inactive')->count(),
                'featured_ads' => (clone $adsBaseQuery)->where('is_featured', true)->count(),
                'total_views' => (int) (clone $adsBaseQuery)->sum('views'),
                'total_favorites' => (clone $favoritesBaseQuery)->count(),
                'followers_count' => $user->followers()->count(),
                'following_count' => $user->following()->count(),
                'reviews_count' => (clone $reviewsBaseQuery)->count(),
                'average_rating' => round((float) ((clone $reviewsBaseQuery)->avg('rating') ?? 0), 2),
                'notifications_total' => (clone $notificationsBaseQuery)->count(),
                'notifications_unread' => (clone $notificationsBaseQuery)->where('is_read', false)->count(),
                'saved_searches_count' => (clone $savedSearchesBaseQuery)->count(),
                'sessions_count' => (clone $sessionsBaseQuery)->count(),
                'active_sessions_count' => (clone $sessionsBaseQuery)
                    ->whereNull('logout_at')
                    ->count(),
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
                    'show_last_seen' => $user->show_last_seen,
                    'allow_messages' => $user->allow_messages,
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
                        'slug' => $ad->slug,
                        'title' => $ad->title,
                        'description' => Str::limit((string) $ad->description, 500),
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
                        'slug' => $ad->slug,
                        'title' => $ad->title,
                        'description' => Str::limit((string) $ad->description, 350),
                        'price' => $ad->price,
                        'currency' => $ad->currency,
                        'status' => $ad->status,
                        'category' => $ad->category?->title,
                        'location' => $ad->location,
                        'views' => $ad->views,
                        'favorites_count' => $ad->favorited_by_count,
                        'comments_count' => $ad->comments_count,
                        'is_featured' => $ad->is_featured,
                        'condition' => $ad->condition,
                        'created_at' => $ad->created_at?->toIso8601String(),
                        'updated_at' => $ad->updated_at?->toIso8601String(),
                        'favorited_at' => $ad->pivot?->created_at,
                    ];
                }),
                'reviews' => $reviews->map(function ($review) {
                    return [
                        'rating' => $review->rating,
                        'comment' => Str::limit((string) $review->comment, 350),
                        'reviewer_name' => $review->reviewer?->name,
                        'ad_title' => $review->ad?->title,
                        'is_approved' => $review->is_approved,
                        'created_at' => $review->created_at->toIso8601String(),
                    ];
                }),
                'notifications' => $notifications->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'title' => $notification->title,
                        'message' => Str::limit((string) $notification->message, 350),
                        'is_read' => $notification->is_read,
                        'read_at' => $notification->read_at?->toIso8601String(),
                        'created_at' => $notification->created_at?->toIso8601String(),
                    ];
                }),
                'saved_searches' => $savedSearches->map(function ($search) {
                    return [
                        'id' => $search->id,
                        'name' => $search->name,
                        'filters' => $search->filters,
                        'notify_enabled' => $search->notify_enabled,
                        'last_notified_at' => $search->last_notified_at?->toIso8601String(),
                        'created_at' => $search->created_at?->toIso8601String(),
                        'updated_at' => $search->updated_at?->toIso8601String(),
                    ];
                }),
                'sessions' => $sessions->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'login_at' => $session->login_at?->toIso8601String(),
                        'logout_at' => $session->logout_at?->toIso8601String(),
                        'ip_address' => $session->ip_address,
                        'device_type' => $session->device_type,
                        'duration_minutes' => $session->duration,
                        'is_active' => $session->isActive(),
                        'user_agent' => Str::limit((string) $session->user_agent, 220),
                    ];
                }),
                'export_limits' => [
                    'records_per_section' => $recordLimit,
                    'is_limited' => true,
                ],
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
