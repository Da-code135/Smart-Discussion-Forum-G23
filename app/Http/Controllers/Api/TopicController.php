<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    /**
     * T1: List active topics in the authenticated user's group.
     *
     * GET /api/v1/topics
     *
     * Paginated, ordered by most recent first.
     * Group isolation: only topics from the user's group are returned.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $topics = Topic::forGroup($user->group_id)
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
     * T2: Get topic detail with its posts (paginated).
     *
     * GET /api/v1/topics/{topicId}
     *
     * Group isolation enforced: topic must belong to user's group.
     * Posts are filtered by visibility and moderation status.
     */
    public function show(Request $request, int $topicId)
    {
        $user = $request->user();

        $topic = Topic::with('creator')->findOrFail($topicId);

        // Group isolation check
        if ($topic->group_id !== $user->group_id) {
            return response()->json([
                'message' => 'You do not have access to this topic.',
            ], 403);
        }

        // Load posts: non-removed, visible to user, chronological, with author
        $posts = $topic->posts()
            ->notRemoved()
            ->visibleToUser($user->id)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json([
            'data' => [
                'topic' => [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'description' => $topic->description,
                    'status' => $topic->status,
                    'post_type' => $topic->post_type,
                    'group_id' => $topic->group_id,
                    'creator' => $topic->creator ? [
                        'id' => $topic->creator->id,
                        'full_name' => $topic->creator->full_name,
                    ] : null,
                    'posts_count' => $posts->total(),
                    'created_at' => $topic->created_at,
                    'updated_at' => $topic->updated_at,
                ],
                'posts' => $posts,
            ],
        ], 200);
    }

    /**
     * T3: Create a new topic.
     *
     * POST /api/v1/topics
     *
     * Topic is scoped to the authenticated user's group.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255|unique:topics,title',
            'description' => 'required|string|max:10000',
            'post_type' => 'sometimes|in:discussion,question',
        ]);

        $user = $request->user();

        $topic = Topic::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'post_type' => $validated['post_type'] ?? 'discussion',
            'created_by' => $user->id,
            'group_id' => $user->group_id, // Critical: scoped to user's group
            'status' => 'active',
        ]);

        $topic->load('creator');

        return response()->json([
            'message' => 'Topic created successfully.',
            'data' => [
                'topic' => [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'description' => $topic->description,
                    'post_type' => $topic->post_type,
                    'status' => $topic->status,
                    'group_id' => $topic->group_id,
                    'creator' => $topic->creator ? [
                        'id' => $topic->creator->id,
                        'full_name' => $topic->creator->full_name,
                    ] : null,
                    'created_at' => $topic->created_at,
                    'updated_at' => $topic->updated_at,
                ],
            ],
        ], 201);
    }

    /**
     * T4: Update a topic (title, description, status).
     *
     * PUT /api/v1/topics/{topicId}
     *
     * Only the topic creator or an admin can update.
     * Group isolation enforced.
     */
    public function update(Request $request, int $topicId)
    {
        $user = $request->user();

        $topic = Topic::findOrFail($topicId);

        // Group isolation check
        if ($topic->group_id !== $user->group_id) {
            return response()->json([
                'message' => 'You do not have access to this topic.',
            ], 403);
        }

        // Authorization: only creator or admin
        if ($topic->created_by !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'You are not authorized to update this topic.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255|unique:topics,title,' . $topic->id,
            'description' => 'sometimes|string|max:10000',
            'status' => 'sometimes|in:active,archived',
            'post_type' => 'sometimes|in:discussion,question',
        ]);

        $topic->update($validated);
        $topic->load('creator');

        return response()->json([
            'message' => 'Topic updated successfully.',
            'data' => [
                'topic' => [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'description' => $topic->description,
                    'status' => $topic->status,
                    'post_type' => $topic->post_type,
                    'group_id' => $topic->group_id,
                    'creator' => $topic->creator ? [
                        'id' => $topic->creator->id,
                        'full_name' => $topic->creator->full_name,
                    ] : null,
                    'created_at' => $topic->created_at,
                    'updated_at' => $topic->updated_at,
                ],
            ],
        ], 200);
    }

    /**
     * T5: Soft-delete / archive a topic.
     *
     * DELETE /api/v1/topics/{topicId}
     *
     * Sets status to 'archived'. Only creator or admin can delete.
     * Group isolation enforced.
     */
    public function destroy(Request $request, int $topicId, AuditLogService $auditLog)
    {
        $user = $request->user();

        $topic = Topic::findOrFail($topicId);

        // Group isolation check
        if ($topic->group_id !== $user->group_id) {
            return response()->json([
                'message' => 'You do not have access to this topic.',
            ], 403);
        }

        // Authorization: only creator or admin
        if ($topic->created_by !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'You are not authorized to delete this topic.',
            ], 403);
        }

        $topic->update(['status' => 'archived']);

        $auditLog->log(
            action: 'topic.archived',
            target: $topic,
            newValues: ['status' => 'archived'],
            description: $user->full_name . ' archived topic "' . $topic->title . '"'
        );

        return response()->json([
            'message' => 'Topic archived successfully.',
        ], 200);
    }

    /**
     * T6: List posts in a topic (paginated, filtered).
     *
     * GET /api/v1/topics/{topicId}/posts
     *
     * Group isolation enforced.
     * Posts filtered by visibility and moderation status.
     */
    public function posts(Request $request, int $topicId)
    {
        $user = $request->user();

        $topic = Topic::findOrFail($topicId);

        // Group isolation check
        if ($topic->group_id !== $user->group_id) {
            return response()->json([
                'message' => 'You do not have access to this topic.',
            ], 403);
        }

        $posts = $topic->posts()
            ->notRemoved()
            ->visibleToUser($user->id)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json([
            'data' => $posts,
        ], 200);
    }

    /**
     * T7: Filter topics by post_type (discussion or question).
     *
     * GET /api/v1/topics/type/{type}
     *
     * Group isolation enforced.
     */
    public function byType(Request $request, string $type)
    {
        if (!in_array($type, ['discussion', 'question'])) {
            return response()->json([
                'message' => 'Invalid type. Must be "discussion" or "question".',
            ], 422);
        }

        $user = $request->user();

        $topics = Topic::forGroup($user->group_id)
            ->active()
            ->byType($type)
            ->with('creator')
            ->withCount('posts')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $topics,
        ], 200);
    }
}
