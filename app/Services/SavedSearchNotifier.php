<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\Notification;
use App\Models\SavedSearch;
use Illuminate\Support\Str;

class SavedSearchNotifier
{
    public function notifyMatchingSearches(Ad $ad): void
    {
        if ($ad->status !== 'active') {
            return;
        }

        $ad->loadMissing('category');

        SavedSearch::query()
            ->where('notify_enabled', true)
            ->where('user_id', '!=', $ad->user_id)
            ->chunkById(100, function ($savedSearches) use ($ad) {
                foreach ($savedSearches as $savedSearch) {
                    if (!$this->adMatches($ad, $savedSearch->filters ?? [])) {
                        continue;
                    }

                    Notification::create([
                        'user_id' => $savedSearch->user_id,
                        'type' => 'saved_search_match',
                        'title' => 'إعلان جديد مطابق لبحثك',
                        'message' => "وصل إعلان جديد يناسب البحث المحفوظ: {$savedSearch->name}",
                        'data' => [
                            'ad_id' => $ad->id,
                            'saved_search_id' => $savedSearch->id,
                            'title' => $ad->title,
                        ],
                        'is_read' => false,
                    ]);

                    $savedSearch->forceFill(['last_notified_at' => now()])->save();
                }
            });
    }

    public function adMatches(Ad $ad, array $filters): bool
    {
        if (!$this->matchesSearchText($ad, $filters['search'] ?? null)) {
            return false;
        }

        if (!empty($filters['category_id']) && (int) $filters['category_id'] !== (int) $ad->category_id) {
            return false;
        }

        $city = $filters['city'] ?? $filters['location'] ?? null;
        if (!empty($city) && !Str::contains(Str::lower((string) $ad->location), Str::lower((string) $city))) {
            return false;
        }

        if (isset($filters['min_price']) && (float) $ad->price < (float) $filters['min_price']) {
            return false;
        }

        if (isset($filters['max_price']) && (float) $ad->price > (float) $filters['max_price']) {
            return false;
        }

        if (!empty($filters['currency']) && $filters['currency'] !== $ad->currency) {
            return false;
        }

        if (!empty($filters['condition']) && $filters['condition'] !== $ad->condition) {
            return false;
        }

        if (!$this->matchesRadius($ad, $filters)) {
            return false;
        }

        return true;
    }

    private function matchesSearchText(Ad $ad, ?string $search): bool
    {
        $search = trim((string) $search);
        if ($search === '') {
            return true;
        }

        $haystack = Str::lower(implode(' ', [
            $ad->title,
            $ad->description,
            $ad->location,
            $ad->category?->title,
        ]));

        return Str::contains($haystack, Str::lower($search));
    }

    private function matchesRadius(Ad $ad, array $filters): bool
    {
        if (!isset($filters['latitude'], $filters['longitude'], $filters['radius_km'])) {
            return true;
        }

        if ($ad->latitude === null || $ad->longitude === null) {
            return false;
        }

        $distance = $this->distanceKm(
            (float) $filters['latitude'],
            (float) $filters['longitude'],
            (float) $ad->latitude,
            (float) $ad->longitude
        );

        return $distance <= (float) $filters['radius_km'];
    }

    private function distanceKm(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
