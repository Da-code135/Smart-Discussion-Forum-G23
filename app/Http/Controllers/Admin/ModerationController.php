<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationLog;
use App\Models\Post;
use Illuminate\Http\Request;

class ModerationController extends Controller
{
    /**
     * Show all reported posts scoped to groups the admin can manage.
     */
    public function index()
    {
        $reportedPosts = $this->reportedPostsQuery()->get();

        return view('admin.moderation-index', compact('reportedPosts'));
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

        return back()->with('success', 'Report dismissed.');
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
        } elseif (! $user->isSystemAdmin()) {
            $query->whereHas('topic', function ($q) use ($user) {
                $q->where('group_id', $user->group_id);
            });
        }

        return $query->latest();
    }
}
