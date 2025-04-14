<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SavedSearchController extends Controller
{
    /**
     * Display a listing of the saved searches.
     */
    public function index(Request $request)
    {
        $savedSearches = SavedSearch::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'data' => $savedSearches,
            'meta' => [
                'total' => $savedSearches->total(),
                'per_page' => $savedSearches->perPage(),
                'current_page' => $savedSearches->currentPage(),
                'last_page' => $savedSearches->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created saved search.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'criteria' => 'required|array',
            'notification_frequency' => 'nullable|in:daily,weekly,never',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $savedSearch = SavedSearch::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'criteria' => $request->criteria,
            'notification_frequency' => $request->notification_frequency,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Search saved successfully',
            'data' => $savedSearch,
        ], 201);
    }

    /**
     * Display the specified saved search.
     */
    public function show(Request $request, SavedSearch $savedSearch)
    {
        // Ensure the user owns this saved search
        if ($savedSearch->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $savedSearch,
        ]);
    }

    /**
     * Update the specified saved search.
     */
    public function update(Request $request, SavedSearch $savedSearch)
    {
        // Ensure the user owns this saved search
        if ($savedSearch->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'criteria' => 'sometimes|required|array',
            'notification_frequency' => 'nullable|in:daily,weekly,never',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $savedSearch->update($request->only([
            'name', 'criteria', 'notification_frequency', 'is_active'
        ]));

        return response()->json([
            'message' => 'Saved search updated successfully',
            'data' => $savedSearch->fresh(),
        ]);
    }

    /**
     * Remove the specified saved search.
     */
    public function destroy(Request $request, SavedSearch $savedSearch)
    {
        // Ensure the user owns this saved search
        if ($savedSearch->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $savedSearch->delete();

        return response()->json(['message' => 'Saved search deleted successfully']);
    }

    /**
     * Execute a saved search and return matching listings.
     */
    public function execute(Request $request, SavedSearch $savedSearch)
    {
        // Ensure the user owns this saved search
        if ($savedSearch->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $criteria = $savedSearch->criteria;
        $query = Listing::with(['user', 'adType', 'propertyImages'])
            ->active();

        // Apply filters from the saved criteria
        if (isset($criteria['city'])) {
            $query->where('city', $criteria['city']);
        }

        if (isset($criteria['state'])) {
            $query->where('state', $criteria['state']);
        }

        if (isset($criteria['property_type'])) {
            $query->where('property_type', $criteria['property_type']);
        }

        if (isset($criteria['listing_type'])) {
            $query->where('listing_type', $criteria['listing_type']);
        }

        if (isset($criteria['min_price']) && isset($criteria['max_price'])) {
            $query->whereBetween('price', [$criteria['min_price'], $criteria['max_price']]);
        } else {
            if (isset($criteria['min_price'])) {
                $query->where('price', '>=', $criteria['min_price']);
            }
            if (isset($criteria['max_price'])) {
                $query->where('price', '<=', $criteria['max_price']);
            }
        }

        if (isset($criteria['bedrooms'])) {
            $query->where('bedrooms', $criteria['bedrooms']);
        }

        if (isset($criteria['bathrooms'])) {
            $query->where('bathrooms', $criteria['bathrooms']);
        }

        if (isset($criteria['min_area']) && isset($criteria['max_area'])) {
            $query->whereBetween('area', [$criteria['min_area'], $criteria['max_area']]);
        } else {
            if (isset($criteria['min_area'])) {
                $query->where('area', '>=', $criteria['min_area']);
            }
            if (isset($criteria['max_area'])) {
                $query->where('area', '<=', $criteria['max_area']);
            }
        }

        if (isset($criteria['is_furnished'])) {
            $query->where('is_furnished', $criteria['is_furnished']);
        }

        // Apply sorting
        $sortBy = $criteria['sort_by'] ?? 'created_at';
        $sortOrder = $criteria['sort_order'] ?? 'desc';
        $allowedSortFields = ['price', 'created_at', 'area', 'bedrooms'];
        
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $listings = $query->paginate(15);

        return response()->json([
            'data' => $listings,
            'meta' => [
                'total' => $listings->total(),
                'per_page' => $listings->perPage(),
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
            ],
        ]);
    }
} 