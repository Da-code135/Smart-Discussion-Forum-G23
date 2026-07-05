<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use App\Models\TopicCategory;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * C1: List all categories for the authenticated user's group.
     *
     * GET /api/v1/categories
     *
     * Scoped to the user's group via scopeForGroup().
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $categories = TopicCategory::forGroup($user->group_id)
            ->orderBy('category_name')
            ->get()
            ->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'group_id' => $cat->group_id,
                    'category_name' => $cat->category_name,
                    'keyword_hints' => $cat->keyword_hints,
                    'posts_count' => $cat->posts()->count(),
                    'created_at' => $cat->created_at,
                    'updated_at' => $cat->updated_at,
                ];
            });

        return response()->json([
            'data' => $categories,
        ], 200);
    }

    /**
     * C2: List all topics that have posts classified under a given category.
     *
     * GET /api/v1/categories/{categoryId}/topics
     *
     * Only returns topics from the user's group.
     * Group isolation: category must belong to user's group.
     */
    public function topics(Request $request, int $categoryId)
    {
        $user = $request->user();

        $category = TopicCategory::forGroup($user->group_id)
            ->findOrFail($categoryId);

        // Get distinct topic IDs from posts classified under this category
        $topicIds = $category->posts()
            ->whereHas('topic', function ($q) use ($user) {
                $q->forGroup($user->group_id);
            })
            ->whereHas('topic', function ($q) {
                $q->active();
            })
            ->pluck('topic_id')
            ->unique()
            ->values()
            ->toArray();

        if (empty($topicIds)) {
            return response()->json([
                'data' => [],
            ], 200);
        }

        $topics = Topic::whereIn('id', $topicIds)
            ->forGroup($user->group_id)
            ->active()
            ->with('creator')
            ->withCount('posts')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $topics,
        ], 200);
    }

    /**
     * C3: Create a new category (Admin only).
     *
     * POST /api/v1/admin/categories
     *
     * Admin middleware applied at route level.
     * Category is scoped to a specific group.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'group_id' => 'required|integer|exists:groups,id',
            'category_name' => 'required|string|max:100',
            'keyword_hints' => 'nullable|string|max:5000',
        ]);

        // Check unique constraint manually for a clearer error message
        $existing = TopicCategory::where('group_id', $validated['group_id'])
            ->where('category_name', $validated['category_name'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A category with this name already exists in this group.',
            ], 409);
        }

        // Group admins can only create categories for groups they administer
        if ($user->isGroupAdmin() && !$user->canAdminGroup(\App\Models\Group::findOrFail($validated['group_id']))) {
            return response()->json([
                'message' => 'You can only create categories for groups you administer.',
            ], 403);
        }

        $category = TopicCategory::create([
            'group_id' => $validated['group_id'],
            'category_name' => $validated['category_name'],
            'keyword_hints' => $validated['keyword_hints'],
        ]);

        return response()->json([
            'message' => 'Category created successfully.',
            'data' => [
                'category' => [
                    'id' => $category->id,
                    'group_id' => $category->group_id,
                    'category_name' => $category->category_name,
                    'keyword_hints' => $category->keyword_hints,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                ],
            ],
        ], 201);
    }

    /**
     * C4: Update a category (Admin only).
     *
     * PUT /api/v1/admin/categories/{categoryId}
     *
     * Admin middleware applied at route level.
     */
    public function update(Request $request, int $categoryId)
    {
        $user = $request->user();

        $category = TopicCategory::findOrFail($categoryId);

        // Group admins can only update categories in groups they administer
        if ($user->isGroupAdmin() && !$user->canAdminGroup($category->group)) {
            return response()->json([
                'message' => 'You can only update categories in groups you administer.',
            ], 403);
        }

        $validated = $request->validate([
            'category_name' => 'sometimes|string|max:100',
            'keyword_hints' => 'nullable|string|max:5000',
            'group_id' => 'sometimes|integer|exists:groups,id',
        ]);

        // If name changed, check uniqueness within the (possibly new) group
        $targetGroupId = $validated['group_id'] ?? $category->group_id;
        if (
            isset($validated['category_name']) &&
            $validated['category_name'] !== $category->category_name
        ) {
            $nameExists = TopicCategory::where('group_id', $targetGroupId)
                ->where('category_name', $validated['category_name'])
                ->where('id', '!=', $category->id)
                ->exists();

            if ($nameExists) {
                return response()->json([
                    'message' => 'A category with this name already exists in this group.',
                ], 409);
            }
        }

        $category->update($validated);

        $category->refresh();

        return response()->json([
            'message' => 'Category updated successfully.',
            'data' => [
                'category' => [
                    'id' => $category->id,
                    'group_id' => $category->group_id,
                    'category_name' => $category->category_name,
                    'keyword_hints' => $category->keyword_hints,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                ],
            ],
        ], 200);
    }

    /**
     * C5: Delete a category (Admin only).
     *
     * DELETE /api/v1/admin/categories/{categoryId}
     *
     * Admin middleware applied at route level.
     * Posts with this category will have category_id set to null (SET NULL).
     */
    public function destroy(Request $request, int $categoryId)
    {
        $user = $request->user();

        $category = TopicCategory::findOrFail($categoryId);

        // Group admins can only delete categories in groups they administer
        if ($user->isGroupAdmin() && !$user->canAdminGroup($category->group)) {
            return response()->json([
                'message' => 'You can only delete categories in groups you administer.',
            ], 403);
        }

        $postsCount = $category->posts()->count();

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
            'data' => [
                'affected_posts' => $postsCount,
            ],
        ], 200);
    }
}
