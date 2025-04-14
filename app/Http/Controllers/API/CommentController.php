<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * Display a listing of the comments for a listing.
     */
    public function index(Listing $listing)
    {
        $comments = $listing->comments()
            ->with('user')
            ->where('is_approved', true)
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => $comments,
            'meta' => [
                'total' => $comments->total(),
                'per_page' => $comments->perPage(),
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created comment.
     */
    public function store(Request $request, Listing $listing)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comment = new Comment([
            'user_id' => $request->user()->id,
            'listing_id' => $listing->id,
            'content' => $request->content,
            'is_approved' => true, // Auto-approve for now, can be changed to require approval
        ]);

        $listing->comments()->save($comment);

        return response()->json([
            'message' => 'Comment added successfully',
            'data' => $comment->load('user'),
        ], 201);
    }

    /**
     * Display the specified comment.
     */
    public function show(Listing $listing, Comment $comment)
    {
        // Ensure the comment belongs to the listing
        if ($comment->listing_id !== $listing->id) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        return response()->json([
            'data' => $comment->load('user'),
        ]);
    }

    /**
     * Update the specified comment.
     */
    public function update(Request $request, Listing $listing, Comment $comment)
    {
        // Ensure the comment belongs to the listing
        if ($comment->listing_id !== $listing->id) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        // Ensure the user owns this comment or is an admin
        if ($comment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comment->update([
            'content' => $request->content,
        ]);

        return response()->json([
            'message' => 'Comment updated successfully',
            'data' => $comment->fresh()->load('user'),
        ]);
    }

    /**
     * Remove the specified comment.
     */
    public function destroy(Request $request, Listing $listing, Comment $comment)
    {
        // Ensure the comment belongs to the listing
        if ($comment->listing_id !== $listing->id) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        // Ensure the user owns this comment or is an admin
        if ($comment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }

    /**
     * Get all comments for listings owned by the authenticated user.
     */
    public function myListingsComments(Request $request)
    {
        $comments = Comment::whereHas('listing', function($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with(['user', 'listing'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => $comments,
            'meta' => [
                'total' => $comments->total(),
                'per_page' => $comments->perPage(),
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
            ],
        ]);
    }
} 