<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MapSearchController extends Controller
{
    /**
     * Search for properties within a specific area on the map.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|numeric|min:0.1|max:50', // radius in kilometers
            'property_type' => 'nullable|string|in:apartment,house,villa,office,land,commercial',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'min_area' => 'nullable|numeric|min:0',
            'max_area' => 'nullable|numeric|min:0',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'sort_by' => 'nullable|string|in:price_asc,price_desc,date_asc,date_desc,area_asc,area_desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Build a query for active listings with geographic filtering
        $query = Listing::select('*')
            ->selectRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance',
                [$request->latitude, $request->longitude, $request->latitude]
            )
            ->having('distance', '<=', $request->radius)
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        // Apply additional filters if provided
        if ($request->filled('property_type')) {
            $query->where('property_type', $request->property_type);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->filled('min_area')) {
            $query->where('area', '>=', $request->min_area);
        }

        if ($request->filled('max_area')) {
            $query->where('area', '<=', $request->max_area);
        }

        if ($request->filled('bedrooms')) {
            $query->where('bedrooms', '>=', $request->bedrooms);
        }

        if ($request->filled('bathrooms')) {
            $query->where('bathrooms', '>=', $request->bathrooms);
        }

        // Apply sorting
        switch ($request->input('sort_by', 'distance')) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'date_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'date_desc':
                $query->orderBy('created_at', 'desc');
                break;
            case 'area_asc':
                $query->orderBy('area', 'asc');
                break;
            case 'area_desc':
                $query->orderBy('area', 'desc');
                break;
            default:
                $query->orderBy('distance', 'asc'); // Default sort by distance
                break;
        }

        // Add secondary sorting by distance if sorting by something else
        if ($request->input('sort_by') && $request->input('sort_by') !== 'distance') {
            $query->orderBy('distance', 'asc');
        }

        // Paginate results
        $perPage = $request->input('per_page', 15);
        $listings = $query->paginate($perPage);

        // Transform results to include distance in kilometers
        $listings->getCollection()->transform(function ($listing) {
            $listing->distance_km = round($listing->distance, 2);
            return $listing;
        });

        return response()->json([
            'data' => $listings->items(),
            'meta' => [
                'total' => $listings->total(),
                'per_page' => $listings->perPage(),
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
            ],
        ]);
    }

    /**
     * Get property clusters for map display.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClusters(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bounds' => 'required|array',
            'bounds.north' => 'required|numeric|between:-90,90',
            'bounds.south' => 'required|numeric|between:-90,90',
            'bounds.east' => 'required|numeric|between:-180,180',
            'bounds.west' => 'required|numeric|between:-180,180',
            'zoom_level' => 'required|integer|min:1|max:20',
            'property_type' => 'nullable|string|in:apartment,house,villa,office,land,commercial',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bounds = $request->bounds;

        // Query for active listings within the map bounds
        $query = Listing::where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '>=', $bounds['south'])
            ->where('latitude', '<=', $bounds['north'])
            ->where('longitude', '>=', $bounds['west'])
            ->where('longitude', '<=', $bounds['east']);

        // Apply additional filters if provided
        if ($request->filled('property_type')) {
            $query->where('property_type', $request->property_type);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Select only necessary fields for map display
        $listings = $query->select('id', 'title', 'price', 'property_type', 'latitude', 'longitude', 'thumbnail_url')
            ->limit(500) // Limit for performance
            ->get();

        // For higher zoom levels, return individual properties
        if ($request->zoom_level >= 15) {
            return response()->json([
                'type' => 'individual',
                'data' => $listings,
                'count' => $listings->count(),
            ]);
        }

        // For lower zoom levels, create price clusters
        // This is a simple clustering approach - in a real app, you might use a more sophisticated algorithm
        $clusters = [];
        $gridSize = 0.01; // Grid size for clustering - adjust based on zoom level

        // Adjust grid size based on zoom level
        if ($request->zoom_level < 10) {
            $gridSize = 0.1;
        } elseif ($request->zoom_level < 13) {
            $gridSize = 0.05;
        }

        foreach ($listings as $listing) {
            // Create a grid cell key based on coordinates
            $latGrid = floor($listing->latitude / $gridSize) * $gridSize;
            $lngGrid = floor($listing->longitude / $gridSize) * $gridSize;
            $key = $latGrid . ',' . $lngGrid;

            if (!isset($clusters[$key])) {
                $clusters[$key] = [
                    'latitude' => $latGrid + ($gridSize / 2), // Center of grid cell
                    'longitude' => $lngGrid + ($gridSize / 2),
                    'count' => 0,
                    'min_price' => PHP_INT_MAX,
                    'max_price' => 0,
                    'property_types' => [],
                ];
            }

            $clusters[$key]['count']++;
            $clusters[$key]['min_price'] = min($clusters[$key]['min_price'], $listing->price);
            $clusters[$key]['max_price'] = max($clusters[$key]['max_price'], $listing->price);
            
            if (!in_array($listing->property_type, $clusters[$key]['property_types'])) {
                $clusters[$key]['property_types'][] = $listing->property_type;
            }
        }

        return response()->json([
            'type' => 'cluster',
            'data' => array_values($clusters),
            'count' => count($clusters),
        ]);
    }
} 