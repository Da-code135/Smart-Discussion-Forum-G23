<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Topic;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    /**
     * T1: List active topics in the authenticated user's group.
     *
     * GET /api/v1/topics
     *
     * Paginated, ordered by most recent first.
     * System admins see all topics; regular users see only their group.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Topic::with('creator')->withCount('posts')->latest();

        // System admins see all topics; others see only accessible groups
        if (! $user->isSystemAdmin()) {
            $query->whereIn('group_id', $user->accessibleGroupIds());
        }

        $topics = $query->active()->paginate(20);

        return response()->json(
            [
                'data' => $topics,
            ],
            200,
        );
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

        // Group isolation check (SysAdmin / Lecturer / Group Admin bypass)
        if (! $user->canAccessGroup($topic->group_id)) {
            return response()->json(
                [
                    'message' => 'You do not have access to this topic.',
                ],
                403,
            );
        }

        // Load posts: non-removed, visible to user, chronological, with author
        $posts = $topic
            ->posts()
            ->notRemoved()
            ->visibleToUser($user->id)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json(
            [
                'data' => [
                    'topic' => [
                        'id' => $topic->id,
                        'title' => $topic->title,
                        'description' => $topic->description,
                        'status' => $topic->status,
                        'post_type' => $topic->post_type,
                        'group_id' => $topic->group_id,
                        'creator' => $topic->creator
                            ? [
                                'id' => $topic->creator->id,
                                'full_name' => $topic->creator->full_name,
                            ]
                            : null,
                        'posts_count' => $posts->total(),
                        'created_at' => $topic->created_at,
                        'updated_at' => $topic->updated_at,
                    ],
                    'posts' => $posts,
                ],
            ],
            200,
        );
    }

    /**
     * T3: Create a new topic.
     *
     * POST /api/v1/topics
     *
     * Topic is scoped to the authenticated user's group.
     * System admins can optionally specify a target group_id.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $formRules = [
            'title' => 'required|string|max:255|unique:topics,title',
            'description' => 'required|string|max:10000',
            'post_type' => 'sometimes|in:discussion,question',
        ];

        // System admins can optionally specify a target group
        if ($user->isSystemAdmin()) {
            $formRules['group_id'] = 'sometimes|integer|exists:groups,id';
        }

        $validated = $request->validate($formRules);

        $topic = Topic::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'post_type' => $validated['post_type'] ?? 'discussion',
            'created_by' => $user->id,
            'group_id' => $user->isSystemAdmin() && $request->has('group_id')
                    ? $validated['group_id']
                    : $user->group_id,
            'status' => 'active',
        ]);

        $topic->load('creator');

        return response()->json(
            [
                'message' => 'Topic created successfully.',
                'data' => [
                    'topic' => [
                        'id' => $topic->id,
                        'title' => $topic->title,
                        'description' => $topic->description,
                        'post_type' => $topic->post_type,
                        'status' => $topic->status,
                        'group_id' => $topic->group_id,
                        'creator' => $topic->creator
                            ? [
                                'id' => $topic->creator->id,
                                'full_name' => $topic->creator->full_name,
                            ]
                            : null,
                        'created_at' => $topic->created_at,
                        'updated_at' => $topic->updated_at,
                    ],
                ],
            ],
            201,
        );
    }

    /**
     * T4: Update a topic (title, description, status).
     *
     * PUT /api/v1/topics/{topicId}
     *
     * Only the topic creator or an admin can update.
     * Group isolation enforced (SysAdmin bypass).
     */
    public function update(Request $request, int $topicId)
    {
        $user = $request->user();

        $topic = Topic::findOrFail($topicId);

        // Group isolation check (SysAdmin / Lecturer / Group Admin bypass)
        if (! $user->canAccessGroup($topic->group_id)) {
            return response()->json(
                [
                    'message' => 'You do not have access to this topic.',
                ],
                403,
            );
        }

        // Authorization: only creator or admin
        if ($topic->created_by !== $user->id && ! $user->isAdmin()) {
            return response()->json(
                [
                    'message' => 'You are not authorized to update this topic.',
                ],
                403,
            );
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255|unique:topics,title,'.$topic->id,
            'description' => 'sometimes|string|max:10000',
            'status' => 'sometimes|in:active,archived',
            'post_type' => 'sometimes|in:discussion,question',
        ]);

        $topic->update($validated);
        $topic->load('creator');

        return response()->json(
            [
                'message' => 'Topic updated successfully.',
                'data' => [
                    'topic' => [
                        'id' => $topic->id,
                        'title' => $topic->title,
                        'description' => $topic->description,
                        'status' => $topic->status,
                        'post_type' => $topic->post_type,
                        'group_id' => $topic->group_id,
                        'creator' => $topic->creator
                            ? [
                                'id' => $topic->creator->id,
                                'full_name' => $topic->creator->full_name,
                            ]
                            : null,
                        'created_at' => $topic->created_at,
                        'updated_at' => $topic->updated_at,
                    ],
                ],
            ],
            200,
        );
    }

    /**
     * T5: Soft-delete / archive a topic.
     *
     * DELETE /api/v1/topics/{topicId}
     *
     * Sets status to 'archived'. Only creator or admin can delete.
     * Group isolation enforced (SysAdmin bypass).
     */
    public function destroy(
        Request $request,
        int $topicId,
        AuditLogService $auditLog,
    ) {
        $user = $request->user();

        $topic = Topic::findOrFail($topicId);

        // Group isolation check (SysAdmin / Lecturer / Group Admin bypass)
        if (! $user->canAccessGroup($topic->group_id)) {
            return response()->json(
                [
                    'message' => 'You do not have access to this topic.',
                ],
                403,
            );
        }

        // Authorization: only creator or admin (SysAdmin bypass via isAdmin)
        if ($topic->created_by !== $user->id && ! $user->isAdmin()) {
            return response()->json(
                [
                    'message' => 'You are not authorized to delete this topic.',
                ],
                403,
            );
        }

        $topic->update(['status' => 'archived']);

        $auditLog->log(
            action: 'topic.archived',
            target: $topic,
            newValues: ['status' => 'archived'],
            description: $user->full_name.
                ' archived topic "'.
                $topic->title.
                '"',
        );

        return response()->json(
            [
                'message' => 'Topic archived successfully.',
            ],
            200,
        );
    }

    /**
     * T6: List posts in a topic (paginated, filtered).
     *
     * GET /api/v1/topics/{topicId}/posts
     *
     * Group isolation enforced (SysAdmin bypass).
     * Posts filtered by visibility and moderation status.
     */
    public function posts(Request $request, int $topicId)
    {
        $user = $request->user();

        $topic = Topic::findOrFail($topicId);

        // Group isolation check (SysAdmin / Lecturer / Group Admin bypass)
        if (! $user->canAccessGroup($topic->group_id)) {
            return response()->json(
                [
                    'message' => 'You do not have access to this topic.',
                ],
                403,
            );
        }

        $posts = $topic
            ->posts()
            ->notRemoved()
            ->visibleToUser($user->id)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json(
            [
                'data' => $posts,
            ],
            200,
        );
    }

    /**
     * T7: Filter topics by post_type (discussion or question).
     *
     * GET /api/v1/topics/type/{type}
     *
     * Group isolation enforced (SysAdmin bypass).
     */
    public function byType(Request $request, string $type)
    {
        if (! in_array($type, ['discussion', 'question'])) {
            return response()->json(
                [
                    'message' => 'Invalid type. Must be "discussion" or "question".',
                ],
                422,
            );
        }

        $user = $request->user();

        $query = Topic::active()
            ->byType($type)
            ->with('creator')
            ->withCount('posts')
            ->latest();

        // System admins see all topics; others see only accessible groups
        if (! $user->isSystemAdmin()) {
            $query->whereIn('group_id', $user->accessibleGroupIds());
        }

        $topics = $query->paginate(20);

        return response()->json(
            [
                'data' => $topics,
            ],
            200,
        );
    }

    /**
     * E1: Export a topic thread as a formatted PDF.
     *
     * GET /api/v1/topics/{topicId}/export/pdf
     *
     * Generates a PDF containing the topic opening post and all visible,
     * non-removed replies. Enforces group isolation (SysAdmin bypass),
     * visibility rules, and moderation filtering. Logs the export to the audit trail.
     */
    public function exportPDF(
        Request $request,
        int $topicId,
        AuditLogService $auditLog,
    ) {
        $user = $request->user();

        $topic = Topic::with(['creator', 'group'])->findOrFail($topicId);

        // Group isolation check (SysAdmin / Lecturer / Group Admin bypass)
        if (! $user->canAccessGroup($topic->group_id)) {
            return response()->json(
                [
                    'message' => 'You do not have access to this topic.',
                ],
                403,
            );
        }

        // Load visible, non-removed replies with their authors
        $replies = Post::where('topic_id', $topic->id)
            ->notRemoved()
            ->visibleToUser($user->id)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        // Log the export for audit trail
        $auditLog->log(
            action: 'topic.exported',
            target: $topic,
            newValues: ['format' => 'pdf'],
            description: $user->full_name.
                ' exported topic "'.
                $topic->title.
                '" as PDF',
        );

        $pdf = Pdf::loadView('forum.export-pdf', [
            'topic' => $topic,
            'replies' => $replies,
        ]);

        return $pdf->download('topic-'.$topic->id.'.pdf');
    }

    /**
     * E2: Generate a time-limited signed URL for topic access.
     *
     * POST /api/v1/topics/{topicId}/share
     *
     * Creates a temporary signed URL that allows anyone with the link
     * to view the topic for a limited time (default 24 hours).
     * Enforces group isolation (SysAdmin bypass) — only members of the topic's group
     * can generate share links.
     *
     * Request body (optional):
     *   - expires_in: int (minutes, default 1440 = 24h, max 10080 = 7 days)
     */
    public function share(
        Request $request,
        int $topicId,
        AuditLogService $auditLog,
    ) {
        $user = $request->user();

        $topic = Topic::findOrFail($topicId);

        // Group isolation check (SysAdmin / Lecturer / Group Admin bypass)
        if (! $user->canAccessGroup($topic->group_id)) {
            return response()->json(
                [
                    'message' => 'You do not have access to this topic.',
                ],
                403,
            );
        }

        // Only active topics can be shared
        if ($topic->status !== 'active') {
            return response()->json(
                [
                    'message' => 'Only active topics can be shared.',
                ],
                422,
            );
        }

        $validated = $request->validate([
            'expires_in' => 'sometimes|integer|min:1|max:10080',
        ]);

        $expiresInMinutes = $validated['expires_in'] ?? 1440; // default 24 hours

        // Generate a temporary signed route URL pointing to the topic show endpoint
        $signedUrl = \URL::temporarySignedRoute(
            'topics.share.access', // named route for share access
            now()->addMinutes($expiresInMinutes),
            ['topicId' => $topic->id],
        );

        // Log the share generation
        $auditLog->log(
            action: 'topic.shared',
            target: $topic,
            newValues: [
                'expires_in_minutes' => $expiresInMinutes,
                'expires_at' => now()
                    ->addMinutes($expiresInMinutes)
                    ->toIso8601String(),
            ],
            description: $user->full_name.
                ' generated a share link for topic "'.
                $topic->title.
                '"',
        );

        return response()->json(
            [
                'message' => 'Share link generated successfully.',
                'data' => [
                    'url' => $signedUrl,
                    'expires_at' => now()
                        ->addMinutes($expiresInMinutes)
                        ->toIso8601String(),
                    'expires_in_minutes' => $expiresInMinutes,
                ],
            ],
            201,
        );
    }

    /**
     * Access a topic via a signed share URL (no authentication required).
     *
     * GET /api/v1/topics/{topicId}/shared
     *
     * Validates the temporary signed URL signature. If valid, returns
     * the topic with its visible, non-removed posts. No group isolation
     * check — the signed URL itself is the authorization.
     */
    public function sharedAccess(Request $request, int $topicId)
    {
        // Validate the signed URL signature (Laravel handles this via middleware)
        if (! $request->hasValidSignature()) {
            return response()->json(
                [
                    'message' => 'Invalid or expired share link.',
                ],
                403,
            );
        }

        $topic = Topic::with('creator')->findOrFail($topicId);

        // Only active topics accessible via share links
        if ($topic->status !== 'active') {
            return response()->json(
                [
                    'message' => 'This topic is no longer available.',
                ],
                410,
            );
        }

        // Load visible, non-removed replies with authors
        $posts = $topic
            ->posts()
            ->notRemoved()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json(
            [
                'data' => [
                    'topic' => [
                        'id' => $topic->id,
                        'title' => $topic->title,
                        'description' => $topic->description,
                        'status' => $topic->status,
                        'post_type' => $topic->post_type,
                        'group_id' => $topic->group_id,
                        'creator' => $topic->creator
                            ? [
                                'id' => $topic->creator->id,
                                'full_name' => $topic->creator->full_name,
                            ]
                            : null,
                        'posts_count' => $posts->total(),
                        'created_at' => $topic->created_at,
                        'updated_at' => $topic->updated_at,
                    ],
                    'posts' => $posts,
                ],
            ],
            200,
        );
    }

    /**
     * N3: Toggle the answered status of a question topic.
     *
     * POST /api/v1/topics/{topicId}/toggle-answered
     *
     * Only the topic creator or a group admin can toggle.
     * Group isolation enforced (SysAdmin bypass).
     */
    public function toggleAnswered(Request $request, int $topicId)
    {
        $user = $request->user();
        $topic = Topic::findOrFail($topicId);

        // Group isolation check (SysAdmin / Lecturer / Group Admin bypass)
        if (! $user->canAccessGroup($topic->group_id)) {
            return response()->json(
                [
                    'message' => 'You do not have access to this topic.',
                ],
                403,
            );
        }

        // Only question-type topics can be marked as answered
        if ($topic->post_type !== 'question') {
            return response()->json(
                [
                    'message' => 'Only question topics can be marked as answered.',
                ],
                422,
            );
        }

        // Authorization: only topic creator or admin
        if ($topic->created_by !== $user->id && ! $user->isAdmin()) {
            return response()->json(
                [
                    'message' => 'You are not authorized to toggle answered status.',
                ],
                403,
            );
        }

        $topic->update(['is_answered' => ! $topic->is_answered]);

        $status = $topic->is_answered ? 'answered' : 'unanswered';

        return response()->json(
            [
                'message' => "Topic marked as {$status}.",
                'data' => [
                    'id' => $topic->id,
                    'is_answered' => $topic->is_answered,
                ],
            ],
            200,
        );
    }

    /**
     * N4: Toggle the pinned status of a topic.
     *
     * POST /api/v1/topics/{topicId}/toggle-pinned
     *
     * Only group admins can pin/unpin topics (moderation action).
     * Group isolation enforced (SysAdmin bypass).
     */
    public function togglePinned(Request $request, int $topicId)
    {
        $user = $request->user();
        $topic = Topic::findOrFail($topicId);

        // Group isolation check (SysAdmin / Lecturer / Group Admin bypass)
        if (! $user->canAccessGroup($topic->group_id)) {
            return response()->json(
                [
                    'message' => 'You do not have access to this topic.',
                ],
                403,
            );
        }

        // Authorization: only admins can pin topics
        if (! $user->isAdmin()) {
            return response()->json(
                [
                    'message' => 'Only administrators can pin topics.',
                ],
                403,
            );
        }

        $topic->update(['is_pinned' => ! $topic->is_pinned]);

        $status = $topic->is_pinned ? 'pinned' : 'unpinned';

        return response()->json(
            [
                'message' => "Topic {$status} successfully.",
                'data' => [
                    'id' => $topic->id,
                    'is_pinned' => $topic->is_pinned,
                ],
            ],
            200,
        );
    }
}
