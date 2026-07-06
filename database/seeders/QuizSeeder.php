<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Group;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizConfiguration;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    public function run(): void
    {
        // Find or create a Lecturer role
        $lecturerRole = Role::firstOrCreate(
            ['role_name' => 'Lecturer'],
            ['description' => 'Access to quiz configuration, participation marking criteria, and discussion features.']
        );

        // Find or create a student group for the quiz
        $studentGroup = Group::firstOrCreate(
            ['group_type' => 'student'],
            ['group_name' => 'Default Student Group', 'description' => 'Default group for students']
        );

        // Find or create a lecturer user
        $lecturer = User::firstOrCreate(
            ['email' => 'lecturer@example.com'],
            [
                'full_name' => 'Sample Lecturer',
                'password' => bcrypt('password'),
                'role_id' => $lecturerRole->id,
                'group_id' => $studentGroup->id,
                'account_status' => 'active',
            ]
        );

        $this->command->info("✅ Quiz lecturer created: ID {$lecturer->id} ({$lecturer->email})");

        // Create a sample quiz
        $quiz = Quiz::create([
            'lecturer_id' => $lecturer->id,
            'title' => 'Laravel Basics Quiz',
            'description' => 'Test your understanding of Laravel fundamentals',
            'target_category' => 'Student',
            'scheduled_date' => Carbon::now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00',
            'duration_minutes' => 30,
            'is_active' => false,
            'published_at' => null,
        ]);

        // Create configuration for this quiz
        QuizConfiguration::create([
            'quiz_id' => $quiz->quiz_id,
            'allow_late_join' => false,
            'notification_minutes_before' => 15,
            'participation_criteria' => 'Full marks if score >= 80%, half marks if score >= 50%',
            'lock_screen_on_start' => true,
            'show_results_after_close' => true,
            'show_correct_answers' => true,
        ]);

        $this->command->info("✅ Sample quiz created: '{$quiz->title}' (ID: {$quiz->quiz_id})");

        // Question 1: MCQ
        $q1 = Question::create([
            'quiz_id' => $quiz->quiz_id,
            'question_text' => 'What is Laravel?',
            'question_type' => 'MCQ',
            'marks' => 5,
            'question_order' => 1,
        ]);

        Answer::create(['question_id' => $q1->question_id, 'answer_text' => 'A PHP framework', 'is_correct' => true]);
        Answer::create(['question_id' => $q1->question_id, 'answer_text' => 'A JavaScript library', 'is_correct' => false]);
        Answer::create(['question_id' => $q1->question_id, 'answer_text' => 'A database manager', 'is_correct' => false]);

        // Question 2: True/False
        $q2 = Question::create([
            'quiz_id' => $quiz->quiz_id,
            'question_text' => 'Laravel uses the MVC pattern.',
            'question_type' => 'TF',
            'marks' => 5,
            'question_order' => 2,
        ]);

        Answer::create(['question_id' => $q2->question_id, 'answer_text' => 'True', 'is_correct' => true]);
        Answer::create(['question_id' => $q2->question_id, 'answer_text' => 'False', 'is_correct' => false]);

        $this->command->info("✅ 2 questions with answers added to quiz '{$quiz->title}'");
    }
}
