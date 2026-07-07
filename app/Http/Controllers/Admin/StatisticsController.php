<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Statistics;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Controller for the Statistics Dashboard (Tasks 1 & 2 of the Analytics module).
 *
 * Displays engagement metrics per group and provides a "Recalculate" action
 * to recompute the snapshot from live data.
 */
class StatisticsController extends Controller
{
    /**
     * Show the statistics dashboard.
     *
     * - System Administrators see stats for ALL groups.
     * - Group Administrators see stats only for the groups they administer.
     *
     * If a group doesn't have a statistics row yet, one is created
     * on-the-fly with default zero values so the dashboard never appears empty.
     */
    public function index(): View
    {
        $user = Auth::user();

        // Determine which groups this admin can see
        if ($user->isSystemAdmin()) {
            $groups = Group::all();
        } elseif ($user->isGroupAdmin()) {
            $adminGroupIds = $user->administeredGroups()->pluck('groups.id');
            $groups = Group::whereIn('id', $adminGroupIds)->get();
        } else {
            // Lecturers or other roles — only their own group
            $groups = $user->group_id
                ? Group::where('id', $user->group_id)->get()
                : collect();
        }

        // Get or create statistics for each group
        $groupStats = $groups->map(function (Group $group) {
            $stats = Statistics::firstOrCreate(
                ['group_id' => $group->id],
                [
                    'total_members' => 0,
                    'active_members_this_week' => 0,
                    'total_topics' => 0,
                    'total_posts' => 0,
                    'unanswered_questions' => 0,
                    'inactive_members_30days' => 0,
                ]
            );

            return [
                'group' => $group,
                'stats' => $stats,
            ];
        });

        return view('admin.statistics.index', compact('groupStats'));
    }

    /**
     * Recalculate statistics for a given group from live data.
     *
     * Queries the actual users, topics, and posts tables to recompute
     * every metric, then updates the statistics row with fresh values
     * and a new last_calculated_at timestamp.
     *
     * Access control: the caller must already be behind the 'admin'
     * middleware, but we also verify they can admin this specific group.
     */
    public function recalculate(int $groupId): RedirectResponse
    {
        $user = Auth::user();
        $group = Group::findOrFail($groupId);

        // Authorise: the user must be able to access this group
        if (! $user->canAccessGroup($groupId)) {
            abort(403, 'You do not have access to statistics for this group.');
        }

        // 1. Total members in the group
        $totalMembers = User::where('group_id', $groupId)->count();

        // 2. Active members this week (last_active_at within the last 7 days)
        $activeMembersThisWeek = User::where('group_id', $groupId)
            ->where('last_active_at', '>=', now()->subWeek())
            ->count();

        // 3. Total topics in this group
        $totalTopics = Topic::where('group_id', $groupId)->count();

        // 4. Total posts (replies) in this group
        //    Posts don't have a direct group_id; we join through topics.
        $totalPosts = Topic::where('group_id', $groupId)
            ->withCount('posts')
            ->get()
            ->sum('posts_count');

        // 5. Unanswered questions — topics of type 'question' with zero replies
        $unansweredQuestions = Topic::where('group_id', $groupId)
            ->where('post_type', 'question')
            ->whereDoesntHave('posts')
            ->count();

        // 6. Inactive members (30+ days since last_active_at)
        //    Only counts users who have EVER been active (last_active_at is not null)
        //    to avoid counting brand-new users who just registered.
        $inactiveMembers30days = User::where('group_id', $groupId)
            ->whereNotNull('last_active_at')
            ->where('last_active_at', '<', now()->subDays(30))
            ->count();

        // Persist the snapshot
        Statistics::updateOrCreate(
            ['group_id' => $groupId],
            [
                'total_members' => $totalMembers,
                'active_members_this_week' => $activeMembersThisWeek,
                'total_topics' => $totalTopics,
                'total_posts' => $totalPosts,
                'unanswered_questions' => $unansweredQuestions,
                'inactive_members_30days' => $inactiveMembers30days,
                'last_calculated_at' => now(),
            ]
        );

        return redirect()
            ->route('admin.statistics.index')
            ->with('success', "Statistics recalculated for {$group->group_name}.");
    }
}
