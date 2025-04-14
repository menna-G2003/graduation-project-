<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\AdType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ListingController extends Controller
{
    /**
     * Display a listing of the listings.
     */
    public function index(Request $request)
    {
        $query = Listing::with(['user', 'adType', 'images'])
            ->where('is_approved', true)
            ->where('is_paid', true)
            ->where('is_active', true)
            ->where('expiry_date', '>=', now());

        // Apply filters
        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        if ($request->has('property_type')) {
            $query->where('property_type', $request->property_type);
        }

        if ($request->has('listing_type')) {
            $query->where('listing_type', $request->listing_type);
        }

        if ($request->has('min_price') && $request->has('max_price')) {
            $query->whereBetween('price', [$request->min_price, $request->max_price]);
        } else {
            if ($request->has('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            if ($request->has('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }
        }

        if ($request->has('bedrooms')) {
            $query->where('bedrooms', $request->bedrooms);
        }

        if ($request->has('bathrooms')) {
            $query->where('bathrooms', $request->bathrooms);
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        // Validate sort parameters
        $allowedSortFields = ['price', 'created_at', 'bedrooms', 'bathrooms', 'area'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'created_at';
        $sortOrder = in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'desc';
        
        $query->orderBy($sortBy, $sortOrder);

        // Featured listings first
        $query->orderBy('is_featured', 'desc');

        $perPage = $request->input('per_page', 10);
        $listings = $query->paginate($perPage);

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

    /**
     * Display featured listings.
     */
    public function featured()
    {
        $featuredListings = Listing::with(['user', 'adType', 'images'])
            ->where('is_featured', true)
            ->where('is_approved', true)
            ->where('is_paid', true)
            ->where('is_active', true)
            ->where('expiry_date', '>=', now())
            ->latest()
            ->take(10)
            ->get();

        return response()->json(['data' => $featuredListings]);
    }

    /**
     * Search listings.
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $searchTerm = $request->q;

        $listings = Listing::with(['user', 'adType', 'images'])
            ->where('is_approved', true)
            ->where('is_paid', true)
            ->where('is_active', true)
            ->where('expiry_date', '>=', now())
            ->where(function($query) use ($searchTerm) {
                $query->where('title', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%")
                    ->orWhere('address', 'like', "%{$searchTerm}%")
                    ->orWhere('city', 'like', "%{$searchTerm}%")
                    ->orWhere('state', 'like', "%{$searchTerm}%")
                    ->orWhere('country', 'like', "%{$searchTerm}%")
                    ->orWhere('postal_code', 'like', "%{$searchTerm}%");
            })
            ->orderBy('is_featured', 'desc')
            ->latest()
            ->paginate(10);

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

    /**
     * Store a newly created listing.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ad_type_id' => 'required|exists:ad_types,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'country' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'area' => 'required|numeric|min:0',
            'property_type' => 'required|in:apartment,house,villa,land,commercial,other',
            'listing_type' => 'required|in:rent,sale',
            'is_furnished' => 'nullable|boolean',
            'floor_number' => 'nullable|integer|min:0',
            'insurance_months' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'features.*' => 'string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:5120',
            'ownership_proof' => 'nullable|array',
            'ownership_proof.*' => 'image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get ad type to determine if payment is required and set expiry date
        $adType = AdType::findOrFail($request->ad_type_id);
        $expiryDate = Carbon::now()->addDays($adType->duration_days);
        
        // For free listings, set as already paid
        $isPaid = ($adType->price == 0);

        // Convert features array to JSON string if provided
        $features = $request->has('features') ? json_encode($request->features) : null;

        $listing = Listing::create([
            'user_id' => $request->user()->id,
            'ad_type_id' => $request->ad_type_id,
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'postal_code' => $request->postal_code,
            'country' => $request->country,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'bedrooms' => $request->bedrooms,
            'bathrooms' => $request->bathrooms,
            'area' => $request->area,
            'property_type' => $request->property_type,
            'listing_type' => $request->listing_type,
            'is_furnished' => $request->is_furnished,
            'floor_number' => $request->floor_number,
            'insurance_months' => $request->insurance_months,
            'features' => $features,
            'is_approved' => false, // Requires admin approval
            'is_active' => true,
            'is_paid' => $isPaid,
            'expiry_date' => $expiryDate,
            'is_featured' => $adType->is_featured,
        ]);

        // Handle image uploads
        if ($request->hasFile('images')) {
            $this->uploadImages($request, $listing->id);
        }

        // Handle ownership proof uploads
        if ($request->hasFile('ownership_proof')) {
            $count = 0;
            foreach ($request->file('ownership_proof') as $image) {
                $path = $image->store('listings/' . $listing->id . '/ownership', 'public');
                
                $listingImage = new ListingImage([
                    'image_path' => $path,
                    'is_primary' => false,
                    'is_ownership_proof' => true,
                    'sort_order' => $count,
                ]);
                
                $listing->images()->save($listingImage);
                $count++;
            }
        }

        return response()->json([
            'message' => 'Listing created successfully',
            'data' => $listing->load(['user', 'adType', 'images']),
            'requires_payment' => !$isPaid,
        ], 201);
    }

    /**
     * Display the specified listing.
     */
    public function show(Request $request, Listing $listing)
    {
        // If the listing is not approved, only allow the owner or admin to view it
        if (!$listing->is_approved && 
            (!$request->user() || 
            ($request->user()->id !== $listing->user_id && !$request->user()->isAdmin()))) {
            return response()->json(['message' => 'Listing not found'], 404);
        }

        // If the listing has expired, only allow the owner or admin to view it
        if ($listing->expiry_date < now() && 
            (!$request->user() || 
            ($request->user()->id !== $listing->user_id && !$request->user()->isAdmin()))) {
            return response()->json(['message' => 'Listing not found'], 404);
        }

        // Increment views
        $listing->incrementViews();

        return response()->json([
            'data' => $listing->load(['user', 'adType', 'images']),
        ]);
    }

    /**
     * Update the specified listing.
     */
    public function update(Request $request, Listing $listing)
    {
        // Ensure the user owns this listing or is an admin
        if ($request->user()->id !== $listing->user_id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'price' => 'sometimes|required|numeric|min:0',
            'address' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'state' => 'sometimes|nullable|string',
            'postal_code' => 'sometimes|nullable|string',
            'country' => 'sometimes|required|string',
            'latitude' => 'sometimes|nullable|numeric',
            'longitude' => 'sometimes|nullable|numeric',
            'bedrooms' => 'sometimes|nullable|integer|min:0',
            'bathrooms' => 'sometimes|nullable|integer|min:0',
            'area' => 'sometimes|required|numeric|min:0',
            'property_type' => 'sometimes|required|in:apartment,house,villa,land,commercial,other',
            'listing_type' => 'sometimes|required|in:rent,sale',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $listing->update($request->only([
            'title', 'description', 'price', 'address', 'city', 'state', 
            'postal_code', 'country', 'latitude', 'longitude', 'bedrooms', 
            'bathrooms', 'area', 'property_type', 'listing_type'
        ]));

        return response()->json([
            'message' => 'Listing updated successfully',
            'data' => $listing->fresh()->load(['user', 'adType', 'images']),
        ]);
    }

    /**
     * Remove the specified listing.
     */
    public function destroy(Request $request, Listing $listing)
    {
        // Ensure the user owns this listing or is an admin
        if ($request->user()->id !== $listing->user_id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete associated images from storage
        foreach ($listing->images as $image) {
            if (Storage::exists('public/' . $image->image_path)) {
                Storage::delete('public/' . $image->image_path);
            }
        }

        $listing->delete();

        return response()->json(['message' => 'Listing deleted successfully']);
    }

    /**
     * Upload images for a listing.
     */
    public function uploadImages(Request $request, $listingId)
    {
        $listing = Listing::findOrFail($listingId);

        // Ensure the user owns this listing or is an admin
        if ($request->user()->id !== $listing->user_id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $uploadedImages = [];

        if ($request->hasFile('images')) {
            $count = 0;
            foreach ($request->file('images') as $image) {
                $path = $image->store('listings/' . $listing->id, 'public');
                
                // Make the first image the primary image if no primary image exists
                $isPrimary = ($count === 0 && $listing->images()->where('is_primary', true)->count() === 0);
                
                $listingImage = new ListingImage([
                    'image_path' => $path,
                    'is_primary' => $isPrimary,
                    'sort_order' => $count,
                ]);
                
                $listing->images()->save($listingImage);
                $uploadedImages[] = $listingImage;
                $count++;
            }
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'data' => $uploadedImages,
        ]);
    }

    /**
     * Delete an image from a listing.
     */
    public function deleteImage(Request $request, Listing $listing, ListingImage $image)
    {
        // Ensure the user owns this listing or is an admin
        if ($request->user()->id !== $listing->user_id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Make sure the image belongs to this listing
        if ($image->listing_id !== $listing->id) {
            return response()->json(['message' => 'Image not found for this listing'], 404);
        }

        // Delete the image from storage
        if (Storage::exists('public/' . $image->image_path)) {
            Storage::delete('public/' . $image->image_path);
        }

        // If this was the primary image, set another image as primary
        if ($image->is_primary) {
            $nextImage = $listing->images()
                ->where('id', '!=', $image->id)
                ->orderBy('sort_order')
                ->first();
                
            if ($nextImage) {
                $nextImage->update(['is_primary' => true]);
            }
        }

        $image->delete();

        return response()->json(['message' => 'Image deleted successfully']);
    }

    /**
     * Set an image as the primary image for a listing.
     */
    public function setPrimaryImage(Request $request, Listing $listing, ListingImage $image)
    {
        // Ensure the user owns this listing or is an admin
        if ($request->user()->id !== $listing->user_id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Make sure the image belongs to this listing
        if ($image->listing_id !== $listing->id) {
            return response()->json(['message' => 'Image not found for this listing'], 404);
        }

        // Set all images to not primary
        $listing->images()->update(['is_primary' => false]);

        // Set this image as primary
        $image->update(['is_primary' => true]);

        return response()->json([
            'message' => 'Primary image set successfully',
            'data' => $image,
        ]);
    }

    /**
     * Get the authenticated user's listings.
     */
    public function getMyListings(Request $request)
    {
        $query = Listing::with(['adType', 'propertyImages'])
            ->where('user_id', $request->user()->id);

        // Apply filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->is_approved);
        }

        if ($request->has('is_paid')) {
            $query->where('is_paid', $request->is_paid);
        }

        $listings = $query->latest()->paginate(10);

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

    /**
     * Get location stats (counts of listings by city/state).
     */
    public function locationStats()
    {
        $cities = Listing::where('is_active', true)
            ->where('is_approved', true)
            ->where('is_paid', true)
            ->where('expiry_date', '>=', now())
            ->select('city', DB::raw('count(*) as count'))
            ->groupBy('city')
            ->orderBy('count', 'desc')
            ->get();

        $states = Listing::where('is_active', true)
            ->where('is_approved', true)
            ->where('is_paid', true)
            ->where('expiry_date', '>=', now())
            ->select('state', DB::raw('count(*) as count'))
            ->groupBy('state')
            ->orderBy('count', 'desc')
            ->get();

        return response()->json([
            'cities' => $cities,
            'states' => $states,
        ]);
    }

    /**
     * Get property type stats (counts of listings by property type).
     */
    public function propertyTypeStats()
    {
        $propertyTypes = Listing::where('is_active', true)
            ->where('is_approved', true)
            ->where('is_paid', true)
            ->where('expiry_date', '>=', now())
            ->select('property_type', DB::raw('count(*) as count'))
            ->groupBy('property_type')
            ->orderBy('count', 'desc')
            ->get();

        return response()->json([
            'property_types' => $propertyTypes,
        ]);
    }
} 