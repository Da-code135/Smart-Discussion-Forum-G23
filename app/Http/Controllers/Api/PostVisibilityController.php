<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostVisibility;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class PostVisibilityController extends Controller
{
    /**
     * P5: Exclude a user from seeing a post.
     *
     * POST /api/v1/posts/{postId}/visibility/exclude
     *
     * Only the post author can exclude users.
     * Validates that the excluded user is in the same group.
     */
    public function exclude(Request $request, int $postId)
    {
        $user = $request->user();

        $post = Post::with('topic')->findOrFail($postId);

        // Group isolation check via topic
        if ($post->topic->group_id !== $user->group_id) {
            return response()->json([
                'message' => 'You do not have access to this post.',
            ], 403);
        }

        // Only the post author can manage visibility
        if ($post->user_id !== $user->id) {
            return response()->json([
                'message' => 'Only the post author can manage visibility.',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);

        // Cannot exclude the post author
        if ((int) $validated['user_id'] === $post->user_id) {
            return response()->json([
                'message' => 'You cannot exclude yourself from your own post.',
            ], 422);
        }

        // Validate excluded user is in the same group
        if ($targetUser->group_id !== $user->group_id) {
            return response()->json([
                'message' => 'The specified user is not in your group.',
            ], 422);
        }

        // Check if already excluded
        $existing = PostVisibility::where('post_id', $post->id)
            ->where('excluded_user_id', $validated['user_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'This user is already excluded from this post.',
            ], 409);
        }

        $visibility = PostVisibility::create([
            'post_id' => $post->id,
            'excluded_user_id' => $validated['user_id'],
        ]);

        $visibility->load('excludedUser');

        return response()->json([
            'message' => 'User excluded from post successfully.',
            'data' => [
                'visibility' => [
                    'id' => $visibility->id,
                    'post_id' => $visibility->post_id,
                    'excluded_user' => [
                        'id' => $visibility->excludedUser->id,
                        'full_name' => $visibility->excludedUser->full_name,
                    ],
                    'created_at' => $visibility->created_at,
                ],
            ],
        ], 201);
    }

    /**
     * P6: Remove a user from the post's exclusion list.
     *
     * DELETE /api/v1/posts/{postId}/visibility/{userId}
     *
     * Only the post author can remove exclusions.
     */
    public function removeExclusion(Request $request, int $postId, int $userId)
    {
        $user = $request->user();

        $post = Post::with('topic')->findOrFail($postId);

        // Group isolation check via topic
        if ($post->topic->group_id !== $user->group_id) {
            return response()->json([
                'message' => 'You do not have access to this post.',
            ], 403);
        }

        // Only the post author can manage visibility
        if ($post->user_id !== $user->id) {
            return response()->json([
                'message' => 'Only the post author can manage visibility.',
            ], 403);
        }

        $visibility = PostVisibility::where('post_id', $post->id)
            ->where('excluded_user_id', $userId)
            ->firstOrFail();

        $visibility->delete();

        return response()->json([
            'message' => 'User exclusion removed successfully.',
        ], 200);
    }

    /**
     * P7: List all users excluded from seeing a post.
     *
     * GET /api/v1/posts/{postId}/visibility
     *
     * Only the post author can view the exclusion list.
     */
    public function index(Request $request, int $postId)
    {
        $user = $request->user();

        $post = Post::with('topic')->findOrFail($postId);

        // Group isolation check via topic
        if ($post->topic->group_id !== $user->group_id) {
            return response()->json([
                'message' => 'You do not have access to this post.',
            ], 403);
        }

        // Only the post author can view exclusions
        if ($post->user_id !== $user->id) {
            return response()->json([
                'message' => 'Only the post author can view visibility settings.',
            ], 403);
        }

        $exclusions = PostVisibility::where('post_id', $post->id)
            ->with('excludedUser')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'post_id' => $item->post_id,
                    'excluded_user' => [
                        'id' => $item->excludedUser->id,
                        'full_name' => $item->excludedUser->full_name,
                    ],
                    'excluded_at' => $item->created_at,
                ];
            });

        return response()->json([
            'data' => [
                'post_id' => $post->id,
                'excluded_users_count' => $exclusions->count(),
                'excluded_users' => $exclusions,
            ],
        ], 200);
    }
}
