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
        $rating = round((float) ($this->rating ?? 0), 1);
        $ratingsCount = (int) ($this->ratings_count ?? 0);
        $activeAdsCount = (int) ($this->active_ads_count ?? 0);
        $successfulAdsCount = (int) ($this->successful_ads_count ?? 0);
        $phoneVerified = (bool) $this->phone_verified_at;
        $showLastSeen = (bool) ($this->show_last_seen ?? true);
        $recentlyActive = (bool) ($this->last_activity_at && $this->last_activity_at->gte(now()->subDays(7)));
        $fastResponder = (bool) ($this->is_online || ($this->last_activity_at && $this->last_activity_at->gte(now()->subDay())));

        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'email'                => $this->email,
            'phone'                => ($this->show_phone_number || $canViewPrivatePhone) ? $this->phone : null,
            'phone_verified'       => $phoneVerified,
            'phone_verified_at'    => $this->phone_verified_at,
            'avatar'               => $this->avatar,
            'avatar_url'           => $this->avatar_url,
            'role'                 => $this->role,
            'is_active'            => (bool) $this->is_active,
            'accepts_notifications'=> $this->accepts_notifications,
            'show_phone_number'    => $this->show_phone_number,
            'show_last_seen'       => $showLastSeen,
            'allow_messages'       => (bool) ($this->allow_messages ?? true),
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
            'active_ads_count'     => $activeAdsCount,
            'successful_ads_count' => $successfulAdsCount,
            'rating'               => $rating,
            'ratings_count'        => $ratingsCount,
            'is_online'            => $showLastSeen ? $this->is_online : false,
            'last_activity_at'     => $showLastSeen ? $this->last_activity_at : null,
            'trust_signals'        => [
                'phone_verified' => $phoneVerified,
                'recently_active' => $recentlyActive,
                'fast_responder' => $fastResponder,
                'rating' => $rating,
                'ratings_count' => $ratingsCount,
                'active_ads_count' => $activeAdsCount,
                'successful_ads_count' => $successfulAdsCount,
                'response_speed_label' => $this->responseSpeedLabel($fastResponder, $recentlyActive),
            ],
            'trust_badges'         => $this->trustBadges(
                $phoneVerified,
                $recentlyActive,
                $fastResponder,
                $rating,
                $ratingsCount,
                $activeAdsCount,
                $successfulAdsCount
            ),
            'created_at'           => $this->created_at,
        ];
    }

    private function responseSpeedLabel(bool $fastResponder, bool $recentlyActive): string
    {
        if ($this->is_online) {
            return 'متصل الآن';
        }

        if ($fastResponder) {
            return 'نشط اليوم';
        }

        return $recentlyActive ? 'نشط مؤخراً' : 'نشاطه محدود';
    }

    private function trustBadges(
        bool $phoneVerified,
        bool $recentlyActive,
        bool $fastResponder,
        float $rating,
        int $ratingsCount,
        int $activeAdsCount,
        int $successfulAdsCount
    ): array {
        $badges = [];

        if ($phoneVerified) {
            $badges[] = [
                'id' => 'phone_verified',
                'label' => 'رقم موثق',
                'description' => 'تم التحقق من رقم الجوال',
                'icon' => 'verified_user',
                'color' => '#10B981',
            ];
        }

        if ($activeAdsCount > 0 || $recentlyActive) {
            $badges[] = [
                'id' => 'active_seller',
                'label' => 'بائع نشط',
                'description' => $activeAdsCount > 0
                    ? "{$activeAdsCount} إعلان نشط"
                    : 'نشط مؤخراً في التطبيق',
                'icon' => 'storefront',
                'color' => '#4A6DFF',
            ];
        }

        if ($fastResponder) {
            $badges[] = [
                'id' => 'fast_responder',
                'label' => 'سريع الرد',
                'description' => $this->responseSpeedLabel($fastResponder, $recentlyActive),
                'icon' => 'bolt',
                'color' => '#F59E0B',
            ];
        }

        if ($ratingsCount > 0) {
            $badges[] = [
                'id' => 'post_sale_rating',
                'label' => 'تقييم بعد البيع',
                'description' => "{$rating} من 5 عبر {$ratingsCount} تقييم",
                'icon' => 'star',
                'color' => '#F97316',
            ];
        }

        if ($successfulAdsCount > 0) {
            $badges[] = [
                'id' => 'successful_sales',
                'label' => 'بيع ناجح',
                'description' => "{$successfulAdsCount} إعلان تم بيعه",
                'icon' => 'task_alt',
                'color' => '#8B5CF6',
            ];
        }

        return $badges;
    }
}
