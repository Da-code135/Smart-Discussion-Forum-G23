<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use Illuminate\Http\Request;

class UserSearchController extends Controller
{
    /**
     * Search topics accessible to the authenticated user.
     *
     * POST /api/v1/search/topics
     *
     * Accepts:
     *   - q: search query (required, min 2 chars)
     *   - category_id: filter by category (optional)
     *   - post_type: filter by discussion/question (optional)
     *   - per_page: results per page (optional, default 20, max 50)
     *
     * Scoped to user's accessibleGroupIds().
     */
    public function searchTopics(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'q' => 'required|string|min:2|max:255',
            'category_id' => 'sometimes|integer|exists:topic_categories,id',
            'post_type' => 'sometimes|in:discussion,question',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = Topic::active()
            ->with('creator')
            ->withCount('posts')
            ->latest();

        // Group isolation (SysAdmin sees all)
        if (! $user->isSystemAdmin()) {
            $query->whereIn('group_id', $user->accessibleGroupIds());
        }

        // Search by title and description
        $searchTerm = $validated['q'];
        $query->where(function ($q) use ($searchTerm) {
            $q->where('title', 'like', "%{$searchTerm}%")
                ->orWhere('description', 'like', "%{$searchTerm}%");
        });

        // Optional filters
        if (isset($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (isset($validated['post_type'])) {
            $query->where('post_type', $validated['post_type']);
        }

        $perPage = $validated['per_page'] ?? 20;
        $topics = $query->paginate($perPage);

        return response()->json([
            'data' => $topics,
        ], 200);
    }
}
