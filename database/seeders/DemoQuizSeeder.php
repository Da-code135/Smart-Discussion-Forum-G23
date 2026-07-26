<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizConfiguration;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoQuizSeeder extends Seeder
{
    /**
     * Seed two demo quizzes with configurations, questions, answers,
     * student attempts, and grades.
     *
     * Quizzes are keyed by title, questions by quiz + order, answers by
     * question + text, attempts by quiz + student — so re-running the
     * seeder never duplicates rows. Run AFTER DemoUserSeeder.
     */
    public function run(): void
    {
        $studentGroup = Group::where('group_type', 'student')->firstOrFail();
        $lecturer = User::where('email', 'lecturer@example.com')->firstOrFail();
        $student1 = User::where('email', 'student1@example.com')->firstOrFail();
        $student2 = User::where('email', 'student2@example.com')->firstOrFail();
        $student3 = User::where('email', 'student3@example.com')->firstOrFail();

        // -------------------------------------------------------------------
        //  Quiz 1: Laravel Basics Quiz — upcoming, unpublished
        // -------------------------------------------------------------------
        $quiz1 = Quiz::firstOrCreate(
            ['title' => 'Laravel Basics Quiz'],
            [
                'lecturer_id' => $lecturer->id,
                'group_id' => $studentGroup->id,
                'description' => 'Test your understanding of Laravel fundamentals',
                'target_category' => 'Student',
                'scheduled_date' => Carbon::now()->addDay()->format('Y-m-d'),
                'start_time' => '10:00',
                'duration_minutes' => 30,
                'is_active' => false,
                'published_at' => null,
            ]
        );

        // Backfill the group for rows seeded before group_id existed
        if ($quiz1->group_id === null) {
            $quiz1->update(['group_id' => $studentGroup->id]);
        }

        QuizConfiguration::firstOrCreate(
            ['quiz_id' => $quiz1->quiz_id],
            [
                'allow_late_join' => false,
                'notification_minutes_before' => 15,
                'participation_criteria' => 'Full marks if score >= 80%, half marks if score >= 50%',
                'lock_screen_on_start' => true,
                'show_results_after_close' => true,
                'show_correct_answers' => true,
            ]
        );

        $quiz1Q1 = Question::firstOrCreate(
            ['quiz_id' => $quiz1->quiz_id, 'question_order' => 1],
            ['question_text' => 'What is Laravel?', 'question_type' => 'MCQ', 'marks' => 5]
        );
        $this->answers($quiz1Q1, [
            ['A PHP framework', true],
            ['A JavaScript library', false],
            ['A database manager', false],
        ]);

        $quiz1Q2 = Question::firstOrCreate(
            ['quiz_id' => $quiz1->quiz_id, 'question_order' => 2],
            ['question_text' => 'Laravel uses the MVC pattern.', 'question_type' => 'TF', 'marks' => 5]
        );
        $this->answers($quiz1Q2, [
            ['True', true],
            ['False', false],
        ]);

        // -------------------------------------------------------------------
        //  Quiz 2: PHP Fundamentals Quiz — published, with attempts
        // -------------------------------------------------------------------
        $quiz2 = Quiz::firstOrCreate(
            ['title' => 'PHP Fundamentals Quiz'],
            [
                'lecturer_id' => $lecturer->id,
                'group_id' => $studentGroup->id,
                'description' => 'Test your understanding of PHP basics including arrays, functions, and OOP.',
                'target_category' => 'Student',
                'scheduled_date' => Carbon::now()->subDay()->format('Y-m-d'),
                'start_time' => '09:00',
                'duration_minutes' => 45,
                'is_active' => true,
                'published_at' => Carbon::now()->subDays(2),
            ]
        );

        QuizConfiguration::firstOrCreate(
            ['quiz_id' => $quiz2->quiz_id],
            [
                'allow_late_join' => true,
                'notification_minutes_before' => 10,
                'participation_criteria' => 'Full marks if score >= 80%, half marks if score >= 50%',
                'lock_screen_on_start' => false,
                'show_results_after_close' => true,
                'show_correct_answers' => true,
            ]
        );

        $q1 = Question::firstOrCreate(
            ['quiz_id' => $quiz2->quiz_id, 'question_order' => 1],
            ['question_text' => 'Which function is used to output text in PHP?', 'question_type' => 'MCQ', 'marks' => 5]
        );
        [$q1Echo, $q1Print] = $this->answers($q1, [
            ['echo', true],
            ['print', false],
            ['console.log', false],
            ['System.out.println', false],
        ]);

        $q2 = Question::firstOrCreate(
            ['quiz_id' => $quiz2->quiz_id, 'question_order' => 2],
            ['question_text' => 'PHP supports multiple inheritance through interfaces.', 'question_type' => 'TF', 'marks' => 3]
        );
        [$q2True, $q2False] = $this->answers($q2, [
            ['True', true],
            ['False', false],
        ]);

        $q3 = Question::firstOrCreate(
            ['quiz_id' => $quiz2->quiz_id, 'question_order' => 3],
            ['question_text' => 'What does the "->" operator do in PHP?', 'question_type' => 'MCQ', 'marks' => 5]
        );
        [$q3Access, $q3Subtract] = $this->answers($q3, [
            ['Access object properties/methods', true],
            ['Subtract values', false],
            ['Create a new array', false],
        ]);

        // -------------------------------------------------------------------
        //  Student attempts, answers, and grades (keyed by quiz + student)
        // -------------------------------------------------------------------
        // Student 1 — submitted on time, perfect score
        $attempt1 = StudentAttempt::firstOrCreate(
            ['quiz_id' => $quiz2->quiz_id, 'student_id' => $student1->id],
            [
                'start_time' => Carbon::now()->subDay()->setTime(9, 0),
                'submit_time' => Carbon::now()->subDay()->setTime(9, 35),
                'is_auto_submit' => false,
                'is_late' => false,
            ]
        );
        $this->studentAnswers($attempt1, [
            [$q1, $q1Echo],   // correct
            [$q2, $q2True],   // correct
            [$q3, $q3Access], // correct
        ]);
        Grade::updateOrCreate(
            ['attempt_id' => $attempt1->attempt_id],
            [
                'student_id' => $student1->id,
                'quiz_id' => $quiz2->quiz_id,
                'group_id' => $studentGroup->id,
                'total_score' => 13,
                'max_score' => 13,
                'percentage' => 100,
                'participation_mark' => 10,
                'final_grade' => 100,
                'graded_at' => now()->subDay()->setTime(10, 0),
            ]
        );

        // Student 2 — late submission, average score
        $attempt2 = StudentAttempt::firstOrCreate(
            ['quiz_id' => $quiz2->quiz_id, 'student_id' => $student2->id],
            [
                'start_time' => Carbon::now()->subDay()->setTime(9, 5),
                'submit_time' => Carbon::now()->subDay()->setTime(10, 0),
                'is_auto_submit' => false,
                'is_late' => true,
            ]
        );
        $this->studentAnswers($attempt2, [
            [$q1, $q1Echo],     // correct
            [$q2, $q2False],    // incorrect
            [$q3, $q3Subtract], // incorrect
        ]);
        Grade::updateOrCreate(
            ['attempt_id' => $attempt2->attempt_id],
            [
                'student_id' => $student2->id,
                'quiz_id' => $quiz2->quiz_id,
                'group_id' => $studentGroup->id,
                'total_score' => 5,
                'max_score' => 13,
                'percentage' => 38.46,
                'participation_mark' => 5,
                'final_grade' => 38.46,
                'graded_at' => now()->subDay()->setTime(10, 30),
            ]
        );

        // Student 3 — auto-submitted when time ran out
        $attempt3 = StudentAttempt::firstOrCreate(
            ['quiz_id' => $quiz2->quiz_id, 'student_id' => $student3->id],
            [
                'start_time' => Carbon::now()->subDay()->setTime(9, 10),
                'submit_time' => Carbon::now()->subDay()->setTime(9, 45),
                'is_auto_submit' => true,
                'is_late' => false,
            ]
        );
        $this->studentAnswers($attempt3, [
            [$q1, $q1Print],  // incorrect
            [$q2, $q2True],   // correct
            [$q3, $q3Access], // correct
        ]);
        Grade::updateOrCreate(
            ['attempt_id' => $attempt3->attempt_id],
            [
                'student_id' => $student3->id,
                'quiz_id' => $quiz2->quiz_id,
                'group_id' => $studentGroup->id,
                'total_score' => 8,
                'max_score' => 13,
                'percentage' => 61.54,
                'participation_mark' => 8,
                'final_grade' => 61.54,
                'graded_at' => now()->subDay()->setTime(10, 0),
            ]
        );

        $this->command->info('✅ Demo quizzes ready (2 quizzes, 5 questions, 3 attempts with grades).');
    }

    /**
     * Idempotently create the answers for a question.
     *
     * @param  list<array{0: string, 1: bool}>  $answers
     * @return list<Answer>
     */
    private function answers(Question $question, array $answers): array
    {
        $models = [];

        foreach ($answers as [$text, $isCorrect]) {
            $models[] = Answer::firstOrCreate(
                ['question_id' => $question->question_id, 'answer_text' => $text],
                ['is_correct' => $isCorrect]
            );
        }

        return $models;
    }

    /**
     * Idempotently record the selected answer for each question of an attempt.
     *
     * @param  list<array{0: Question, 1: Answer}>  $selections
     */
    private function studentAnswers(StudentAttempt $attempt, array $selections): void
    {
        foreach ($selections as [$question, $answer]) {
            StudentAnswer::updateOrCreate(
                ['attempt_id' => $attempt->attempt_id, 'question_id' => $question->question_id],
                ['selected_answer_id' => $answer->answer_id]
            );
        }
    }
}
