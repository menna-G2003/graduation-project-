<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ListingComparison;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ListingComparisonController extends Controller
{
    /**
     * Display a listing of the user's comparisons.
     */
    public function index(Request $request)
    {
        $comparisons = ListingComparison::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'data' => $comparisons,
            'meta' => [
                'total' => $comparisons->total(),
                'per_page' => $comparisons->perPage(),
                'current_page' => $comparisons->currentPage(),
                'last_page' => $comparisons->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created comparison.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'listings' => 'required|array|min:2|max:5',
            'listings.*' => 'required|integer|exists:listings,id',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ensure all listings exist and are active
        $listingIds = $request->listings;
        $validListings = Listing::whereIn('id', $listingIds)
            ->active()
            ->pluck('id')
            ->toArray();

        if (count($validListings) !== count($listingIds)) {
            return response()->json([
                'message' => 'One or more listings are invalid or inactive',
            ], 422);
        }

        $comparison = ListingComparison::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'listings' => $listingIds,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Comparison created successfully',
            'data' => $comparison,
        ], 201);
    }

    /**
     * Display the specified comparison.
     */
    public function show(Request $request, ListingComparison $comparison)
    {
        // Ensure the user owns this comparison
        if ($comparison->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get the actual listings data
        $listings = $comparison->getListingsCollection();

        return response()->json([
            'data' => [
                'comparison' => $comparison,
                'listings' => $listings,
            ],
        ]);
    }

    /**
     * Update the specified comparison.
     */
    public function update(Request $request, ListingComparison $comparison)
    {
        // Ensure the user owns this comparison
        if ($comparison->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'listings' => 'sometimes|required|array|min:2|max:5',
            'listings.*' => 'required|integer|exists:listings,id',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // If listings are being updated, ensure they all exist and are active
        if ($request->has('listings')) {
            $listingIds = $request->listings;
            $validListings = Listing::whereIn('id', $listingIds)
                ->active()
                ->pluck('id')
                ->toArray();

            if (count($validListings) !== count($listingIds)) {
                return response()->json([
                    'message' => 'One or more listings are invalid or inactive',
                ], 422);
            }
        }

        $comparison->update($request->only(['name', 'listings', 'notes']));

        return response()->json([
            'message' => 'Comparison updated successfully',
            'data' => $comparison->fresh(),
        ]);
    }

    /**
     * Remove the specified comparison.
     */
    public function destroy(Request $request, ListingComparison $comparison)
    {
        // Ensure the user owns this comparison
        if ($comparison->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comparison->delete();

        return response()->json(['message' => 'Comparison deleted successfully']);
    }

    /**
     * Add a listing to an existing comparison.
     */
    public function addListing(Request $request, ListingComparison $comparison)
    {
        // Ensure the user owns this comparison
        if ($comparison->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'listing_id' => 'required|integer|exists:listings,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ensure listing exists and is active
        $listing = Listing::where('id', $request->listing_id)
            ->active()
            ->first();

        if (!$listing) {
            return response()->json([
                'message' => 'Listing is invalid or inactive',
            ], 422);
        }

        // Get current listings
        $listings = $comparison->listings ?? [];

        // Check if listing is already in the comparison
        if (in_array($request->listing_id, $listings)) {
            return response()->json([
                'message' => 'Listing is already in this comparison',
            ], 422);
        }

        // Check if max number of listings reached
        if (count($listings) >= 5) {
            return response()->json([
                'message' => 'Maximum number of listings (5) reached for this comparison',
            ], 422);
        }

        // Add the new listing
        $listings[] = $request->listing_id;
        $comparison->update(['listings' => $listings]);

        return response()->json([
            'message' => 'Listing added to comparison successfully',
            'data' => $comparison->fresh(),
        ]);
    }

    /**
     * Remove a listing from an existing comparison.
     */
    public function removeListing(Request $request, ListingComparison $comparison)
    {
        // Ensure the user owns this comparison
        if ($comparison->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'listing_id' => 'required|integer|exists:listings,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get current listings
        $listings = $comparison->listings ?? [];

        // Check if listing is in the comparison
        if (!in_array($request->listing_id, $listings)) {
            return response()->json([
                'message' => 'Listing is not in this comparison',
            ], 422);
        }

        // Check if this would leave too few listings
        if (count($listings) <= 2) {
            return response()->json([
                'message' => 'Comparison must have at least 2 listings',
            ], 422);
        }

        // Remove the listing
        $listings = array_values(array_diff($listings, [$request->listing_id]));
        $comparison->update(['listings' => $listings]);

        return response()->json([
            'message' => 'Listing removed from comparison successfully',
            'data' => $comparison->fresh(),
        ]);
    }
} 