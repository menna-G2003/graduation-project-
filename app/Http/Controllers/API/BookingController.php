<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    /**
     * Display a listing of the bookings for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Booking::where('user_id', $user->id)->with(['listing.propertyImages']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->latest()->paginate($request->input('per_page', 10));

        return response()->json([
            'data' => $bookings,
            'meta' => [
                'total' => $bookings->total(),
                'per_page' => $bookings->perPage(),
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created booking.
     */
    public function store(Request $request, Listing $listing)
    {
        // Validate the listing is available
        if (!$listing->is_active || !$listing->is_approved || !$listing->is_paid || $listing->expiry_date < now()) {
            return response()->json(['message' => 'This listing is not available for booking'], 422);
        }

        $validator = Validator::make($request->all(), [
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Calculate total price based on listing price
        // This is a simple calculation, could be more complex based on requirements
        $checkIn = new \DateTime($request->check_in);
        $checkOut = new \DateTime($request->check_out);
        $interval = $checkIn->diff($checkOut);
        $days = $interval->days > 0 ? $interval->days : 1; // Minimum 1 day
        $totalPrice = $listing->price * $days;

        $booking = Booking::create([
            'user_id' => Auth::id(),
            'listing_id' => $listing->id,
            'booking_date' => now(),
            'check_in' => $request->check_in,
            'check_out' => $request->check_out,
            'total_price' => $totalPrice,
            'status' => 'pending',
            'notes' => $request->notes,
            'is_paid' => false,
        ]);

        return response()->json([
            'message' => 'Booking created successfully',
            'data' => $booking->load('listing'),
        ], 201);
    }

    /**
     * Display the specified booking.
     */
    public function show(Booking $booking)
    {
        $user = Auth::user();
        // Check if user is authorized to view this booking
        if ($user->id !== $booking->user_id && $user->id !== $booking->listing->user_id && $user->role->name !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $booking->load(['listing.propertyImages', 'user']),
        ]);
    }

    /**
     * Update booking status (for property owners or admins).
     */
    public function updateStatus(Request $request, Booking $booking)
    {
        $user = Auth::user();
        // Check if user is authorized to update this booking status
        if ($user->id !== $booking->listing->user_id && $user->role->name !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,cancelled,completed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Booking status updated successfully',
            'data' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'updated_at' => $booking->updated_at,
            ],
        ]);
    }

    /**
     * Cancel booking (for booking owner).
     */
    public function cancel(Booking $booking)
    {
        // Check if user is authorized to cancel this booking
        if (Auth::id() !== $booking->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Can only cancel if status is pending or confirmed
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'This booking cannot be cancelled'], 422);
        }

        $booking->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Booking cancelled successfully',
            'data' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'updated_at' => $booking->updated_at,
            ],
        ]);
    }

    /**
     * Get bookings for the authenticated user's properties.
     */
    public function getPropertyBookings(Request $request)
    {
        $query = Booking::with(['user', 'listing'])
            ->whereHas('listing', function ($query) {
                $query->where('user_id', Auth::id());
            });

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('property_id')) {
            $query->where('listing_id', $request->property_id);
        }

        $bookings = $query->latest()->paginate($request->input('per_page', 10));

        return response()->json([
            'data' => $bookings,
            'meta' => [
                'total' => $bookings->total(),
                'per_page' => $bookings->perPage(),
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
            ],
        ]);
    }
}
