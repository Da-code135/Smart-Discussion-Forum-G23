<?php

namespace App\Utilities;

use App\Models\Group;
use App\Models\Statistics;
use App\Models\Topic;
use App\Models\User;

/**
 * Shared utility for group statistics used by both web and API controllers.
 *
 * Centralizes the statistics recalculation and retrieval logic so
 * the admin dashboard and API endpoints behave identically.
 */
class StatisticsUtility
{
    /**
     * Recalculate and persist statistics for a given group from live data.
     */
    public function recalculate(int $groupId): Statistics
    {
        // 1. Total members in the group
        $totalMembers = User::where('group_id', $groupId)->count();

        // 2. Active members this week (last_active_at within the last 7 days)
        $activeMembersThisWeek = User::where('group_id', $groupId)
            ->where('last_active_at', '>=', now()->subWeek())
            ->count();

        // 3. Total topics in this group
        $totalTopics = Topic::where('group_id', $groupId)->count();

        // 4. Total posts (replies) in this group
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

        return Statistics::where('group_id', $groupId)->first();
    }

    /**
     * Get statistics for all groups the user has access to.
     *
     * If a group doesn't have a statistics row yet, one is created
     * on-the-fly with default zero values.
     *
     * @return array Array of ['group' => Group, 'stats' => Statistics]
     */
    public function getStatsForUser(User $user): array
    {
        if ($user->isSystemAdmin()) {
            $groups = Group::all();
        } elseif ($user->isGroupAdmin()) {
            $adminGroupIds = $user->administeredGroups()->pluck('groups.id');
            $groups = Group::whereIn('id', $adminGroupIds)->get();
        } else {
            $groups = $user->group_id
                ? Group::where('id', $user->group_id)->get()
                : collect();
        }

        return $groups->map(function (Group $group) {
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
        })->toArray();
    }
}
