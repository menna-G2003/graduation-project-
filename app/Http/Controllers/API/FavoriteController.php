<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Listing;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * Display a listing of the user's favorite listings.
     */
    public function index()
    {
        $favorites = auth()->user()->favoritedListings()
            ->with(['adType', 'images'])
            ->paginate(10);

        return response()->json([
            'data' => $favorites,
            'meta' => [
                'total' => $favorites->total(),
                'per_page' => $favorites->perPage(),
                'current_page' => $favorites->currentPage(),
                'last_page' => $favorites->lastPage(),
            ],
        ]);
    }

    /**
     * Toggle favorite status for a listing.
     */
    public function toggle(Listing $listing)
    {
        // Ensure the listing is active, approved, and not expired
        if (!$listing->is_active || !$listing->is_approved || $listing->expiry_date < now()) {
            return response()->json(['message' => 'Listing not found'], 404);
        }

        $user = auth()->user();
        $favorite = Favorite::where('user_id', $user->id)
            ->where('listing_id', $listing->id)
            ->first();

        if ($favorite) {
            // If already favorited, remove it
            $favorite->delete();
            return response()->json(['message' => 'Listing removed from favorites']);
        } else {
            // If not favorited, add it
            Favorite::create([
                'user_id' => $user->id,
                'listing_id' => $listing->id,
            ]);
            return response()->json(['message' => 'Listing added to favorites']);
        }
    }

    /**
     * Remove a listing from favorites.
     */
    public function remove(Listing $listing)
    {
        $user = auth()->user();
        $favorite = Favorite::where('user_id', $user->id)
            ->where('listing_id', $listing->id)
            ->first();

        if (!$favorite) {
            return response()->json(['message' => 'Listing not found in favorites'], 404);
        }

        $favorite->delete();
        return response()->json(['message' => 'Listing removed from favorites']);
    }
} 