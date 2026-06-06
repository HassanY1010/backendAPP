<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $canViewPrivatePhone = $viewer && (
            $viewer->id === $this->id ||
            in_array($viewer->role, ['admin', 'moderator'], true)
        );

        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'email'                => $this->email,
            'phone'                => ($this->show_phone_number || $canViewPrivatePhone) ? $this->phone : null,
            'avatar'               => $this->avatar,
            'avatar_url'           => $this->avatar_url,
            'role'                 => $this->role,
            'accepts_notifications'=> $this->accepts_notifications,
            'show_phone_number'    => $this->show_phone_number,
            // Use whenLoaded to avoid N+1; is_following computed in controller when needed
            'is_following'         => $this->when(
                $viewer && $viewer->id !== $this->id,
                fn () => $this->relationLoaded('followers')
                    ? $this->followers->contains('id', $viewer->id)
                    : false
            ),
            'followers_count'      => (int) ($this->followers_count ?? 0),
            'following_count'      => (int) ($this->following_count ?? 0),
            'ads_count'            => (int) ($this->ads_count ?? 0),
            'is_online'            => $this->is_online,
            'last_activity_at'     => $this->last_activity_at,
            'created_at'           => $this->created_at,
        ];
    }
}
