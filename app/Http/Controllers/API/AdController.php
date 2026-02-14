<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdImage;
use Illuminate\Http\Request;
use App\Http\Resources\AdResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Import Log for debugging

class AdController extends Controller
{
    public function index(Request $request)
    {
        $cacheKey = 'ads_list_' . md5(json_encode($request->all()));

        $ads = \Illuminate\Support\Facades\Cache::remember($cacheKey, 600, function () use ($request) {
            $query = Ad::with(['user', 'category', 'mainImage', 'images'])
                ->where('status', 'active');

            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                            $q->where('title', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        }
                        );
                    }

                    // Filter by category
                    if ($request->has('category_id') && $request->category_id) {
                        $query->where('category_id', $request->category_id);
                    }

                    // Filter by price range
                    if ($request->has('min_price') && $request->min_price) {
                        $query->where('price', '>=', $request->min_price);
                    }
                    if ($request->has('max_price') && $request->max_price) {
                        $query->where('price', '<=', $request->max_price);
                    }

                    // Filter by currency
                    if ($request->has('currency') && $request->currency) {
                        $query->where('currency', $request->currency);
                    }

                    // Filter by location
                    if ($request->has('location') && $request->location) {
                        $query->where('location', 'like', "%{$request->location}%");
                    }

                    // Exclude featured ads if requested
                    if ($request->has('exclude_featured') && $request->exclude_featured) {
                        $query->where('is_featured', false);
                    }

                    // Sort
                    $sort = $request->get('sort', 'latest');
                    if ($sort === 'cheapest') {
                        $query->orderBy('price', 'asc');
                    }
                    elseif ($sort === 'expensive') {
                        $query->orderBy('price', 'desc');
                    }
                    else {
                        $query->latest();
                    }

                    return $query->paginate(20);
                });

        return AdResource::collection($ads);
    }

    public function featured(Request $request)
    {
        // Cache featured ads for 5 minutes
        $ads = \Illuminate\Support\Facades\Cache::remember('featured_ads', 300, function () {
            $query = Ad::with(['user', 'category', 'mainImage', 'images'])
                ->where('status', 'active')
                ->where('is_featured', true)
                ->where(function ($q) {
                $q->whereNull('featured_until')
                    ->orWhere('featured_until', '>', now());
            }
            )
                ->latest()
                ->withCount('favoritedBy as likes_count'); // Assuming you have a likes relationship

            if (auth('sanctum')->check()) {
                $query->withExists([
                    'favoritedBy as is_liked' => function ($q) {
                    $q->where('user_id', auth('sanctum')->id());
                }
                ]);
            }

            return $query->take(10)->get();
        });

        return AdResource::collection($ads);
    }

    public function recent(Request $request)
    {
        $ads = Ad::with(['user', 'category', 'mainImage', 'images'])
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
                'contact_phone' => $request->contact_phone,
                'contact_whatsapp' => $request->contact_whatsapp,
                'status' => 'pending', // Default status
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
                    \App\Jobs\ProcessAdImage::dispatch($adImage);
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

            return new AdResource($ad->load('images'));
        }
        catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating ad: ' . $e->getMessage());
            return response()->json(['message' => 'Error creating ad', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $ad = Ad::with(['user', 'category', 'images', 'customFields.field', 'favoritedBy'])
            ->withCount('favoritedBy as likes_count')
            ->findOrFail($id);

        // Check if liked by current user
        if (auth('sanctum')->check()) {
            $ad->is_liked = $ad->favoritedBy()->where('user_id', auth('sanctum')->id())->exists();
        }

        // Incremenet views
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
        ]);

        $ad->update($request->only([
            'title', 'description', 'price', 'currency', 'location',
            'contact_phone', 'contact_whatsapp'
        ]));

        // Status might reset to pending on update depending on business logic
        // $ad->update(['status' => 'pending']); 

        return new AdResource($ad);
    }

    public function destroy(Request $request, $id)
    {
        $ad = Ad::where('user_id', $request->user()->id)->findOrFail($id);
        $ad->delete();

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

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // Max 10MB
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('ads', 'public');
            // Or store to s3/supabase if configured
            // $path = $request->file('image')->store('ads', 's3');

            // Return the path or full URL
            return response()->json([
                'path' => $path,
                'url' => Storage::url($path)
            ]);
        }

        return response()->json(['message' => 'No image uploaded'], 400);
    }
}
