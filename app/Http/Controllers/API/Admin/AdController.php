<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\Request;

class AdController extends Controller
{
    public function index(Request $request)
    {
        $query = Ad::with(['user', 'category', 'mainImage']);
        
        // Search by title or description
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
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

    public function destroy($id)
    {
        $ad = Ad::findOrFail($id);
        $ad->delete();

        return response()->json(['message' => 'Ad deleted successfully']);
    }
}
