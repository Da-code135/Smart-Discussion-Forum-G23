<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\Post;
use App\Models\Statistics;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Console\Command;

class CalculateStatistics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-statistics {groupId?}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Calculate statistics for one or all groups';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // If groupId provided, calculate only that group
        // Otherwise, calculate all groups

        if ($groupId = $this->argument('groupId')) {
            $groups = Group::where('id', $groupId)->get();
        } else {
            $groups = Group::all();
        }

        foreach ($groups as $group) {
            $this->calculateForGroup($group);
        }

        $this->info('Statistics calculated successfully.');
    }

    /**
     * Calculate stats for a single group
     */
    private function calculateForGroup(Group $group)
    {
        // 1. Count total members in group
        $totalMembers = User::where('group_id', $group->id)->count();

        // 2. Count active members this week (posted something in last 7 days)
        $activeMembersThisWeek = Post::whereIn('topic_id', Topic::where('group_id', $group->id)->pluck('id'))
            ->where('created_at', '>=', now()->subWeek())
            ->distinct('user_id')
            ->count();

        // 3. Count total posts in group
        $totalPosts = Post::whereIn('topic_id', Topic::where('group_id', $group->id)->pluck('id'))
            ->count();

        // 4. Count total topics in group
        $totalTopics = Topic::where('group_id', $group->id)->count();

        // 5. Count unanswered questions
        // (Questions with post_type='question' AND zero replies)
        $unansweredQuestions = Topic::where('group_id', $group->id)
            ->where('post_type', 'question')
            ->withCount('posts')
            ->get()
            ->filter(function ($topic) {
                return $topic->posts_count == 0;
            })
            ->count();

        // 6. Count inactive members (haven't posted in 30+ days)
        // Note: Assuming users table has last_active_at field
        $inactiveMembers = User::where('group_id', $group->id)
            ->where('last_active_at', '<', now()->subDays(30))
            ->orWhereNull('last_active_at')  // Never posted
            ->count();

        // 7. Update or create the statistics row
        Statistics::updateOrCreate(
            ['group_id' => $group->id],
            [
                'total_members' => $totalMembers,
                'active_members_this_week' => $activeMembersThisWeek,
                'total_posts' => $totalPosts,
                'total_topics' => $totalTopics,
                'unanswered_questions' => $unansweredQuestions,
                'inactive_members_30days' => $inactiveMembers,
                'last_calculated_at' => now(),
            ]
        );

        $this->info("Calculated stats for: {$group->group_name}");
    }
}
