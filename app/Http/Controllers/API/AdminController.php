<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * Display a listing of all users.
     */
    public function users(Request $request)
    {
        $query = User::with('role');

        // Apply filters
        if ($request->has('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(15);

        return response()->json([
            'data' => $users,
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Display a specific user.
     */
    public function showUser(User $user)
    {
        return response()->json([
            'data' => $user->load(['role', 'listings', 'payments']),
        ]);
    }

    /**
     * Toggle user activation status.
     */
    public function toggleUserActivation(Request $request, User $user)
    {
        // Don't allow deactivating your own account
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account'], 422);
        }

        $user->update([
            'is_active' => !$user->is_active,
        ]);

        $status = $user->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'message' => "User {$status} successfully",
            'data' => $user->fresh(),
        ]);
    }

    /**
     * Delete a user.
     */
    public function deleteUser(Request $request, User $user)
    {
        // Don't allow deleting your own account
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account'], 422);
        }

        // Delete user's profile image if exists
        if ($user->profile_image && Storage::exists('public/' . $user->profile_image)) {
            Storage::delete('public/' . $user->profile_image);
        }

        // Delete all user's listing images
        foreach ($user->listings as $listing) {
            foreach ($listing->images as $image) {
                if (Storage::exists('public/' . $image->image_path)) {
                    Storage::delete('public/' . $image->image_path);
                }
            }
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Display a listing of all listings.
     */
    public function listings(Request $request)
    {
        $query = Listing::with(['user', 'adType', 'images']);

        // Apply filters
        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->is_approved);
        }

        if ($request->has('is_paid')) {
            $query->where('is_paid', $request->is_paid);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->is_featured);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
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
     * Approve a listing.
     */
    public function approveListing(Listing $listing)
    {
        $listing->update([
            'is_approved' => true,
        ]);

        return response()->json([
            'message' => 'Listing approved successfully',
            'data' => $listing->fresh(),
        ]);
    }

    /**
     * Reject a listing.
     */
    public function rejectListing(Request $request, Listing $listing)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $listing->update([
            'is_approved' => false,
            'is_active' => false,
        ]);

        // In a real application, send a notification to the owner with rejection reason
        // For now, just return a message
        return response()->json([
            'message' => 'Listing rejected successfully',
            'data' => $listing->fresh(),
        ]);
    }

    /**
     * Delete a listing.
     */
    public function deleteListing(Listing $listing)
    {
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
     * Display a listing of all payments.
     */
    public function payments(Request $request)
    {
        $query = Payment::with(['user', 'listing']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('listing_id')) {
            $query->where('listing_id', $request->listing_id);
        }

        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('created_at', [$request->from_date, $request->to_date]);
        }

        $payments = $query->latest()->paginate(15);

        return response()->json([
            'data' => $payments,
            'meta' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    /**
     * Get statistics for the admin dashboard.
     */
    public function statistics()
    {
        // Total users count by role
        $userStats = User::select('role_id', DB::raw('count(*) as count'))
            ->groupBy('role_id')
            ->with('role')
            ->get();

        // Total listings count by status
        $listingStats = [
            'total' => Listing::count(),
            'active' => Listing::where('is_active', true)->count(),
            'pending_approval' => Listing::where('is_approved', false)->where('is_active', true)->count(),
            'expired' => Listing::where('expiry_date', '<', now())->count(),
            'featured' => Listing::where('is_featured', true)->count(),
        ];

        // Payment statistics
        $paymentStats = [
            'total_revenue' => Payment::where('status', 'completed')->sum('amount'),
            'recent_payments' => Payment::with(['user', 'listing'])
                ->where('status', 'completed')
                ->latest()
                ->take(5)
                ->get(),
            'payment_count' => Payment::where('status', 'completed')->count(),
        ];

        // Recent activity
        $recentListings = Listing::with(['user', 'adType'])
            ->latest()
            ->take(5)
            ->get();

        $recentUsers = User::with('role')
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'user_stats' => $userStats,
            'listing_stats' => $listingStats,
            'payment_stats' => $paymentStats,
            'recent_listings' => $recentListings,
            'recent_users' => $recentUsers,
        ]);
    }
} 