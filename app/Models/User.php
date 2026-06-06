<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'password',
        'avatar',
        'is_active',
        'last_login_at',
        'login_count',
        'show_phone_number',
        'qr_code',
        'accepts_notifications',
        'last_activity_at',
    ];

    protected $guarded = [
        'role',
        'otp',
        'otp_expires_at',
        'otp_attempts',
        'otp_locked_until',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp',
        'otp_expires_at',
        'otp_attempts',
        'otp_locked_until',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'show_phone_number' => 'boolean',
            'otp_expires_at' => 'datetime',
            'otp_locked_until' => 'datetime',
            'accepts_notifications' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['avatar_url', 'is_online'];

    /**
     * Check if user is currently online.
     *
     * @return bool
     */
    public function getIsOnlineAttribute()
    {
        return $this->last_activity_at && $this->last_activity_at->diffInMinutes(now()) < 5;
    }

    public function scopeWithTrustMetrics($query)
    {
        return $query
            ->withCount([
                'followers',
                'following',
                'ads',
                'ads as active_ads_count' => fn ($q) => $q->where('status', 'active'),
                'ads as successful_ads_count' => fn ($q) => $q->where('status', 'sold'),
                'reviewsReceived as ratings_count' => fn ($q) => $q->where('is_approved', true),
            ])
            ->withAvg([
                'reviewsReceived as rating' => fn ($q) => $q->where('is_approved', true),
            ], 'rating');
    }

    /**
     * Get the full URL for the avatar.
     *
     * @return string|null
     */
    public function getAvatarUrlAttribute()
    {
        if (!$this->avatar) {
            return null;
        }

        if (Str::startsWith($this->avatar, ['http://', 'https://'])) {
            return $this->versionedAvatarUrl($this->avatar);
        }

        $path = ltrim(Str::replaceStart('public/', '', $this->avatar), '/');

        if (Storage::disk('public')->exists($path)) {
            return $this->versionedAvatarUrl(Storage::disk('public')->url($path));
        }

        return $this->versionedAvatarUrl(Storage::disk('supabase_avatars')->url($path));
    }

    private function versionedAvatarUrl(string $url): string
    {
        if (!$this->updated_at) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'v=' . $this->updated_at->timestamp;
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::deleting(function ($user) {
            // Delete associated ads (this will trigger Ad model's deleting event to clean images etc.)
            $user->ads()->each(function ($ad) {
                    $ad->delete();
                }
                );

                // Delete physical avatar file
                if ($user->avatar) {
                    \Illuminate\Support\Facades\Storage::disk('supabase_avatars')->delete($user->avatar);
                }

                // Delete comments, messages, etc.
                $user->comments()->delete();
                $user->sentMessages()->delete();
                $user->receivedMessages()->delete();
                $user->favorites()->detach(); // Clean up pivot table
            });
    }

    public function ads()
    {
        return $this->hasMany(Ad::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(Ad::class , 'favorites', 'user_id', 'ad_id')->withPivot('created_at');
    }

    public function savedSearches()
    {
        return $this->hasMany(SavedSearch::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class , 'followers', 'following_id', 'follower_id');
    }

    public function following()
    {
        return $this->belongsToMany(User::class , 'followers', 'follower_id', 'following_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class , 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class , 'receiver_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function reviewsReceived()
    {
        return $this->hasMany(Review::class , 'reviewed_id');
    }

    public function blockedUsers()
    {
        return $this->belongsToMany(User::class , 'blocked_users', 'user_id', 'blocked_id')->withTimestamps();
    }

    public function blockedBy()
    {
        return $this->belongsToMany(User::class , 'blocked_users', 'blocked_id', 'user_id')->withTimestamps();
    }
}
