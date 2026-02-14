<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\Request;

class AdController extends Controller
{
    public function index(Request $request)
    {
        $query = Ad::with(['user', 'category', 'mainImage'])->withCount('favoritedBy as likes_count');

        // Search by title or description
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by user
        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $ads = $query->paginate($request->get('per_page', 20));

        return \App\Http\Resources\AdResource::collection($ads);
    }

    public function show($id)
    {
        $ad = Ad::with(['user', 'category', 'images', 'customFields.field'])
            ->findOrFail($id);

        return new \App\Http\Resources\AdResource($ad);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,rejected,pending,sold,expired',
            'reject_reason' => 'required_if:status,rejected|string|nullable',
        ]);

        $ad = Ad::findOrFail($id);
        $ad->update([
            'status' => $request->status,
            'reject_reason' => $request->reject_reason,
        ]);

        return response()->json(['message' => 'Ad status updated', 'data' => $ad]);
    }

    public function activateFeatured(Request $request, $id)
    {
        $request->validate([
            'payment_notes' => 'nullable|string|max:1000',
            'duration_days' => 'nullable|integer|min:1|max:30',
        ]);

        $ad = Ad::findOrFail($id);

        // Calculate featured_until date (default 7 days)
        $durationDays = $request->get('duration_days', 7);
        $featuredUntil = now()->addDays($durationDays);

        $ad->update([
            'is_featured' => true,
            'featured_until' => $featuredUntil,
        ]);

        // Log payment verification in ad metadata if needed
        // You could add a payment_notes field to ads table or use a separate payments table

        return response()->json([
            'message' => 'Featured ad activated successfully',
            'data' => [
                'id' => $ad->id,
                'is_featured' => $ad->is_featured,
                'featured_until' => $ad->featured_until,
                'duration_days' => $durationDays,
            ]
        ]);
    }

    public function deactivateFeatured($id)
    {
        $ad = Ad::findOrFail($id);

        $ad->update([
            'is_featured' => false,
            'featured_until' => null,
        ]);

        return response()->json([
            'message' => 'Featured ad deactivated successfully',
            'data' => $ad
        ]);
    }

    public function destroy($id)
    {
        $ad = Ad::findOrFail($id);
        $ad->forceDelete();

        return response()->json(['message' => 'Ad deleted successfully']);
    }
}
