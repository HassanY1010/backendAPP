<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar_url, // Uses the accessor with local-cdn
            'role' => $this->role,
            'accepts_notifications' => $this->accepts_notifications,
            'show_phone_number' => $this->show_phone_number,
            'is_following' => auth('sanctum')->check() ? $this->followers()->where('follower_id', auth('sanctum')->id())->exists() : false,
            'followers_count' => $this->followers()->count(),
            'following_count' => $this->following()->count(),
            'ads_count' => $this->ads()->count(),
            'is_online' => $this->is_online,
            'last_activity_at' => $this->last_activity_at,
            'created_at' => $this->created_at,
        ];
    }
}
