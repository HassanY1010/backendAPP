<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdImage;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Resources\AdResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use App\Jobs\ProcessAdImage;
use App\Services\ImageStorageService;
use App\Services\SavedSearchNotifier;

class AdController extends Controller
{
    private function publicAdRelations(): array
    {
        return [
            'user' => fn ($query) => $query->withTrustMetrics(),
            'category',
            'images',
            'mainImage',
        ];
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'search' => 'sometimes|nullable|string|max:120',
            'category_id' => 'sometimes|nullable|integer|exists:categories,id',
            'location' => 'sometimes|nullable|string|max:120',
            'city' => 'sometimes|nullable|string|max:120',
            'min_price' => 'sometimes|nullable|numeric|min:0',
            'max_price' => 'sometimes|nullable|numeric|min:0',
            'currency' => 'sometimes|nullable|string|max:10',
            'condition' => ['sometimes', 'nullable', Rule::in(['new', 'used', 'refurbished'])],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'sold'])],
            'sort' => ['sometimes', 'nullable', Rule::in(['latest', 'cheapest', 'expensive', 'price_low', 'price_high', 'most_viewed', 'nearest'])],
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'radius_km' => 'sometimes|nullable|numeric|min:1|max:500',
            'exclude_featured' => 'sometimes|boolean',
        ]);

        if (isset($validated['city']) && !isset($validated['location'])) {
            $validated['location'] = $validated['city'];
        }
        unset($validated['city']);

        if (
            isset($validated['min_price'], $validated['max_price'])
            && (float) $validated['min_price'] > (float) $validated['max_price']
        ) {
            [$validated['min_price'], $validated['max_price']] = [$validated['max_price'], $validated['min_price']];
        }

        $cacheVersion = Cache::get('ads_cache_version', 1);
        $cacheKey = 'ads_list_' . $cacheVersion . '_' . md5(json_encode($validated));

        $ads = Cache::remember($cacheKey, 120, function () use ($validated) {
            $query = Ad::with($this->publicAdRelations())
                ->withCount('favoritedBy as likes_count');

            $this->applyPublicStatusFilter($query, $validated['status'] ?? null);
            $this->applyTextSearch($query, $validated['search'] ?? null);
            $this->applyCategoryFilter($query, $validated['category_id'] ?? null);
            $this->applyLocationFilter($query, $validated['location'] ?? null);
            $this->applyPriceFilter($query, $validated['min_price'] ?? null, $validated['max_price'] ?? null);

            if (!empty($validated['currency'])) {
                $query->where('currency', $validated['currency']);
            }

            if (!empty($validated['condition'])) {
                $query->where('condition', $validated['condition']);
            }

            if (!empty($validated['exclude_featured'])) {
                $query->where('is_featured', false);
            }

            $this->applySort($query, $validated);

            return $query->paginate(20);
        });

        // Add user-specific state outside the cache
        if (auth('sanctum')->check()) {
            $userId = auth('sanctum')->id();
            $likedAdIds = \App\Models\Favorite::where('user_id', $userId)
                ->whereIn('ad_id', $ads->pluck('id'))
                ->pluck('ad_id')
                ->toArray();

            foreach ($ads as $ad) {
                $ad->is_liked = in_array($ad->id, $likedAdIds);
            }
        }

        return AdResource::collection($ads);
    }

    private function applyPublicStatusFilter($query, ?string $status): void
    {
        // Public search only exposes sellable/visible states. Internal states stay hidden.
        $query->where('status', $status === 'sold' ? 'sold' : 'active');
    }

    private function applyTextSearch($query, ?string $search): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $like = '%' . addcslashes($search, '%_\\') . '%';
        $driver = DB::connection()->getDriverName();

        $query->where(function ($q) use ($search, $like, $driver) {
            if ($driver !== 'sqlite') {
                $q->whereFullText(['title', 'description'], $search)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like);
            } else {
                $q->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like);
            }

            $q->orWhere('location', 'like', $like)
                ->orWhere('address', 'like', $like)
                ->orWhereHas('category', function ($categoryQuery) use ($like) {
                    $categoryQuery->where('title', 'like', $like);
                });
        });
    }

    private function applyCategoryFilter($query, $categoryId): void
    {
        if (!$categoryId) {
            return;
        }

        $categoryIds = Category::query()
            ->where('id', $categoryId)
            ->orWhere('parent_id', $categoryId)
            ->pluck('id')
            ->all();

        $query->whereIn('category_id', $categoryIds ?: [(int) $categoryId]);
    }

    private function applyLocationFilter($query, ?string $location): void
    {
        $location = trim((string) $location);
        if ($location === '') {
            return;
        }

        $like = '%' . addcslashes($location, '%_\\') . '%';

        $query->where(function ($q) use ($like) {
            $q->where('location', 'like', $like)
                ->orWhere('address', 'like', $like);
        });
    }

    private function applyPriceFilter($query, $minPrice, $maxPrice): void
    {
        if ($minPrice !== null && $minPrice !== '') {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null && $maxPrice !== '') {
            $query->where('price', '<=', $maxPrice);
        }
    }

    private function applySort($query, array $filters): void
    {
        $sort = $filters['sort'] ?? 'latest';

        if (in_array($sort, ['cheapest', 'price_low'], true)) {
            $query->orderBy('price')->latest();
            return;
        }

        if (in_array($sort, ['expensive', 'price_high'], true)) {
            $query->orderByDesc('price')->latest();
            return;
        }

        if ($sort === 'most_viewed') {
            $query->orderByDesc('views')->latest();
            return;
        }

        if (
            $sort === 'nearest'
            && isset($filters['latitude'], $filters['longitude'])
            && DB::connection()->getDriverName() !== 'sqlite'
        ) {
            $latitude = (float) $filters['latitude'];
            $longitude = (float) $filters['longitude'];
            $distanceSql = '(6371 * acos(LEAST(1, GREATEST(-1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))))';
            $bindings = [$latitude, $longitude, $latitude];

            $query->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->select('ads.*')
                ->selectRaw("{$distanceSql} as distance_km", $bindings);

            if (!empty($filters['radius_km'])) {
                $query->whereRaw("{$distanceSql} <= ?", array_merge($bindings, [(float) $filters['radius_km']]));
            }

            $query->orderBy('distance_km')->latest();
            return;
        }

        $query->latest();
    }

    private function bumpAdsCacheVersion(): void
    {
        Cache::forget('featured_ads_base');
        Cache::put('ads_cache_version', now()->getTimestamp());
    }

    public function featured(Request $request)
    {
        // Cache the base featured ads (without user-specific likes)
        $ads = \Illuminate\Support\Facades\Cache::remember('featured_ads_base', 300, function () {
            return Ad::with($this->publicAdRelations())
                ->where('status', 'active')
                ->where('is_featured', true)
                ->where(function ($q) {
                    $q->whereNull('featured_until')
                        ->orWhere('featured_until', '>', now());
                })
                ->latest()
                ->withCount('favoritedBy as likes_count')
                ->take(10)
                ->get();
        });

        // Add user-specific state outside the cache
        if (auth('sanctum')->check()) {
            $userId = auth('sanctum')->id();
            $likedAdIds = \App\Models\Favorite::where('user_id', $userId)
                ->whereIn('ad_id', $ads->pluck('id'))
                ->pluck('ad_id')
                ->toArray();

            foreach ($ads as $ad) {
                $ad->is_liked = in_array($ad->id, $likedAdIds);
            }
        }

        return AdResource::collection($ads);
    }

    public function recent(Request $request)
    {
        $ads = Ad::with($this->publicAdRelations())
            ->where('status', 'active')
            ->latest()
            ->take(4)
            ->get();

        return AdResource::collection($ads);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'currency' => 'required|string|max:10',
            'category_id' => 'required|exists:categories,id',
            'location' => 'required|string',
            'address' => 'sometimes|nullable|string|max:1000',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'condition' => ['sometimes', 'nullable', Rule::in(['new', 'used', 'refurbished'])],
            'is_negotiable' => 'sometimes|boolean',
            'contact_phone' => 'sometimes|nullable|string|max:20',
            'contact_whatsapp' => 'sometimes|nullable|string|max:20',
            'images' => 'array',
            'images.*' => 'string', // Expecting image paths
        ]);

        DB::beginTransaction();
        try {
            $ad = Ad::create([
                'user_id' => $request->user()->id,
                'category_id' => $request->category_id,
                'title' => $request->title,
                'slug' => \Illuminate\Support\Str::slug($request->title) . '-' . uniqid(),
                'description' => $request->description,
                'price' => $request->price,
                'currency' => $request->currency,
                'location' => $request->location,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'condition' => $request->condition ?? 'used',
                'is_negotiable' => $request->boolean('is_negotiable'),
                'contact_phone' => $request->contact_phone,
                'contact_whatsapp' => $request->contact_whatsapp,
                'status' => 'active', // Changed from pending to active for immediate visibility
            ]);

            // Handle Images
            if ($request->has('images')) {
                foreach ($request->images as $index => $imagePath) {
                    $adImage = AdImage::create([
                        'ad_id' => $ad->id,
                        'image_path' => $imagePath,
                        'is_main' => $index === 0,
                    ]);

                    // Level 1 Optimization: Background processing
                    ProcessAdImage::dispatch($adImage);
                }
            }

            // Handle Custom Fields (if any)
            if ($request->has('custom_fields')) {
                foreach ($request->custom_fields as $fieldId => $value) {
                    \App\Models\AdCustomField::create([
                        'ad_id' => $ad->id,
                        'field_id' => $fieldId,
                        'value' => is_array($value) ? json_encode($value) : $value,
                    ]);
                }
            }

            DB::commit();

            $this->bumpAdsCacheVersion();
            try {
                app(SavedSearchNotifier::class)->notifyMatchingSearches($ad->fresh(['category']));
            } catch (\Exception $notifyException) {
                Log::warning('Saved search notification failed', [
                    'ad_id' => $ad->id,
                    'error' => $notifyException->getMessage(),
                ]);
            }

            return new AdResource($ad->load($this->publicAdRelations()));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating ad', ['exception' => $e->getMessage()]);
            return response()->json(['message' => 'Error creating ad'], 500);
        }
    }

    public function show($id)
    {
        $ad = Ad::with(array_merge($this->publicAdRelations(), ['customFields.field']))
            ->withCount('favoritedBy as likes_count')
            ->findOrFail($id);

        // Check if liked by current user
        if (auth('sanctum')->check()) {
            $ad->is_liked = $ad->favoritedBy()->where('user_id', auth('sanctum')->id())->exists();
        }

        // Increment views
        $ad->increment('views');

        return new AdResource($ad);
    }

    public function update(Request $request, $id)
    {
        $ad = Ad::where('user_id', $request->user()->id)->findOrFail($id);

        $request->validate([
            'title' => 'string|max:255',
            'description' => 'string',
            'price' => 'numeric',
            'currency' => 'string|max:10',
            'location' => 'string',
            'address' => 'sometimes|nullable|string|max:1000',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'condition' => ['sometimes', 'nullable', Rule::in(['new', 'used', 'refurbished'])],
            'is_negotiable' => 'sometimes|boolean',
        ]);

        $ad->update($request->only([
            'title', 'description', 'price', 'currency', 'location',
            'address', 'latitude', 'longitude', 'condition', 'is_negotiable',
            'contact_phone', 'contact_whatsapp'
        ]));

        $this->bumpAdsCacheVersion();

        return new AdResource($ad);
    }

    public function destroy(Request $request, $id)
    {
        $ad = Ad::where('user_id', $request->user()->id)->findOrFail($id);
        $ad->delete();
        $this->bumpAdsCacheVersion();

        return response()->json(['message' => 'Ad deleted successfully']);
    }

    public function userAds(Request $request)
    {
        $ads = Ad::with(['category', 'mainImage'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return AdResource::collection($ads);
    }

    public function uploadImage(Request $request, ImageStorageService $imageStorage)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,webp,gif|max:10240', // Max 10MB
        ]);

        if ($request->hasFile('image')) {
            try {
                $path = $imageStorage->uploadPublicImage($request->file('image'), 'ads', [
                    'user_id' => $request->user()?->id,
                    'type'    => 'ad_image',
                ]);

                return response()->json([
                    'path' => $path,
                    'url'  => $imageStorage->publicUrl($path),
                ]);
            } catch (\Throwable $exception) {
                Log::error('Ad image upload exception', [
                    'user_id'   => $request->user()?->id,
                    'exception' => $exception->getMessage(),
                    'file'      => $request->file('image')?->getClientOriginalName(),
                    'size'      => $request->file('image')?->getSize(),
                ]);

                return response()->json([
                    'message' => 'فشل رفع صورة الإعلان. تحقق من إعدادات التخزين ثم حاول مرة أخرى',
                ], 500);
            }
        }

        return response()->json(['message' => 'لم يتم إرسال صورة للرفع'], 400);
    }

    /**
     * Health Check لـ Storage — مفيد للتشخيص
     */
    public function storageHealth(ImageStorageService $imageStorage)
    {
        $result = $imageStorage->healthCheck();
        $status = $result['status'] === 'ok' ? 200 : 500;
        return response()->json($result, $status);
    }

    public function suggest(Request $request)
    {
        $search = $request->get('search');
        if (!$search || strlen($search) < 2) {
            return response()->json([]);
        }

        $ads = Ad::with(['category', 'mainImage'])
            ->where('status', 'active')
            ->where(function ($q) use ($search) {
                $q->whereFullText(['title', 'description'], $search)
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($cq) use ($search) {
                        $cq->where('title', 'like', "%{$search}%");
                    });
            })
            ->latest()
            ->take(10)
            ->get();

        return AdResource::collection($ads);
    }
}
