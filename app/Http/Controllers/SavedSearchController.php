<?php

namespace App\Http\Controllers;

use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SavedSearchController extends Controller
{
    /**
     * Display a listing of the saved searches.
     */
    public function index()
    {
        $savedSearches = Auth::user()->savedSearches;
        return response()->json([
            'status' => 'success',
            'data' => $savedSearches
        ]);
    }

    /**
     * Store a newly created saved search.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'search_params' => 'required|array',
            'is_notifiable' => 'boolean',
            'notification_frequency' => 'nullable|required_if:is_notifiable,true|in:daily,weekly,monthly',
        ]);

        $validated['user_id'] = Auth::id();
        $savedSearch = SavedSearch::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Search saved successfully',
            'data' => $savedSearch
        ], 201);
    }

    /**
     * Display the specified saved search.
     */
    public function show(SavedSearch $savedSearch)
    {
        $this->authorize('view', $savedSearch);

        return response()->json([
            'status' => 'success',
            'data' => $savedSearch
        ]);
    }

    /**
     * Update the specified saved search.
     */
    public function update(Request $request, SavedSearch $savedSearch)
    {
        $this->authorize('update', $savedSearch);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'search_params' => 'array',
            'is_notifiable' => 'boolean',
            'notification_frequency' => 'nullable|required_if:is_notifiable,true|in:daily,weekly,monthly',
        ]);

        $savedSearch->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Search updated successfully',
            'data' => $savedSearch
        ]);
    }

    /**
     * Remove the specified saved search.
     */
    public function destroy(SavedSearch $savedSearch)
    {
        $this->authorize('delete', $savedSearch);

        $savedSearch->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Search deleted successfully'
        ]);
    }
} 