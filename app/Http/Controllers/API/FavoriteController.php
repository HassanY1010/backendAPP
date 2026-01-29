<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Request $request, $ad_id)
    {
        $user = $request->user();

        $favorite = Favorite::where('user_id', $user->id)
            ->where('ad_id', $ad_id)
            ->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json(['message' => 'Removed from favorites', 'is_favorited' => false]);
        } else {
            Favorite::create([
                'user_id' => $user->id,
                'ad_id' => $ad_id,
                'created_at' => now(),
            ]);
            return response()->json(['message' => 'Added to favorites', 'is_favorited' => true]);
        }
    }

    public function index(Request $request)
    {
        $favorites = $request->user()->favorites()
            ->with(['mainImage', 'images']) // detailed relationship
            ->paginate(20);

        return \App\Http\Resources\AdResource::collection($favorites);
    }
}
