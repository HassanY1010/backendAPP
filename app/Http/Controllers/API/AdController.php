<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdImage;
use App\Http\Requests\StoreAdRequest;
use App\Http\Requests\UpdateAdRequest;
use App\Http\Resources\AdResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class AdController extends Controller
{
    public function recent(Request $request)
    {
        $ads = \Illuminate\Support\Facades\Cache::remember('recent_ads', 300, function () {
            $query = Ad::with(['user', 'category', 'mainImage', 'images'])
                ->where('status', 'active')
                ->orderBy('is_featured', 'desc')
                ->latest()
                ->withCount('favoritedBy as likes_count');

            if (auth('sanctum')->check()) {
                $query->withExists([
                    'favoritedBy as is_liked' => function ($q) {
                        $q->where('user_id', auth('sanctum')->id());
                    }
                ]);
            }

            return $query->take(4)->get();
        });

        return AdResource::collection($ads);
    }

    public function index(Request $request)
    {
        $query = Ad::with(['user', 'category', 'mainImage', 'images'])
            ->where('status', 'active')
            ->withCount('favoritedBy as likes_count');

        if (auth('sanctum')->check()) {
            $query->withExists([
                'favoritedBy as is_liked' => function ($q) {
                    $q->where('user_id', auth('sanctum')->id());
                }
            ]);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->has('currency')) {
            $query->where('currency', $request->currency);
        }

        $ads = $query->orderBy('is_featured', 'desc')->latest()->paginate(10);

        return AdResource::collection($ads);
    }

    public function show($id)
    {
        $ad = Ad::with(['user', 'category', 'mainImage', 'images', 'customFields.field'])
            ->findOrFail($id);

        $ad->increment('views');

        return new AdResource($ad);
    }

    public function store(StoreAdRequest $request)
    {
        $validated = $request->validated();
        $slug = Str::slug($request->title) . '-' . time();

        $ad = Ad::create([
            'user_id' => $request->user()->id,
            'category_id' => $request->category_id,
            'title' => $request->title,
            'slug' => $slug,
            'description' => $request->description,
            'price' => $request->price,
            'currency' => $request->input('currency', 'YER'), // Default to YER
            'is_negotiable' => $request->input('is_negotiable', false),
            'condition' => $request->input('condition', 'used'),
            'location' => $request->location,
            'status' => 'active', // Instant publishing - no review needed
            'contact_phone' => $request->has('contact_phone') ? $request->contact_phone : $request->user()->phone,
            'contact_whatsapp' => $request->input('contact_whatsapp'),
        ]);

        if ($request->has('images')) {
            foreach ($request->images as $index => $imagePath) {
                AdImage::create([
                    'ad_id' => $ad->id,
                    'image_path' => $imagePath,
                    'thumbnail_path' => 'ads/thumbnails/' . basename($imagePath), // Infer thumbnail path from convention
                    'is_main' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        }

        return new AdResource($ad->load(['user', 'category', 'mainImage', 'images']));
    }

    public function update(UpdateAdRequest $request, $id)
    {
        $ad = Ad::where('user_id', $request->user()->id)->findOrFail($id);

        $ad->update($request->validated());

        // Handle removed images
        if ($request->has('removed_images')) {
            $removedImages = $request->removed_images;
            if (is_array($removedImages)) {
                AdImage::where('ad_id', $ad->id)
                    ->whereIn('image_path', $removedImages)
                    ->delete();

                // Optional: Delete physical files if needed. 
                // For now, we keep them or rely on a scheduled cleanup job.
            }
        }

        // Handle new images
        if ($request->has('images')) {
            $currentImagesCount = AdImage::where('ad_id', $ad->id)->count();

            foreach ($request->images as $index => $imagePath) {
                // Check if image already exists for this ad
                $exists = AdImage::where('ad_id', $ad->id)
                    ->where('image_path', $imagePath)
                    ->exists();

                if (!$exists) {
                    AdImage::create([
                        'ad_id' => $ad->id,
                        'image_path' => $imagePath,
                        'thumbnail_path' => 'ads/thumbnails/' . basename($imagePath), // Infer thumbnail path
                        'is_main' => ($currentImagesCount + $index) === 0, // Set as main if it's the first ever image
                        'sort_order' => $currentImagesCount + $index,
                    ]);
                }
            }
        }

        // Ensure we always have a main image
        $hasMain = AdImage::where('ad_id', $ad->id)->where('is_main', true)->exists();
        if (!$hasMain) {
            $firstImage = AdImage::where('ad_id', $ad->id)->orderBy('sort_order')->first();
            if ($firstImage) {
                $firstImage->update(['is_main' => true]);
            }
        }

        return new AdResource($ad->load(['user', 'category', 'mainImage', 'images']));
    }

    public function destroy(Request $request, $id)
    {
        $ad = Ad::where('user_id', $request->user()->id)->findOrFail($id);
        $ad->delete();

        return response()->json(['message' => 'Ad deleted successfully']);
    }

    public function userAds(Request $request)
    {
        $query = Ad::where('user_id', $request->user()->id)
            ->with(['category', 'mainImage'])
            ->latest()
            ->withCount('favoritedBy as likes_count');

        if (auth('sanctum')->check()) {
            $query->withExists([
                'favoritedBy as is_liked' => function ($q) {
                    $q->where('user_id', auth('sanctum')->id());
                }
            ]);
        }

        $ads = $query->paginate(20);

        return AdResource::collection($ads);
    }

    public function uploadImage(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|max:10240', // 10MB max
            ]);

            if ($request->file('image')) {
                $file = $request->file('image');

                if (!$file->isValid()) {
                    return response()->json(['message' => 'File is not valid: ' . $file->getErrorMessage()], 400);
                }

                // Generate a unique filename
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = 'ads/' . $filename;
                $thumbnailPath = 'ads/thumbnails/' . $filename;

                // Upload original image to Supabase
                // Using stream to save memory
                $stream = fopen($file->getRealPath(), 'r+');
                Storage::disk('supabase')->put($path, $stream, 'public');
                fclose($stream);

                // Generate and upload thumbnail
                // We use Intervention Image to resize. 
                // Since v3, usage is slightly different. We'll use the manager or static facade if registered.
                // Fallback to simple file upload if image manipulation fails (or just upload same file as thumb if needed, but resizing is better)

                try {
                    // Create manager instance with desired driver (gd or imagick)
                    $manager = new \Intervention\Image\ImageManager(
                        new \Intervention\Image\Drivers\Gd\Driver()
                    );

                    $image = $manager->read($file);

                    // Resize to max width 400px, constrain aspect ratio
                    $image->scale(width: 400);

                    // Encode as w/e original format was, or jpeg/webp
                    $encoded = $image->toJpeg(quality: 80);

                    Storage::disk('supabase')->put($thumbnailPath, (string) $encoded, 'public');
                } catch (\Exception $e) {
                    \Log::warning('Thumbnail generation failed: ' . $e->getMessage());
                    // Fallback: use original image as thumbnail to avoid breaking UI
                    $streamOriginal = fopen($file->getRealPath(), 'r+');
                    Storage::disk('supabase')->put($thumbnailPath, $streamOriginal, 'public');
                    fclose($streamOriginal);
                }

                // Return paths to be saved in AdImage model (or temporary usage)
                // Note: The AdController::store method expects 'images' array with paths.
                // We return the path relative to the bucket root.

                // Construct Public URL for immediate frontend preview
                $url = Storage::disk('supabase')->url($path);

                return response()->json([
                    'path' => $path,
                    'thumbnail_path' => $thumbnailPath,
                    'url' => $url
                ]);
            }

            return response()->json(['message' => 'No image file uploaded'], 400);

        } catch (\Exception $e) {
            \Log::error('Upload exception', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
}
