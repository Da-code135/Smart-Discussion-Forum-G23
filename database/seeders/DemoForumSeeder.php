<?php

namespace Database\Seeders;

use App\Models\BlacklistRecord;
use App\Models\Group;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Topic;
use App\Models\TopicCategory;
use App\Models\User;
use App\Models\Warning;
use Illuminate\Database\Seeder;

class DemoForumSeeder extends Seeder
{
    /**
     * Seed demo forum content: topics, posts, a report, moderation log,
     * warnings, and a blacklist record.
     *
     * Every record is keyed by its natural unique columns (topic title per
     * group, post content per topic, warning number per user, ...) so
     * re-running the seeder never duplicates rows. Run AFTER DemoUserSeeder.
     */
    public function run(): void
    {
        $lecturerGroup = Group::where('group_type', 'lecturer')->firstOrFail();
        $studentGroup = Group::where('group_type', 'student')->firstOrFail();

        $superAdmin = User::where('email', 'superadmin@example.com')->firstOrFail();
        $groupAdmin = User::where('email', 'groupadmin@example.com')->firstOrFail();
        $student1 = User::where('email', 'student1@example.com')->firstOrFail();
        $student2 = User::where('email', 'student2@example.com')->firstOrFail();
        $student3 = User::where('email', 'student3@example.com')->firstOrFail();
        $lecturer = User::where('email', 'lecturer@example.com')->firstOrFail();
        $member = User::where('email', 'member@example.com')->firstOrFail();
        $blacklistedUser = User::where('email', 'blacklisted@example.com')->firstOrFail();

        $mathCategory = TopicCategory::where('group_id', $studentGroup->id)
            ->where('category_name', 'Mathematics')->first();
        $progCategory = TopicCategory::where('group_id', $studentGroup->id)
            ->where('category_name', 'Programming')->first();
        $scienceCategory = TopicCategory::where('group_id', $studentGroup->id)
            ->where('category_name', 'Science')->first();
        $generalCategory = TopicCategory::where('group_id', $studentGroup->id)
            ->where('category_name', 'General')->first();

        // -------------------------------------------------------------------
        //  Topics with various states (keyed by group + title)
        // -------------------------------------------------------------------
        $topic1 = Topic::firstOrCreate(
            ['group_id' => $studentGroup->id, 'title' => 'What is the derivative of x²?'],
            [
                'created_by' => $student1->id,
                'description' => 'I am struggling to understand how to differentiate x². Can someone explain step by step?',
                'status' => 'active',
                'post_type' => 'question',
                'category_id' => $mathCategory->id,
                'is_answered' => false,
            ]
        );

        $topic2 = Topic::firstOrCreate(
            ['group_id' => $studentGroup->id, 'title' => 'Best practices for Laravel Eloquent queries'],
            [
                'created_by' => $student2->id,
                'description' => 'What are some best practices for writing efficient Eloquent queries in Laravel? I want to avoid N+1 issues.',
                'status' => 'active',
                'post_type' => 'discussion',
                'category_id' => $progCategory->id,
                'is_answered' => false,
            ]
        );

        $topic3 = Topic::firstOrCreate(
            ['group_id' => $studentGroup->id, 'title' => 'Photosynthesis experiment results'],
            [
                'created_by' => $student3->id,
                'description' => 'I conducted an experiment on photosynthesis and got interesting results. The rate increased with light intensity up to a point.',
                'status' => 'active',
                'post_type' => 'discussion',
                'category_id' => $scienceCategory->id,
                'is_answered' => true,
            ]
        );

        $topic4 = Topic::firstOrCreate(
            ['group_id' => $studentGroup->id, 'title' => 'Study group meetup this Friday'],
            [
                'created_by' => $member->id,
                'description' => 'Anyone interested in a study group session this Friday at 3 PM? We can review the upcoming exam topics.',
                'status' => 'active',
                'post_type' => 'discussion',
                'category_id' => $generalCategory->id,
                'is_answered' => false,
            ]
        );

        Topic::firstOrCreate(
            ['group_id' => $studentGroup->id, 'title' => 'Old announcement about course changes'],
            [
                'created_by' => $student1->id,
                'description' => 'This is an old announcement that has been archived.',
                'status' => 'archived',
                'post_type' => 'discussion',
                'category_id' => $generalCategory->id,
                'is_answered' => false,
            ]
        );

        // Unanswered question — intentionally has no posts
        Topic::firstOrCreate(
            ['group_id' => $studentGroup->id, 'title' => 'How do I solve this integral? ∫ e^x sin(x) dx'],
            [
                'created_by' => $student2->id,
                'description' => 'I keep getting stuck on integration by parts. Can someone show me the full solution?',
                'status' => 'active',
                'post_type' => 'question',
                'category_id' => $mathCategory->id,
                'is_answered' => false,
            ]
        );

        $topic7 = Topic::firstOrCreate(
            ['group_id' => $studentGroup->id, 'title' => 'Tips for debugging PHP code effectively'],
            [
                'created_by' => $student1->id,
                'description' => 'Share your favourite debugging tips and tools for PHP development.',
                'status' => 'active',
                'post_type' => 'discussion',
                'category_id' => $progCategory->id,
                'is_answered' => false,
            ]
        );

        // Topic in Faculty group (for cross-group testing)
        Topic::firstOrCreate(
            ['group_id' => $lecturerGroup->id, 'title' => 'Faculty meeting notes — Term 1'],
            [
                'created_by' => $lecturer->id,
                'description' => 'Summary of the faculty meeting held on Monday.',
                'status' => 'active',
                'post_type' => 'discussion',
                'category_id' => TopicCategory::where('group_id', $lecturerGroup->id)
                    ->where('category_name', 'General')->first()->id,
                'is_answered' => false,
            ]
        );

        // -------------------------------------------------------------------
        //  Posts (keyed by topic + author + content)
        // -------------------------------------------------------------------
        $posts = [
            [$topic1, $student2, 'The derivative of x² is 2x. Use the power rule: d/dx[x^n] = n*x^(n-1). So for n=2, you get 2*x^(2-1) = 2x.'],
            [$topic1, $lecturer, 'Great explanation! Additionally, you can think of it as the limit of the difference quotient: lim(h→0) [(x+h)² - x²]/h.'],
            [$topic2, $student1, 'Always use eager loading with `with()` to avoid N+1. You can also use `load()` for lazy eager loading.'],
            [$topic2, $lecturer, 'Also consider using `cursor()` or `chunk()` for large datasets to reduce memory usage.'],
            [$topic3, $student1, 'That matches the law of limiting factors. Light is the limiting factor until other factors like CO₂ become limited.'],
            [$topic3, $lecturer, 'Excellent observation! You have identified the light saturation point correctly.'],
            [$topic4, $student2, 'I am in! Let us meet at the library room 3B.'],
            [$topic4, $student3, 'Count me in too! I will bring my notes from the last lecture.'],
            [$topic7, $student2, 'I use dd() and dump() all the time. They are lifesavers for quick debugging.'],
            [$topic7, $lecturer, 'Xdebug with step-through debugging in VS Code is a game changer for complex issues.'],
        ];

        foreach ($posts as [$topic, $author, $content]) {
            Post::firstOrCreate(
                ['topic_id' => $topic->id, 'user_id' => $author->id, 'content' => $content],
                ['is_removed' => false]
            );
        }

        // Post flagged as reported (for moderation testing)
        $reportedPost = Post::firstOrCreate(
            [
                'topic_id' => $topic4->id,
                'user_id' => $student3->id,
                'content' => 'This is an inappropriate comment that should be reported for testing moderation features.',
            ],
            ['is_removed' => false, 'is_reported' => true]
        );

        // Post removed by moderation
        $removedPost = Post::firstOrCreate(
            [
                'topic_id' => $topic2->id,
                'user_id' => $student3->id,
                'content' => 'This post has been removed due to violating community guidelines.',
            ],
            ['is_removed' => true]
        );

        // -------------------------------------------------------------------
        //  Report on the reported post (polymorphic, keyed by reporter)
        // -------------------------------------------------------------------
        $reportedPost->reports()->firstOrCreate(
            ['user_id' => $student1->id],
            [
                'reason' => 'This comment contains inappropriate language and violates the community guidelines.',
                'status' => 'pending',
            ]
        );

        // -------------------------------------------------------------------
        //  Moderation log for the removed post
        // -------------------------------------------------------------------
        ModerationLog::firstOrCreate(
            ['post_id' => $removedPost->id, 'action' => 'removed'],
            [
                'admin_id' => $superAdmin->id,
                'reason' => 'Violation of community guidelines — inappropriate content.',
            ]
        );

        // -------------------------------------------------------------------
        //  Warnings for student3 (keyed by user + warning number)
        // -------------------------------------------------------------------
        Warning::firstOrCreate(
            ['user_id' => $student3->id, 'warning_number' => 1],
            [
                'reason' => 'First warning: Please follow the community guidelines when posting.',
                'response_deadline' => now()->addDays(7),
                'is_acknowledged' => true,
                'is_resolved' => true,
                'resolved_at' => now()->subDays(2),
                'created_by' => $superAdmin->id,
            ]
        );

        Warning::firstOrCreate(
            ['user_id' => $student3->id, 'warning_number' => 2],
            [
                'reason' => 'Second warning: Continued violation of posting rules.',
                'response_deadline' => now()->addDays(7),
                'is_acknowledged' => true,
                'is_resolved' => false,
                'created_by' => $groupAdmin->id,
            ]
        );

        $student3->update(['is_warned' => true]);

        // -------------------------------------------------------------------
        //  Blacklist record (one per blacklisted demo user)
        // -------------------------------------------------------------------
        BlacklistRecord::firstOrCreate(
            ['user_id' => $blacklistedUser->id],
            [
                'reason' => 'Repeated violations of community guidelines after warnings.',
                'blacklisted_at' => now(),
                'expires_at' => now()->addDays(30),
                'lifted_at' => null,
                'lifted_by' => null,
            ]
        );

        $this->command->info('✅ Demo forum content ready (8 topics, 12 posts, report, moderation, warnings, blacklist).');
    }
}
