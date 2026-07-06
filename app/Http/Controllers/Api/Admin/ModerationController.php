<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

/**
 * Admin API controller for post moderation.
 *
 * Handles listing reported posts, removing offensive content,
 * and dismissing false reports.
 *
 * Group isolation: Group Admins only see reported posts from
 * their administered groups. System Admins see everything.
 */
class ModerationController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * GET /api/v1/admin/moderation
     *
     * List reported posts with reporter info, topic, and creator.
     * Group-scoped for Group Admins.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Post::where('is_reported', true)
            ->with([
                'reports' => function ($q) {
                    $q->with('reporter:id,full_name');
                },
                'topic:id,title,group_id',
                'creator:id,full_name',
            ]);

        // Group-admin scope: only posts in administered groups
        if ($user->isGroupAdmin()) {
            $adminGroupIds = $user->administeredGroups()->pluck('groups.id');
            $query->whereHas('topic', function ($q) use ($adminGroupIds) {
                $q->whereIn('group_id', $adminGroupIds);
            });
        } elseif (! $user->isSystemAdmin()) {
            // Regular admin: only their own group
            $query->whereHas('topic', function ($q) use ($user) {
                $q->where('group_id', $user->group_id);
            });
        }

        $posts = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $posts,
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/moderation/{post}/remove
     *
     * Remove a reported post. Marks it as removed so it's hidden
     * from public view, and logs the action for auditing.
     */
    public function removePost(Request $request, Post $post)
    {
        $post->loadMissing('topic.group');

        if (! auth()->user()->canAdminGroup($post->topic->group)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot moderate posts in this group.',
            ], 403);
        }

        $post->update([
            'is_removed' => true,
            'is_reported' => false,
        ]);

        // Log to moderation log
        ModerationLog::create([
            'post_id' => $post->id,
            'admin_id' => auth()->id(),
            'action' => 'removed',
            'reason' => $request->input('reason', 'No reason provided'),
        ]);

        // Log to audit log
        $this->auditLogService->log('post_removed', [
            'post_id' => $post->id,
            'topic_id' => $post->topic_id,
            'reason' => $request->input('reason'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Post removed.',
        ]);
    }

    /**
     * POST /api/v1/admin/moderation/{post}/ignore
     *
     * Dismiss a report without removing the post.
     * Clears the reported flag so it no longer appears in the
     * moderation queue.
     */
    public function ignoreReport(Post $post)
    {
        $post->loadMissing('topic.group');

        if (! auth()->user()->canAdminGroup($post->topic->group)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot moderate posts in this group.',
            ], 403);
        }

        $post->update(['is_reported' => false]);

        $this->auditLogService->log('report_ignored', [
            'post_id' => $post->id,
            'topic_id' => $post->topic_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report ignored.',
        ]);
    }
}
