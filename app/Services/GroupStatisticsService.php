<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

class GroupStatisticsService
{
    /**
     * Summary stats for ALL groups (used on the index page that lists every group).
     * One query per stat, but each is lightweight with aggregate counts.
     */
    public function allGroupsOverview(): array
    {
        $groups = Group::withCount('users')->orderBy('group_name')->get();

        return $groups->map(fn ($group) => [
            'id' => $group->id,
            'group_name' => $group->group_name,
            'total_members' => $group->users_count,
            'active_members' => $group->users()->where('account_status', 'active')->count(),
            'total_topics' => Topic::where('group_id', $group->id)->count(),
            'total_posts' => Post::whereIn('topic_id',
                Topic::where('group_id', $group->id)->select('id')
            )->count(),
            'last_activity' => Post::whereIn('topic_id',
                Topic::where('group_id', $group->id)->select('id')
            )->max('created_at'),
        ])->toArray();
    }

    /**
     * Deep-dive stats for a SINGLE group.
     */
    public function groupDetail(Group $group): array
    {
        // 1. Membership breakdown
        $allUsers = $group->users();
        $totalMembers = (clone $allUsers)->count();
        $activeUsers = (clone $allUsers)->where('account_status', 'active')->count();
        $warnedUsers = (clone $allUsers)->where('account_status', 'warned')->count();
        $blacklisted = (clone $allUsers)->whereNotNull('blacklisted_at')->count();
        $inactiveUsers = $totalMembers - $activeUsers - $warnedUsers - $blacklisted;

        // 2. Topic stats
        $topicIds = Topic::where('group_id', $group->id)->pluck('id');
        $totalTopics = $topicIds->count();
        $discussionTopics = Topic::where('group_id', $group->id)->where('post_type', 'discussion')->count();
        $questionTopics = Topic::where('group_id', $group->id)->where('post_type', 'question')->count();
        $unansweredQuestions = Topic::where('group_id', $group->id)
            ->where('post_type', 'question')
            ->whereDoesntHave('posts')
            ->count();

        // 3. Post stats
        $totalPosts = Post::whereIn('topic_id', $topicIds)->count();
        $removedPosts = Post::whereIn('topic_id', $topicIds)->where('is_removed', true)->count();
        $reportedPosts = Post::whereIn('topic_id', $topicIds)->where('is_reported', true)->count();
        $avgPostsPerTopic = $totalTopics > 0 ? round($totalPosts / $totalTopics, 1) : 0;
        $avgPostsPerMember = $totalMembers > 0 ? round($totalPosts / $totalMembers, 1) : 0;

        // 4. Most active members (top 10 by post count)
        $topMembers = (clone $allUsers)
            ->withCount(['posts' => fn ($q) => $q->whereIn('topic_id', $topicIds)])
            ->orderByDesc('posts_count')
            ->limit(10)
            ->get()
            ->map(fn ($u) => [
                'full_name' => $u->full_name,
                'post_count' => $u->posts_count,
                'last_active' => $u->last_active_at?->diffForHumans(),
            ]);

        // 5. Weekly topic-creation trend (last 12 weeks)
        // Uses MySQL's DATE_FORMAT instead of SQLite's strftime
        $weeklyTopics = Topic::where('group_id', $group->id)
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%u') as week"), DB::raw('count(*) as total'))
            ->where('created_at', '>=', now()->subWeeks(12))
            ->groupBy('week')
            ->orderBy('week')
            ->get()
            ->map(fn ($r) => ['week' => $r->week, 'topics' => $r->total]);

        // 6. Members who have never posted
        $lurkers = (clone $allUsers)
            ->whereDoesntHave('posts', fn ($q) => $q->whereIn('topic_id', $topicIds))
            ->count();

        return [
            'group' => $group,
            'total_members' => $totalMembers,
            'active_members' => $activeUsers,
            'warned_members' => $warnedUsers,
            'blacklisted_members' => $blacklisted,
            'inactive_members' => $inactiveUsers,
            'total_topics' => $totalTopics,
            'discussion_topics' => $discussionTopics,
            'question_topics' => $questionTopics,
            'unanswered_questions' => $unansweredQuestions,
            'total_posts' => $totalPosts,
            'removed_posts' => $removedPosts,
            'reported_posts' => $reportedPosts,
            'avg_posts_per_topic' => $avgPostsPerTopic,
            'avg_posts_per_member' => $avgPostsPerMember,
            'top_members' => $topMembers,
            'weekly_topics' => $weeklyTopics,
            'lurkers' => $lurkers,
        ];
    }
}
