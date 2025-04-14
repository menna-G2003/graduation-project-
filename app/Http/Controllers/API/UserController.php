<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get the authenticated user's profile.
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        $user->listings_count = $user->getListingsCountAttribute();
        $user->sales_count = $user->getSalesCountAttribute();
        
        return response()->json([
            'data' => $user->load('role')
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'id_card_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'wallet_number' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update profile data
        $userData = $request->only(['name', 'phone', 'wallet_number']);

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            // Delete old image if exists
            if ($user->profile_image && Storage::exists('public/' . $user->profile_image)) {
                Storage::delete('public/' . $user->profile_image);
            }

            // Store new image
            $path = $request->file('profile_image')->store('profile_images', 'public');
            $userData['profile_image'] = $path;
        }

        // Handle ID card image upload
        if ($request->hasFile('id_card_image')) {
            // Delete old image if exists
            if ($user->id_card_image && Storage::exists('public/' . $user->id_card_image)) {
                Storage::delete('public/' . $user->id_card_image);
            }

            // Store new image
            $path = $request->file('id_card_image')->store('id_cards', 'public');
            $userData['id_card_image'] = $path;
        }

        $user->update($userData);

        $user->listings_count = $user->getListingsCountAttribute();
        $user->sales_count = $user->getSalesCountAttribute();

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $user->fresh()->load('role'),
        ]);
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['message' => 'Password changed successfully']);
    }

    /**
     * Get user favorites.
     */
    public function getFavorites(Request $request)
    {
        $user = $request->user();
        $favorites = $user->favoritedListings()
            ->with(['user', 'adType', 'propertyImages'])
            ->active()
            ->paginate(15);

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
     * Get filtered apartments.
     */
    public function getFilteredApartments(Request $request)
    {
        $query = Listing::with(['user', 'adType', 'propertyImages'])
            ->active()
            ->where('property_type', 'apartment');

        // Apply filters
        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        if ($request->has('state')) {
            $query->where('state', $request->state);
        }

        if ($request->has('min_area') && $request->has('max_area')) {
            $query->whereBetween('area', [$request->min_area, $request->max_area]);
        } else {
            if ($request->has('min_area')) {
                $query->where('area', '>=', $request->min_area);
            }
            if ($request->has('max_area')) {
                $query->where('area', '<=', $request->max_area);
            }
        }

        if ($request->has('bedrooms')) {
            $query->where('bedrooms', $request->bedrooms);
        }

        if ($request->has('bathrooms')) {
            $query->where('bathrooms', $request->bathrooms);
        }

        if ($request->has('is_furnished')) {
            $query->where('is_furnished', $request->is_furnished);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSortFields = ['price', 'created_at', 'area', 'bedrooms'];
        
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $apartments = $query->paginate(15);

        return response()->json([
            'data' => $apartments,
            'meta' => [
                'total' => $apartments->total(),
                'per_page' => $apartments->perPage(),
                'current_page' => $apartments->currentPage(),
                'last_page' => $apartments->lastPage(),
            ],
        ]);
    }
} 