<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'role',
        'is_active',
        'last_login_at',
        'login_count',
        'show_phone_number',
        'qr_code',
        'otp',
        'otp_expires_at',
        'accepts_notifications',
        'last_activity_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'remember_token',
        'otp',
        'otp_expires_at',
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

    /**
     * Get the full URL for the avatar.
     *
     * @return string|null
     */
    public function getAvatarUrlAttribute()
    {
        if (!$this->avatar)
            return null;

        // Manually construct Supabase public URL for avatars
        $supabaseUrl = env('SUPABASE_URL');
        $bucket = 'avatars';
        return "{$supabaseUrl}/storage/v1/object/public/{$bucket}/{$this->avatar}";
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
            });

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
        return $this->belongsToMany(Ad::class, 'favorites', 'user_id', 'ad_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'followers', 'following_id', 'follower_id');
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'followers', 'follower_id', 'following_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function reviewsReceived()
    {
        return $this->hasMany(Review::class, 'reviewed_id');
    }
}
