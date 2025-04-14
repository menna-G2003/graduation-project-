<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /**
     * Store a newly created report.
     */
    public function store(Request $request, Listing $listing)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|in:spam,offensive,fake,misleading,other',
            'details' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if user already reported this listing
        $existingReport = Report::where('user_id', $request->user()->id)
            ->where('listing_id', $listing->id)
            ->where('status', Report::STATUS_PENDING)
            ->first();

        if ($existingReport) {
            return response()->json([
                'message' => 'You have already reported this listing',
                'data' => $existingReport,
            ], 422);
        }

        $report = Report::create([
            'user_id' => $request->user()->id,
            'listing_id' => $listing->id,
            'reason' => $request->reason,
            'details' => $request->details,
            'status' => Report::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => 'Listing reported successfully',
            'data' => $report,
        ], 201);
    }

    /**
     * Display a listing of user's reports (Admin only).
     */
    public function index(Request $request)
    {
        // Ensure the user is an admin
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Report::with(['user', 'listing']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('listing_id')) {
            $query->where('listing_id', $request->listing_id);
        }

        $reports = $query->latest()->paginate(15);

        return response()->json([
            'data' => $reports,
            'meta' => [
                'total' => $reports->total(),
                'per_page' => $reports->perPage(),
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
            ],
        ]);
    }

    /**
     * Display the specified report (Admin only).
     */
    public function show(Request $request, Report $report)
    {
        // Ensure the user is an admin
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $report->load(['user', 'listing']),
        ]);
    }

    /**
     * Update the specified report status (Admin only).
     */
    public function update(Request $request, Report $report)
    {
        // Ensure the user is an admin
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,resolved,rejected',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $report->update([
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
        ]);

        return response()->json([
            'message' => 'Report status updated successfully',
            'data' => $report->fresh()->load(['user', 'listing']),
        ]);
    }
} 