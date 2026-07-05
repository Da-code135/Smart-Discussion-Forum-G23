<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Topic;
use App\Models\Report;
use Illuminate\Http\Request;

class ModerationController extends Controller
{
    /**
     * Show all reported content scoped to groups the admin can manage.
     * Includes both reported posts (flagged via is_reported) and
     * topics with pending reports (from the reports table).
     */
    public function index()
    {
        $reportedPosts = $this->reportedPostsQuery()->get();
        $reportedTopics = $this->reportedTopicsQuery()->get();

        return view('admin.moderation-index', compact('reportedPosts', 'reportedTopics'));
    }

    /**
     * Remove a reported post and log the action for auditing.
     */
    public function removePost(Request $request, Post $post)
    {
        $post->loadMissing('topic.group');

        if (! auth()->user()->canAdminGroup($post->topic->group)) {
            abort(403);
        }

        $post->update(['is_removed' => true, 'is_reported' => false]);

        // Resolve all pending reports for this post
        Report::where('reportable_type', Post::class)
            ->where('reportable_id', $post->id)
            ->where('status', 'pending')
            ->update(['status' => 'resolved']);

        ModerationLog::create([
            'post_id' => $post->id,
            'admin_id' => auth()->id(),
            'action' => 'removed',
            'reason' => $request->input('reason', 'No reason provided'),
        ]);

        return back()->with('success', 'Post removed.');
    }

    /**
     * Dismiss a report without removing the post.
     */
    public function ignoreReport(Post $post)
    {
        $post->loadMissing('topic.group');

        if (! auth()->user()->canAdminGroup($post->topic->group)) {
            abort(403);
        }

        $post->update(['is_reported' => false]);

        // Resolve all pending reports for this post
        Report::where('reportable_type', Post::class)
            ->where('reportable_id', $post->id)
            ->where('status', 'pending')
            ->update(['status' => 'resolved']);

        return back()->with('success', 'Report dismissed.');
    }

    /**
     * Dismiss a topic report without taking further action.
     */
    public function ignoreTopicReport(Topic $topic)
    {
        if (! auth()->user()->canAdminGroup($topic->group)) {
            abort(403);
        }

        // Resolve all pending reports for this topic
        Report::where('reportable_type', Topic::class)
            ->where('reportable_id', $topic->id)
            ->where('status', 'pending')
            ->update(['status' => 'resolved']);

        return back()->with('success', 'Topic report dismissed.');
    }

    /**
     * Base query for reported posts with group isolation enforced in SQL.
     */
    private function reportedPostsQuery()
    {
        $user = auth()->user();

        $query = Post::where('is_reported', true)
            ->with(['topic', 'user']);

        if ($user->isGroupAdmin()) {
            $adminGroupIds = $user->administeredGroups()->pluck('groups.id');

            $query->whereHas('topic', function ($q) use ($adminGroupIds) {
                $q->whereIn('group_id', $adminGroupIds);
            });
        } elseif(! $user->isSystemAdmin()) {
            $query->whereHas('topic', function ($q) use ($user) {
                $q->where('group_id', $user->group_id);
            });
        }

        return $query->latest();
    }

    /**
     * Base query for topics with pending reports, with group isolation.
     */
    private function reportedTopicsQuery()
    {
        $user = auth()->user();

        $query = Topic::whereHas('reports', function ($q) {
                $q->where('status', 'pending');
            })
            ->with(['creator', 'group'])
            ->withCount(['reports' => function ($q) {
                $q->where('status', 'pending');
            }]);

        if ($user->isGroupAdmin()) {
            $adminGroupIds = $user->administeredGroups()->pluck('groups.id');
            $query->whereIn('group_id', $adminGroupIds);
        } elseif(! $user->isSystemAdmin()) {
            $query->where('group_id', $user->group_id);
        }

        return $query->latest();
    }
}
