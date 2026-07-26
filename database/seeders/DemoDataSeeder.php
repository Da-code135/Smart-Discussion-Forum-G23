<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\AuditLog;
use App\Models\BlacklistRecord;
use App\Models\Grade;
use App\Models\Group;
use App\Models\ModerationLog;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizConfiguration;
use App\Models\RecommendationLog;
use App\Models\Report;
use App\Models\Role;
use App\Models\Statistics;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\Topic;
use App\Models\TopicCategory;
use App\Models\User;
use App\Models\Warning;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed demo data for every application feature.
     *
     * Run this AFTER RoleSeeder, GroupSeeder, SuperAdminSeeder,
     * and TopicCategorySeeder have been executed.
     */
    public function run(): void
    {
        // -------------------------------------------------------------------
        //  0. Idempotency guard — the demo content below uses plain create()
        //     calls, so re-running this seeder would duplicate every topic,
        //     post, quiz, warning, etc. Skip entirely if it already ran.
        // -------------------------------------------------------------------
        if (Topic::where('title', 'What is the derivative of x²?')->exists()) {
            $this->command->warn('Demo data already seeded — skipping to avoid duplicates.');

            return;
        }

        // -------------------------------------------------------------------
        //  1. Fetch seeded records
        // -------------------------------------------------------------------
        $adminRole = Role::where('role_name', 'System Administrator')->firstOrFail();
        $groupAdminRole = Role::where('role_name', 'Group Administrator')->firstOrFail();
        $studentRole = Role::where('role_name', 'Student')->firstOrFail();
        $lecturerRole = Role::where('role_name', 'Lecturer')->firstOrFail();
        $memberRole = Role::where('role_name', 'Member')->firstOrFail();

        $sysadminGroup = Group::where('group_type', 'sysadmin')->firstOrFail();     // ID 1
        $lecturerGroup = Group::where('group_type', 'lecturer')->firstOrFail();     // ID 2
        $studentGroup = Group::where('group_type', 'student')->firstOrFail();       // ID 3

        $superAdmin = User::where('email', 'superadmin@example.com')->firstOrFail();

        // -------------------------------------------------------------------
        //  2. Create additional users for every role
        //     (use firstOrCreate so prior seeders don't cause conflicts)
        // -------------------------------------------------------------------
        $groupAdmin = User::firstOrCreate(
            ['email' => 'groupadmin@example.com'],
            [
                'full_name' => 'Alice Group Manager',
                'password' => bcrypt('password'),
                'role_id' => $groupAdminRole->id,
                'group_id' => $studentGroup->id,
                'account_status' => 'active',
                'email_verified_at' => now(),
                'last_active_at' => now(),
            ]
        );

        $student1 = User::firstOrCreate(
            ['email' => 'student1@example.com'],
            [
                'full_name' => 'Bob Student',
                'password' => bcrypt('password'),
                'role_id' => $studentRole->id,
                'group_id' => $studentGroup->id,
                'account_status' => 'active',
                'email_verified_at' => now(),
                'last_active_at' => now()->subDay(),
            ]
        );

        $student2 = User::firstOrCreate(
            ['email' => 'student2@example.com'],
            [
                'full_name' => 'Carol Student',
                'password' => bcrypt('password'),
                'role_id' => $studentRole->id,
                'group_id' => $studentGroup->id,
                'account_status' => 'active',
                'email_verified_at' => now(),
                'last_active_at' => now()->subDays(3),
            ]
        );

        $student3 = User::firstOrCreate(
            ['email' => 'student3@example.com'],
            [
                'full_name' => 'Inactive Student',
                'password' => bcrypt('password'),
                'role_id' => $studentRole->id,
                'group_id' => $studentGroup->id,
                'account_status' => 'active',
                'email_verified_at' => now(),
                'last_active_at' => now()->subDays(45), // Inactive 45 days
            ]
        );

        $lecturer = User::firstOrCreate(
            ['email' => 'lecturer@example.com'],
            [
                'full_name' => 'Dr. David Lecturer',
                'password' => bcrypt('password'),
                'role_id' => $lecturerRole->id,
                'group_id' => $lecturerGroup->id,
                'account_status' => 'active',
                'email_verified_at' => now(),
                'last_active_at' => now(),
            ]
        );

        $member = User::firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'full_name' => 'Eve Member',
                'password' => bcrypt('password'),
                'role_id' => $memberRole->id,
                'group_id' => $studentGroup->id,
                'account_status' => 'active',
                'email_verified_at' => now(),
                'last_active_at' => now()->subWeek(),
            ]
        );

        $this->command->info('✅ 6 demo users created.');

        // -------------------------------------------------------------------
        //  3. Assign group admin and lecturer access
        // -------------------------------------------------------------------
        $studentGroup->addAdmin($groupAdmin, $superAdmin->id);

        // Give lecturer access to the student group
        $lecturer->taughtGroups()->syncWithoutDetaching([$studentGroup->id]);

        // Also give the super admin some taught groups for testing
        $superAdmin->taughtGroups()->syncWithoutDetaching([
            $studentGroup->id,
            $lecturerGroup->id,
        ]);

        $this->command->info('✅ Group admin and lecturer access assigned.');

        // -------------------------------------------------------------------
        //  4. Fetch topic categories for the student group
        // -------------------------------------------------------------------
        $mathCategory = TopicCategory::where('group_id', $studentGroup->id)
            ->where('category_name', 'Mathematics')->first();
        $progCategory = TopicCategory::where('group_id', $studentGroup->id)
            ->where('category_name', 'Programming')->first();
        $scienceCategory = TopicCategory::where('group_id', $studentGroup->id)
            ->where('category_name', 'Science')->first();
        $generalCategory = TopicCategory::where('group_id', $studentGroup->id)
            ->where('category_name', 'General')->first();

        // -------------------------------------------------------------------
        //  5. Create topics with various states
        // -------------------------------------------------------------------
        // Math discussion — has replies
        $topic1 = Topic::create([
            'group_id' => $studentGroup->id,
            'created_by' => $student1->id,
            'title' => 'What is the derivative of x²?',
            'description' => 'I am struggling to understand how to differentiate x². Can someone explain step by step?',
            'status' => 'active',
            'post_type' => 'question',
            'category_id' => $mathCategory->id,
            'is_answered' => false,
        ]);

        // Programming discussion — has replies
        $topic2 = Topic::create([
            'group_id' => $studentGroup->id,
            'created_by' => $student2->id,
            'title' => 'Best practices for Laravel Eloquent queries',
            'description' => 'What are some best practices for writing efficient Eloquent queries in Laravel? I want to avoid N+1 issues.',
            'status' => 'active',
            'post_type' => 'discussion',
            'category_id' => $progCategory->id,
            'is_answered' => false,
        ]);

        // Science discussion — has replies and is answered
        $topic3 = Topic::create([
            'group_id' => $studentGroup->id,
            'created_by' => $student3->id,
            'title' => 'Photosynthesis experiment results',
            'description' => 'I conducted an experiment on photosynthesis and got interesting results. The rate increased with light intensity up to a point.',
            'status' => 'active',
            'post_type' => 'discussion',
            'category_id' => $scienceCategory->id,
            'is_answered' => true,
        ]);

        // General discussion — with replies
        $topic4 = Topic::create([
            'group_id' => $studentGroup->id,
            'created_by' => $member->id,
            'title' => 'Study group meetup this Friday',
            'description' => 'Anyone interested in a study group session this Friday at 3 PM? We can review the upcoming exam topics.',
            'status' => 'active',
            'post_type' => 'discussion',
            'category_id' => $generalCategory->id,
            'is_answered' => false,
        ]);

        // Archived topic
        $topic5 = Topic::create([
            'group_id' => $studentGroup->id,
            'created_by' => $student1->id,
            'title' => 'Old announcement about course changes',
            'description' => 'This is an old announcement that has been archived.',
            'status' => 'archived',
            'post_type' => 'discussion',
            'category_id' => $generalCategory->id,
            'is_answered' => false,
        ]);

        // Unanswered question — no posts at all
        $topic6 = Topic::create([
            'group_id' => $studentGroup->id,
            'created_by' => $student2->id,
            'title' => 'How do I solve this integral? ∫ e^x sin(x) dx',
            'description' => 'I keep getting stuck on integration by parts. Can someone show me the full solution?',
            'status' => 'active',
            'post_type' => 'question',
            'category_id' => $mathCategory->id,
            'is_answered' => false,
        ]);

        // Another active discussion for more content variety
        $topic7 = Topic::create([
            'group_id' => $studentGroup->id,
            'created_by' => $student1->id,
            'title' => 'Tips for debugging PHP code effectively',
            'description' => 'Share your favourite debugging tips and tools for PHP development.',
            'status' => 'active',
            'post_type' => 'discussion',
            'category_id' => $progCategory->id,
            'is_answered' => false,
        ]);

        // Topic in Faculty group (for cross-group testing)
        $topic8 = Topic::create([
            'group_id' => $lecturerGroup->id,
            'created_by' => $lecturer->id,
            'title' => 'Faculty meeting notes — Term 1',
            'description' => 'Summary of the faculty meeting held on Monday.',
            'status' => 'active',
            'post_type' => 'discussion',
            'category_id' => TopicCategory::where('group_id', $lecturerGroup->id)
                ->where('category_name', 'General')->first()->id,
            'is_answered' => false,
        ]);

        $this->command->info('✅ 8 topics created with various states.');

        // -------------------------------------------------------------------
        //  6. Create posts (replies) on topics
        // -------------------------------------------------------------------
        // Topic 1 — Math question replies
        $post1 = Post::create([
            'topic_id' => $topic1->id,
            'user_id' => $student2->id,
            'content' => 'The derivative of x² is 2x. Use the power rule: d/dx[x^n] = n*x^(n-1). So for n=2, you get 2*x^(2-1) = 2x.',
            'is_removed' => false,
        ]);
        $post2 = Post::create([
            'topic_id' => $topic1->id,
            'user_id' => $lecturer->id,
            'content' => 'Great explanation! Additionally, you can think of it as the limit of the difference quotient: lim(h→0) [(x+h)² - x²]/h.',
            'is_removed' => false,
        ]);

        // Topic 2 — Programming discussion replies
        $post3 = Post::create([
            'topic_id' => $topic2->id,
            'user_id' => $student1->id,
            'content' => 'Always use eager loading with `with()` to avoid N+1. You can also use `load()` for lazy eager loading.',
            'is_removed' => false,
        ]);
        $post4 = Post::create([
            'topic_id' => $topic2->id,
            'user_id' => $lecturer->id,
            'content' => 'Also consider using `cursor()` or `chunk()` for large datasets to reduce memory usage.',
            'is_removed' => false,
        ]);

        // Topic 3 — Science discussion
        $post5 = Post::create([
            'topic_id' => $topic3->id,
            'user_id' => $student1->id,
            'content' => 'That matches the law of limiting factors. Light is the limiting factor until other factors like CO₂ become limited.',
            'is_removed' => false,
        ]);
        $post6 = Post::create([
            'topic_id' => $topic3->id,
            'user_id' => $lecturer->id,
            'content' => 'Excellent observation! You have identified the light saturation point correctly.',
            'is_removed' => false,
        ]);

        // Topic 4 — General
        $post7 = Post::create([
            'topic_id' => $topic4->id,
            'user_id' => $student2->id,
            'content' => 'I am in! Let us meet at the library room 3B.',
            'is_removed' => false,
        ]);
        $post8 = Post::create([
            'topic_id' => $topic4->id,
            'user_id' => $student3->id,
            'content' => 'Count me in too! I will bring my notes from the last lecture.',
            'is_removed' => false,
        ]);

        // Topic 7 — PHP debugging
        Post::create([
            'topic_id' => $topic7->id,
            'user_id' => $student2->id,
            'content' => 'I use dd() and dump() all the time. They are lifesavers for quick debugging.',
            'is_removed' => false,
        ]);
        Post::create([
            'topic_id' => $topic7->id,
            'user_id' => $lecturer->id,
            'content' => 'Xdebug with step-through debugging in VS Code is a game changer for complex issues.',
            'is_removed' => false,
        ]);

        // Post that is flagged as reported (for moderation testing)
        $reportedPost = Post::create([
            'topic_id' => $topic4->id,
            'user_id' => $student3->id,
            'content' => 'This is an inappropriate comment that should be reported for testing moderation features.',
            'is_removed' => false,
            'is_reported' => true,
        ]);

        // Post that is removed by moderation
        $removedPost = Post::create([
            'topic_id' => $topic2->id,
            'user_id' => $student3->id,
            'content' => 'This post has been removed due to violating community guidelines.',
            'is_removed' => true,
        ]);

        $this->command->info('✅ 11 posts created on various topics.');

        // -------------------------------------------------------------------
        //  7. Create a report on the reported post via the polymorphic relationship
        // -------------------------------------------------------------------
        $reportedPost->reports()->create([
            'user_id' => $student1->id,
            'reason' => 'This comment contains inappropriate language and violates the community guidelines.',
            'status' => 'pending',
        ]);

        $this->command->info('✅ 1 report created.');

        // -------------------------------------------------------------------
        //  8. Create moderation log entry
        // -------------------------------------------------------------------
        ModerationLog::create([
            'post_id' => $removedPost->id,
            'admin_id' => $superAdmin->id,
            'action' => 'removed',
            'reason' => 'Violation of community guidelines — inappropriate content.',
        ]);

        $this->command->info('✅ 1 moderation log entry created.');

        // -------------------------------------------------------------------
        //  9. Create warnings for a student
        // -------------------------------------------------------------------
        Warning::create([
            'user_id' => $student3->id,
            'warning_number' => 1,
            'reason' => 'First warning: Please follow the community guidelines when posting.',
            'response_deadline' => now()->addDays(7),
            'is_acknowledged' => true,
            'is_resolved' => true,
            'resolved_at' => now()->subDays(2),
            'created_by' => $superAdmin->id,
        ]);

        Warning::create([
            'user_id' => $student3->id,
            'warning_number' => 2,
            'reason' => 'Second warning: Continued violation of posting rules.',
            'response_deadline' => now()->addDays(7),
            'is_acknowledged' => true,
            'is_resolved' => false,
            'created_by' => $groupAdmin->id,
        ]);

        // Update student3's warning status
        $student3->update(['is_warned' => true]);

        $this->command->info('✅ 2 warnings created.');

        // -------------------------------------------------------------------
        //  10. Create blacklist record
        // -------------------------------------------------------------------
        // Create a separate user to be blacklisted
        $blacklistedUser = User::firstOrCreate(
            ['email' => 'blacklisted@example.com'],
            [
                'full_name' => 'Bad Actor User',
                'password' => bcrypt('password'),
                'role_id' => $memberRole->id,
                'group_id' => $studentGroup->id,
                'account_status' => 'blacklisted',
                'blacklisted_at' => now(),
            ]
        );

        BlacklistRecord::create([
            'user_id' => $blacklistedUser->id,
            'reason' => 'Repeated violations of community guidelines after warnings.',
            'blacklisted_at' => now(),
            'expires_at' => now()->addDays(30),
            'lifted_at' => null,
            'lifted_by' => null,
        ]);

        $this->command->info('✅ 1 blacklisted user created.');

        // -------------------------------------------------------------------
        //  11. Create quizzes with questions, attempts, and grades
        // -------------------------------------------------------------------
        // Quiz 1: Already created by QuizSeeder — ensure it has a group_id
        $existingQuiz = Quiz::where('title', 'Laravel Basics Quiz')->first();
        if ($existingQuiz) {
            $existingQuiz->update(['group_id' => $studentGroup->id]);
        }

        // Quiz 2: A published quiz with student attempts
        $quiz2 = Quiz::create([
            'lecturer_id' => $lecturer->id,
            'group_id' => $studentGroup->id,
            'title' => 'PHP Fundamentals Quiz',
            'description' => 'Test your understanding of PHP basics including arrays, functions, and OOP.',
            'target_category' => 'Student',
            'scheduled_date' => Carbon::now()->subDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'duration_minutes' => 45,
            'is_active' => true,
            'published_at' => Carbon::now()->subDays(2),
        ]);

        QuizConfiguration::create([
            'quiz_id' => $quiz2->quiz_id,
            'allow_late_join' => true,
            'notification_minutes_before' => 10,
            'participation_criteria' => 'Full marks if score >= 80%, half marks if score >= 50%',
            'lock_screen_on_start' => false,
            'show_results_after_close' => true,
            'show_correct_answers' => true,
        ]);

        // Questions for Quiz 2
        $q1 = Question::create([
            'quiz_id' => $quiz2->quiz_id,
            'question_text' => 'Which function is used to output text in PHP?',
            'question_type' => 'MCQ',
            'marks' => 5,
            'question_order' => 1,
        ]);
        $q1Echo = Answer::create(['question_id' => $q1->question_id, 'answer_text' => 'echo', 'is_correct' => true]);
        $q1Print = Answer::create(['question_id' => $q1->question_id, 'answer_text' => 'print', 'is_correct' => false]);
        Answer::create(['question_id' => $q1->question_id, 'answer_text' => 'console.log', 'is_correct' => false]);
        Answer::create(['question_id' => $q1->question_id, 'answer_text' => 'System.out.println', 'is_correct' => false]);

        $q2 = Question::create([
            'quiz_id' => $quiz2->quiz_id,
            'question_text' => 'PHP supports multiple inheritance through interfaces.',
            'question_type' => 'TF',
            'marks' => 3,
            'question_order' => 2,
        ]);
        $q2True = Answer::create(['question_id' => $q2->question_id, 'answer_text' => 'True', 'is_correct' => true]);
        $q2False = Answer::create(['question_id' => $q2->question_id, 'answer_text' => 'False', 'is_correct' => false]);

        $q3 = Question::create([
            'quiz_id' => $quiz2->quiz_id,
            'question_text' => 'What does the "->" operator do in PHP?',
            'question_type' => 'MCQ',
            'marks' => 5,
            'question_order' => 3,
        ]);
        $q3Access = Answer::create(['question_id' => $q3->question_id, 'answer_text' => 'Access object properties/methods', 'is_correct' => true]);
        $q3Subtract = Answer::create(['question_id' => $q3->question_id, 'answer_text' => 'Subtract values', 'is_correct' => false]);
        Answer::create(['question_id' => $q3->question_id, 'answer_text' => 'Create a new array', 'is_correct' => false]);

        $this->command->info('✅ 2 quizzes configured with questions and answers.');

        // -------------------------------------------------------------------
        //  12. Student attempts and grades
        // -------------------------------------------------------------------
        // Student 1 attempt on Quiz 2 — submitted on time, good score
        $attempt1 = StudentAttempt::create([
            'quiz_id' => $quiz2->quiz_id,
            'student_id' => $student1->id,
            'start_time' => Carbon::now()->subDay()->setTime(9, 0),
            'submit_time' => Carbon::now()->subDay()->setTime(9, 35),
            'is_auto_submit' => false,
            'is_late' => false,
        ]);

        // Student 1 answers — all 3 correct
        StudentAnswer::create(['attempt_id' => $attempt1->attempt_id, 'question_id' => $q1->question_id, 'selected_answer_id' => $q1Echo->answer_id]);  // echo - correct
        StudentAnswer::create(['attempt_id' => $attempt1->attempt_id, 'question_id' => $q2->question_id, 'selected_answer_id' => $q2True->answer_id]); // True - correct
        StudentAnswer::create(['attempt_id' => $attempt1->attempt_id, 'question_id' => $q3->question_id, 'selected_answer_id' => $q3Access->answer_id]); // Access object - correct

        Grade::create([
            'attempt_id' => $attempt1->attempt_id,
            'student_id' => $student1->id,
            'quiz_id' => $quiz2->quiz_id,
            'group_id' => $studentGroup->id,
            'total_score' => 13,
            'max_score' => 13,
            'percentage' => 100,
            'participation_mark' => 10,
            'final_grade' => 100,
            'graded_at' => now()->subDay()->setTime(10, 0),
        ]);

        // Student 2 attempt on Quiz 2 — late submission, average score
        $attempt2 = StudentAttempt::create([
            'quiz_id' => $quiz2->quiz_id,
            'student_id' => $student2->id,
            'start_time' => Carbon::now()->subDay()->setTime(9, 5),
            'submit_time' => Carbon::now()->subDay()->setTime(10, 0),
            'is_auto_submit' => false,
            'is_late' => true,
        ]);

        StudentAnswer::create(['attempt_id' => $attempt2->attempt_id, 'question_id' => $q1->question_id, 'selected_answer_id' => $q1Echo->answer_id]);  // echo - correct
        StudentAnswer::create(['attempt_id' => $attempt2->attempt_id, 'question_id' => $q2->question_id, 'selected_answer_id' => $q2False->answer_id]); // False - incorrect
        StudentAnswer::create(['attempt_id' => $attempt2->attempt_id, 'question_id' => $q3->question_id, 'selected_answer_id' => $q3Subtract->answer_id]); // Subtract - incorrect

        Grade::create([
            'attempt_id' => $attempt2->attempt_id,
            'student_id' => $student2->id,
            'quiz_id' => $quiz2->quiz_id,
            'group_id' => $studentGroup->id,
            'total_score' => 5,
            'max_score' => 13,
            'percentage' => 38.46,
            'participation_mark' => 5,
            'final_grade' => 38.46,
            'graded_at' => now()->subDay()->setTime(10, 30),
        ]);

        // Student 3 attempt on Quiz 2 — auto-submitted (time ran out)
        $attempt3 = StudentAttempt::create([
            'quiz_id' => $quiz2->quiz_id,
            'student_id' => $student3->id,
            'start_time' => Carbon::now()->subDay()->setTime(9, 10),
            'submit_time' => Carbon::now()->subDay()->setTime(9, 45),
            'is_auto_submit' => true,
            'is_late' => false,
        ]);

        StudentAnswer::create(['attempt_id' => $attempt3->attempt_id, 'question_id' => $q1->question_id, 'selected_answer_id' => $q1Print->answer_id]);  // print - incorrect
        StudentAnswer::create(['attempt_id' => $attempt3->attempt_id, 'question_id' => $q2->question_id, 'selected_answer_id' => $q2True->answer_id]); // True - correct
        StudentAnswer::create(['attempt_id' => $attempt3->attempt_id, 'question_id' => $q3->question_id, 'selected_answer_id' => $q3Access->answer_id]); // Access object - correct

        Grade::create([
            'attempt_id' => $attempt3->attempt_id,
            'student_id' => $student3->id,
            'quiz_id' => $quiz2->quiz_id,
            'group_id' => $studentGroup->id,
            'total_score' => 8,
            'max_score' => 13,
            'percentage' => 61.54,
            'participation_mark' => 8,
            'final_grade' => 61.54,
            'graded_at' => now()->subDay()->setTime(10, 0),
        ]);

        $this->command->info('✅ 3 student attempts with grades created.');

        // -------------------------------------------------------------------
        //  13. Audit logs for various actions
        // -------------------------------------------------------------------
        AuditLog::create([
            'user_id' => $superAdmin->id,
            'action' => 'group.created',
            'target_type' => 'group',
            'target_id' => $studentGroup->id,
            'description' => 'Created the Students group',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Seeder',
        ]);

        AuditLog::create([
            'user_id' => $superAdmin->id,
            'action' => 'user.role.changed',
            'target_type' => 'user',
            'target_id' => $groupAdmin->id,
            'old_values' => json_encode(['role_id' => 5]),
            'new_values' => json_encode(['role_id' => $groupAdminRole->id]),
            'description' => "Promoted {$groupAdmin->full_name} to Group Administrator",
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Seeder',
        ]);

        AuditLog::create([
            'user_id' => $superAdmin->id,
            'action' => 'user.blacklisted',
            'target_type' => 'user',
            'target_id' => $blacklistedUser->id,
            'description' => "Blacklisted {$blacklistedUser->full_name} for guideline violations",
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Seeder',
        ]);

        $this->command->info('✅ 3 audit log entries created.');

        // -------------------------------------------------------------------
        //  14. Notifications for users
        // -------------------------------------------------------------------
        Notification::create([
            'user_id' => $student1->id,
            'group_id' => $studentGroup->id,
            'type' => 'new_reply',
            'title' => 'New reply to your topic',
            'message' => 'Dr. David Lecturer replied to your topic "What is the derivative of x²?"',
            'data' => [
                'message' => 'Dr. David Lecturer replied to your topic "What is the derivative of x²?"',
                'topic_id' => $topic1->id,
            ],
            'read_at' => null,
        ]);

        Notification::create([
            'user_id' => $student1->id,
            'group_id' => $studentGroup->id,
            'type' => 'new_reply',
            'title' => 'New reply to your topic',
            'message' => 'Carol Student replied to your topic "Tips for debugging PHP code effectively"',
            'data' => [
                'message' => 'Carol Student replied to your topic "Tips for debugging PHP code effectively"',
                'topic_id' => $topic7->id,
            ],
            'read_at' => now(),
        ]);

        Notification::create([
            'user_id' => $student2->id,
            'group_id' => $studentGroup->id,
            'type' => 'warning',
            'title' => 'Warning notification',
            'message' => 'You have received a warning. Please check your account for details.',
            'data' => [
                'message' => 'You have received a warning. Please check your account for details.',
            ],
            'read_at' => null,
        ]);

        $this->command->info('✅ 3 notifications created.');

        // -------------------------------------------------------------------
        //  15. Recommendation log entries
        // -------------------------------------------------------------------
        RecommendationLog::create([
            'user_id' => $student1->id,
            'topic_id' => $topic6->id,
            'group_id' => $studentGroup->id,
            'recommended_at' => now()->subHours(2),
            'reason' => 'Based on similar topics you engaged with',
        ]);

        RecommendationLog::create([
            'user_id' => $student1->id,
            'topic_id' => $topic4->id,
            'group_id' => $studentGroup->id,
            'recommended_at' => now()->subHours(2),
            'reason' => 'Based on similar topics you engaged with',
        ]);

        $this->command->info('✅ 2 recommendation log entries created.');

        // -------------------------------------------------------------------
        //  16. Statistics for each group
        // -------------------------------------------------------------------
        // Student group statistics
        Statistics::create([
            'group_id' => $studentGroup->id,
            'total_members' => User::where('group_id', $studentGroup->id)->count(),
            'active_members_this_week' => User::where('group_id', $studentGroup->id)
                ->where('last_active_at', '>=', now()->subWeek())
                ->count(),
            'total_topics' => Topic::where('group_id', $studentGroup->id)->count(),
            'total_posts' => Post::whereIn('topic_id', Topic::where('group_id', $studentGroup->id)->pluck('id'))
                ->count(),
            'unanswered_questions' => Topic::where('group_id', $studentGroup->id)
                ->where('post_type', 'question')
                ->whereDoesntHave('posts')
                ->count(),
            'inactive_members_30days' => User::where('group_id', $studentGroup->id)
                ->whereNotNull('last_active_at')
                ->where('last_active_at', '<', now()->subDays(30))
                ->count(),
            'last_calculated_at' => now(),
        ]);

        // Faculty group statistics
        Statistics::create([
            'group_id' => $lecturerGroup->id,
            'total_members' => User::where('group_id', $lecturerGroup->id)->count(),
            'active_members_this_week' => User::where('group_id', $lecturerGroup->id)
                ->where('last_active_at', '>=', now()->subWeek())
                ->count(),
            'total_topics' => Topic::where('group_id', $lecturerGroup->id)->count(),
            'total_posts' => Post::whereIn('topic_id', Topic::where('group_id', $lecturerGroup->id)->pluck('id'))
                ->count(),
            'unanswered_questions' => Topic::where('group_id', $lecturerGroup->id)
                ->where('post_type', 'question')
                ->whereDoesntHave('posts')
                ->count(),
            'inactive_members_30days' => User::where('group_id', $lecturerGroup->id)
                ->whereNotNull('last_active_at')
                ->where('last_active_at', '<', now()->subDays(30))
                ->count(),
            'last_calculated_at' => now(),
        ]);

        // Sysadmin group statistics
        Statistics::create([
            'group_id' => $sysadminGroup->id,
            'total_members' => User::where('group_id', $sysadminGroup->id)->count(),
            'active_members_this_week' => User::where('group_id', $sysadminGroup->id)
                ->where('last_active_at', '>=', now()->subWeek())
                ->count(),
            'total_topics' => Topic::where('group_id', $sysadminGroup->id)->count(),
            'total_posts' => Post::whereIn('topic_id', Topic::where('group_id', $sysadminGroup->id)->pluck('id'))
                ->count(),
            'unanswered_questions' => Topic::where('group_id', $sysadminGroup->id)
                ->where('post_type', 'question')
                ->whereDoesntHave('posts')
                ->count(),
            'inactive_members_30days' => User::where('group_id', $sysadminGroup->id)
                ->whereNotNull('last_active_at')
                ->where('last_active_at', '<', now()->subDays(30))
                ->count(),
            'last_calculated_at' => now(),
        ]);

        $this->command->info('✅ Statistics seeded for all 3 groups.');

        // -------------------------------------------------------------------
        //  Summary
        // -------------------------------------------------------------------
        $this->command->info('═══════════════════════════════════════════════');
        $this->command->info('  ✅ Demo data seeded successfully!');
        $this->command->info('═══════════════════════════════════════════════');
        $this->command->info('  Test accounts:');
        $this->command->info('    superadmin@example.com / password — System Admin');
        $this->command->info('    groupadmin@example.com / password  — Group Admin');
        $this->command->info('    student1@example.com / password   — Student');
        $this->command->info('    student2@example.com / password   — Student');
        $this->command->info('    student3@example.com / password   — Student (inactive, warned)');
        $this->command->info('    lecturer@example.com / password   — Lecturer');
        $this->command->info('    member@example.com / password     — Member');
        $this->command->info('    blacklisted@example.com / password — Blacklisted');
        $this->command->info('═══════════════════════════════════════════════');
    }
}
