<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\Notification;
use App\Models\Post;
use App\Models\RecommendationLog;
use App\Models\Statistics;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoActivitySeeder extends Seeder
{
    /**
     * Seed demo activity data: audit logs, notifications, recommendation
     * log entries, and per-group statistics.
     *
     * Audit logs, notifications, and recommendations are keyed by their
     * natural columns; statistics use updateOrCreate per group so counts
     * are refreshed instead of duplicated. Run AFTER DemoForumSeeder.
     */
    public function run(): void
    {
        $studentGroup = Group::where('group_type', 'student')->firstOrFail();

        $superAdmin = User::where('email', 'superadmin@example.com')->firstOrFail();
        $groupAdmin = User::where('email', 'groupadmin@example.com')->firstOrFail();
        $student1 = User::where('email', 'student1@example.com')->firstOrFail();
        $student2 = User::where('email', 'student2@example.com')->firstOrFail();
        $blacklistedUser = User::where('email', 'blacklisted@example.com')->firstOrFail();

        $topic1 = Topic::where('title', 'What is the derivative of x²?')->firstOrFail();
        $topic4 = Topic::where('title', 'Study group meetup this Friday')->firstOrFail();
        $topic6 = Topic::where('title', 'How do I solve this integral? ∫ e^x sin(x) dx')->firstOrFail();
        $topic7 = Topic::where('title', 'Tips for debugging PHP code effectively')->firstOrFail();

        // -------------------------------------------------------------------
        //  Audit logs (keyed by user + action + target)
        // -------------------------------------------------------------------
        AuditLog::firstOrCreate(
            [
                'user_id' => $superAdmin->id,
                'action' => 'group.created',
                'target_type' => 'group',
                'target_id' => $studentGroup->id,
            ],
            [
                'description' => 'Created the Students group',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Seeder',
            ]
        );

        AuditLog::firstOrCreate(
            [
                'user_id' => $superAdmin->id,
                'action' => 'user.role.changed',
                'target_type' => 'user',
                'target_id' => $groupAdmin->id,
            ],
            [
                'old_values' => json_encode(['role_id' => 5]),
                'new_values' => json_encode(['role_id' => $groupAdmin->role_id]),
                'description' => "Promoted {$groupAdmin->full_name} to Group Administrator",
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Seeder',
            ]
        );

        AuditLog::firstOrCreate(
            [
                'user_id' => $superAdmin->id,
                'action' => 'user.blacklisted',
                'target_type' => 'user',
                'target_id' => $blacklistedUser->id,
            ],
            [
                'description' => "Blacklisted {$blacklistedUser->full_name} for guideline violations",
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Seeder',
            ]
        );

        // -------------------------------------------------------------------
        //  Notifications (keyed by user + type + message)
        // -------------------------------------------------------------------
        Notification::firstOrCreate(
            [
                'user_id' => $student1->id,
                'type' => 'new_reply',
                'message' => 'Dr. David Lecturer replied to your topic "What is the derivative of x²?"',
            ],
            [
                'group_id' => $studentGroup->id,
                'title' => 'New reply to your topic',
                'data' => [
                    'message' => 'Dr. David Lecturer replied to your topic "What is the derivative of x²?"',
                    'topic_id' => $topic1->id,
                ],
                'read_at' => null,
            ]
        );

        Notification::firstOrCreate(
            [
                'user_id' => $student1->id,
                'type' => 'new_reply',
                'message' => 'Carol Student replied to your topic "Tips for debugging PHP code effectively"',
            ],
            [
                'group_id' => $studentGroup->id,
                'title' => 'New reply to your topic',
                'data' => [
                    'message' => 'Carol Student replied to your topic "Tips for debugging PHP code effectively"',
                    'topic_id' => $topic7->id,
                ],
                'read_at' => now(),
            ]
        );

        Notification::firstOrCreate(
            [
                'user_id' => $student2->id,
                'type' => 'warning',
                'message' => 'You have received a warning. Please check your account for details.',
            ],
            [
                'group_id' => $studentGroup->id,
                'title' => 'Warning notification',
                'data' => [
                    'message' => 'You have received a warning. Please check your account for details.',
                ],
                'read_at' => null,
            ]
        );

        // -------------------------------------------------------------------
        //  Recommendation log entries (keyed by user + topic)
        // -------------------------------------------------------------------
        RecommendationLog::firstOrCreate(
            ['user_id' => $student1->id, 'topic_id' => $topic6->id],
            [
                'group_id' => $studentGroup->id,
                'recommended_at' => now()->subHours(2),
                'reason' => 'Based on similar topics you engaged with',
            ]
        );

        RecommendationLog::firstOrCreate(
            ['user_id' => $student1->id, 'topic_id' => $topic4->id],
            [
                'group_id' => $studentGroup->id,
                'recommended_at' => now()->subHours(2),
                'reason' => 'Based on similar topics you engaged with',
            ]
        );

        // -------------------------------------------------------------------
        //  Statistics — one row per group, refreshed on every run
        // -------------------------------------------------------------------
        foreach (Group::all() as $group) {
            Statistics::updateOrCreate(
                ['group_id' => $group->id],
                [
                    'total_members' => User::where('group_id', $group->id)->count(),
                    'active_members_this_week' => User::where('group_id', $group->id)
                        ->where('last_active_at', '>=', now()->subWeek())
                        ->count(),
                    'total_topics' => Topic::where('group_id', $group->id)->count(),
                    'total_posts' => Post::whereIn('topic_id', Topic::where('group_id', $group->id)->pluck('id'))
                        ->count(),
                    'unanswered_questions' => Topic::where('group_id', $group->id)
                        ->where('post_type', 'question')
                        ->whereDoesntHave('posts')
                        ->count(),
                    'inactive_members_30days' => User::where('group_id', $group->id)
                        ->whereNotNull('last_active_at')
                        ->where('last_active_at', '<', now()->subDays(30))
                        ->count(),
                    'last_calculated_at' => now(),
                ]
            );
        }

        $this->command->info('✅ Demo activity ready (audit logs, notifications, recommendations, statistics).');
    }
}
