<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Utilities\StatisticsUtility;
use Illuminate\Support\Facades\Auth;

class GroupStatisticsController extends Controller
{
    public function __construct(
        protected StatisticsUtility $statisticsUtility
    ) {}

    /**
     * GET /api/v1/admin/group-statistics
     *
     * Returns per-group statistics for all groups.
     * System Administrator access only.
     *
     * Each group includes: member_count, topic_count, post_count, active_members_30d
     */
    public function index()
    {
        // System Admin only
        if (! Auth::user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. System Administrator access required.',
            ], 403);
        }

        $stats = Group::withCount(['users', 'topics'])
            ->with(['topics' => function ($q) {
                $q->withCount('posts');
            }])
            ->get()
            ->map(fn ($g) => [
                'group_id' => $g->id,
                'group_name' => $g->group_name,
                'member_count' => $g->users_count,
                'topic_count' => $g->topics_count,
                'post_count' => $g->topics->sum('posts_count'),
                'active_members_30d' => User::where('group_id', $g->id)
                    ->where('last_active_at', '>=', now()->subDays(30))
                    ->count(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'groups' => $stats,
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/group-statistics/{group}
     *
     * Returns detailed statistics for a single group:
     * members list, recent topics with post counts.
     * System Administrator access only.
     */
    public function show(Group $group)
    {
        // System Admin only
        if (! Auth::user()->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. System Administrator access required.',
            ], 403);
        }

        $group->load([
            'users:id,full_name,email,last_active_at,created_at',
            'topics' => function ($q) {
                $q->withCount('posts')
                    ->latest()
                    ->take(20);
            },
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'group' => [
                    'id' => $group->id,
                    'name' => $group->group_name,
                    'members' => $group->users,
                    'topics' => $group->topics,
                    'member_count' => $group->users->count(),
                    'topic_count' => $group->topics->count(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/statistics/{group}/recalculate
     *
     * Recalculate and persist statistics for a given group from live data.
     * Access is restricted to users who can access the group (admins).
     */
    public function recalculate(Group $group)
    {
        $user = Auth::user();

        // Authorise: the user must be able to access this group
        if (! $user->canAccessGroup($group->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to statistics for this group.',
            ], 403);
        }

        $stats = $this->statisticsUtility->recalculate($group->id);

        return response()->json([
            'success' => true,
            'message' => "Statistics recalculated for {$group->group_name}.",
            'data' => [
                'group_id' => $group->id,
                'group_name' => $group->group_name,
                'statistics' => $stats,
            ],
        ]);
    }
}
