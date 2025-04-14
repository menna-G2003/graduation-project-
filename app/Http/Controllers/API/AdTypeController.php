<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdTypeController extends Controller
{
    /**
     * Display a listing of the ad types.
     */
    public function index()
    {
        $adTypes = AdType::all();
        return response()->json(['data' => $adTypes]);
    }

    /**
     * Store a newly created ad type.
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:ad_types',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'is_featured' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adType = AdType::create($request->all());

        return response()->json([
            'message' => 'Ad type created successfully',
            'data' => $adType,
        ], 201);
    }

    /**
     * Update the specified ad type.
     */
    public function update(Request $request, AdType $adType)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:ad_types,name,' . $adType->id,
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'duration_days' => 'sometimes|required|integer|min:1',
            'is_featured' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adType->update($request->all());

        return response()->json([
            'message' => 'Ad type updated successfully',
            'data' => $adType,
        ]);
    }

    /**
     * Remove the specified ad type.
     */
    public function destroy(AdType $adType)
    {
        // Check if this ad type is being used by any listings
        if ($adType->listings()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete ad type because it is being used by listings',
            ], 422);
        }

        $adType->delete();

        return response()->json([
            'message' => 'Ad type deleted successfully',
        ]);
    }
} 